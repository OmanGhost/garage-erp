<?php

namespace App\Modules\Accounting\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\AssetDepreciation;
use App\Modules\Accounting\Models\BankReconciliation;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\FundTransfer;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\CRM\Models\Customer;
use App\Modules\Purchasing\Models\Supplier;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Expenses, fund transfers, fixed asset depreciation & disposal,
 * bank reconciliation and opening balances.
 */
class FinanceOperations
{
    public function __construct(protected PostingService $posting) {}

    public function postExpense(Expense $e): Expense
    {
        return DB::transaction(function () use ($e) {
            $e = Expense::query()->whereKey($e->id)->lockForUpdate()->firstOrFail();
            if ($e->status === 'posted') {
                return $e;
            }
            $total = Money::add($e->amount, $e->tax_amount);
            $credit = $e->bank_account_id
                ? ['account' => $e->bankAccount->account_id, 'credit' => $total]
                : ['account' => 'ap', 'credit' => $total, 'party' => $e->supplier ?? throw new BusinessException(__('Select a payment account or a supplier.'))];
            $je = $this->posting->post([
                'date' => $e->date->toDateString(), 'description' => __('Expense').' '.$e->number.' - '.$e->description, 'source' => $e,
                'branch_id' => $e->branch_id,
                'lines' => [
                    ['account' => $e->account_id, 'debit' => $e->amount, 'cost_center_id' => $e->cost_center_id],
                    ['account' => 'vat_input', 'debit' => $e->tax_amount],
                    $credit,
                ],
            ]);
            $e->forceFill(['status' => 'posted', 'total' => $total, 'journal_entry_id' => $je->id])->save();

            return $e;
        });
    }

    public function postTransfer(FundTransfer $t): FundTransfer
    {
        return DB::transaction(function () use ($t) {
            $t = FundTransfer::query()->whereKey($t->id)->lockForUpdate()->firstOrFail();
            if ($t->status === 'posted') {
                return $t;
            }
            if ($t->from_bank_account_id === $t->to_bank_account_id) {
                throw new BusinessException(__('Source and destination accounts must differ.'));
            }
            $je = $this->posting->post([
                'date' => $t->date->toDateString(), 'description' => __('Fund transfer').' '.$t->number, 'source' => $t,
                'lines' => [['account' => $t->toAccount->account_id, 'debit' => $t->amount], ['account' => $t->fromAccount->account_id, 'credit' => $t->amount]],
            ]);
            $t->forceFill(['status' => 'posted', 'journal_entry_id' => $je->id])->save();

            return $t;
        });
    }

    /** Monthly straight-line depreciation for all active assets for the month of $date. */
    public function runDepreciation(Carbon $date): array
    {
        $period = $date->copy()->endOfMonth();
        $done = [];
        foreach (FixedAsset::query()->where('status', 'active')->whereDate('depreciation_start', '<=', $period)->get() as $asset) {
            if (AssetDepreciation::query()->where('fixed_asset_id', $asset->id)->whereDate('period_date', $period)->exists()) {
                continue;
            }
            $depreciable = Money::sub($asset->cost, $asset->salvage_value);
            $remaining = Money::sub($depreciable, $asset->accumulated_depreciation);
            if (! Money::isPositive($remaining)) {
                $asset->update(['status' => 'fully_depreciated']);
                continue;
            }
            $amount = $asset->method === 'declining'
                ? Money::div(Money::mul(Money::sub($asset->cost, $asset->accumulated_depreciation), 2), $asset->useful_life_months)
                : Money::div($depreciable, $asset->useful_life_months);
            $amount = Money::min($amount, $remaining);
            DB::transaction(function () use ($asset, $amount, $period, &$done) {
                $dep = AssetDepreciation::create(['fixed_asset_id' => $asset->id, 'period_date' => $period->toDateString(), 'amount' => $amount]);
                $je = $this->posting->post([
                    'date' => $period->toDateString(), 'description' => __('Depreciation').' '.$asset->code.' '.$period->format('Y-m'), 'source' => $dep,
                    'lines' => [
                        ['account' => $asset->expense_account_id, 'debit' => $amount, 'cost_center_id' => $asset->cost_center_id],
                        ['account' => $asset->accumulated_account_id, 'credit' => $amount],
                    ],
                ]);
                $dep->update(['journal_entry_id' => $je->id]);
                $acc = Money::add($asset->accumulated_depreciation, $amount);
                $asset->update(['accumulated_depreciation' => $acc, 'book_value' => Money::sub($asset->cost, $acc),
                    'status' => Money::cmp($acc, Money::sub($asset->cost, $asset->salvage_value)) >= 0 ? 'fully_depreciated' : 'active']);
                $done[] = $asset->code;
            });
        }

        return $done;
    }

    /** Register an asset acquisition: Dr Asset, Cr Bank/AP. */
    public function acquireAsset(FixedAsset $asset, ?int $bankAccountId = null, ?Supplier $supplier = null): void
    {
        $credit = $bankAccountId
            ? ['account' => \App\Modules\Accounting\Models\BankAccount::query()->findOrFail($bankAccountId)->account_id, 'credit' => $asset->cost]
            : ['account' => $supplier ? 'ap' : 'opening_equity', 'credit' => $asset->cost, 'party' => $supplier];
        $this->posting->post([
            'date' => $asset->purchase_date->toDateString(), 'description' => __('Asset acquisition').' '.$asset->code, 'source' => $asset, 'purpose' => 'acquisition',
            'lines' => [['account' => $asset->asset_account_id, 'debit' => $asset->cost], $credit],
        ]);
        $asset->update(['book_value' => Money::sub($asset->cost, $asset->accumulated_depreciation)]);
    }

    public function disposeAsset(FixedAsset $asset, mixed $proceeds, ?int $bankAccountId = null): void
    {
        DB::transaction(function () use ($asset, $proceeds, $bankAccountId) {
            $proceeds = Money::round($proceeds);
            $book = Money::sub($asset->cost, $asset->accumulated_depreciation);
            $gain = Money::sub($proceeds, $book);
            $lines = [
                ['account' => $asset->accumulated_account_id, 'debit' => $asset->accumulated_depreciation],
                ['account' => $asset->asset_account_id, 'credit' => $asset->cost],
            ];
            if (Money::isPositive($proceeds)) {
                $lines[] = ['account' => \App\Modules\Accounting\Models\BankAccount::query()->findOrFail($bankAccountId)->account_id, 'debit' => $proceeds];
            }
            $lines[] = Money::isPositive($gain) ? ['account' => 'asset_disposal_gain', 'credit' => $gain] : ['account' => 'asset_disposal_loss', 'debit' => Money::neg($gain)];
            $this->posting->post(['date' => now()->toDateString(), 'description' => __('Asset disposal').' '.$asset->code, 'source' => $asset, 'purpose' => 'disposal', 'lines' => $lines]);
            $asset->update(['status' => 'disposed', 'disposed_at' => now(), 'disposal_amount' => $proceeds, 'book_value' => 0]);
        });
    }

    /** Mark journal lines as reconciled against a bank statement. */
    public function reconcile(BankReconciliation $rec, array $lineIds): void
    {
        DB::transaction(function () use ($rec, $lineIds) {
            $accountId = $rec->bankAccount->account_id;
            JournalLine::query()->where('account_id', $accountId)->whereIn('id', $lineIds)->whereNull('reconciled_at')
                ->update(['reconciled_at' => now(), 'bank_reconciliation_id' => $rec->id]);
            $book = Ledger::accountBalance($accountId, null, $rec->statement_date->toDateString());
            $unreconciled = JournalLine::query()->where('account_id', $accountId)->whereNull('reconciled_at')
                ->whereHas('entry', fn ($q) => $q->whereDate('date', '<=', $rec->statement_date)->whereIn('status', ['posted', 'reversed']))
                ->selectRaw('COALESCE(SUM(debit - credit),0) as v')->value('v');
            $adjustedBook = Money::sub($book, $unreconciled);
            $rec->update(['book_balance' => $book, 'difference' => Money::sub($rec->statement_balance, $adjustedBook),
                'status' => Money::isZero(Money::sub($rec->statement_balance, $adjustedBook)) ? 'completed' : 'draft',
                'completed_at' => Money::isZero(Money::sub($rec->statement_balance, $adjustedBook)) ? now() : null]);
        });
    }

    /**
     * Opening balances: account lines [account_id => [debit, credit]] and
     * party balances for customers (receivable) / suppliers (payable).
     */
    public function openingBalances(string $date, array $accountLines, array $customers = [], array $suppliers = []): void
    {
        $lines = [];
        $diff = '0';
        foreach ($accountLines as $accountId => $l) {
            $dr = Money::round($l['debit'] ?? 0);
            $cr = Money::round($l['credit'] ?? 0);
            if (Money::isZero($dr) && Money::isZero($cr)) {
                continue;
            }
            $lines[] = ['account' => (int) $accountId, 'debit' => $dr, 'credit' => $cr];
            $diff = Money::add($diff, Money::sub($dr, $cr));
        }
        foreach ($customers as $id => $amount) {
            $amount = Money::round($amount);
            if (! Money::isZero($amount)) {
                $lines[] = ['account' => 'ar', 'debit' => $amount, 'party' => Customer::query()->findOrFail($id)];
                $diff = Money::add($diff, $amount);
            }
        }
        foreach ($suppliers as $id => $amount) {
            $amount = Money::round($amount);
            if (! Money::isZero($amount)) {
                $lines[] = ['account' => 'ap', 'credit' => $amount, 'party' => Supplier::query()->findOrFail($id)];
                $diff = Money::sub($diff, $amount);
            }
        }
        if (! $lines) {
            throw new BusinessException(__('Enter at least one opening balance.'));
        }
        if (! Money::isZero($diff)) {
            $lines[] = ['account' => 'opening_equity', 'debit' => Money::neg($diff)];
        }
        $this->posting->post(['date' => $date, 'description' => __('Opening balances'), 'lines' => $lines, 'is_opening' => true]);
        AuditLogger::log('accounting.opening_balances', null, ['date' => $date]);
    }
}
