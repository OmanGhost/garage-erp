<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\SubcontractOrder;
use App\Modules\Workshop\Services\SubcontractService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Outside (subcontracted) repairs: list of work out with specialists, and the actions on the job card. */
class SubcontractController extends Controller
{
    public function __construct(protected SubcontractService $svc) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    public function index(Request $request)
    {
        $this->can('subcontracts.view');
        $status = in_array($request->query('status'), ['draft', 'sent_out', 'back_in', 'cancelled', 'all'], true) ? $request->query('status') : 'open';
        $orders = SubcontractOrder::query()->with('jobCard.vehicle', 'supplier', 'supplierInvoice')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['draft', 'sent_out']))
            ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when($request->query('supplier_id'), fn ($q, $s) => $q->where('supplier_id', $s))
            ->latest('id')->paginate(30)->withQueryString();
        $base = SubcontractOrder::query();

        return view('workshop::subcontracts.index', [
            'orders' => $orders, 'status' => $status,
            'stats' => [
                'out' => (clone $base)->where('status', 'sent_out')->count(),
                'overdue' => (clone $base)->where('status', 'sent_out')->whereDate('expected_at', '<', today())->count(),
                'month_cost' => (float) (clone $base)->where('status', 'back_in')->where('returned_at', '>=', now()->startOfMonth())->sum('actual_cost'),
                'month_sell' => (float) (clone $base)->where('status', 'back_in')->where('returned_at', '>=', now()->startOfMonth())->sum('sell_price'),
                'warranty' => (clone $base)->where('status', 'back_in')->whereDate('warranty_until', '>=', today())->count(),
            ],
            'suppliers' => \App\Modules\Purchasing\Models\Supplier::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, JobCard $jobCard)
    {
        $this->can('subcontracts.create');
        $d = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('company_id', tenant()->id())],
            'work_type' => ['required', Rule::in(SubcontractOrder::WORK_TYPES)],
            'description' => ['required', 'string', 'max:190'], 'item_sent' => ['nullable', 'string', 'max:190'],
            'quoted_cost' => ['required', 'numeric', 'min:0', 'max:1000000'], 'sell_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'expected_at' => ['nullable', 'date'], 'warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:1000'], 'pre_approved' => ['nullable', 'boolean'],
        ]);
        $o = $this->svc->create($jobCard, $d);

        return back()->with('success', __('Outside work order :n created.', ['n' => $o->number]));
    }

    public function action(Request $request, SubcontractOrder $order, string $action)
    {
        $this->can('subcontracts.update');
        switch ($action) {
            case 'send':
                $this->svc->send($order);

                return back()->with('success', __('Marked as sent to the subcontractor.'));
            case 'receive':
                $d = $request->validate(['actual_cost' => ['required', 'numeric', 'min:0', 'max:1000000'], 'supplier_invoice_number' => ['nullable', 'string', 'max:60'],
                    'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100']]);
                $o = $this->svc->receive($order, (string) $d['actual_cost'], $d['supplier_invoice_number'] ?? null, isset($d['tax_rate']) ? (string) $d['tax_rate'] : null);

                return back()->with('success', $o->supplier_invoice_id ? __('Received back. A draft supplier invoice was prepared.') : __('Received back.'));
            case 'cancel':
                $this->svc->cancel($order, $request->validate(['reason' => ['nullable', 'string', 'max:190']])['reason'] ?? null);

                return back()->with('success', __('Outside work order cancelled.'));
        }
        abort(404);
    }

    public function print(SubcontractOrder $order)
    {
        $this->can('subcontracts.view');

        return view('workshop::subcontracts.print', ['o' => $order->load('jobCard.vehicle.make', 'jobCard.vehicle.vehicleModel', 'jobCard.customer', 'supplier'), 'paper' => 'A5']);
    }
}
