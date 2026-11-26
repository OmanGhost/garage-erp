<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\CRM\Models\Customer;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Models\SupplierInvoice;
use App\Modules\Sales\Http\Requests\InvoiceRequest;
use App\Modules\Sales\Models\CashRegister;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Models\PosSession;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\CreditNoteService;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Sales\Services\PosService;
use App\Modules\Store\Models\StoreOrder;
use App\Modules\Store\Services\StoreService;
use App\Support\Codes;
use App\Support\DocumentNumber;
use App\Support\Export\Exporter;
use App\Support\Money;
use App\Support\Options;
use App\Support\Totals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesController extends Controller
{
    public function __construct(protected InvoiceService $invoices, protected PaymentService $payments) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    // ------------------------------------------------------------------ POS

    public function pos()
    {
        $this->can('pos.view');
        $session = app(PosService::class)->currentSession();

        return view('sales::pos', ['session' => $session, 'registers' => CashRegister::query()->where('is_active', true)->get()]);
    }

    public function openSession(Request $request)
    {
        $this->can('pos.open_session');
        $data = $request->validate(['cash_register_id' => ['required', 'integer'], 'opening_cash' => ['required', 'numeric', 'min:0']]);
        app(PosService::class)->openSession(CashRegister::query()->findOrFail($data['cash_register_id']), $data['opening_cash']);

        return redirect()->route('pos.index')->with('success', __('Cash session opened.'));
    }

    public function closeSession(Request $request)
    {
        $this->can('pos.close_session');
        $data = $request->validate(['session_id' => ['required', 'integer'], 'counted_cash' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:500']]);
        $s = PosSession::query()->where('user_id', auth()->id())->findOrFail($data['session_id']);
        $s = app(PosService::class)->closeSession($s, $data['counted_cash'], $data['notes'] ?? null);

        return redirect()->route('pos.sessions.show', $s)->with('success', __('Cash session closed.'));
    }

    public function session(PosSession $session)
    {
        $this->can('pos.view');
        $session->load('register', 'user');
        $invoices = Invoice::query()->where('pos_session_id', $session->id)->whereNotIn('status', ['draft'])->with('customer')->get();
        $byMethod = Payment::query()->where('pos_session_id', $session->id)->where('status', 'posted')
            ->selectRaw("method, direction, SUM(amount) total")->groupBy('method', 'direction')->get();

        return view('sales::pos-session', ['s' => $session, 'invoices' => $invoices, 'byMethod' => $byMethod,
            'expected' => $session->status === 'open' ? app(PosService::class)->expectedCash($session) : $session->expected_cash]);
    }

    // ------------------------------------------------------------- invoices

    public function createInvoice(Request $request)
    {
        $this->can('invoices.create');

        return view('sales::invoice-form', ['invoice' => null, 'rows' => [], 'warehouses' => Options::warehouses(),
            'customer' => $request->query('customer_id') ? Customer::query()->find($request->query('customer_id')) : null]);
    }

    public function storeInvoice(InvoiceRequest $request)
    {
        $d = $request->validated();
        $inv = $this->invoices->createDraft([
            'customer_id' => $d['customer_id'], 'type' => 'sale', 'issue_date' => $d['issue_date'], 'due_date' => $d['due_date'] ?? null,
            'payment_terms' => $d['payment_terms'], 'price_level' => $d['price_level'], 'warehouse_id' => $d['warehouse_id'], 'notes' => $d['notes'] ?? null,
        ], $d['items']);
        if ($request->boolean('post_now') && auth()->user()->can('invoices.post')) {
            $this->invoices->post($inv);

            return redirect()->route('invoices.show', $inv)->with('success', __('Invoice posted.'));
        }

        return redirect()->route('invoices.show', $inv)->with('success', __('Draft invoice saved.'));
    }

    public function invoice(Invoice $invoice)
    {
        $this->can('invoices.view');
        $invoice->load('items.part', 'items.service', 'customer', 'jobCard.vehicle', 'allocations.payment', 'creditNotes', 'journal.lines.account', 'creator', 'salesperson');

        return view('sales::invoice', ['inv' => $invoice, 'banks' => Options::bankAccounts()]);
    }

    public function editInvoice(Invoice $invoice)
    {
        $this->can('invoices.update');
        abort_unless(in_array($invoice->status, ['draft', 'held'], true), 403);
        $rows = $invoice->items->map(fn ($i) => ['item_type' => $i->item_type, 'part_id' => $i->part_id, 'service_id' => $i->service_id,
            'description' => $i->description, 'qty' => (float) $i->qty, 'unit_price' => (float) $i->unit_price, 'discount' => (float) $i->discount,
            'tax_rate' => (float) $i->tax_rate, 'label' => $i->part?->label() ?? $i->service?->displayName() ?? ''])->all();

        return view('sales::invoice-form', ['invoice' => $invoice, 'rows' => $rows, 'warehouses' => Options::warehouses(), 'customer' => $invoice->customer]);
    }

    public function updateInvoice(InvoiceRequest $request, Invoice $invoice)
    {
        $d = $request->validated();
        if ($invoice->job_card_id) {
            // workshop invoices keep their job-card links: only header changes
            $invoice->fill(collect($d)->only(['issue_date', 'due_date', 'payment_terms', 'notes'])->all())->save();
        } else {
            $this->invoices->updateDraft($invoice, $d, $d['items']);
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice updated.'));
    }

    public function postInvoice(Invoice $invoice)
    {
        $this->can('invoices.post');
        $this->invoices->post($invoice);

        return back()->with('success', __('Invoice posted. Stock and accounts were updated.'));
    }

    public function printInvoice(Invoice $invoice, string $format = 'a4')
    {
        $this->can('invoices.print');
        $invoice->load('items', 'customer', 'jobCard.vehicle.make', 'jobCard.vehicle.vehicleModel', 'salesperson', 'allocations');
        $company = tenant()->company();
        $qr = Codes::qr($invoice->number ? route('public.invoice', $invoice->uuid).'#'.Codes::invoiceTlv($company->displayName(), $company->vat_number,
            ($invoice->posted_at ?? now())->toIso8601String(), (string) $invoice->total, (string) $invoice->tax_total) : route('invoices.show', $invoice), 3);
        $view = $format === '80mm' ? 'sales::print.receipt-80' : 'sales::print.invoice';
        $html = view($view, ['inv' => $invoice, 'qr' => $qr])->render();
        if ($format === 'pdf') {
            return response(Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$invoice->displayNumber().'.pdf"']);
        }

        return response($html);
    }

    // ---------------------------------------------------------- credit notes

    public function createCreditNote(Invoice $invoice)
    {
        $this->can('credit_notes.create');
        abort_unless($invoice->isPosted(), 403);

        return view('sales::credit-note-form', ['inv' => $invoice->load('items'), 'warehouses' => Options::warehouses(), 'banks' => Options::bankAccounts()]);
    }

    public function storeCreditNote(Request $request, Invoice $invoice, CreditNoteService $service)
    {
        $this->can('credit_notes.create');
        $data = $request->validate([
            'qty' => ['required', 'array'], 'qty.*' => ['nullable', 'numeric', 'min:0'], 'reason' => ['required', 'string', 'max:250'],
            'restock' => ['nullable', 'boolean'], 'warehouse_id' => ['nullable', 'integer'], 'refund_bank_account_id' => ['nullable', 'integer'],
        ]);
        $cn = $service->create($invoice, $data['qty'], $request->boolean('restock'), $data['reason'], $data['warehouse_id'] ?? null);
        if ($request->boolean('post_now') && auth()->user()->can('credit_notes.approve')) {
            $service->post($cn, $data['refund_bank_account_id'] ?? null);
        }

        return redirect()->route('credit-notes.show', $cn)->with('success', __('Credit note saved.'));
    }

    public function creditNote(CreditNote $creditNote)
    {
        $this->can('credit_notes.view');

        $creditNote->load('items.part', 'invoice.creditNotes', 'customer', 'warehouse', 'journal.lines.account', 'refundPayment.bankAccount', 'creator', 'poster');

        return view('sales::credit-note', [
            'cn' => $creditNote,
            'banks' => Options::bankAccounts(),
            'others' => $creditNote->invoice->creditNotes->where('id', '!=', $creditNote->id)->sortByDesc('id'),
        ]);
    }

    public function printCreditNote(CreditNote $creditNote, string $format = 'a4')
    {
        $this->can('credit_notes.print');
        $creditNote->load('items', 'invoice', 'customer', 'warehouse', 'refundPayment.bankAccount', 'creator', 'poster');
        $html = view('sales::print.credit-note', ['cn' => $creditNote])->render();
        if ($format === 'pdf') {
            return response(Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$creditNote->displayNumber().'.pdf"']);
        }

        return response($html);
    }

    public function postCreditNote(Request $request, CreditNote $creditNote, CreditNoteService $service)
    {
        $this->can('credit_notes.approve');
        $service->post($creditNote, $request->integer('refund_bank_account_id') ?: null);

        return back()->with('success', __('Credit note posted.'));
    }

    // -------------------------------------------------------------- payments

    public function receiveForm(Request $request)
    {
        $this->can('payments.create');
        $customer = $request->query('customer_id') ? Customer::query()->find($request->query('customer_id')) : null;
        $open = $customer ? Invoice::query()->where('customer_id', $customer->id)->whereIn('status', ['posted', 'partially_paid'])->orderBy('issue_date')->get() : collect();

        return view('sales::payment-receive', ['customer' => $customer, 'open' => $open, 'banks' => Options::bankAccounts(),
            'invoiceId' => $request->query('invoice_id')]);
    }

    public function receive(Request $request)
    {
        $this->can('payments.create');
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', tenant()->id())],
            'amount' => ['required', 'numeric', 'gt:0'], 'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where('company_id', tenant()->id())],
            'method' => ['required', 'in:'.implode(',', Options::PAYMENT_METHODS)], 'date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'], 'cheque_number' => ['nullable', 'string', 'max:40'], 'cheque_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'], 'allocations' => ['nullable', 'array'], 'allocations.*' => ['nullable', 'numeric', 'min:0'],
            'auto_allocate' => ['nullable', 'boolean'],
        ]);
        $alloc = $request->boolean('auto_allocate') ? null : array_filter($data['allocations'] ?? [], fn ($v) => Money::isPositive($v));
        $p = $this->payments->receiveFromCustomer(Customer::query()->findOrFail($data['customer_id']), $data['amount'], $data['bank_account_id'], $data['method'],
            $alloc, collect($data)->only(['date', 'reference', 'cheque_number', 'cheque_date', 'notes'])->all());

        return redirect()->route('payments.show', $p)->with('success', __('Receipt :n recorded.', ['n' => $p->number]));
    }

    public function payForm(Request $request)
    {
        $this->can('payments.create');
        $supplier = $request->query('supplier_id') ? Supplier::query()->find($request->query('supplier_id')) : null;
        $open = $supplier ? SupplierInvoice::query()->where('supplier_id', $supplier->id)->whereIn('status', ['posted', 'partially_paid'])->orderBy('invoice_date')->get() : collect();

        return view('sales::payment-pay', ['supplier' => $supplier, 'open' => $open, 'banks' => Options::bankAccounts()]);
    }

    public function pay(Request $request)
    {
        $this->can('payments.create');
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('company_id', tenant()->id())],
            'amount' => ['required', 'numeric', 'gt:0'], 'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where('company_id', tenant()->id())],
            'method' => ['required', 'in:'.implode(',', Options::PAYMENT_METHODS)], 'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'], 'cheque_number' => ['nullable', 'string', 'max:40'], 'cheque_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'], 'allocations' => ['nullable', 'array'], 'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $alloc = array_filter($data['allocations'] ?? [], fn ($v) => Money::isPositive($v));
        $p = $this->payments->payToSupplier(Supplier::query()->findOrFail($data['supplier_id']), $data['amount'], $data['bank_account_id'], $data['method'],
            $alloc ?: null, collect($data)->only(['date', 'reference', 'cheque_number', 'cheque_date', 'notes'])->all());

        return redirect()->route('payments.show', $p)->with('success', __('Payment :n recorded.', ['n' => $p->number]));
    }

    public function payment(Payment $payment)
    {
        $this->can('payments.view');
        $payment->load('allocations.allocatable', 'bankAccount', 'party', 'journal.lines.account', 'creator');
        if (request('print')) {
            return redirect()->route('vouchers.payment', $payment);
        }

        return view('sales::payment', ['p' => $payment]);
    }

    public function cancelPayment(Request $request, Payment $payment)
    {
        $this->can('payments.approve');
        $this->payments->cancel($payment, (string) $request->input('reason'));

        return back()->with('success', __('Payment cancelled and reversed.'));
    }

    // ---------------------------------------------------------- sales orders

    public function createOrder()
    {
        $this->can('sales_orders.create');

        return view('sales::order-form', ['warehouses' => Options::warehouses()]);
    }

    public function storeOrder(InvoiceRequest $request)
    {
        $d = $request->validated();
        $order = DB::transaction(function () use ($d) {
            $o = SalesOrder::create(['number' => DocumentNumber::next('sales_order'), 'customer_id' => $d['customer_id'], 'warehouse_id' => $d['warehouse_id'],
                'order_date' => $d['issue_date'], 'delivery_date' => $d['due_date'] ?? null, 'status' => 'draft', 'notes' => $d['notes'] ?? null, 'created_by' => auth()->id()]);
            foreach ($d['items'] as $i) {
                $c = Totals::line($i['qty'], $i['unit_price'], $i['discount'] ?? 0, $i['tax_rate'] ?? 5);
                $o->items()->create(['part_id' => $i['part_id'] ?: null, 'service_id' => $i['service_id'] ?? null, 'description' => $i['description'] ?: '-',
                    'qty' => $i['qty'], 'unit_price' => $i['unit_price'], 'discount' => $c['discount'], 'tax_rate' => $i['tax_rate'] ?? 5, 'tax_amount' => $c['tax'], 'line_total' => $c['net']]);
            }
            Totals::apply($o);

            return $o;
        });

        return redirect()->route('sales-orders.show', $order)->with('success', __('Sales order created.'));
    }

    public function order(SalesOrder $order)
    {
        $this->can('sales_orders.view');

        return view('sales::order', ['o' => $order->load('items.part', 'customer', 'invoice', 'warehouse')]);
    }

    public function orderAction(SalesOrder $order, string $action)
    {
        $this->can('sales_orders.update');
        if ($action === 'confirm' && $order->status === 'draft') {
            $order->update(['status' => 'confirmed']);
        } elseif ($action === 'cancel' && in_array($order->status, ['draft', 'confirmed'], true)) {
            $order->update(['status' => 'cancelled']);
        } elseif ($action === 'invoice' && $order->status === 'confirmed') {
            $inv = $this->invoices->createDraft(['customer_id' => $order->customer_id, 'warehouse_id' => $order->warehouse_id, 'sales_order_id' => $order->id,
                'payment_terms' => 'credit', 'notes' => __('From sales order').' '.$order->number],
                $order->items->map(fn ($i) => ['item_type' => $i->part_id ? 'part' : 'service', 'part_id' => $i->part_id, 'service_id' => $i->service_id,
                    'description' => $i->description, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'discount' => $i->discount, 'tax_rate' => $i->tax_rate])->all());
            $order->update(['status' => 'invoiced', 'invoice_id' => $inv->id]);

            return redirect()->route('invoices.show', $inv)->with('success', __('Invoice draft created.'));
        }

        return back()->with('success', __('Order updated.'));
    }

    // ----------------------------------------------------------- store orders

    public function storeOrderView(StoreOrder $order)
    {
        $this->can('store_orders.view');

        return view('sales::store-order', ['o' => $order->load('items.part', 'invoice', 'coupon'), 'banks' => Options::bankAccounts()]);
    }

    public function storeOrderAction(Request $request, StoreOrder $order, string $action)
    {
        $this->can('store_orders.update');
        if ($action === 'invoice') {
            $inv = app(StoreService::class)->invoice($order);

            return redirect()->route('invoices.show', $inv)->with('success', __('Order invoiced and stock issued.'));
        }
        if ($action === 'paid') {
            $inv = app(StoreService::class)->invoice($order);
            $bank = $request->integer('bank_account_id') ?: BankAccount::query()->where('type', 'cash')->value('id');
            if (Money::isPositive($inv->balanceDue())) {
                $this->payments->receiveFromCustomer($inv->customer, $inv->balanceDue(), $bank, $order->payment_method === 'online' ? 'online' : 'cash', [$inv->id => $inv->balanceDue()]);
            }
            $order->update(['payment_status' => 'paid']);

            return back()->with('success', __('Payment recorded.'));
        }
        $order->update(['status' => $action]);

        return back()->with('success', __('Order updated.'));
    }
}
