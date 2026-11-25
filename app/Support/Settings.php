<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Company level settings stored as JSON on companies.settings with defaults
 * from config('erp.defaults').
 */
class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $company = tenant()->company();
        $settings = $company?->settings ?? [];
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return Arr::get(config('erp.defaults'), $key, $default) ?? config('erp.defaults')[$key] ?? $default;
    }

    public static function set(array $values): void
    {
        $company = tenant()->company();
        if (! $company) {
            return;
        }
        $company->settings = array_merge($company->settings ?? [], $values);
        $company->save();
    }

    public static function bool(string $key): bool
    {
        return filter_var(static::get($key), FILTER_VALIDATE_BOOLEAN);
    }
}
