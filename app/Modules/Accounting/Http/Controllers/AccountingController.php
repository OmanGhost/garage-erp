<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankReconciliation;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\FiscalPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\FundTransfer;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Services\FinanceOperations;
use App\Modules\Accounting\Services\FinancialReports;
use App\Modules\Accounting\Services\FiscalService;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Purchasing\Models\Supplier;
use App\Support\Export\Exporter;
use App\Support\Money;
use App\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    public function __construct(protected FinanceOperations $ops, protected PostingService $posting) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    public function statements(Request $request)
    {
        $this->can('reports.view');
        $tab = $request->query('tab', 'trial');
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $branch = $request->integer('branch_id') ?: null;
        $data = match ($tab) {
            'income' => FinancialReports::incomeStatement($from, $to, $branch, $request->integer('cost_center_id') ?: null),
            'balance' => FinancialReports::balanceSheet($to),
            'cashflow' => FinancialReports::cashFlow($from, $to),
            'ar_aging' => FinancialReports::aging('customer', $to),
            'ap_aging' => FinancialReports::aging('supplier', $to),
            'vat' => FinancialReports::vatReport($from, $to),
            default => FinancialReports::trialBalance($from, $to, $branch),
        };
        if ($format = $request->query('export')) {
            [$head, $rows] = $this->flatten($tab, $data);

            return Exporter::download($format, __('Financial statements').' - '.$tab, $head, $rows, ['filters' => ['from' => $from, 'to' => $to]]);
        }

        return view('accounting::statements', compact('tab', 'from', 'to', 'data', 'branch') + ['branches' => Options::branches(), 'costCenters' => Options::costCenters()]);
    }

    protected function flatten(string $tab, array $d): array
    {
        return match ($tab) {
            'trial' => [[__('Code'), __('Account'), __('Opening Dr'), __('Opening Cr'), __('Debit'), __('Credit'), __('Closing Dr'), __('Closing Cr')],
                array_map(fn ($r) => [$r['code'], $r['name'], $r['opening_debit'], $r['opening_credit'], $r['debit'], $r['credit'], $r['closing_debit'], $r['closing_credit']], $d['rows'])],
            'income' => [[__('Section'), __('Code'), __('Account'), __('Amount')], array_merge(
                array_map(fn ($r) => [__('Revenue'), $r['code'], $r['name'], $r['amount']], $d['revenue']),
                array_map(fn ($r) => [__('Cost of sales'), $r['code'], $r['name'], $r['amount']], $d['cogs']),
                array_map(fn ($r) => [__('Expenses'), $r['code'], $r['name'], $r['amount']], $d['expenses']),
                [['', '', __('Net income'), $d['net_income']]])],
            'balance' => [[__('Section'), __('Code'), __('Account'), __('Amount')], collect($d['sections'])->flatMap(fn ($rows, $sec) => array_map(fn ($r) => [status_label($sec), $r['code'], $r['name'], $r['amount']], $rows))->all()],
            'ar_aging', 'ap_aging' => [[__('Party'), __('Current'), '1-30', '31-60', '61-90', '90+', __('Total')],
                array_values(array_map(fn ($p) => [$p['name'], ...array_values($p['buckets']), $p['total']], $d['parties']))],
            default => [[__('Item'), __('Value')], collect($d)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->values()->all()],
        };
    }

    public function ledger(Request $request)
    {
        $this->can('journal_entries.view');
        $accountId = $request->integer('account_id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $account = $accountId ? Account::query()->findOrFail($accountId) : null;
        $data = $account ? Ledger::accountLedger($account->id, $from, $to) : null;
        if ($account && ($format = $request->query('export'))) {
            return Exporter::download($format, __('General ledger').' '.$account->label(), [__('Date'), __('Entry'), __('Description'), __('Debit'), __('Credit'), __('Balance')],
                array_map(fn ($r) => [$r->date, $r->number, $r->line_description ?: $r->description, $r->debit, $r->credit, $r->balance], $data['rows']));
        }

        return view('accounting::ledger', ['account' => $account, 'data' => $data, 'from' => $from, 'to' => $to, 'accounts' => Options::accounts()]);
    }

    public function openingForm()
    {
        $this->can('journal_entries.create');

        return view('accounting::opening', ['accounts' => Account::query()->where('is_group', false)
            ->where(fn ($q) => $q->whereNull('system_key')->orWhereNotIn('system_key', ['ar', 'ap', 'opening_equity']))->orderBy('code')->get()]);
    }

    public function opening(Request $request)
    {
        $this->can('journal_entries.create');
        $data = $request->validate(['date' => ['required', 'date'], 'accounts' => ['array'], 'accounts.*.debit' => ['nullable', 'numeric', 'min:0'], 'accounts.*.credit' => ['nullable', 'numeric', 'min:0'],
            'customers' => ['array'], 'customers.*.id' => ['nullable', 'integer'], 'customers.*.amount' => ['nullable', 'numeric'],
            'suppliers' => ['array'], 'suppliers.*.id' => ['nullable', 'integer'], 'suppliers.*.amount' => ['nullable', 'numeric']]);
        $customers = collect($data['customers'] ?? [])->filter(fn ($c) => ! empty($c['id']) && ! empty($c['amount']))->mapWithKeys(fn ($c) => [Customer::query()->findOrFail($c['id'])->id => $c['amount']])->all();
        $suppliers = collect($data['suppliers'] ?? [])->filter(fn ($c) => ! empty($c['id']) && ! empty($c['amount']))->mapWithKeys(fn ($c) => [Supplier::query()->findOrFail($c['id'])->id => $c['amount']])->all();
        $accounts = collect($data['accounts'] ?? [])->filter(fn ($a, $id) => Account::query()->whereKey($id)->exists())->all();
        $this->ops->openingBalances($data['date'], $accounts, $customers, $suppliers);

        return redirect()->route('crud.index', ['journal-entries', 'f_is_opening' => 1])->with('success', __('Opening balances posted.'));
    }

    public function createJournal()
    {
        $this->can('journal_entries.create');

        return view('accounting::journal-form', ['accounts' => Options::accounts(), 'costCenters' => Options::costCenters()]);
    }

    public function storeJournal(Request $request)
    {
        $this->can('journal_entries.post');
        $c = tenant()->id();
        $data = $request->validate([
            'date' => ['required', 'date'], 'description' => ['required', 'string', 'max:250'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('company_id', $c)->where('is_group', false)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', 'integer'], 'lines.*.description' => ['nullable', 'string', 'max:250'],
        ]);
        $lines = array_values(array_map(fn ($l) => ['account' => (int) $l['account_id'], 'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0,
            'cost_center_id' => $l['cost_center_id'] ?? null, 'description' => $l['description'] ?? null], array_filter($data['lines'], fn ($l) => ! empty($l['account_id']))));
        $je = $this->posting->post(['date' => $data['date'], 'description' => $data['description'], 'lines' => $lines]);

        return redirect()->route('journal.show', $je)->with('success', __('Journal entry posted.'));
    }

    public function journal(JournalEntry $entry)
    {
        $this->can('journal_entries.view');

        return view('accounting::journal', ['e' => $entry->load('lines.account', 'lines.costCenter', 'poster', 'creator', 'reversalOf', 'reversedBy')]);
    }

    public function reverse(Request $request, JournalEntry $entry)
    {
        $this->can('journal_entries.reverse');
        $data = $request->validate(['reason' => ['required', 'string', 'max:200'], 'date' => ['nullable', 'date']]);
        abort_if($entry->source_type && ! in_array($entry->source_type, ['journal_entry'], true) && ! auth()->user()->can('journal_entries.approve'), 403,
            __('Entries generated by documents must be corrected from their document (credit note, cancellation).'));
        $rev = $this->posting->reverse($entry, $data['date'] ?? null, $data['reason']);

        return redirect()->route('journal.show', $rev)->with('success', __('Reversal entry posted.'));
    }

    public function postExpense(Expense $expense)
    {
        $this->can('expenses.approve');
        $this->ops->postExpense($expense);

        return back()->with('success', __('Expense posted.'));
    }

    public function postTransfer(FundTransfer $transfer)
    {
        $this->can('fund_transfers.approve');
        $this->ops->postTransfer($transfer);

        return back()->with('success', __('Transfer posted.'));
    }

    public function reconciliation(BankReconciliation $rec)
    {
        $this->can('bank_reconciliations.view');
        $accountId = $rec->bankAccount->account_id;
        $lines = JournalLine::query()->with('entry')->where('account_id', $accountId)
            ->whereHas('entry', fn ($q) => $q->whereDate('date', '<=', $rec->statement_date)->whereIn('status', ['posted', 'reversed']))
            ->where(fn ($q) => $q->whereNull('reconciled_at')->orWhere('bank_reconciliation_id', $rec->id))->orderBy('id')->get();

        return view('accounting::reconciliation', ['rec' => $rec->load('bankAccount'), 'lines' => $lines, 'book' => Ledger::accountBalance($accountId, null, $rec->statement_date->toDateString())]);
    }

    public function reconcile(Request $request, BankReconciliation $rec)
    {
        $this->can('bank_reconciliations.update');
        $this->ops->reconcile($rec, array_map('intval', (array) $request->input('lines', [])));

        return back()->with('success', __('Reconciliation updated.'));
    }

    public function asset(FixedAsset $asset)
    {
        $this->can('fixed_assets.view');

        return view('accounting::asset', ['a' => $asset->load('depreciations', 'assetAccount', 'accumulatedAccount', 'expenseAccount'), 'banks' => Options::bankAccounts(),
            'acquired' => JournalEntry::query()->where('source_type', $asset->getMorphClass())->where('source_id', $asset->id)->where('purpose', 'acquisition')->exists()]);
    }

    public function acquireAsset(Request $request, FixedAsset $asset)
    {
        $this->can('fixed_assets.approve');
        $data = $request->validate(['bank_account_id' => ['nullable', 'integer'], 'supplier_id' => ['nullable', 'integer']]);
        $this->ops->acquireAsset($asset, $data['bank_account_id'] ?? null, ! empty($data['supplier_id']) ? Supplier::query()->findOrFail($data['supplier_id']) : null);

        return back()->with('success', __('Acquisition posted.'));
    }

    public function disposeAsset(Request $request, FixedAsset $asset)
    {
        $this->can('fixed_assets.approve');
        $data = $request->validate(['proceeds' => ['required', 'numeric', 'min:0'], 'bank_account_id' => ['nullable', 'required_unless:proceeds,0', 'integer']]);
        $this->ops->disposeAsset($asset, $data['proceeds'], $data['bank_account_id'] ?? null);

        return back()->with('success', __('Asset disposed.'));
    }

    public function depreciationForm()
    {
        $this->can('fixed_assets.approve');

        return view('accounting::depreciation');
    }

    public function runDepreciation(Request $request)
    {
        $this->can('fixed_assets.approve');
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $done = $this->ops->runDepreciation(Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth());

        return redirect()->route('crud.index', 'fixed-assets')->with('success', __('Depreciation posted for :n assets.', ['n' => count($done)]));
    }

    public function budget(Budget $budget)
    {
        $this->can('budgets.view');
        $lines = $budget->lines()->get()->groupBy('account_id')->map(fn ($g) => $g->keyBy('month'));

        return view('accounting::budget', ['b' => $budget->load('fiscalYear'), 'lines' => $lines, 'accounts' => Account::query()->where('is_group', false)->whereIn('type', ['revenue', 'expense'])->orderBy('code')->get(),
            'compare' => FinancialReports::budgetVsActual($budget)]);
    }

    public function saveBudget(Request $request, Budget $budget)
    {
        $this->can('budgets.update');
        $data = $request->validate(['amounts' => ['array'], 'amounts.*' => ['array'], 'amounts.*.*' => ['nullable', 'numeric', 'min:0']]);
        $budget->lines()->delete();
        foreach ($data['amounts'] ?? [] as $accountId => $months) {
            if (! Account::query()->whereKey($accountId)->exists()) {
                continue;
            }
            foreach ($months as $m => $amount) {
                if (Money::isPositive($amount)) {
                    $budget->lines()->create(['account_id' => $accountId, 'month' => (int) $m, 'amount' => $amount]);
                }
            }
        }

        return back()->with('success', __('Budget saved.'));
    }

    public function fiscalYear(FiscalYear $year)
    {
        $this->can('fiscal_years.view');

        return view('accounting::fiscal-year', ['y' => $year->load('periods')]);
    }

    public function periodAction(FiscalPeriod $period, string $action)
    {
        $this->can('fiscal_years.close_period');
        $action === 'close' ? FiscalService::closePeriod($period) : FiscalService::reopenPeriod($period);

        return back()->with('success', __('Period updated.'));
    }

    public function closeYear(FiscalYear $year)
    {
        $this->can('fiscal_years.close_period');
        abort_unless($year->status === 'open', 403);
        FiscalService::closeYear($year);

        return back()->with('success', __('Fiscal year closed and net income transferred to retained earnings.'));
    }
}
