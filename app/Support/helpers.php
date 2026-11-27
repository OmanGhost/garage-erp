<?php

use App\Support\Money;
use App\Support\Settings;
use App\Support\Tenancy\Tenant;

if (! function_exists('tenant')) {
    function tenant(): Tenant
    {
        return app(Tenant::class);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('money')) {
    function money(mixed $v, bool $withCurrency = true): string
    {
        return Money::format($v, $withCurrency);
    }
}

if (! function_exists('qty')) {
    function qty(mixed $v): string
    {
        $s = rtrim(rtrim(Money::round($v, 3), '0'), '.');

        return $s === '' || $s === '-' ? '0' : $s;
    }
}

if (! function_exists('is_rtl')) {
    function is_rtl(): bool
    {
        return app()->getLocale() === 'ar';
    }
}

if (! function_exists('local_name')) {
    /** Pick the localized name attribute (name_ar / name_en / name). */
    function local_name(?object $model, string $base = 'name'): string
    {
        if (! $model) {
            return '';
        }
        $loc = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $other = $loc === 'ar' ? 'en' : 'ar';

        return (string) ($model->{$base.'_'.$loc} ?? null ?: $model->{$base.'_'.$other} ?? null ?: $model->{$base} ?? '');
    }
}

if (! function_exists('status_badge')) {
    function status_badge(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }
        $map = [
            'success' => ['approved', 'paid', 'posted', 'completed', 'delivered', 'received', 'active', 'pass', 'present', 'closed_ok', 'in_stock', 'confirmed', 'accepted', 'resolved', 'converted', 'ready_for_delivery', 'done', 'open', 'back_in'],
            'warning' => ['pending', 'pending_inspection', 'awaiting_quotation', 'awaiting_customer_approval', 'awaiting_spare_parts', 'partially_paid', 'partially_received', 'partially_approved', 'submitted', 'sent', 'draft', 'held', 'on_hold', 'scheduled', 'under_review', 'late', 'trial', 'in_transit', 'requested', 'reserved', 'sent_out', 'assigned'],
            'info' => ['under_inspection', 'in_progress', 'quality_inspection', 'issued', 'checked_in', 'dismantling', 'ordered', 'invoiced', 'en_route', 'on_site'],
            'danger' => ['cancelled', 'rejected', 'expired', 'fail', 'absent', 'no_show', 'reversed', 'overdue', 'suspended', 'scrapped', 'damaged', 'critical', 'high'],
        ];
        $color = 'secondary';
        foreach ($map as $c => $list) {
            if (in_array($status, $list, true)) {
                $color = $c;
                break;
            }
        }

        return '<span class="badge rounded-pill text-bg-'.$color.'">'.e(__('status.'.$status) === 'status.'.$status ? \Illuminate\Support\Str::headline($status) : __('status.'.$status)).'</span>';
    }
}

if (! function_exists('status_label')) {
    function status_label(?string $status): string
    {
        if (! $status) {
            return '';
        }
        $t = __('status.'.$status);

        return $t === 'status.'.$status ? \Illuminate\Support\Str::headline($status) : $t;
    }
}

if (! function_exists('omani_phone')) {
    /** Normalize Omani phone numbers to +968XXXXXXXX. */
    function omani_phone(?string $phone): ?string
    {
        if (! $phone) {
            return $phone;
        }
        $d = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($d, '00968')) {
            $d = substr($d, 5);
        } elseif (str_starts_with($d, '968') && strlen($d) === 11) {
            $d = substr($d, 3);
        }

        return strlen($d) === 8 ? '+968'.$d : $phone;
    }
}
