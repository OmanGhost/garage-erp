<?php

namespace App\Modules\Accounting\Crud;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\BankReconciliation;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\FundTransfer;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\Ledger;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class AccountingResource extends CrudResource
{
    public static ?string $module = 'accounting';
}

class AccountResource extends AccountingResource
{
    public static string $model = Account::class;
    public static string $slug = 'accounts';
    public static string $permission = 'accounts';
    public static string $icon = 'bi-diagram-2';
    public static array $with = ['parent'];
    public static string $defaultSort = 'code';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Chart of Accounts'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->unique('accounts')->col(3),
            Field::text('name_ar')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(5)
                ->display(fn ($a) => '<span style="padding-inline-start:'.(max(0, strlen($a->code) - 1) * 10).'px" class="'.($a->is_group ? 'fw-bold' : '').'">'.e($a->name_ar).'</span>'),
            Field::text('name_en')->label(__('Name (English)'))->index()->searchable()->col(4),
            Field::select('type', Options::ACCOUNT_TYPES)->required()->index()->filterable()->col(3),
            Field::select('parent_id', fn () => Account::query()->where('is_group', true)->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all())->label(__('Parent account'))->relation('parent', 'label')->col(5),
            Field::checkbox('is_group')->label(__('Group (header) account'))->index()->col(4),
            Field::select('cash_flow_category', ['operating', 'investing', 'financing'])->label(__('Cash flow category'))->col(4),
            Field::text('system_key')->label(__('System key'))->hideOnForm()->index(),
            Field::text('balance')->label(__('Balance'))->hideOnForm()->display(fn ($a) => $a->is_group ? '' : '<span class="font-monospace">'.e(money(Ledger::accountBalance($a->id))).'</span>')->index(false),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->col(4),
            Field::text('description')->col(12),
        ];
    }

    public function saving(Model $m, array $data, bool $creating): void
    {
        $m->normal_balance = in_array($m->type, ['asset', 'expense'], true) ? 'debit' : 'credit';
        if (! $creating && $m->isDirty('type') && $m->lines()->exists()) {
            throw new \App\Exceptions\BusinessException(__('The type of an account with postings cannot be changed.'));
        }
    }

    public function rowActions(Model $m): array
    {
        return $m->is_group ? [] : [['label' => __('Ledger'), 'url' => route('accounting.ledger', ['account_id' => $m->id]), 'icon' => 'bi-journal-text']];
    }

    public function deleteBlocker(Model $m): ?string
    {
        if ($m->system_key) {
            return __('System accounts cannot be deleted.');
        }

        return $m->lines()->exists() || $m->children()->exists() ? __('Accounts with postings or sub-accounts cannot be deleted.') : null;
    }
}

class JournalEntryResource extends AccountingResource
{
    public static string $model = JournalEntry::class;
    public static string $slug = 'journal-entries';
    public static string $permission = 'journal_entries';
    public static string $icon = 'bi-journal-text';
    public static array $with = ['creator'];
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Journal Entries'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('date')->index(),
            Field::text('description')->index()->searchable(),
            Field::text('source_type')->label(__('Source'))->display(fn ($e) => e($e->source_type ? status_label($e->source_type) : __('Manual')))->index(false),
            Field::select('status', ['draft', 'posted', 'reversed'])->index()->filterable(),
            Field::checkbox('is_opening')->label(__('Opening'))->filterable(),
            Field::money('total_debit')->label(__('Amount'))->index(),
        ];
    }

    public function createUrl(): ?string { return route('journal.create'); }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('journal.show', $m); }
    public function deleteBlocker(Model $m): ?string { return __('Journal entries cannot be deleted. Reverse them instead.'); }
}

class BankAccountResource extends AccountingResource
{
    public static string $model = BankAccount::class;
    public static string $slug = 'bank-accounts';
    public static string $permission = 'bank_accounts';
    public static string $icon = 'bi-bank';
    public static array $with = ['account', 'branch'];

    public function title(): string { return __('Cash Boxes & Bank Accounts'); }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable(),
            Field::select('type', ['cash' => __('Cash box'), 'bank' => __('Bank'), 'card' => __('Card terminal'), 'wallet' => __('E-wallet')])->required()->index()->filterable()->col(3),
            Field::select('account_id', fn () => Options::accounts(['asset']))->label(__('GL account'))->relation('account', 'label')->required()->col(5),
            Field::select('branch_id', fn () => Options::branches())->label(__('Branch'))->relation('branch', 'displayName')->col(4),
            Field::text('bank_name')->label(__('Bank name'))->col(4),
            Field::text('account_number')->label(__('Account number'))->col(4),
            Field::text('iban')->label('IBAN')->col(4),
            Field::make('card_fee_percent', 'decimal')->label(__('Card commission (%)'))->col(4)->default(0)->rules(['nullable', 'numeric', 'min:0', 'max:20'])
                ->help(__('Percentage the bank deducts from every card payment received into this account, e.g. 1.20'))->section(__('Card payments')),
            Field::money('card_fee_max')->label(__('Maximum commission per transaction'))->col(4)->rules(['nullable', 'numeric', 'min:0'])
                ->help(__('Leave empty for no maximum, e.g. 10.000')),
            Field::text('balance')->label(__('Balance'))->hideOnForm()->display(fn ($b) => '<span class="font-monospace fw-bold">'.e(money(Ledger::accountBalance($b->account_id))).'</span>')->index(false),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index(),
        ];
    }
}

class ExpenseResource extends AccountingResource
{
    public static string $model = Expense::class;
    public static string $slug = 'expenses';
    public static string $permission = 'expenses';
    public static string $icon = 'bi-credit-card-2-back';
    public static array $with = ['account', 'bankAccount', 'supplier'];
    public static ?string $dateColumn = 'date';
    public static bool $hasAttachments = true;

    public function title(): string { return __('Expenses'); }

    public function fields(): array
    {
        return [
            Field::text('number')->hideOnForm()->index()->searchable(),
            Field::date('date')->required()->default(now()->toDateString())->index()->col(3),
            Field::select('account_id', fn () => Options::accounts(['expense']))->label(__('Expense account'))->relation('account', 'label')->required()->index()->col(5),
            Field::select('cost_center_id', fn () => Options::costCenters())->label(__('Cost center'))->relation('costCenter', 'displayName')->col(4),
            Field::text('description')->required()->index()->searchable()->col(8),
            Field::text('reference')->col(4),
            Field::money('amount')->label(__('Amount (excl. VAT)'))->required()->index()->col(3),
            Field::money('tax_amount')->label(__('VAT'))->col(3),
            Field::select('bank_account_id', fn () => Options::bankAccounts())->label(__('Paid from'))->relation('bankAccount')->col(3)->help(__('Leave empty to record as payable to the supplier.')),
            Field::lookup('supplier_id', 'suppliers', 'supplier')->label(__('Supplier'))->col(3),
            Field::money('total')->hideOnForm()->index(),
            Field::select('status', ['draft', 'posted'])->hideOnForm()->index()->filterable(),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['number'] = DocumentNumber::next('expense');
            $data['status'] = 'draft';
            $data['created_by'] = auth()->id();
        }
        $data['total'] = Money::add($data['amount'] ?? 0, $data['tax_amount'] ?? 0);

        return $data;
    }

    public function canEditRecord(Model $m): bool { return $m->status === 'draft'; }
    public function deleteBlocker(Model $m): ?string { return $m->status === 'posted' ? __('Posted documents cannot be deleted.') : null; }

    public function rowActions(Model $m): array
    {
        if ($m->status === 'draft') {
            return auth()->user()->can('expenses.approve')
                ? [['label' => __('Post'), 'url' => route('accounting.expenses.post', $m), 'icon' => 'bi-check2-circle', 'method' => 'post', 'class' => 'btn-success', 'confirm' => __('Post this expense?')]] : [];
        }

        return [['label' => __('Print voucher'), 'url' => route('vouchers.expense', $m), 'icon' => 'bi-printer', 'blank' => true]];
    }
}

class FundTransferResource extends AccountingResource
{
    public static string $model = FundTransfer::class;
    public static string $slug = 'fund-transfers';
    public static string $permission = 'fund_transfers';
    public static string $icon = 'bi-arrow-repeat';
    public static array $with = ['fromAccount', 'toAccount'];
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Fund Transfers'); }

    // dedicated screen showing the live balance of both accounts
    public function createUrl(): ?string { return route('accounting.transfers.create'); }
    public function editUrl(Model $m): ?string { return $m->status === 'draft' ? route('accounting.transfers.edit', $m) : null; }

    public function fields(): array
    {
        return [
            Field::text('number')->hideOnForm()->index()->searchable(),
            Field::date('date')->required()->default(now()->toDateString())->index()->col(4),
            Field::select('from_bank_account_id', fn () => Options::bankAccounts())->label(__('From'))->relation('fromAccount')->required()->index()->col(4),
            Field::select('to_bank_account_id', fn () => Options::bankAccounts())->label(__('To'))->relation('toAccount')->required()->index()->col(4)->rules(['different:from_bank_account_id']),
            Field::money('amount')->required()->index()->col(4)->rules(['gt:0']),
            Field::text('reference')->col(4),
            Field::text('notes')->col(4),
            Field::select('status', ['draft', 'posted'])->hideOnForm()->index()->filterable(),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['number'] = DocumentNumber::next('fund_transfer');
            $data['status'] = 'draft';
            $data['created_by'] = auth()->id();
        }

        return $data;
    }

    public function canEditRecord(Model $m): bool { return $m->status === 'draft'; }
    public function deleteBlocker(Model $m): ?string { return $m->status === 'posted' ? __('Posted documents cannot be deleted.') : null; }

    public function rowActions(Model $m): array
    {
        if ($m->status === 'draft') {
            return auth()->user()->can('fund_transfers.approve')
                ? [['label' => __('Post'), 'url' => route('accounting.transfers.post', $m), 'icon' => 'bi-check2-circle', 'method' => 'post', 'class' => 'btn-success', 'confirm' => __('Post this transfer?')]] : [];
        }

        return [['label' => __('Print voucher'), 'url' => route('vouchers.transfer', $m), 'icon' => 'bi-printer', 'blank' => true]];
    }
}

class BankReconciliationResource extends AccountingResource
{
    public static string $model = BankReconciliation::class;
    public static string $slug = 'bank-reconciliations';
    public static string $permission = 'bank_reconciliations';
    public static string $icon = 'bi-check2-square';
    public static array $with = ['bankAccount'];
    public static string $defaultSort = 'statement_date';

    public function title(): string { return __('Bank Reconciliations'); }

    public function fields(): array
    {
        return [
            Field::select('bank_account_id', fn () => Options::bankAccounts('bank'))->label(__('Bank account'))->relation('bankAccount')->required()->index(),
            Field::date('statement_date')->label(__('Statement date'))->required()->index()->col(3),
            Field::money('statement_balance')->label(__('Statement balance'))->required()->index()->col(3),
            Field::money('book_balance')->label(__('Book balance'))->hideOnForm()->index(),
            Field::money('difference')->hideOnForm()->index(),
            Field::select('status', ['draft', 'completed'])->hideOnForm()->index(),
            Field::text('notes')->col(12),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['created_by'] = auth()->id();
        }

        return $data;
    }

    public function showUrl(Model $m): ?string { return route('accounting.reconciliations.show', $m); }
}

class FixedAssetResource extends AccountingResource
{
    public static string $model = FixedAsset::class;
    public static string $slug = 'fixed-assets';
    public static string $permission = 'fixed_assets';
    public static string $icon = 'bi-building-gear';
    public static bool $hasAttachments = true;

    public function title(): string { return __('Fixed Assets & Depreciation'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->unique('fixed_assets')->col(3),
            Field::text('name')->required()->index()->searchable()->col(5),
            Field::select('category', ['equipment', 'vehicles', 'furniture', 'computers', 'buildings', 'other'])->index()->filterable()->col(4),
            Field::select('asset_account_id', fn () => Options::accounts(['asset']))->label(__('Asset account'))->relation('assetAccount', 'label')->required()->col(4),
            Field::select('accumulated_account_id', fn () => Options::accounts(['asset']))->label(__('Accumulated depreciation account'))->relation('accumulatedAccount', 'label')->required()->col(4),
            Field::select('expense_account_id', fn () => Options::accounts(['expense']))->label(__('Depreciation expense account'))->relation('expenseAccount', 'label')->required()->col(4),
            Field::date('purchase_date')->label(__('Purchase date'))->required()->index()->col(3),
            Field::date('depreciation_start')->label(__('Depreciation start'))->required()->col(3),
            Field::money('cost')->required()->index()->col(3),
            Field::money('salvage_value')->label(__('Salvage value'))->col(3),
            Field::number('useful_life_months')->label(__('Useful life (months)'))->required()->col(3)->rules(['min:1']),
            Field::select('method', ['straight_line' => __('Straight line'), 'declining' => __('Declining balance')])->default('straight_line')->col(3),
            Field::select('cost_center_id', fn () => Options::costCenters())->label(__('Cost center'))->col(3),
            Field::select('branch_id', fn () => Options::branches())->label(__('Branch'))->col(3),
            Field::money('accumulated_depreciation')->label(__('Accumulated depreciation'))->hideOnForm()->index(),
            Field::money('book_value')->label(__('Book value'))->hideOnForm()->index(),
            Field::select('status', ['active', 'fully_depreciated', 'disposed'])->hideOnForm()->index()->filterable(),
            Field::textarea('notes'),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['book_value'] = $data['cost'] ?? 0;
        }

        return $data;
    }

    public function canEditRecord(Model $m): bool { return ! $m->depreciations()->exists(); }
    public function deleteBlocker(Model $m): ?string { return $m->depreciations()->exists() ? __('Assets with depreciation cannot be deleted. Dispose them instead.') : null; }
    public function showUrl(Model $m): ?string { return route('accounting.assets.show', $m); }

    public function headerActions(): array
    {
        return [['label' => __('Run monthly depreciation'), 'url' => route('accounting.assets.depreciation'), 'icon' => 'bi-calculator']];
    }
}

class BudgetResource extends AccountingResource
{
    public static string $model = Budget::class;
    public static string $slug = 'budgets';
    public static string $permission = 'budgets';
    public static string $icon = 'bi-piggy-bank';
    public static array $with = ['fiscalYear'];

    public function title(): string { return __('Budgets'); }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable(),
            Field::select('fiscal_year_id', fn () => FiscalYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all())->label(__('Fiscal year'))->relation('fiscalYear')->required()->index(),
            Field::select('status', ['draft', 'approved'])->default('draft')->index(),
        ];
    }

    public function showUrl(Model $m): ?string { return route('accounting.budgets.show', $m); }
}

class FiscalYearResource extends AccountingResource
{
    public static string $model = FiscalYear::class;
    public static string $slug = 'fiscal-years';
    public static string $permission = 'fiscal_years';
    public static string $icon = 'bi-calendar3';
    public static string $defaultSort = 'start_date';

    public function title(): string { return __('Fiscal Years & Periods'); }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index(),
            Field::date('start_date')->label(__('Start'))->required()->index()->col(3),
            Field::date('end_date')->label(__('End'))->required()->index()->col(3)->rules(['after:start_date']),
            Field::select('status', ['open', 'closed'])->hideOnForm()->index(),
        ];
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        if ($created) {
            for ($d = $m->start_date->copy(); $d->lte($m->end_date); $d->addMonth()) {
                $m->periods()->create(['name' => $d->format('Y-m'), 'start_date' => $d->copy()->startOfMonth(), 'end_date' => $d->copy()->endOfMonth(), 'status' => 'open']);
            }
        }
    }

    public function canEditRecord(Model $m): bool { return $m->status === 'open' && ! \App\Modules\Accounting\Models\JournalEntry::query()->whereBetween('date', [$m->start_date, $m->end_date])->exists(); }
    public function deleteBlocker(Model $m): ?string { return $this->canEditRecord($m) ? null : __('Fiscal years with postings cannot be deleted.'); }
    public function showUrl(Model $m): ?string { return route('accounting.fiscal.show', $m); }
}
