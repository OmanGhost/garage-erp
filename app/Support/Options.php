<?php

namespace App\Support;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\CostCenter;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Workshop\Models\Department;

/**
 * Shared option lists for selects (values are stable keys, labels translated).
 */
class Options
{
    public const VEHICLE_TYPES = ['car', 'suv', 'pickup', 'van', 'commercial', 'truck', 'bus', 'heavy_equipment', 'motorcycle'];
    public const FUEL_TYPES = ['petrol', 'diesel', 'hybrid', 'electric', 'lpg'];
    public const TRANSMISSIONS = ['automatic', 'manual', 'cvt', 'dct'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const PAYMENT_METHODS = ['cash', 'card', 'bank_transfer', 'cheque', 'online', 'wallet'];
    public const CUSTOMER_TYPES = ['individual', 'company', 'government', 'insurance'];
    public const CLASSIFICATIONS = ['regular', 'vip', 'corporate', 'blacklisted'];
    public const PRICE_LEVELS = ['retail', 'wholesale', 'workshop'];
    public const ACCOUNT_TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    public static function branches(): array
    {
        return Branch::query()->orderBy('name_ar')->get()->mapWithKeys(fn ($b) => [$b->id => $b->displayName()])->all();
    }

    public static function warehouses(): array
    {
        return Warehouse::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($w) => [$w->id => $w->displayName()])->all();
    }

    public static function departments(): array
    {
        return Department::query()->where('is_active', true)->orderBy('sort')->get()->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all();
    }

    public static function costCenters(): array
    {
        return CostCenter::query()->orderBy('code')->get()->mapWithKeys(fn ($c) => [$c->id => $c->code.' - '.$c->displayName()])->all();
    }

    public static function bankAccounts(?string $type = null): array
    {
        return BankAccount::query()->where('is_active', true)->when($type, fn ($q) => $q->where('type', $type))->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function accounts(?array $types = null): array
    {
        return Account::query()->where('is_group', false)->where('is_active', true)->when($types, fn ($q) => $q->whereIn('type', $types))
            ->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all();
    }

    public static function users(): array
    {
        return \App\Models\User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function technicians(): array
    {
        return \App\Models\User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)
            ->where(fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('is_technician', true))->orWhereHas('departments'))
            ->orderBy('name')->pluck('name', 'id')->all() ?: self::users();
    }
}
