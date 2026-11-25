<?php

namespace App\Support;

/**
 * Exact decimal arithmetic for monetary values and quantities (bcmath).
 * All values are handled as numeric strings; floats are never used.
 */
final class Money
{
    public const SCALE = 3;

    public static function n(mixed $v): string
    {
        if ($v === null || $v === '' || $v === false) {
            return '0';
        }
        if (is_float($v)) {
            $v = sprintf('%.8F', $v);
        }
        $v = str_replace([',', ' '], '', trim((string) $v));

        return is_numeric($v) && ! str_contains(strtolower($v), 'e') ? $v : (is_numeric($v) ? sprintf('%.8F', (float) $v) : '0');
    }

    public static function round(mixed $v, int $scale = self::SCALE): string
    {
        $v = self::n($v);
        $offset = '0.'.str_repeat('0', $scale).'5';
        $r = bccomp($v, '0', $scale + 6) >= 0 ? bcadd($v, $offset, $scale) : bcsub($v, $offset, $scale);
        if (bccomp($r, '0', $scale) === 0) {
            return bcadd('0', '0', $scale);
        }

        return $r;
    }

    public static function add(mixed ...$values): string
    {
        $t = '0';
        foreach ($values as $v) {
            $t = bcadd($t, self::n($v), 8);
        }

        return self::round($t);
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return self::round(bcsub(self::n($a), self::n($b), 8));
    }

    public static function mul(mixed $a, mixed $b, int $scale = self::SCALE): string
    {
        return self::round(bcmul(self::n($a), self::n($b), 10), $scale);
    }

    public static function div(mixed $a, mixed $b, int $scale = self::SCALE): string
    {
        if (bccomp(self::n($b), '0', 10) === 0) {
            return self::round('0', $scale);
        }

        return self::round(bcdiv(self::n($a), self::n($b), 10), $scale);
    }

    /** amount * rate / 100 */
    public static function percent(mixed $amount, mixed $rate): string
    {
        return self::round(bcdiv(bcmul(self::n($amount), self::n($rate), 10), '100', 10));
    }

    public static function cmp(mixed $a, mixed $b): int
    {
        return bccomp(self::n($a), self::n($b), 6);
    }

    public static function isZero(mixed $v): bool
    {
        return self::cmp($v, 0) === 0;
    }

    public static function isPositive(mixed $v): bool
    {
        return self::cmp($v, 0) > 0;
    }

    public static function neg(mixed $v): string
    {
        return self::round(bcmul(self::n($v), '-1', 8));
    }

    public static function max(mixed $a, mixed $b): string
    {
        return self::cmp($a, $b) >= 0 ? self::round($a) : self::round($b);
    }

    public static function min(mixed $a, mixed $b): string
    {
        return self::cmp($a, $b) <= 0 ? self::round($a) : self::round($b);
    }

    public static function sum(iterable $values): string
    {
        $t = '0';
        foreach ($values as $v) {
            $t = bcadd($t, self::n($v), 8);
        }

        return self::round($t);
    }

    public static function format(mixed $v, bool $withCurrency = true): string
    {
        $company = tenant()->company();
        $decimals = (int) ($company?->currency_decimals ?? config('erp.decimals'));
        $currency = $company?->currency_code ?? config('erp.currency');
        $s = number_format((float) self::round($v, $decimals), $decimals);

        return $withCurrency ? $s.' '.$currency : $s;
    }
}
