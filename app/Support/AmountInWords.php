<?php

namespace App\Support;

/**
 * Amounts written in words for vouchers and cheques (Omani rial: 1 rial = 1000 baisa).
 *
 *   AmountInWords::arabic('1235.500')  => "فقط ألف ومائتان وخمسة وثلاثون ريالاً عمانياً وخمسمائة بيسة لا غير"
 *   AmountInWords::english('1235.500') => "One thousand two hundred thirty-five Omani Rials and five hundred Baisa only"
 */
final class AmountInWords
{
    protected const AR_ONES = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة',
        'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];

    protected const AR_TENS = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    protected const AR_HUNDREDS = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    /** scale => [one, two, plural (3-10), accusative singular (11+)] */
    protected const AR_SCALES = [
        1_000_000_000 => ['مليار', 'ملياران', 'مليارات', 'ملياراً'],
        1_000_000 => ['مليون', 'مليونان', 'ملايين', 'مليوناً'],
        1_000 => ['ألف', 'ألفان', 'آلاف', 'ألفاً'],
    ];

    protected const EN_ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    protected const EN_TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    /** Split an amount into [rials, baisa] using exact decimal math. */
    public static function split(mixed $amount, int $decimals = 3): array
    {
        $v = Money::round($amount, $decimals);
        $negative = str_starts_with($v, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($v, '-')), 2, '0');

        return [(int) $int, (int) str_pad(substr($frac, 0, $decimals), $decimals, '0'), $negative];
    }

    // ------------------------------------------------------------------ Arabic

    public static function arabic(mixed $amount, string $currency = 'ريال عماني', string $fraction = 'بيسة'): string
    {
        [$rials, $baisa] = self::split($amount);
        $parts = [];
        if ($rials > 0) {
            // Arabic: one and two are expressed by the noun itself ("ريال عماني واحد"، "ريالان عمانيان")
            $parts[] = match (true) {
                $rials === 1 && $currency === 'ريال عماني' => 'ريال عماني واحد',
                $rials === 2 && $currency === 'ريال عماني' => 'ريالان عمانيان',
                default => self::arabicNumber($rials).' '.self::arabicCurrency($rials, $currency),
            };
        }
        if ($baisa > 0) {
            $parts[] = self::arabicNumber($baisa).' '.$fraction;
        }

        return 'فقط '.($parts ? implode(' و', $parts) : 'صفر '.$currency).' لا غير';
    }

    /** "ريال عماني" agrees with the counted number (ريالان، ريالات، ريالاً). */
    protected static function arabicCurrency(int $n, string $currency): string
    {
        if ($currency !== 'ريال عماني') {
            return $currency;
        }
        $last = $n % 100;

        return match (true) {
            $n === 2 => 'ريالان عمانيان',
            $last >= 3 && $last <= 10 && $n < 100 => 'ريالات عمانية',
            $last >= 11 && $last <= 99 => 'ريالاً عمانياً',
            default => 'ريال عماني',
        };
    }

    public static function arabicNumber(int $n): string
    {
        if ($n === 0) {
            return 'صفر';
        }
        $words = [];
        foreach (self::AR_SCALES as $scale => [$one, $two, $plural, $acc]) {
            if ($n >= $scale) {
                $count = intdiv($n, $scale);
                $n %= $scale;
                $words[] = match (true) {
                    $count === 1 => $one,
                    $count === 2 => $two,
                    $count <= 10 => self::arabicBelowThousand($count).' '.$plural,
                    default => self::arabicBelowThousand($count).' '.($count % 100 === 0 ? $one : $acc),
                };
            }
        }
        if ($n > 0) {
            $words[] = self::arabicBelowThousand($n);
        }

        return implode(' و', $words);
    }

    protected static function arabicBelowThousand(int $n): string
    {
        $words = [];
        if ($n >= 100) {
            $words[] = self::AR_HUNDREDS[intdiv($n, 100)];
            $n %= 100;
        }
        if ($n > 0) {
            if ($n < 20) {
                $words[] = self::AR_ONES[$n];
            } else {
                $ones = $n % 10;
                $words[] = ($ones ? self::AR_ONES[$ones].' و' : '').self::AR_TENS[intdiv($n, 10)];
            }
        }

        return implode(' و', $words);
    }

    // ----------------------------------------------------------------- English

    public static function english(mixed $amount, string $currency = 'Omani Rials', string $fraction = 'Baisa'): string
    {
        [$rials, $baisa] = self::split($amount);
        $parts = [];
        if ($rials > 0) {
            $parts[] = self::englishNumber($rials).' '.($rials === 1 ? 'Omani Rial' : $currency);
        }
        if ($baisa > 0) {
            $parts[] = self::englishNumber($baisa).' '.$fraction;
        }

        return ucfirst(($parts ? implode(' and ', $parts) : 'zero '.$currency).' only');
    }

    public static function englishNumber(int $n): string
    {
        if ($n === 0) {
            return 'zero';
        }
        $words = [];
        foreach ([1_000_000_000 => 'billion', 1_000_000 => 'million', 1_000 => 'thousand'] as $scale => $name) {
            if ($n >= $scale) {
                $words[] = self::englishBelowThousand(intdiv($n, $scale)).' '.$name;
                $n %= $scale;
            }
        }
        if ($n > 0) {
            $words[] = self::englishBelowThousand($n);
        }

        return implode(' ', $words);
    }

    protected static function englishBelowThousand(int $n): string
    {
        $words = [];
        if ($n >= 100) {
            $words[] = self::EN_ONES[intdiv($n, 100)].' hundred';
            $n %= 100;
        }
        if ($n > 0) {
            $words[] = $n < 20 ? self::EN_ONES[$n] : self::EN_TENS[intdiv($n, 10)].($n % 10 ? '-'.self::EN_ONES[$n % 10] : '');
        }

        return implode(' ', $words);
    }
}
