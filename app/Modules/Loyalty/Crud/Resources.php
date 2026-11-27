<?php

namespace App\Modules\Loyalty\Crud;

use App\Modules\Loyalty\Models\GiftCard;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Loyalty\Models\Membership;
use App\Modules\Loyalty\Models\MembershipPlan;
use App\Modules\Workshop\Models\Service;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MembershipPlanResource extends CrudResource
{
    public static string $model = MembershipPlan::class;
    public static string $slug = 'membership-plans';
    public static string $permission = 'membership_plans';
    public static ?string $module = 'memberships';
    public static string $icon = 'bi-patch-check';
    public static string $defaultSort = 'price';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Membership plans'); }
    public function singular(): string { return __('Membership plan'); }

    public function query(): Builder
    {
        return MembershipPlan::query()->withCount('items');
    }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->col(3),
            Field::text('name')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(5),
            Field::text('name_en')->label(__('Name (English)'))->col(4),
            Field::money('price')->label(__('Price'))->required()->index()->col(3),
            Field::number('duration_months')->label(__('Duration (months)'))->default(12)->required()->index()->col(3),
            Field::decimal('parts_discount')->label(__('Parts discount %'))->default(0)->col(3)->rules(['min:0', 'max:100']),
            Field::decimal('labor_discount')->label(__('Labour discount %'))->default(0)->col(3)->rules(['min:0', 'max:100']),
            Field::checkbox('priority_booking')->label(__('Priority booking'))->col(4),
            Field::number('roadside_calls')->label(__('Roadside assistance calls'))->default(0)->col(4),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index()->col(4),
            Field::number('items_count')->label(__('Included services'))->hideOnForm()->index(false),
            Field::textarea('items_text')->label(__('Included services'))->hideOnShow()
                ->help(__('One service per line: service code | times per membership (e.g. OIL-01 | 4). Saving replaces the list.')),
            Field::textarea('description'),
        ];
    }

    public function virtualFields(): array { return ['items_text']; }

    public function saved(Model $m, array $data, bool $created): void
    {
        $text = trim((string) ($data['items_text'] ?? ''));
        if ($text === '') {
            return;
        }
        $m->items()->delete();
        foreach (preg_split('/\R/u', $text) as $line) {
            $p = array_map('trim', explode('|', $line));
            $service = $p[0] !== '' ? Service::query()->where('code', $p[0])->first() : null;
            if ($service) {
                $m->items()->create(['service_id' => $service->id, 'qty' => max(1, (int) ($p[1] ?? 1))]);
            }
        }
    }

    public function showPanels(Model $m): array
    {
        return [['view' => 'loyalty::partials.plan-items', 'data' => ['plan' => $m->load('items.service')]]];
    }
}

class MembershipResource extends CrudResource
{
    public static string $model = Membership::class;
    public static string $slug = 'memberships';
    public static string $permission = 'memberships';
    public static ?string $module = 'memberships';
    public static string $icon = 'bi-person-vcard';
    public static array $with = ['customer', 'plan', 'vehicle'];
    public static bool $readonly = true;

    public function title(): string { return __('Memberships'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::text('cust')->label(__('Customer'))->display(fn ($m) => e($m->customer?->name))->index(false),
            Field::text('plan_name')->label(__('Plan'))->display(fn ($m) => e($m->plan?->displayName()))->index(false),
            Field::text('veh')->label(__('Vehicle'))->display(fn ($m) => e($m->vehicle?->plate() ?? __('All vehicles')))->index(false),
            Field::date('starts_at')->label(__('Starts'))->index(),
            Field::date('ends_at')->label(__('Ends'))->index(),
            Field::select('status', array_combine($s = ['pending', 'active', 'expired', 'cancelled'], array_map('status_label', $s)))->index()->filterable(),
            Field::money('price')->label(__('Price'))->index(),
        ];
    }

    public function createUrl(): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('memberships.show', $m); }
}

class GiftCardResource extends CrudResource
{
    public static string $model = GiftCard::class;
    public static string $slug = 'gift-cards';
    public static string $permission = 'gift_cards';
    public static ?string $module = 'loyalty';
    public static string $icon = 'bi-gift';
    public static array $with = ['customer'];
    public static bool $readonly = true;

    public function title(): string { return __('Gift cards'); }

    public function fields(): array
    {
        return [
            Field::text('code')->index()->searchable(),
            Field::money('initial_amount')->label(__('Amount'))->index(),
            Field::money('balance')->label(__('Balance'))->index(),
            Field::text('cust')->label(__('Bought by'))->display(fn ($g) => e($g->customer?->name ?? ''))->index(false),
            Field::text('recipient_name')->label(__('Recipient'))->index()->searchable(),
            Field::date('expires_at')->label(__('Expires'))->index(),
            Field::select('status', array_combine($s = ['active', 'used', 'expired', 'void'], array_map('status_label', $s)))->index()->filterable(),
        ];
    }

    public function createUrl(): ?string { return null; }

    public function rowActions(Model $m): array
    {
        return [['label' => __('Print'), 'url' => route('gift-cards.print', $m), 'icon' => 'bi-printer', 'blank' => true]];
    }

    public function headerActions(): array
    {
        return [['label' => __('Issue gift card'), 'url' => route('loyalty.dashboard').'#gift-cards', 'icon' => 'bi-plus-lg', 'class' => 'btn-primary']];
    }
}

class LoyaltyTransactionResource extends CrudResource
{
    public static string $model = LoyaltyTransaction::class;
    public static string $slug = 'loyalty-transactions';
    public static string $permission = 'loyalty';
    public static ?string $module = 'loyalty';
    public static string $icon = 'bi-stars';
    public static array $with = ['customer'];
    public static bool $readonly = true;

    public function title(): string { return __('Loyalty points ledger'); }

    public function fields(): array
    {
        return [
            Field::date('created_at')->label(__('Date'))->index(),
            Field::text('cust')->label(__('Customer'))->display(fn ($t) => e($t->customer?->name))->index(false),
            Field::select('type', array_combine($s = ['earn', 'redeem', 'reverse', 'refund', 'referral', 'adjust', 'expire'], array_map('status_label', $s)))->index()->filterable(),
            Field::decimal('points')->label(__('Points'))->index(),
            Field::money('value')->label(__('Value'))->index(),
            Field::text('notes')->index()->searchable(),
        ];
    }

    public function showUrl(Model $m): ?string { return null; }
}
