<?php

namespace App\Support;

/**
 * Line & document totals. Prices are tax-exclusive; discount is an amount per line.
 * line_total = qty * unit_price - discount ; tax = line_total * rate%
 */
final class Totals
{
    /** @return array{gross:string,discount:string,net:string,tax:string,total:string} */
    public static function line(mixed $qty, mixed $unitPrice, mixed $discount = 0, mixed $taxRate = 0): array
    {
        $gross = Money::mul($qty, $unitPrice);
        $discount = Money::min(Money::max(0, $discount), $gross);
        $net = Money::sub($gross, $discount);
        $tax = Money::percent($net, $taxRate);

        return ['gross' => $gross, 'discount' => $discount, 'net' => $net, 'tax' => $tax, 'total' => Money::add($net, $tax)];
    }

    /**
     * @param  iterable<array{qty:mixed,unit_price:mixed,discount?:mixed,tax_rate?:mixed}>  $lines
     * @return array{subtotal:string,discount_total:string,tax_total:string,total:string,lines:array}
     */
    public static function document(iterable $lines): array
    {
        $sub = $disc = $tax = '0';
        $out = [];
        foreach ($lines as $k => $l) {
            $c = self::line($l['qty'] ?? 1, $l['unit_price'] ?? 0, $l['discount'] ?? 0, $l['tax_rate'] ?? 0);
            $sub = Money::add($sub, $c['gross']);
            $disc = Money::add($disc, $c['discount']);
            $tax = Money::add($tax, $c['tax']);
            $out[$k] = $c;
        }
        $net = Money::sub($sub, $disc);

        return ['subtotal' => $sub, 'discount_total' => $disc, 'tax_total' => $tax, 'total' => Money::add($net, $tax), 'lines' => $out];
    }

    /** Recalculate and persist totals on a document model with an items() relation. */
    public static function apply($document, string $relation = 'items'): void
    {
        $items = $document->{$relation}()->get();
        foreach ($items as $item) {
            $c = self::line($item->qty, $item->unit_price ?? $item->unit_cost, $item->discount ?? 0, $item->tax_rate ?? 0);
            $item->forceFill(['tax_amount' => $c['tax'], 'line_total' => $c['net']])->saveQuietly();
        }
        $t = self::document($items->map(fn ($i) => [
            'qty' => $i->qty, 'unit_price' => $i->unit_price ?? $i->unit_cost, 'discount' => $i->discount ?? 0, 'tax_rate' => $i->tax_rate ?? 0,
        ]));
        $document->forceFill([
            'subtotal' => $t['subtotal'],
            'discount_total' => $t['discount_total'],
            'tax_total' => $t['tax_total'],
            'total' => $t['total'],
        ])->save();
    }
}
