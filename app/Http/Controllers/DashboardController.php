<?php

namespace App\Http\Controllers;

use App\Modules\Accounting\Services\Accounts;
use App\Modules\Accounting\Services\FinancialReports;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Assets\Models\Equipment;
use App\Modules\HR\Models\Employee;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Workshop\Models\JobCard;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public const WIDGETS = [
        'finance' => 'Financial KPIs',
        'workshop' => 'Workshop KPIs',
        'revenue_chart' => 'Revenue chart',
        'status_chart' => 'Job cards by status',
        'technicians' => 'Technician performance',
        'departments' => 'Department performance',
        'low_stock' => 'Low stock parts',
        'alerts' => 'Alerts & notifications',
        'recent_jobs' => 'Recent job cards',
    ];

    public function index(Request $request)
    {
        abort_unless($request->user()->can('dashboard.view'), 403);
        $enabled = $request->user()->dashboard_widgets ?: array_keys(self::WIDGETS);
        $period = in_array($request->query('period'), ['day', 'month', 'year'], true) ? $request->query('period') : 'month';
        $from = match ($period) { 'day' => now()->startOfDay(), 'year' => now()->startOfYear(), default => now()->startOfMonth() };
        $canFinance = $request->user()->can('reports.view');
        $key = 'dash:'.tenant()->id().':'.tenant()->branchId().':'.$period.':'.($canFinance ? 1 : 0);

        $data = Cache::remember($key, 300, fn () => $this->compute($from->toDateString(), now()->toDateString(), $canFinance));

        return view('dashboard', ['d' => $data, 'enabled' => $enabled, 'period' => $period, 'widgets' => self::WIDGETS, 'canFinance' => $canFinance]);
    }

    public function saveWidgets(Request $request)
    {
        $data = $request->validate(['widgets' => ['array'], 'widgets.*' => ['in:'.implode(',', array_keys(self::WIDGETS))]]);
        $request->user()->forceFill(['dashboard_widgets' => array_values($data['widgets'] ?? [])])->save();

        return back()->with('success', __('Dashboard updated.'));
    }

    protected function compute(string $from, string $to, bool $finance): array
    {
        $d = [];
        if ($finance) {
            $is = FinancialReports::incomeStatement($from, $to);
            $d['revenue'] = $is['total_revenue'];
            $d['expenses'] = Money::add($is['total_cogs'], $is['total_expenses']);
            $d['net_profit'] = $is['net_income'];
            try {
                $d['receivables'] = Ledger::accountBalance(Accounts::id('ar'));
                $d['payables'] = Money::neg(Ledger::accountBalance(Accounts::id('ap')));
            } catch (\Throwable) {
                $d['receivables'] = $d['payables'] = '0';
            }
            $d['inventory_value'] = app(StockService::class)->valuation();
            $d['parts_sales'] = Money::round(DB::table('invoice_items as i')->join('invoices as v', 'v.id', '=', 'i.invoice_id')
                ->where('v.company_id', tenant()->id())->whereIn('v.status', ['posted', 'partially_paid', 'paid'])
                ->whereBetween('v.issue_date', [$from, $to])->where('i.item_type', 'part')->sum('i.line_total'));
        }

        $statusCounts = JobCard::query()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all();
        $d['status_counts'] = $statusCounts;
        $d['in_workshop'] = array_sum(array_intersect_key($statusCounts, array_flip(JobCard::OPEN_STATUSES)));
        $d['in_progress'] = $statusCounts[JobCard::IN_PROGRESS] ?? 0;
        $d['awaiting_approval'] = ($statusCounts[JobCard::AWAITING_APPROVAL] ?? 0) + ($statusCounts[JobCard::AWAITING_QUOTATION] ?? 0);
        $d['awaiting_parts'] = $statusCounts[JobCard::AWAITING_PARTS] ?? 0;
        $d['ready'] = $statusCounts[JobCard::READY] ?? 0;
        $d['opened_period'] = JobCard::query()->whereBetween('received_at', [$from, $to.' 23:59:59'])->count();
        $d['closed_period'] = JobCard::query()->whereIn('status', [JobCard::DELIVERED, JobCard::COMPLETED])->whereBetween('delivered_at', [$from, $to.' 23:59:59'])->count();

        // revenue charts (posted invoices, net of VAT)
        $daily = Invoice::query()->whereIn('status', ['posted', 'partially_paid', 'paid'])->where('issue_date', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('issue_date d, SUM(total - tax_total) v')->groupBy('issue_date')->pluck('v', 'd')->all();
        $d['daily'] = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $d['daily'][$day] = (float) ($daily[$day] ?? 0);
        }
        $monthly = [];
        Invoice::query()->whereIn('status', ['posted', 'partially_paid', 'paid'])->where('issue_date', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->select('issue_date', 'total', 'tax_total')->orderBy('id')->each(function ($inv) use (&$monthly) {
                $k = $inv->issue_date->format('Y-m');
                $monthly[$k] = ($monthly[$k] ?? 0) + (float) $inv->total - (float) $inv->tax_total;
            });
        $d['monthly'] = [];
        for ($i = 11; $i >= 0; $i--) {
            $k = now()->subMonths($i)->format('Y-m');
            $d['monthly'][$k] = round($monthly[$k] ?? 0, 3);
        }

        $d['technicians'] = DB::table('job_card_tasks as t')->join('users as u', 'u.id', '=', 't.technician_id')
            ->join('job_cards as j', 'j.id', '=', 't.job_card_id')->where('j.company_id', tenant()->id())
            ->where('t.status', 'done')->whereBetween('t.finished_at', [$from, $to.' 23:59:59'])
            ->groupBy('u.id', 'u.name')->selectRaw('u.name, COUNT(*) tasks, SUM(t.actual_minutes) minutes, SUM(t.estimated_minutes) est, SUM(t.line_total) billed')
            ->orderByDesc('billed')->limit(8)->get();
        $d['departments'] = DB::table('job_card_tasks as t')->join('departments as dp', 'dp.id', '=', 't.department_id')
            ->join('job_cards as j', 'j.id', '=', 't.job_card_id')->where('j.company_id', tenant()->id())
            ->where('t.status', '!=', 'cancelled')->whereBetween('j.received_at', [$from, $to.' 23:59:59'])
            ->groupBy('dp.id', 'dp.name_ar', 'dp.name_en')->selectRaw('dp.name_ar, dp.name_en, COUNT(DISTINCT j.id) jobs, SUM(t.line_total) revenue, SUM(t.labor_cost) labor')
            ->orderByDesc('revenue')->limit(8)->get();

        $d['low_stock'] = DB::table('parts as p')->leftJoin('stock_levels as s', 's.part_id', '=', 'p.id')
            ->where('p.company_id', tenant()->id())->where('p.is_active', true)->whereNull('p.deleted_at')->where('p.min_stock', '>', 0)
            ->groupBy('p.id', 'p.sku', 'p.name_ar', 'p.name_en', 'p.min_stock')
            ->havingRaw('COALESCE(SUM(s.qty_on_hand),0) <= p.min_stock')
            ->selectRaw('p.id, p.sku, p.name_ar, p.name_en, p.min_stock, COALESCE(SUM(s.qty_on_hand),0) on_hand')->limit(10)->get();

        $alerts = [];
        $overdue = Invoice::query()->whereIn('status', ['posted', 'partially_paid'])->whereDate('due_date', '<', now())->count();
        if ($overdue) {
            $alerts[] = ['danger', 'bi-receipt', __(':n overdue invoices', ['n' => $overdue]), route('crud.index', ['invoices', 'f_status' => 'posted'])];
        }
        if (count($d['low_stock'])) {
            $alerts[] = ['warning', 'bi-box-seam', __(':n parts at or below minimum stock', ['n' => count($d['low_stock'])]), route('inventory.reorder')];
        }
        $docs = Employee::query()->where('status', 'active')->where(fn ($q) => $q->whereDate('id_expiry', '<=', now()->addDays(30))
            ->orWhereDate('visa_expiry', '<=', now()->addDays(30))->orWhereDate('passport_expiry', '<=', now()->addDays(30)))->count();
        if ($docs) {
            $alerts[] = ['warning', 'bi-person-badge', __(':n employees with documents expiring within 30 days', ['n' => $docs]), route('crud.index', 'employees')];
        }
        $equip = Equipment::query()->where('status', '!=', 'disposed')->where(fn ($q) => $q->whereDate('next_maintenance_at', '<=', now()->addDays(7))
            ->orWhereDate('next_calibration_at', '<=', now()->addDays(7)))->count();
        if ($equip) {
            $alerts[] = ['info', 'bi-wrench-adjustable', __(':n equipment items due for maintenance/calibration', ['n' => $equip]), route('crud.index', 'equipment')];
        }
        $pendingAdd = DB::table('job_card_tasks as t')->join('job_cards as j', 'j.id', '=', 't.job_card_id')->where('j.company_id', tenant()->id())
            ->where('t.approval_status', 'pending')->whereNull('j.deleted_at')->distinct()->count('j.id');
        if ($pendingAdd) {
            $alerts[] = ['warning', 'bi-hourglass-split', __(':n job cards waiting for additional work approval', ['n' => $pendingAdd]), route('crud.index', ['job-cards', 'open' => 1])];
        }
        $late = JobCard::query()->open()->whereNotNull('promised_at')->where('promised_at', '<', now())->count();
        if ($late) {
            $alerts[] = ['danger', 'bi-alarm', __(':n vehicles past their promised delivery time', ['n' => $late]), route('workshop.board')];
        }
        $d['alerts'] = $alerts;
        $d['recent_jobs'] = JobCard::query()->with('vehicle', 'customer')->latest('id')->limit(8)->get();

        return $d;
    }
}
