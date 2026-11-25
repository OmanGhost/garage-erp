<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Financial statements built from the general ledger.
 */
class FinancialReports
{
    /** Per-account debit/credit totals for a date range. */
    public static function accountBalances(?string $from, ?string $to, ?array $types = null, ?int $branchId = null, ?int $costCenterId = null): Collection
    {
        $sub = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.company_id', tenant()->id())
            ->whereIn('e.status', ['posted', 'reversed'])
            ->when($from, fn ($q) => $q->where('e.date', '>=', $from))
            ->when($to, fn ($q) => $q->where('e.date', '<=', $to))
            ->when($branchId, fn ($q) => $q->where('l.branch_id', $branchId))
            ->when($costCenterId, fn ($q) => $q->where('l.cost_center_id', $costCenterId))
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit) as debit, SUM(l.credit) as credit');

        return DB::table('accounts as a')
            ->leftJoinSub($sub, 't', 't.account_id', '=', 'a.id')
            ->where('a.company_id', tenant()->id())
            ->where('a.is_group', false)
            ->when($types, fn ($q) => $q->whereIn('a.type', $types))
            ->orderBy('a.code')
            ->select('a.id', 'a.code', 'a.name_ar', 'a.name_en', 'a.type', 'a.parent_id', 'a.normal_balance', 'a.system_key', 'a.cash_flow_category',
                DB::raw('COALESCE(t.debit,0) as debit'), DB::raw('COALESCE(t.credit,0) as credit'))
            ->get();
    }

    public static function trialBalance(?string $from, ?string $to, ?int $branchId = null): array
    {
        $opening = $from ? self::accountBalances(null, date('Y-m-d', strtotime($from.' -1 day')), null, $branchId)->keyBy('id') : collect();
        $period = self::accountBalances($from, $to, null, $branchId);
        $rows = [];
        $tot = ['opening_debit' => '0', 'opening_credit' => '0', 'debit' => '0', 'credit' => '0', 'closing_debit' => '0', 'closing_credit' => '0'];
        foreach ($period as $a) {
            $o = $opening[$a->id] ?? null;
            $ob = $o ? Money::sub($o->debit, $o->credit) : '0.000';
            $cb = Money::add($ob, Money::sub($a->debit, $a->credit));
            if (Money::isZero($ob) && Money::isZero($a->debit) && Money::isZero($a->credit)) {
                continue;
            }
            $row = [
                'id' => $a->id, 'code' => $a->code, 'name' => app()->getLocale() === 'ar' ? $a->name_ar : ($a->name_en ?: $a->name_ar), 'type' => $a->type,
                'opening_debit' => Money::max($ob, 0), 'opening_credit' => Money::max(Money::neg($ob), 0),
                'debit' => Money::round($a->debit), 'credit' => Money::round($a->credit),
                'closing_debit' => Money::max($cb, 0), 'closing_credit' => Money::max(Money::neg($cb), 0),
            ];
            foreach ($tot as $k => $v) {
                $tot[$k] = Money::add($v, $row[$k]);
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'totals' => $tot];
    }

    public static function incomeStatement(?string $from, ?string $to, ?int $branchId = null, ?int $costCenterId = null): array
    {
        $rows = self::accountBalances($from, $to, ['revenue', 'expense'], $branchId, $costCenterId);
        $revenue = $expenses = $cogs = [];
        $tr = $te = $tc = '0';
        $cogsKeys = ['cogs', 'warranty_expense', 'price_variance'];
        foreach ($rows as $a) {
            $name = app()->getLocale() === 'ar' ? $a->name_ar : ($a->name_en ?: $a->name_ar);
            if ($a->type === 'revenue') {
                $v = Money::sub($a->credit, $a->debit);
                if (! Money::isZero($v)) {
                    $revenue[] = ['code' => $a->code, 'name' => $name, 'amount' => $v];
                    $tr = Money::add($tr, $v);
                }
            } else {
                $v = Money::sub($a->debit, $a->credit);
                if (Money::isZero($v)) {
                    continue;
                }
                if (in_array($a->system_key, $cogsKeys, true) || str_starts_with($a->code, '51')) {
                    $cogs[] = ['code' => $a->code, 'name' => $name, 'amount' => $v];
                    $tc = Money::add($tc, $v);
                } else {
                    $expenses[] = ['code' => $a->code, 'name' => $name, 'amount' => $v];
                    $te = Money::add($te, $v);
                }
            }
        }
        $gross = Money::sub($tr, $tc);

        return [
            'revenue' => $revenue, 'total_revenue' => $tr,
            'cogs' => $cogs, 'total_cogs' => $tc, 'gross_profit' => $gross,
            'expenses' => $expenses, 'total_expenses' => $te,
            'net_income' => Money::sub($gross, $te),
        ];
    }

    public static function netIncome(?string $from, ?string $to): string
    {
        return self::incomeStatement($from, $to)['net_income'];
    }

    public static function balanceSheet(string $asOf): array
    {
        $rows = self::accountBalances(null, $asOf);
        $sections = ['asset' => [], 'liability' => [], 'equity' => []];
        $totals = ['asset' => '0', 'liability' => '0', 'equity' => '0'];
        $pl = '0';
        foreach ($rows as $a) {
            $name = app()->getLocale() === 'ar' ? $a->name_ar : ($a->name_en ?: $a->name_ar);
            if (in_array($a->type, ['revenue', 'expense'], true)) {
                $pl = Money::add($pl, Money::sub($a->credit, $a->debit));
                continue;
            }
            $v = $a->type === 'asset' ? Money::sub($a->debit, $a->credit) : Money::sub($a->credit, $a->debit);
            if (Money::isZero($v)) {
                continue;
            }
            $sections[$a->type][] = ['code' => $a->code, 'name' => $name, 'amount' => $v];
            $totals[$a->type] = Money::add($totals[$a->type], $v);
        }
        if (! Money::isZero($pl)) {
            $sections['equity'][] = ['code' => '', 'name' => __('Current period earnings'), 'amount' => $pl];
            $totals['equity'] = Money::add($totals['equity'], $pl);
        }

        return [
            'sections' => $sections, 'totals' => $totals,
            'liabilities_equity' => Money::add($totals['liability'], $totals['equity']),
            'balanced' => Money::cmp($totals['asset'], Money::add($totals['liability'], $totals['equity'])) === 0,
        ];
    }

    /** Direct-method cash flow: cash account movements classified by counter-account category. */
    public static function cashFlow(string $from, string $to): array
    {
        $cashIds = BankAccount::query()->pluck('account_id')
            ->merge(Account::query()->whereIn('system_key', ['cash', 'pos_cash', 'bank', 'card_clearing'])->pluck('id'))
            ->unique()->values()->all();
        $opening = '0';
        foreach ($cashIds as $id) {
            $opening = Money::add($opening, Ledger::accountBalance($id, null, date('Y-m-d', strtotime($from.' -1 day'))));
        }
        $entries = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.company_id', tenant()->id())->whereIn('e.status', ['posted', 'reversed'])
            ->whereBetween('e.date', [$from, $to])->whereIn('l.account_id', $cashIds ?: [0])
            ->groupBy('l.journal_entry_id')->selectRaw('l.journal_entry_id, SUM(l.debit) - SUM(l.credit) as delta')->get();
        $cats = ['operating' => ['in' => '0', 'out' => '0'], 'investing' => ['in' => '0', 'out' => '0'], 'financing' => ['in' => '0', 'out' => '0']];
        foreach ($entries as $e) {
            if (Money::isZero($e->delta)) {
                continue; // internal transfers between cash accounts
            }
            $counter = DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')
                ->where('l.journal_entry_id', $e->journal_entry_id)->whereNotIn('l.account_id', $cashIds ?: [0])
                ->orderByRaw('(l.debit + l.credit) desc')->value('a.cash_flow_category') ?: 'operating';
            $key = Money::isPositive($e->delta) ? 'in' : 'out';
            $cats[$counter][$key] = Money::add($cats[$counter][$key], Money::isPositive($e->delta) ? $e->delta : Money::neg($e->delta));
        }
        $net = '0';
        foreach ($cats as $k => $c) {
            $cats[$k]['net'] = Money::sub($c['in'], $c['out']);
            $net = Money::add($net, $cats[$k]['net']);
        }

        return ['opening' => $opening, 'categories' => $cats, 'net_change' => $net, 'closing' => Money::add($opening, $net)];
    }

    /** Receivable (customer) or payable (supplier) aging buckets from open documents. */
    public static function aging(string $type = 'customer', ?string $asOf = null): array
    {
        $asOf ??= now()->toDateString();
        if ($type === 'customer') {
            $docs = DB::table('invoices as d')->join('customers as p', 'p.id', '=', 'd.customer_id')
                ->where('d.company_id', tenant()->id())->whereIn('d.status', ['posted', 'partially_paid'])
                ->where('d.issue_date', '<=', $asOf)
                ->select('p.id as party_id', 'p.name as party_name', 'd.number', 'd.issue_date', DB::raw('COALESCE(d.due_date, d.issue_date) as due_date'),
                    DB::raw('d.total - d.paid_amount - COALESCE((select SUM(c.total) from credit_notes c where c.invoice_id = d.id and c.status = \'posted\'),0) as balance'))
                ->get();
        } else {
            $docs = DB::table('supplier_invoices as d')->join('suppliers as p', 'p.id', '=', 'd.supplier_id')
                ->where('d.company_id', tenant()->id())->whereIn('d.status', ['posted', 'partially_paid'])
                ->where('d.invoice_date', '<=', $asOf)
                ->select('p.id as party_id', 'p.name as party_name', 'd.number', 'd.invoice_date as issue_date', DB::raw('COALESCE(d.due_date, d.invoice_date) as due_date'),
                    DB::raw('d.total - d.paid_amount as balance'))
                ->get();
        }
        $buckets = ['current' => '0', '1_30' => '0', '31_60' => '0', '61_90' => '0', 'over_90' => '0'];
        $parties = [];
        foreach ($docs as $d) {
            if (Money::cmp($d->balance, 0) <= 0) {
                continue;
            }
            $days = (int) floor((strtotime($asOf) - strtotime($d->due_date)) / 86400);
            $b = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : 'over_90')));
            $parties[$d->party_id] ??= ['name' => $d->party_name, 'buckets' => array_fill_keys(array_keys($buckets), '0'), 'total' => '0'];
            $parties[$d->party_id]['buckets'][$b] = Money::add($parties[$d->party_id]['buckets'][$b], $d->balance);
            $parties[$d->party_id]['total'] = Money::add($parties[$d->party_id]['total'], $d->balance);
            $buckets[$b] = Money::add($buckets[$b], $d->balance);
        }
        uasort($parties, fn ($a, $b) => Money::cmp($b['total'], $a['total']));

        return ['parties' => $parties, 'totals' => $buckets, 'grand_total' => Money::sum($buckets)];
    }

    public static function vatReport(string $from, string $to): array
    {
        $out = Money::neg(Ledger::accountBalance(Accounts::id('vat_output'), $from, $to));
        $in = Ledger::accountBalance(Accounts::id('vat_input'), $from, $to);
        $sales = DB::table('invoices')->where('company_id', tenant()->id())->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->whereBetween('issue_date', [$from, $to])->selectRaw('COALESCE(SUM(total - tax_total),0) as net, COALESCE(SUM(tax_total),0) as tax')->first();
        $credit = DB::table('credit_notes')->where('company_id', tenant()->id())->where('status', 'posted')
            ->whereBetween('date', [$from, $to])->selectRaw('COALESCE(SUM(subtotal),0) as net, COALESCE(SUM(tax_total),0) as tax')->first();
        $purch = DB::table('supplier_invoices')->where('company_id', tenant()->id())->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->whereBetween('invoice_date', [$from, $to])->selectRaw('COALESCE(SUM(total - tax_total),0) as net, COALESCE(SUM(tax_total),0) as tax')->first();

        return [
            'output_vat' => $out, 'input_vat' => $in, 'net_payable' => Money::sub($out, $in),
            'sales_net' => Money::round($sales->net), 'sales_tax' => Money::round($sales->tax),
            'credit_net' => Money::round($credit->net), 'credit_tax' => Money::round($credit->tax),
            'purchases_net' => Money::round($purch->net), 'purchases_tax' => Money::round($purch->tax),
        ];
    }

    /** Budget vs actual per account for a fiscal year budget. */
    public static function budgetVsActual(\App\Modules\Accounting\Models\Budget $budget): array
    {
        $fy = $budget->fiscalYear;
        $actual = self::accountBalances($fy->start_date->toDateString(), $fy->end_date->toDateString(), ['revenue', 'expense'])->keyBy('id');
        $rows = [];
        foreach ($budget->lines()->with('account')->get()->groupBy('account_id') as $accountId => $lines) {
            $acc = $lines->first()->account;
            $planned = Money::sum($lines->pluck('amount'));
            $a = $actual[$accountId] ?? null;
            $act = $a ? ($acc->type === 'revenue' ? Money::sub($a->credit, $a->debit) : Money::sub($a->debit, $a->credit)) : '0.000';
            $rows[] = ['account' => $acc->label(), 'type' => $acc->type, 'budget' => $planned, 'actual' => $act, 'variance' => Money::sub($act, $planned)];
        }

        return $rows;
    }
}
