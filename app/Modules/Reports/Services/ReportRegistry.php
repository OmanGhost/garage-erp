<?php

namespace App\Modules\Reports\Services;

use App\Modules\HR\Services\PayrollService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Report center definitions. Every report returns
 * ['columns' => [key => label], 'rows' => array, 'totals' => [key => value], 'money' => [keys]].
 * Filters: from, to, branch_id, department_id, user_id, customer_id.
 */
class ReportRegistry
{
    public static function groups(): array
    {
        return [
            __('Sales & purchases') => ['sales' => __('Sales report'), 'sales_by_customer' => __('Sales by customer'), 'sales_by_salesperson' => __('Sales by salesperson'), 'purchases' => __('Purchases report'), 'tax' => __('Tax (VAT) report'), 'cash' => __('Cash & POS sessions')],
            __('Profitability') => ['job_profit' => __('Job card profitability'), 'department_profit' => __('Department profitability'), 'branch_profit' => __('Branch profitability'), 'part_profit' => __('Spare part profitability'), 'dismantling_profit' => __('Dismantled vehicles profitability')],
            __('Workshop & people') => ['technicians' => __('Technician performance'), 'productivity' => __('Employee productivity'), 'work_hours' => __('Work hours log'), 'repair_time' => __('Average repair time'), 'comebacks' => __('Comeback (repeat repair) rate')],
            __('Customers & suppliers') => ['customers' => __('Customers report'), 'receivables' => __('Debts / receivables'), 'suppliers' => __('Suppliers report')],
            __('Inventory') => ['stock_value' => __('Inventory valuation'), 'best_sellers' => __('Best selling parts'), 'dead_stock' => __('Slow moving / dead stock')],
            __('Warranty, insurance & fleets') => ['warranty' => __('Warranty report'), 'insurance' => __('Insurance claims report'), 'fleets' => __('Fleets report')],
        ];
    }

    public static function title(string $key): ?string
    {
        foreach (self::groups() as $items) {
            if (isset($items[$key])) {
                return $items[$key];
            }
        }

        return null;
    }

    public static function run(string $key, array $f): array
    {
        $c = tenant()->id();
        $from = $f['from'] ?? now()->startOfMonth()->toDateString();
        $to = $f['to'] ?? now()->toDateString();
        $toEnd = $to.' 23:59:59';
        $posted = ['posted', 'partially_paid', 'paid'];
        $branch = fn ($q, $col) => ! empty($f['branch_id']) ? $q->where($col, $f['branch_id']) : $q;

        $res = match ($key) {
            'sales' => [
                'columns' => ['number' => __('Invoice'), 'issue_date' => __('Date'), 'type' => __('Type'), 'customer' => __('Customer'), 'net' => __('Net'), 'tax_total' => __('VAT'), 'total' => __('Total'), 'paid_amount' => __('Paid'), 'cost_total' => __('Cost'), 'profit' => __('Profit')],
                'rows' => $branch(DB::table('invoices as i')->join('customers as c', 'c.id', '=', 'i.customer_id')->where('i.company_id', $c)->whereIn('i.status', $posted)
                    ->whereBetween('i.issue_date', [$from, $to])->when($f['customer_id'] ?? null, fn ($q, $v) => $q->where('i.customer_id', $v)), 'i.branch_id')
                    ->selectRaw('i.number, i.issue_date, i.type, c.name customer, (i.total - i.tax_total) net, i.tax_total, i.total, i.paid_amount, i.cost_total, (i.total - i.tax_total - i.cost_total) profit')
                    ->orderBy('i.issue_date')->get(),
                'money' => ['net', 'tax_total', 'total', 'paid_amount', 'cost_total', 'profit'],
            ],
            'sales_by_customer' => [
                'columns' => ['customer' => __('Customer'), 'invoices' => __('Invoices'), 'net' => __('Net sales'), 'profit' => __('Profit')],
                'rows' => $branch(DB::table('invoices as i')->join('customers as c', 'c.id', '=', 'i.customer_id')->where('i.company_id', $c)->whereIn('i.status', $posted)->whereBetween('i.issue_date', [$from, $to]), 'i.branch_id')
                    ->groupBy('c.id', 'c.name')->selectRaw('c.name customer, COUNT(*) invoices, SUM(i.total - i.tax_total) net, SUM(i.total - i.tax_total - i.cost_total) profit')->orderByDesc('net')->get(),
                'money' => ['net', 'profit'],
            ],
            'sales_by_salesperson' => [
                'columns' => ['name' => __('Salesperson'), 'invoices' => __('Invoices'), 'net' => __('Net sales'), 'commission' => __('Commission')],
                'rows' => DB::table('invoices as i')->join('users as u', 'u.id', '=', 'i.salesperson_id')->where('i.company_id', $c)->whereIn('i.status', $posted)->whereBetween('i.issue_date', [$from, $to])
                    ->groupBy('u.id', 'u.name')->selectRaw('u.name, COUNT(*) invoices, SUM(i.total - i.tax_total) net, (SELECT COALESCE(SUM(amount),0) FROM commissions m WHERE m.user_id = u.id AND m.type = \'sales\' AND m.earned_at BETWEEN ? AND ?) commission', [$from, $to])
                    ->orderByDesc('net')->get(),
                'money' => ['net', 'commission'],
            ],
            'purchases' => [
                'columns' => ['number' => __('Number'), 'supplier_invoice_number' => __('Supplier no.'), 'invoice_date' => __('Date'), 'supplier' => __('Supplier'), 'subtotal' => __('Net'), 'tax_total' => __('VAT'), 'total' => __('Total'), 'paid_amount' => __('Paid')],
                'rows' => $branch(DB::table('supplier_invoices as i')->join('suppliers as s', 's.id', '=', 'i.supplier_id')->where('i.company_id', $c)->whereIn('i.status', $posted)->whereBetween('i.invoice_date', [$from, $to]), 'i.branch_id')
                    ->selectRaw('i.number, i.supplier_invoice_number, i.invoice_date, s.name supplier, i.subtotal, i.tax_total, i.total, i.paid_amount')->orderBy('i.invoice_date')->get(),
                'money' => ['subtotal', 'tax_total', 'total', 'paid_amount'],
            ],
            'tax' => [
                'columns' => ['month' => __('Month'), 'sales_net' => __('Taxable sales'), 'output_vat' => __('Output VAT'), 'purchases_net' => __('Taxable purchases'), 'input_vat' => __('Input VAT'), 'net' => __('Net payable')],
                'rows' => self::vatByMonth($from, $to),
                'money' => ['sales_net', 'output_vat', 'purchases_net', 'input_vat', 'net'],
            ],
            'cash' => [
                'columns' => ['number' => __('Session'), 'register' => __('Register'), 'cashier' => __('Cashier'), 'opened_at' => __('Opened'), 'closed_at' => __('Closed'), 'opening_cash' => __('Opening'), 'expected_cash' => __('Expected'), 'counted_cash' => __('Counted'), 'difference' => __('Over/short')],
                'rows' => $branch(DB::table('pos_sessions as s')->join('cash_registers as r', 'r.id', '=', 's.cash_register_id')->join('users as u', 'u.id', '=', 's.user_id')->where('s.company_id', $c)->whereBetween('s.opened_at', [$from, $toEnd]), 's.branch_id')
                    ->selectRaw('s.number, r.name register, u.name cashier, s.opened_at, s.closed_at, s.opening_cash, s.expected_cash, s.counted_cash, s.difference')->orderBy('s.opened_at')->get(),
                'money' => ['opening_cash', 'expected_cash', 'counted_cash', 'difference'],
            ],
            'job_profit' => [
                'columns' => ['number' => __('Job card'), 'received_at' => __('Received'), 'customer' => __('Customer'), 'plate' => __('Vehicle'), 'status' => __('Status'), 'revenue' => __('Revenue'), 'labor_cost' => __('Labour cost'), 'parts_cost' => __('Parts cost'), 'profit' => __('Profit'), 'margin' => __('Margin %')],
                'rows' => $branch(DB::table('job_cards as j')->join('customers as c', 'c.id', '=', 'j.customer_id')->join('vehicles as v', 'v.id', '=', 'j.vehicle_id')->where('j.company_id', $c)->whereNull('j.deleted_at')
                    ->whereBetween('j.received_at', [$from, $toEnd])->when($f['department_id'] ?? null, fn ($q, $d) => $q->where('j.department_id', $d)), 'j.branch_id')
                    ->selectRaw('j.number, j.received_at, c.name customer, v.plate_number plate, j.status, j.revenue, j.labor_cost, j.parts_cost, j.profit, CASE WHEN j.revenue > 0 THEN ROUND(j.profit * 100 / j.revenue, 1) ELSE NULL END margin')
                    ->orderBy('j.received_at')->get(),
                'money' => ['revenue', 'labor_cost', 'parts_cost', 'profit'],
            ],
            'department_profit' => [
                'columns' => ['department' => __('Department'), 'jobs' => __('Jobs'), 'services' => __('Service revenue'), 'labor_cost' => __('Labour cost'), 'hours' => __('Hours'), 'profit' => __('Gross profit')],
                'rows' => DB::table('job_card_tasks as t')->join('job_cards as j', 'j.id', '=', 't.job_card_id')->join('departments as d', 'd.id', '=', 't.department_id')
                    ->where('j.company_id', $c)->whereNotNull('j.invoice_id')->where('t.status', '!=', 'cancelled')->whereBetween('j.received_at', [$from, $toEnd])
                    ->groupBy('d.id', 'd.name_ar')->selectRaw('d.name_ar department, COUNT(DISTINCT j.id) jobs, SUM(t.line_total) services, SUM(t.labor_cost) labor_cost, ROUND(SUM(t.actual_minutes)/60, 1) hours, SUM(t.line_total - t.labor_cost) profit')
                    ->orderByDesc('services')->get(),
                'money' => ['services', 'labor_cost', 'profit'],
            ],
            'branch_profit' => [
                'columns' => ['branch' => __('Branch'), 'invoices' => __('Invoices'), 'net' => __('Net sales'), 'cogs' => __('Cost of sales'), 'gross' => __('Gross profit'), 'expenses' => __('Expenses'), 'profit' => __('Net profit')],
                'rows' => DB::table('branches as b')->where('b.company_id', $c)->whereNull('b.deleted_at')->get()->map(function ($b) use ($from, $to, $posted, $c) {
                    $s = DB::table('invoices')->where('company_id', $c)->where('branch_id', $b->id)->whereIn('status', $posted)->whereBetween('issue_date', [$from, $to])
                        ->selectRaw('COUNT(*) n, COALESCE(SUM(total - tax_total),0) net, COALESCE(SUM(cost_total),0) cogs')->first();
                    $exp = DB::table('expenses')->where('company_id', $c)->where('branch_id', $b->id)->where('status', 'posted')->whereBetween('date', [$from, $to])->sum('amount');

                    return (object) ['branch' => $b->name_ar, 'invoices' => $s->n, 'net' => $s->net, 'cogs' => $s->cogs, 'gross' => $s->net - $s->cogs, 'expenses' => $exp, 'profit' => $s->net - $s->cogs - $exp];
                }),
                'money' => ['net', 'cogs', 'gross', 'expenses', 'profit'],
            ],
            'part_profit' => [
                'columns' => ['sku' => 'SKU', 'name' => __('Part'), 'qty' => __('Qty sold'), 'revenue' => __('Revenue'), 'cost' => __('Cost'), 'profit' => __('Profit'), 'margin' => __('Margin %')],
                'rows' => DB::table('invoice_items as i')->join('invoices as v', 'v.id', '=', 'i.invoice_id')->join('parts as p', 'p.id', '=', 'i.part_id')
                    ->where('v.company_id', $c)->whereIn('v.status', $posted)->whereBetween('v.issue_date', [$from, $to])
                    ->groupBy('p.id', 'p.sku', 'p.name_ar')->selectRaw('p.sku, p.name_ar name, SUM(i.qty - i.returned_qty) qty, SUM(i.line_total) revenue, SUM(i.cost_total) cost, SUM(i.line_total - i.cost_total) profit, CASE WHEN SUM(i.line_total) > 0 THEN ROUND(SUM(i.line_total - i.cost_total) * 100 / SUM(i.line_total), 1) END margin')
                    ->orderByDesc('profit')->get(),
                'money' => ['revenue', 'cost', 'profit'],
            ],
            'dismantling_profit' => [
                'columns' => ['number' => __('File'), 'vehicle' => __('Vehicle'), 'total_cost' => __('Total cost'), 'parts' => __('Parts'), 'revenue' => __('Revenue'), 'stock_value' => __('Stock value'), 'profit' => __('Profit')],
                'rows' => \App\Modules\Dismantling\Models\DismantledVehicle::query()->with('make', 'vehicleModel')->whereBetween('purchase_date', [$from, $to])->get()->map(function ($v) {
                    $p = app(\App\Modules\Dismantling\Services\DismantlingService::class)->profitability($v);

                    return (object) ['number' => $v->number, 'vehicle' => $v->title(), 'total_cost' => $p['total_cost'], 'parts' => $p['parts_total'], 'revenue' => $p['revenue'], 'stock_value' => $p['stock_value'], 'profit' => $p['profit']];
                }),
                'money' => ['total_cost', 'revenue', 'stock_value', 'profit'],
            ],
            'technicians' => [
                'columns' => ['name' => __('Technician'), 'tasks' => __('Tasks done'), 'hours' => __('Actual hours'), 'est_hours' => __('Standard hours'), 'efficiency' => __('Efficiency %'), 'billed' => __('Billed'), 'commission' => __('Commission'), 'comebacks' => __('QC failures')],
                'rows' => DB::table('job_card_tasks as t')->join('job_cards as j', 'j.id', '=', 't.job_card_id')->join('users as u', 'u.id', '=', 't.technician_id')
                    ->where('j.company_id', $c)->where('t.status', 'done')->whereBetween('t.finished_at', [$from, $toEnd])
                    ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('t.technician_id', $v))->when($f['department_id'] ?? null, fn ($q, $v) => $q->where('t.department_id', $v))
                    ->groupBy('u.id', 'u.name')->selectRaw('u.name, COUNT(*) tasks, ROUND(SUM(t.actual_minutes)/60,1) hours, ROUND(SUM(t.estimated_minutes)/60,1) est_hours,
                        CASE WHEN SUM(t.actual_minutes) > 0 THEN ROUND(SUM(t.estimated_minutes)*100/SUM(t.actual_minutes),1) END efficiency, SUM(t.line_total) billed, SUM(t.commission_amount) commission,
                        (SELECT COUNT(*) FROM quality_checks q WHERE q.result = \'fail\' AND q.job_card_id IN (SELECT job_card_id FROM job_card_tasks t2 WHERE t2.technician_id = u.id)) comebacks')
                    ->orderByDesc('billed')->get(),
                'money' => ['billed', 'commission'],
            ],
            'productivity' => [
                'columns' => ['name' => __('Employee'), 'tasks' => __('Tasks'), 'actual_minutes' => __('Productive min'), 'attended_minutes' => __('Attended min'), 'utilization' => __('Utilization %'), 'efficiency' => __('Efficiency %'), 'billed' => __('Billed')],
                'rows' => app(PayrollService::class)->productivity($from, $to),
                'money' => ['billed'],
            ],
            'work_hours' => [
                'columns' => ['date' => __('Date'), 'technician' => __('Technician'), 'job' => __('Job card'), 'task' => __('Task'), 'started_at' => __('Start'), 'ended_at' => __('End'), 'minutes' => __('Minutes')],
                'rows' => DB::table('job_card_time_logs as l')->join('job_card_tasks as t', 't.id', '=', 'l.job_card_task_id')->join('job_cards as j', 'j.id', '=', 't.job_card_id')
                    ->leftJoin('users as u', 'u.id', '=', 'l.technician_id')->where('j.company_id', $c)->whereBetween('l.started_at', [$from, $toEnd])
                    ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('l.technician_id', $v))
                    ->selectRaw('DATE(l.started_at) date, u.name technician, j.number job, t.description task, l.started_at, l.ended_at, l.minutes')->orderBy('l.started_at')->get(),
                'money' => [],
            ],
            'repair_time' => [
                'columns' => ['department' => __('Department'), 'jobs' => __('Jobs'), 'avg_hours' => __('Avg. hours (received → ready)'), 'avg_work' => __('Avg. working hours'), 'late' => __('Late vs promise')],
                'rows' => DB::table('job_cards as j')->leftJoin('departments as d', 'd.id', '=', 'j.department_id')->where('j.company_id', $c)->whereNotNull('j.completed_at')
                    ->whereBetween('j.completed_at', [$from, $toEnd])->groupBy('d.id', 'd.name_ar')->get(['d.name_ar', DB::raw('COUNT(*) jobs')])
                    ->map(function ($r) use ($c, $from, $toEnd) {
                        $jobs = DB::table('job_cards')->where('company_id', $c)->whereNotNull('completed_at')->whereBetween('completed_at', [$from, $toEnd])
                            ->when($r->name_ar, fn ($q) => $q->whereIn('department_id', DB::table('departments')->where('company_id', $c)->where('name_ar', $r->name_ar)->pluck('id')), fn ($q) => $q->whereNull('department_id'))
                            ->get(['id', 'received_at', 'completed_at', 'promised_at']);
                        $h = $jobs->map(fn ($j) => (strtotime($j->completed_at) - strtotime($j->received_at)) / 3600);
                        $work = DB::table('job_card_tasks')->whereIn('job_card_id', $jobs->pluck('id'))->sum('actual_minutes') / 60 / max(1, $jobs->count());

                        return (object) ['department' => $r->name_ar ?? '—', 'jobs' => $jobs->count(), 'avg_hours' => round($h->avg() ?? 0, 1), 'avg_work' => round($work, 1),
                            'late' => $jobs->filter(fn ($j) => $j->promised_at && $j->completed_at > $j->promised_at)->count()];
                    }),
                'money' => [],
            ],
            'comebacks' => [
                'columns' => ['number' => __('Job card'), 'plate' => __('Vehicle'), 'customer' => __('Customer'), 'previous' => __('Previous job'), 'days' => __('Days since previous'), 'complaint' => __('Complaint')],
                'rows' => collect(DB::select('SELECT j.number, v.plate_number plate, c.name customer, p.number previous, j.received_at, p.delivered_at, j.customer_complaint complaint
                    FROM job_cards j JOIN vehicles v ON v.id = j.vehicle_id JOIN customers c ON c.id = j.customer_id
                    JOIN job_cards p ON p.vehicle_id = j.vehicle_id AND p.id < j.id AND p.delivered_at IS NOT NULL AND p.delivered_at >= ?
                    WHERE j.company_id = ? AND j.received_at BETWEEN ? AND ? AND j.deleted_at IS NULL', [date('Y-m-d', strtotime($from.' -30 days')), $c, $from, $toEnd]))
                    ->filter(fn ($r) => (strtotime($r->received_at) - strtotime($r->delivered_at)) / 86400 <= 30)
                    ->map(fn ($r) => (object) ['number' => $r->number, 'plate' => $r->plate, 'customer' => $r->customer, 'previous' => $r->previous,
                        'days' => round((strtotime($r->received_at) - strtotime($r->delivered_at)) / 86400, 1), 'complaint' => mb_substr((string) $r->complaint, 0, 80)])->unique('number')->values(),
                'money' => [],
                'note' => fn ($rows) => __('Comeback rate').': '.(($total = DB::table('job_cards')->where('company_id', $c)->whereBetween('received_at', [$from, $toEnd])->count()) ? round(count($rows) * 100 / $total, 1) : 0).'% ('.count($rows).' / '.$total.')',
            ],
            'customers' => [
                'columns' => ['code' => __('Code'), 'name' => __('Customer'), 'phone' => __('Phone'), 'classification' => __('Class'), 'vehicles' => __('Vehicles'), 'jobs' => __('Jobs'), 'sales' => __('Sales'), 'balance' => __('Balance')],
                'rows' => DB::table('customers as c')->where('c.company_id', $c)->whereNull('c.deleted_at')
                    ->selectRaw('c.code, c.name, c.phone, c.classification,
                        (SELECT COUNT(*) FROM vehicles v WHERE v.customer_id = c.id AND v.deleted_at IS NULL) vehicles,
                        (SELECT COUNT(*) FROM job_cards j WHERE j.customer_id = c.id AND j.received_at BETWEEN ? AND ?) jobs,
                        (SELECT COALESCE(SUM(total),0) FROM invoices i WHERE i.customer_id = c.id AND i.status IN (\'posted\',\'partially_paid\',\'paid\') AND i.issue_date BETWEEN ? AND ?) sales,
                        (SELECT COALESCE(SUM(l.debit - l.credit),0) FROM journal_lines l WHERE l.party_type = \'customer\' AND l.party_id = c.id) balance', [$from, $toEnd, $from, $to])
                    ->orderByDesc('sales')->limit(2000)->get(),
                'money' => ['sales', 'balance'],
            ],
            'receivables' => [
                'columns' => ['customer' => __('Customer'), 'phone' => __('Phone'), 'invoices' => __('Open invoices'), 'overdue' => __('Overdue'), 'balance' => __('Balance due'), 'credit_limit' => __('Credit limit')],
                'rows' => DB::table('invoices as i')->join('customers as c', 'c.id', '=', 'i.customer_id')->where('i.company_id', $c)->whereIn('i.status', ['posted', 'partially_paid'])
                    ->groupBy('c.id', 'c.name', 'c.phone', 'c.credit_limit')
                    ->selectRaw('c.name customer, c.phone, COUNT(*) invoices, SUM(CASE WHEN i.due_date < ? THEN i.total - i.paid_amount ELSE 0 END) overdue, SUM(i.total - i.paid_amount) balance, c.credit_limit', [now()->toDateString()])
                    ->orderByDesc('balance')->get(),
                'money' => ['overdue', 'balance', 'credit_limit'],
            ],
            'suppliers' => [
                'columns' => ['code' => __('Code'), 'name' => __('Supplier'), 'orders' => __('Orders'), 'spend' => __('Purchases'), 'balance' => __('Payable'), 'rating' => __('Rating')],
                'rows' => DB::table('suppliers as s')->where('s.company_id', $c)->whereNull('s.deleted_at')
                    ->selectRaw('s.code, s.name, (SELECT COUNT(*) FROM purchase_orders p WHERE p.supplier_id = s.id AND p.order_date BETWEEN ? AND ?) orders,
                        (SELECT COALESCE(SUM(total),0) FROM supplier_invoices i WHERE i.supplier_id = s.id AND i.status IN (\'posted\',\'partially_paid\',\'paid\') AND i.invoice_date BETWEEN ? AND ?) spend,
                        (SELECT COALESCE(SUM(l.credit - l.debit),0) FROM journal_lines l WHERE l.party_type = \'supplier\' AND l.party_id = s.id) balance, s.rating', [$from, $to, $from, $to])
                    ->orderByDesc('spend')->get(),
                'money' => ['spend', 'balance'],
            ],
            'stock_value' => [
                'columns' => ['sku' => 'SKU', 'name' => __('Part'), 'warehouse' => __('Warehouse'), 'qty' => __('On hand'), 'reserved' => __('Reserved'), 'avg_cost' => __('Avg. cost'), 'value' => __('Value'), 'retail_value' => __('Retail value')],
                'rows' => DB::table('stock_levels as s')->join('parts as p', 'p.id', '=', 's.part_id')->join('warehouses as w', 'w.id', '=', 's.warehouse_id')
                    ->where('s.company_id', $c)->where('s.qty_on_hand', '!=', 0)->when($f['branch_id'] ?? null, fn ($q, $v) => $q->where('w.branch_id', $v))
                    ->selectRaw('p.sku, p.name_ar name, w.name warehouse, s.qty_on_hand qty, s.qty_reserved reserved, s.avg_cost, s.qty_on_hand * s.avg_cost value, s.qty_on_hand * p.sale_price retail_value')
                    ->orderBy('p.sku')->get(),
                'money' => ['avg_cost', 'value', 'retail_value'],
            ],
            'best_sellers' => [
                'columns' => ['sku' => 'SKU', 'name' => __('Part'), 'qty' => __('Qty sold'), 'revenue' => __('Revenue'), 'invoices' => __('Invoices')],
                'rows' => DB::table('invoice_items as i')->join('invoices as v', 'v.id', '=', 'i.invoice_id')->join('parts as p', 'p.id', '=', 'i.part_id')
                    ->where('v.company_id', $c)->whereIn('v.status', $posted)->whereBetween('v.issue_date', [$from, $to])
                    ->groupBy('p.id', 'p.sku', 'p.name_ar')->selectRaw('p.sku, p.name_ar name, SUM(i.qty - i.returned_qty) qty, SUM(i.line_total) revenue, COUNT(DISTINCT v.id) invoices')
                    ->orderByDesc('qty')->limit(100)->get(),
                'money' => ['revenue'],
            ],
            'dead_stock' => [
                'columns' => ['sku' => 'SKU', 'name_ar' => __('Part'), 'warehouse' => __('Warehouse'), 'qty_on_hand' => __('Qty'), 'value' => __('Value'), 'last_movement_at' => __('Last movement')],
                'rows' => app(\App\Modules\Inventory\Services\ReorderService::class)->slowMoving(max(30, (int) ($f['days'] ?? 180)))->get(),
                'money' => ['value'],
            ],
            'warranty' => [
                'columns' => ['number' => __('Claim'), 'claim_date' => __('Date'), 'customer' => __('Customer'), 'item' => __('Item'), 'resolution' => __('Resolution'), 'status' => __('Status'), 'cost' => __('Cost'), 'recovered' => __('Recovered')],
                'rows' => DB::table('warranty_claims as w')->join('warranties as wr', 'wr.id', '=', 'w.warranty_id')->join('customers as c', 'c.id', '=', 'wr.customer_id')
                    ->where('w.company_id', $c)->whereBetween('w.claim_date', [$from, $to])
                    ->selectRaw('w.number, w.claim_date, c.name customer, wr.description item, w.resolution, w.status, w.cost, w.supplier_recovered_amount recovered')->orderBy('w.claim_date')->get(),
                'money' => ['cost', 'recovered'],
            ],
            'insurance' => [
                'columns' => ['number' => __('Claim'), 'company' => __('Insurer'), 'customer' => __('Customer'), 'accident_date' => __('Accident'), 'status' => __('Status'), 'estimated_cost' => __('Estimated'), 'approved_amount' => __('Approved'), 'deductible_amount' => __('Deductible')],
                'rows' => DB::table('insurance_claims as i')->join('insurance_companies as ic', 'ic.id', '=', 'i.insurance_company_id')->join('customers as c', 'c.id', '=', 'i.customer_id')
                    ->where('i.company_id', $c)->whereBetween('i.created_at', [$from, $toEnd])
                    ->selectRaw('i.number, ic.name company, c.name customer, i.accident_date, i.status, i.estimated_cost, i.approved_amount, i.deductible_amount')->orderBy('i.created_at')->get(),
                'money' => ['estimated_cost', 'approved_amount', 'deductible_amount'],
            ],
            'fleets' => [
                'columns' => ['name' => __('Fleet'), 'customer' => __('Customer'), 'vehicles' => __('Vehicles'), 'jobs' => __('Jobs'), 'spend' => __('Invoiced'), 'monthly_spend_limit' => __('Monthly limit')],
                'rows' => DB::table('fleets as f')->join('customers as c', 'c.id', '=', 'f.customer_id')->where('f.company_id', $c)
                    ->selectRaw('f.name, c.name customer, (SELECT COUNT(*) FROM vehicles v WHERE v.fleet_id = f.id) vehicles,
                        (SELECT COUNT(*) FROM job_cards j WHERE j.fleet_id = f.id AND j.received_at BETWEEN ? AND ?) jobs,
                        (SELECT COALESCE(SUM(total),0) FROM invoices i WHERE i.fleet_id = f.id AND i.status IN (\'posted\',\'partially_paid\',\'paid\') AND i.issue_date BETWEEN ? AND ?) spend, f.monthly_spend_limit', [$from, $toEnd, $from, $to])
                    ->get(),
                'money' => ['spend', 'monthly_spend_limit'],
            ],
            default => abort(404),
        };

        $rows = collect($res['rows'])->map(fn ($r) => (array) $r)->values()->all();
        $totals = [];
        foreach ($res['money'] as $m) {
            $totals[$m] = Money::sum(array_column($rows, $m));
        }
        $note = isset($res['note']) ? ($res['note'])($rows) : null;

        return ['columns' => $res['columns'], 'rows' => $rows, 'totals' => $totals, 'money' => $res['money'], 'note' => $note];
    }

    protected static function vatByMonth(string $from, string $to): array
    {
        $out = [];
        for ($m = \Illuminate\Support\Carbon::parse($from)->startOfMonth(); $m->lte(\Illuminate\Support\Carbon::parse($to)); $m->addMonth()) {
            $r = \App\Modules\Accounting\Services\FinancialReports::vatReport($m->copy()->startOfMonth()->toDateString(), $m->copy()->endOfMonth()->toDateString());
            $out[] = ['month' => $m->format('Y-m'), 'sales_net' => Money::sub($r['sales_net'], $r['credit_net']), 'output_vat' => $r['output_vat'],
                'purchases_net' => $r['purchases_net'], 'input_vat' => $r['input_vat'], 'net' => $r['net_payable']];
        }

        return $out;
    }
}
