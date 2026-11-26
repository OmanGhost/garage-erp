<?php

namespace App\Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Models\SubscriptionInvoice;
use App\Modules\Saas\Services\BillingService;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Platform admin: client invoices (issue, list, view, print, record payment, cancel) and invoice header settings. */
class BillingController extends Controller
{
    public function __construct(protected BillingService $billing) {}

    public function index(Request $request)
    {
        tenant()->forget();
        $q = trim((string) $request->query('q'));
        $status = $request->query('status');
        $invoices = SubscriptionInvoice::query()->with('company')
            ->when($status === 'overdue', fn ($w) => $w->where('status', 'unpaid')->whereDate('due_date', '<', today()))
            ->when(in_array($status, SubscriptionInvoice::STATUSES, true), fn ($w) => $w->where('status', $status))
            ->when($request->query('company_id'), fn ($w, $c) => $w->where('company_id', $c))
            ->when($request->query('from'), fn ($w, $d) => $w->whereDate('issue_date', '>=', $d))
            ->when($request->query('to'), fn ($w, $d) => $w->whereDate('issue_date', '<=', $d))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%$q%")->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%$q%"))))
            ->latest('issue_date')->latest('id')->paginate(30)->withQueryString();

        return view('saas::billing', [
            'invoices' => $invoices,
            'clients' => Company::query()->orderBy('name')->pluck('name', 'id'),
            'stats' => [
                'unpaid' => SubscriptionInvoice::query()->where('status', 'unpaid')->sum('amount'),
                'unpaid_count' => SubscriptionInvoice::query()->where('status', 'unpaid')->count(),
                'overdue' => SubscriptionInvoice::query()->where('status', 'unpaid')->whereDate('due_date', '<', today())->sum('amount'),
                'overdue_count' => SubscriptionInvoice::query()->where('status', 'unpaid')->whereDate('due_date', '<', today())->count(),
                'paid_month' => SubscriptionInvoice::query()->where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
                'paid_year' => SubscriptionInvoice::query()->where('status', 'paid')->where('paid_at', '>=', now()->startOfYear())->sum('amount'),
            ],
            'settings' => SiteSettings::all(),
        ]);
    }

    public function create(Request $request)
    {
        return view('saas::billing-form', [
            'clients' => Company::query()->with('plan')->orderBy('name')->get(),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort')->get(),
            'companyId' => (int) $request->query('company_id'),
            'taxRate' => SiteSettings::get('billing_tax_rate', 5),
            'dueDays' => (int) SiteSettings::get('billing_due_days', 7),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'type' => ['required', Rule::in(['renewal', 'custom'])],
            'plan_id' => ['required_if:type,renewal', 'nullable', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required_if:type,renewal', 'nullable', Rule::in(['monthly', 'yearly'])],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.description' => ['nullable', 'string', 'max:250'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', Rule::in(SubscriptionInvoice::METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $company = Company::query()->findOrFail($data['company_id']);
        $items = array_values(array_filter($data['items'] ?? [], fn ($i) => filled($i['description'] ?? null)));
        $opt = [
            'discount' => $data['discount'] ?? 0, 'tax_rate' => $data['tax_rate'], 'issue_date' => $data['issue_date'], 'due_date' => $data['due_date'],
            'notes' => $data['notes'] ?? null, 'paid' => $request->boolean('paid'),
            'payment_method' => $data['payment_method'] ?? null, 'payment_reference' => $data['payment_reference'] ?? null,
        ];
        $inv = $data['type'] === 'renewal'
            ? $this->billing->renew($company, Plan::query()->findOrFail($data['plan_id']), $data['billing_cycle'], $opt + ['extra_items' => $items])
            : $this->billing->issue($company, $items, $opt);

        return redirect()->route('admin.billing.show', $inv)->with('success', __('Invoice :n issued for :c.', ['n' => $inv->number, 'c' => $company->name]));
    }

    public function show(SubscriptionInvoice $invoice)
    {
        return view('saas::billing-show', ['inv' => $invoice->load('company.plan', 'subscription.plan', 'creator'), 'settings' => SiteSettings::all()]);
    }

    public function pay(Request $request, SubscriptionInvoice $invoice)
    {
        $data = $request->validate(['payment_method' => ['required', Rule::in(SubscriptionInvoice::METHODS)], 'payment_reference' => ['nullable', 'string', 'max:120'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today']]);
        $this->billing->markPaid($invoice, $data['payment_method'], $data['payment_reference'] ?? null, $data['paid_on'] ?? null);

        return back()->with('success', __('Payment recorded for :n.', ['n' => $invoice->number]));
    }

    public function cancel(Request $request, SubscriptionInvoice $invoice)
    {
        $this->billing->cancel($invoice, $request->validate(['reason' => ['required', 'string', 'max:250']])['reason']);

        return back()->with('success', __('Invoice :n cancelled.', ['n' => $invoice->number]));
    }

    public function print(SubscriptionInvoice $invoice, string $format = 'a4')
    {
        return self::render($invoice, $format);
    }

    /** Shared with the client's own Subscription page. */
    public static function render(SubscriptionInvoice $invoice, string $format = 'a4')
    {
        $html = view('saas::print.invoice', ['inv' => $invoice->load('company', 'subscription.plan'), 's' => SiteSettings::all()])->render();

        return $format === 'pdf'
            ? response(\App\Support\Export\Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$invoice->number.'.pdf"'])
            : response($html);
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'billing_name' => ['nullable', 'string', 'max:190'], 'billing_vat_number' => ['nullable', 'string', 'max:60'],
            'billing_cr_number' => ['nullable', 'string', 'max:60'], 'billing_address' => ['nullable', 'string', 'max:300'],
            'billing_email' => ['nullable', 'email', 'max:190'], 'billing_phone' => ['nullable', 'string', 'max:40'],
            'billing_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'billing_due_days' => ['required', 'integer', 'min:0', 'max:120'],
            'billing_bank_details' => ['nullable', 'string', 'max:1000'], 'billing_terms' => ['nullable', 'string', 'max:2000'],
        ]);
        SiteSettings::set(array_map(fn ($v) => (string) $v, $data));

        return redirect()->to(route('admin.billing.index').'#billing-settings')->with('success', __('Billing details saved.'));
    }
}
