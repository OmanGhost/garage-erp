<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\LandedCost;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\Rfq;
use App\Modules\Purchasing\Models\RfqSupplier;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Models\SupplierInvoice;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Options;
use App\Support\Totals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchasingController extends Controller
{
    public function __construct(protected PurchasingService $service) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    protected function itemRules(bool $cost = true): array
    {
        $c = tenant()->id();

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['nullable', 'integer', Rule::exists('parts', 'id')->where('company_id', $c)],
            'items.*.description' => ['nullable', 'string', 'max:250'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => [$cost ? 'nullable' : 'sometimes', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function cleanItems(array $items): array
    {
        return array_values(array_filter(array_map(function ($i) {
            $i['unit_cost'] = $i['unit_cost'] ?? $i['unit_price'] ?? null;

            return $i;
        }, $items), fn ($i) => (! empty($i['part_id']) || ! empty($i['description'])) && Money::isPositive($i['qty'] ?? 0)));
    }

    // ------------------------------------------------------------- supplier

    public function supplier(Request $request, Supplier $supplier)
    {
        $this->can('suppliers.view');
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $st = Ledger::partyStatement('supplier', $supplier->id, $from, $to);
        if ($request->query('print')) {
            return view('crm::statement', ['party' => $supplier, 'st' => $st, 'from' => $from, 'to' => $to, 'type' => 'supplier']);
        }

        return view('purchasing::supplier', ['s' => $supplier, 'st' => $st, 'from' => $from, 'to' => $to, 'kpi' => $this->service->supplierPerformance($supplier->id),
            'orders' => $supplier->purchaseOrders()->latest('id')->limit(15)->get(), 'invoices' => $supplier->invoices()->latest('id')->limit(15)->get()]);
    }

    // ----------------------------------------------------- purchase requests

    public function createRequest()
    {
        $this->can('purchase_requests.create');

        return view('purchasing::request-form');
    }

    public function storeRequest(Request $request)
    {
        $this->can('purchase_requests.create');
        $data = $request->validate($this->itemRules() + ['required_date' => ['nullable', 'date'], 'priority' => ['required', 'in:low,normal,high,urgent'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $pr = $this->service->createRequest(collect($data)->except('items')->all(), $this->cleanItems($data['items']));

        return redirect()->route('purchase-requests.show', $pr)->with('success', __('Purchase request submitted.'));
    }

    public function request(PurchaseRequest $pr)
    {
        $this->can('purchase_requests.view');

        return view('purchasing::request', ['pr' => $pr->load('items.part', 'requester', 'approver', 'jobCard')]);
    }

    public function decideRequest(PurchaseRequest $pr, string $decision)
    {
        $this->can('purchase_requests.approve');
        $this->service->decideRequest($pr, $decision === 'approve');

        return back()->with('success', __('Decision recorded.'));
    }

    // ------------------------------------------------------------------ RFQ

    public function createRfq(Request $request)
    {
        $this->can('rfqs.create');
        $pr = $request->query('purchase_request_id') ? PurchaseRequest::query()->with('items.part')->find($request->query('purchase_request_id')) : null;

        return view('purchasing::rfq-form', ['pr' => $pr, 'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')]);
    }

    public function storeRfq(Request $request)
    {
        $this->can('rfqs.create');
        $data = $request->validate(['purchase_request_id' => ['nullable', 'integer'], 'supplier_ids' => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['integer', Rule::exists('suppliers', 'id')->where('company_id', tenant()->id())], 'due_date' => ['nullable', 'date']] + $this->itemRules());
        $pr = ! empty($data['purchase_request_id']) ? PurchaseRequest::query()->findOrFail($data['purchase_request_id']) : null;
        $rfq = $this->service->createRfq($pr, $data['supplier_ids'], $this->cleanItems($data['items'] ?? []), $data['due_date'] ?? null);

        return redirect()->route('rfqs.show', $rfq)->with('success', __('RFQ created.'));
    }

    public function rfq(Rfq $rfq)
    {
        $this->can('rfqs.view');

        return view('purchasing::rfq', ['rfq' => $rfq->load('items.part', 'suppliers.supplier', 'suppliers.quotes'), 'matrix' => $this->service->compare($rfq), 'warehouses' => Options::warehouses()]);
    }

    public function saveQuote(Request $request, RfqSupplier $rs)
    {
        $this->can('rfqs.update');
        Rfq::query()->findOrFail($rs->rfq_id);
        $data = $request->validate(['prices' => ['array'], 'prices.*.unit_price' => ['nullable', 'numeric', 'min:0'], 'prices.*.lead_days' => ['nullable', 'integer', 'min:0'], 'delivery_days' => ['nullable', 'integer', 'min:0']]);
        $this->service->recordQuote($rs, $data['prices'] ?? [], $data['delivery_days'] ?? null);

        return back()->with('success', __('Supplier quote saved.'));
    }

    public function award(Request $request, Rfq $rfq, RfqSupplier $rs)
    {
        $this->can('purchase_orders.create');
        abort_unless($rs->rfq_id === $rfq->id, 404);
        $po = $this->service->awardRfq($rfq, $rs->load('quotes'), $request->integer('warehouse_id'));

        return redirect()->route('purchase-orders.show', $po)->with('success', __('Purchase order created from the selected quote.'));
    }

    // ----------------------------------------------------------------- PO

    public function createOrder()
    {
        $this->can('purchase_orders.create');

        return view('purchasing::order-form', ['po' => null, 'rows' => [], 'warehouses' => Options::warehouses()]);
    }

    public function storeOrder(Request $request)
    {
        $this->can('purchase_orders.create');
        $data = $request->validate(['supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('company_id', tenant()->id())],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', tenant()->id())],
            'expected_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000'], 'terms' => ['nullable', 'string', 'max:2000']] + $this->itemRules());
        $po = $this->service->createOrder(collect($data)->except('items')->all(), $this->cleanItems($data['items']));
        if ($request->boolean('submit')) {
            $this->service->submitOrder($po);
        }

        return redirect()->route('purchase-orders.show', $po)->with('success', __('Purchase order saved.'));
    }

    public function order(PurchaseOrder $po)
    {
        $this->can('purchase_orders.view');

        return view('purchasing::order', ['po' => $po->load('items.part', 'supplier', 'warehouse', 'receipts', 'invoices', 'approver', 'creator')]);
    }

    public function editOrder(PurchaseOrder $po)
    {
        $this->can('purchase_orders.update');
        abort_unless(in_array($po->status, ['draft', 'pending_approval'], true), 403);
        $rows = $po->items->map(fn ($i) => ['item_type' => 'part', 'part_id' => $i->part_id, 'description' => $i->description, 'qty' => (float) $i->qty,
            'unit_price' => (float) $i->unit_cost, 'discount' => (float) $i->discount, 'tax_rate' => (float) $i->tax_rate, 'label' => $i->part->label()])->all();

        return view('purchasing::order-form', ['po' => $po, 'rows' => $rows, 'warehouses' => Options::warehouses()]);
    }

    public function updateOrder(Request $request, PurchaseOrder $po)
    {
        $this->can('purchase_orders.update');
        $data = $request->validate(['supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('company_id', tenant()->id())],
            'warehouse_id' => ['required', 'integer'], 'expected_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string'], 'terms' => ['nullable', 'string']] + $this->itemRules());
        $this->service->updateOrder($po, $data, $this->cleanItems($data['items']));

        return redirect()->route('purchase-orders.show', $po)->with('success', __('Purchase order updated.'));
    }

    public function orderAction(PurchaseOrder $po, string $action)
    {
        match ($action) {
            'submit' => [$this->can('purchase_orders.update'), $this->service->submitOrder($po)],
            'approve' => [$this->can('purchase_orders.approve'), $this->service->approveOrder($po)],
            'cancel' => [$this->can('purchase_orders.delete'), in_array($po->status, ['draft', 'pending_approval', 'approved'], true) && $po->receipts()->count() === 0 ? $po->update(['status' => 'cancelled']) : abort(403)],
            'close' => [$this->can('purchase_orders.update'), $po->update(['status' => 'closed'])],
        };

        return back()->with('success', __('Purchase order updated.'));
    }

    public function receiveForm(PurchaseOrder $po)
    {
        $this->can('goods_receipts.create');
        abort_unless(in_array($po->status, ['approved', 'partially_received'], true), 403, __('Only approved purchase orders can be received.'));

        return view('purchasing::receive', ['po' => $po->load('items.part', 'supplier'), 'warehouses' => Options::warehouses()]);
    }

    public function receive(Request $request, PurchaseOrder $po)
    {
        $this->can('goods_receipts.create');
        $data = $request->validate(['lines' => ['required', 'array'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'], 'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:60'], 'lines.*.serials' => ['nullable', 'string', 'max:5000'], 'lines.*.expiry_date' => ['nullable', 'date'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', tenant()->id())],
            'supplier_reference' => ['nullable', 'string', 'max:60'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $grn = $this->service->receive($po, $data['lines'], collect($data)->except('lines')->all());

        return redirect()->route('goods-receipts.show', $grn)->with('success', __('Goods received and stock updated.'));
    }

    public function printOrder(PurchaseOrder $po)
    {
        $this->can('purchase_orders.print');

        return view('purchasing::print.order', ['po' => $po->load('items.part', 'supplier', 'warehouse', 'approver')]);
    }

    // ------------------------------------------------------------------ GRN

    public function receipt(GoodsReceipt $grn)
    {
        $this->can('goods_receipts.view');

        return view('purchasing::receipt', ['grn' => $grn->load('items.part', 'supplier', 'order', 'warehouse', 'landedCosts')]);
    }

    public function printReceipt(GoodsReceipt $grn, string $format = 'a4')
    {
        $this->can('goods_receipts.print');
        $html = view('purchasing::print.receipt', ['grn' => $grn->load('items.part', 'items.location', 'supplier', 'order', 'warehouse', 'creator', 'poster')])->render();
        if ($format === 'pdf') {
            return response(\App\Support\Export\Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$grn->number.'.pdf"']);
        }

        return response($html);
    }

    public function invoiceReceipt(Request $request, GoodsReceipt $grn)
    {
        $this->can('supplier_invoices.create');
        $data = $request->validate(['supplier_invoice_number' => ['required', 'string', 'max:60'], 'invoice_date' => ['required', 'date']]);
        $si = $this->service->invoiceFromReceipt($grn, $data['supplier_invoice_number'], $data['invoice_date']);

        return redirect()->route('supplier-invoices.show', $si)->with('success', __('Supplier invoice created. Review and post it.'));
    }

    // ------------------------------------------------------ supplier invoices

    public function createSupplierInvoice()
    {
        $this->can('supplier_invoices.create');

        return view('purchasing::supplier-invoice-form', ['accounts' => Options::accounts(['expense', 'asset'])]);
    }

    public function storeSupplierInvoice(Request $request)
    {
        $this->can('supplier_invoices.create');
        $c = tenant()->id();
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $c)],
            'supplier_invoice_number' => ['required', 'string', 'max:60'], 'invoice_date' => ['required', 'date'], 'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'], 'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('company_id', $c)],
            'lines.*.description' => ['nullable', 'string', 'max:250'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'], 'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $lines = array_values(array_filter($data['lines'], fn ($l) => ! empty($l['description']) && Money::isPositive($l['qty'] ?? 0)));
        $si = $this->service->createSupplierInvoice(collect($data)->except('lines')->all(), $lines);

        return redirect()->route('supplier-invoices.show', $si);
    }

    public function supplierInvoice(SupplierInvoice $si)
    {
        $this->can('supplier_invoices.view');

        return view('purchasing::supplier-invoice', ['si' => $si->load('items.part', 'items.account', 'supplier', 'order', 'receipt', 'allocations.payment')]);
    }

    public function postSupplierInvoice(SupplierInvoice $si)
    {
        $this->can('supplier_invoices.approve');
        $this->service->postSupplierInvoice($si);

        return back()->with('success', __('Supplier invoice posted.'));
    }

    // -------------------------------------------------------- landed costs

    public function createLandedCost(Request $request)
    {
        $this->can('landed_costs.create');

        return view('purchasing::landed-cost-form', ['receipts' => GoodsReceipt::query()->where('status', 'posted')->latest('id')->limit(100)->get(),
            'grnId' => $request->query('goods_receipt_id')]);
    }

    public function storeLandedCost(Request $request)
    {
        $this->can('landed_costs.create');
        $data = $request->validate([
            'goods_receipt_id' => ['required', 'integer', Rule::exists('goods_receipts', 'id')->where('company_id', tenant()->id())],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('company_id', tenant()->id())],
            'allocation_method' => ['required', 'in:value,qty'], 'date' => ['required', 'date'], 'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array'], 'lines.*.type' => ['required', 'in:freight,customs,insurance,clearance,other'],
            'lines.*.description' => ['nullable', 'string', 'max:190'], 'lines.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        $lc = DB::transaction(function () use ($data) {
            $lc = LandedCost::create(collect($data)->except('lines')->all() + ['number' => DocumentNumber::next('landed_cost'), 'status' => 'draft', 'created_by' => auth()->id()]);
            foreach ($data['lines'] as $l) {
                if (Money::isPositive($l['amount'] ?? 0)) {
                    $lc->lines()->create($l);
                }
            }
            $lc->update(['total' => Money::sum($lc->lines()->pluck('amount'))]);

            return $lc;
        });

        return redirect()->route('landed-costs.show', $lc);
    }

    public function landedCost(LandedCost $lc)
    {
        $this->can('landed_costs.view');

        return view('purchasing::landed-cost', ['lc' => $lc->load('lines', 'receipt.items.part', 'supplier')]);
    }

    public function postLandedCost(LandedCost $lc)
    {
        $this->can('landed_costs.approve');
        $this->service->postLandedCost($lc, $lc->supplier_id ? 'ap' : 'grni');

        return back()->with('success', __('Landed cost allocated to stock and posted.'));
    }

    // ------------------------------------------------------ purchase returns

    public function createReturn(Request $request)
    {
        $this->can('purchase_returns.create');
        $grn = $request->query('goods_receipt_id') ? GoodsReceipt::query()->with('items.part', 'supplier')->find($request->query('goods_receipt_id')) : null;

        return view('purchasing::return-form', ['grn' => $grn, 'warehouses' => Options::warehouses()]);
    }

    public function storeReturn(Request $request)
    {
        $this->can('purchase_returns.create');
        $data = $request->validate([
            'goods_receipt_id' => ['required', 'integer', Rule::exists('goods_receipts', 'id')->where('company_id', tenant()->id())],
            'reason' => ['required', 'string', 'max:250'], 'qty' => ['required', 'array'], 'qty.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $grn = GoodsReceipt::query()->with('items.orderItem')->findOrFail($data['goods_receipt_id']);
        $ret = DB::transaction(function () use ($grn, $data) {
            $r = PurchaseReturn::create(['number' => DocumentNumber::next('purchase_return'), 'supplier_id' => $grn->supplier_id, 'goods_receipt_id' => $grn->id,
                'warehouse_id' => $grn->warehouse_id, 'branch_id' => $grn->branch_id, 'date' => now()->toDateString(), 'status' => 'draft', 'reason' => $data['reason'], 'created_by' => auth()->id()]);
            foreach ($grn->items as $gi) {
                $q = Money::round($data['qty'][$gi->id] ?? 0);
                if (Money::isPositive($q)) {
                    $rate = $gi->orderItem?->tax_rate ?? 5;
                    $c = Totals::line($q, $gi->unit_cost, 0, $rate);
                    $r->items()->create(['part_id' => $gi->part_id, 'goods_receipt_item_id' => $gi->id, 'qty' => $q, 'unit_cost' => $gi->unit_cost,
                        'tax_rate' => $rate, 'tax_amount' => $c['tax'], 'line_total' => $c['net']]);
                }
            }
            abort_if($r->items()->count() === 0, 422, __('Enter return quantities.'));

            return $r;
        });

        return redirect()->route('purchase-returns.show', $ret);
    }

    public function purchaseReturn(PurchaseReturn $ret)
    {
        $this->can('purchase_returns.view');

        return view('purchasing::return', ['r' => $ret->load('items.part', 'supplier', 'receipt', 'warehouse')]);
    }

    public function postReturn(PurchaseReturn $ret)
    {
        $this->can('purchase_returns.approve');
        $this->service->postReturn($ret);

        return back()->with('success', __('Purchase return posted.'));
    }
}
