<?php

namespace App\Modules\Sales\Crud;

use App\Modules\Sales\Models\CashRegister;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Models\PosSession;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Store\Models\Coupon;
use App\Modules\Store\Models\StoreOrder;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;

class InvoiceResource extends CrudResource
{
    public static string $model = Invoice::class;
    public static string $slug = 'invoices';
    public static string $permission = 'invoices';
    public static ?string $module = 'sales';
    public static string $icon = 'bi-receipt-cutoff';
    public static array $with = ['customer', 'jobCard.vehicle', 'creditNotes'];
    public static ?string $dateColumn = 'issue_date';

    public function title(): string { return __('Sales & Tax Invoices'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable()->display(fn ($i) => e($i->displayNumber())),
            Field::date('issue_date')->label(__('Date'))->index(),
            Field::select('type', ['sale', 'pos', 'workshop', 'insurance', 'fleet', 'store'])->index()->filterable(),
            Field::text('cust')->label(__('Customer'))->display(fn ($i) => e($i->customer?->name))->index(false),
            Field::text('job')->label(__('Job card'))->display(fn ($i) => e($i->jobCard?->number ?? ''))->index(false),
            Field::select('payment_terms', ['cash' => __('Cash'), 'credit' => __('Credit')])->label(__('Terms'))->index()->filterable(),
            Field::select('status', ['draft', 'held', 'posted', 'partially_paid', 'paid', 'cancelled'])->index()->filterable(),
            Field::money('total')->index(),
            Field::text('due')->label(__('Balance due'))->display(fn ($i) => $i->isPosted() ? '<span class="font-monospace">'.e(money($i->balanceDue())).'</span>' : '')->index(false),
        ];
    }

    public function rowActions(Model $m): array
    {
        if (! auth()->user()?->can('invoices.print')) {
            return [];
        }
        $actions = [
            ['label' => __('Print'), 'url' => route('invoices.print', [$m, 'a4']), 'icon' => 'bi-printer', 'blank' => true],
            ['label' => __('Delivery note'), 'url' => route('invoices.delivery-note', $m), 'icon' => 'bi-truck', 'blank' => true],
        ];
        if ($m->job_card_id && auth()->user()->can('job_cards.print')) {
            $actions[] = ['label' => __('Gate pass'), 'url' => route('job-cards.gate-pass', $m->job_card_id), 'icon' => 'bi-door-open', 'blank' => true];
        }

        return $actions;
    }

    public function indexView(): string { return 'sales::invoices-index'; }

    /** Headline numbers and per-status counts for the list page. */
    public function stats(): array
    {
        $base = fn () => $this->scope(Invoice::query());
        $open = fn () => $base()->whereIn('status', ['posted', 'partially_paid']);

        return [
            'month' => (string) $base()->whereIn('status', ['posted', 'partially_paid', 'paid'])->whereBetween('issue_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->sum('total'),
            'month_count' => $base()->whereIn('status', ['posted', 'partially_paid', 'paid'])->whereBetween('issue_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->count(),
            'outstanding' => (string) $open()->sum(\Illuminate\Support\Facades\DB::raw('total - paid_amount')),
            'outstanding_count' => $open()->count(),
            'overdue' => (string) $open()->whereDate('due_date', '<', today())->sum(\Illuminate\Support\Facades\DB::raw('total - paid_amount')),
            'overdue_count' => $open()->whereDate('due_date', '<', today())->count(),
            'drafts' => $base()->whereIn('status', ['draft', 'held'])->count(),
            'by_status' => $base()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all(),
        ];
    }

    public function createUrl(): ?string { return route('invoices.create'); }
    public function editUrl(Model $m): ?string { return in_array($m->status, ['draft', 'held'], true) ? route('invoices.edit', $m) : null; }
    public function canEditRecord(Model $m): bool { return in_array($m->status, ['draft', 'held'], true); }
    public function showUrl(Model $m): ?string { return route('invoices.show', $m); }
    public function deleteBlocker(Model $m): ?string { return in_array($m->status, ['draft', 'held'], true) ? null : __('Posted invoices cannot be deleted. Use a credit note.'); }
}

class CreditNoteResource extends CrudResource
{
    public static string $model = CreditNote::class;
    public static string $slug = 'credit-notes';
    public static string $permission = 'credit_notes';
    public static ?string $module = 'sales';
    public static string $icon = 'bi-file-earmark-minus';
    public static array $with = ['customer', 'invoice'];
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Credit Notes & Sales Returns'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable()->display(fn ($c) => e($c->number ?? 'DRAFT-'.$c->id)),
            Field::date('date')->index(),
            Field::text('inv')->label(__('Invoice'))->display(fn ($c) => $c->invoice ? '<a href="'.e(route('invoices.show', $c->invoice)).'">'.e($c->invoice->displayNumber()).'</a>' : '—')->index(false),
            Field::text('cust')->label(__('Customer'))->display(fn ($c) => e($c->customer?->name))->index(false),
            Field::text('reason')->label(__('Reason'))->searchable()->index(false),
            Field::checkbox('restock')->label(__('Restocked'))->index(),
            Field::select('status', ['draft', 'posted'])->index()->filterable(),
            Field::money('total')->index(),
            Field::text('settlement')->label(__('Settlement'))->index(false)->display(function ($c) {
                if ($c->status !== 'posted') {
                    return '<span class="badge text-bg-warning">'.e(__('Awaiting posting')).'</span>';
                }
                $refunded = (float) $c->refunded_amount;

                return match (true) {
                    $refunded <= 0 => '<span class="badge text-bg-primary">'.e(__('Credited to account')).'</span>',
                    $refunded < (float) $c->total => '<span class="badge text-bg-info">'.e(__('Partly refunded')).'</span> <span class="small font-monospace">'.e(money($refunded, false)).'</span>',
                    default => '<span class="badge text-bg-success">'.e(__('Refunded')).'</span>',
                };
            }),
        ];
    }

    public function rowActions(Model $m): array
    {
        return auth()->user()?->can('credit_notes.print')
            ? [['label' => __('Print'), 'url' => route('credit-notes.print', $m), 'icon' => 'bi-printer', 'blank' => true]]
            : [];
    }

    public function createUrl(): ?string { return null; }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('credit-notes.show', $m); }
    public function deleteBlocker(Model $m): ?string { return $m->status === 'posted' ? __('Posted documents cannot be deleted.') : null; }
}

class PaymentResource extends CrudResource
{
    public static string $model = Payment::class;
    public static string $slug = 'payments';
    public static string $permission = 'payments';
    public static ?string $module = 'sales';
    public static string $icon = 'bi-wallet2';
    public static array $with = ['bankAccount', 'party'];
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Receipts & Payment Vouchers'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('date')->index(),
            Field::select('direction', ['in' => __('Receipt'), 'out' => __('Payment')])->index()->filterable(),
            Field::text('pty')->label(__('Party'))->display(fn ($p) => e($p->party?->name ?? '—'))->index(false),
            Field::select('method', Options::PAYMENT_METHODS)->index()->filterable(),
            Field::text('acc')->label(__('Cash / bank'))->display(fn ($p) => e($p->bankAccount?->name))->index(false),
            Field::text('reference')->index()->searchable(),
            Field::select('status', ['posted', 'cancelled'])->index()->filterable(),
            Field::money('amount')->index(),
            Field::money('fee_amount')->label(__('Card commission'))->hideOnForm()->display(fn ($p) => (float) $p->fee_amount > 0 ? '<span class="font-monospace text-danger">-'.e(money($p->fee_amount, false)).'</span>' : '—'),
            Field::money('net_amount')->label(__('Net to the bank'))->hideOnForm()->display(fn ($p) => '<span class="font-monospace">'.e(money($p->net_amount ?? $p->amount, false)).'</span>'),
        ];
    }

    public function createUrl(): ?string { return route('payments.receive'); }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('payments.show', $m); }
    public function deleteBlocker(Model $m): ?string { return __('Payments cannot be deleted. Cancel (reverse) them instead.'); }

    public function headerActions(): array
    {
        return [
            ['label' => __('Receive from customer'), 'url' => route('payments.receive'), 'icon' => 'bi-box-arrow-in-down', 'class' => 'btn-outline-success'],
            ['label' => __('Pay supplier'), 'url' => route('payments.pay'), 'icon' => 'bi-box-arrow-up', 'class' => 'btn-outline-danger'],
        ];
    }
}

class SalesOrderResource extends CrudResource
{
    public static string $model = SalesOrder::class;
    public static string $slug = 'sales-orders';
    public static string $permission = 'sales_orders';
    public static ?string $module = 'sales';
    public static string $icon = 'bi-bag-check';
    public static array $with = ['customer'];
    public static ?string $dateColumn = 'order_date';

    public function title(): string { return __('Sales Orders'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('order_date')->label(__('Date'))->index(),
            Field::text('cust')->label(__('Customer'))->display(fn ($o) => e($o->customer?->name))->index(false),
            Field::date('delivery_date')->label(__('Delivery'))->index(),
            Field::select('status', ['draft', 'confirmed', 'invoiced', 'cancelled'])->index()->filterable(),
            Field::money('total')->index(),
        ];
    }

    public function createUrl(): ?string { return route('sales-orders.create'); }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('sales-orders.show', $m); }
    public function deleteBlocker(Model $m): ?string { return $m->status === 'invoiced' ? __('Invoiced orders cannot be deleted.') : null; }
}

class PosSessionResource extends CrudResource
{
    public static string $model = PosSession::class;
    public static string $slug = 'pos-sessions';
    public static string $permission = 'pos';
    public static ?string $module = 'sales';
    public static string $icon = 'bi-cash-stack';
    public static bool $readonly = true;
    public static array $with = ['register', 'user'];
    public static ?string $dateColumn = 'opened_at';

    public function title(): string { return __('Cash Sessions'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::text('reg')->label(__('Register'))->display(fn ($s) => e($s->register?->name))->index(false),
            Field::text('usr')->label(__('Cashier'))->display(fn ($s) => e($s->user?->name))->index(false),
            Field::datetime('opened_at')->label(__('Opened'))->index(),
            Field::datetime('closed_at')->label(__('Closed'))->index(),
            Field::money('opening_cash')->label(__('Opening cash'))->index(),
            Field::money('expected_cash')->label(__('Expected'))->index(),
            Field::money('counted_cash')->label(__('Counted'))->index(),
            Field::money('difference')->label(__('Over / short'))->index(),
            Field::select('status', ['open', 'closed'])->index()->filterable(),
        ];
    }

    public function showUrl(Model $m): ?string { return route('pos.sessions.show', $m); }
}

class CashRegisterResource extends CrudResource
{
    public static string $model = CashRegister::class;
    public static string $slug = 'cash-registers';
    public static string $permission = 'cash_registers';
    public static ?string $module = 'sales';
    public static string $icon = 'bi-pc-display-horizontal';
    public static array $with = ['branch', 'bankAccount', 'warehouse'];

    public function title(): string { return __('Cash Registers'); }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable(),
            Field::select('branch_id', fn () => Options::branches())->label(__('Branch'))->relation('branch', 'displayName')->required()->index(),
            Field::select('bank_account_id', fn () => Options::bankAccounts('cash'))->label(__('Cash box account'))->relation('bankAccount')->required()->index(),
            Field::select('warehouse_id', fn () => Options::warehouses())->label(__('Sales warehouse'))->relation('warehouse', 'displayName')->required()->index(),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index(),
        ];
    }
}

class StoreOrderResource extends CrudResource
{
    public static string $model = StoreOrder::class;
    public static string $slug = 'store-orders';
    public static string $permission = 'store_orders';
    public static ?string $module = 'store';
    public static string $icon = 'bi-globe2';
    public static array $with = ['invoice'];
    public static ?string $dateColumn = 'created_at';

    public function title(): string { return __('Online Store Orders'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::datetime('created_at')->label(__('Date'))->index(),
            Field::text('customer_name')->label(__('Customer'))->index()->searchable(),
            Field::text('customer_phone')->label(__('Phone'))->index()->searchable(),
            Field::select('shipping_method', ['pickup' => __('Pickup'), 'delivery' => __('Delivery')])->label(__('Shipping'))->index()->filterable(),
            Field::select('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'])->index()->filterable(),
            Field::select('payment_status', ['unpaid', 'paid', 'refunded'])->label(__('Payment'))->index()->filterable(),
            Field::money('total')->index(),
        ];
    }

    public function createUrl(): ?string { return null; }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('store-orders.show', $m); }
    public function deleteBlocker(Model $m): ?string { return $m->invoice_id ? __('Invoiced orders cannot be deleted.') : null; }
}

class CouponResource extends CrudResource
{
    public static string $model = Coupon::class;
    public static string $slug = 'coupons';
    public static string $permission = 'coupons';
    public static ?string $module = 'store';
    public static string $icon = 'bi-ticket-perforated';

    public function title(): string { return __('Discount Coupons'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->unique('coupons')->col(3),
            Field::select('type', ['percent' => __('Percentage'), 'fixed' => __('Fixed amount')])->default('percent')->index()->col(3),
            Field::money('value')->required()->index()->col(3),
            Field::money('min_order')->label(__('Minimum order'))->col(3),
            Field::number('max_uses')->label(__('Max uses'))->col(3),
            Field::number('used_count')->label(__('Used'))->hideOnForm()->index(),
            Field::date('starts_at')->label(__('Starts'))->index()->col(3),
            Field::date('ends_at')->label(__('Ends'))->index()->col(3),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index()->col(3),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        $data['code'] = strtoupper($data['code'] ?? '');

        return $data;
    }
}
