<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Part;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Everything a salesperson needs to decide on a part, in one call: identification, prices for every level (and
 * what this customer paid last time), stock per warehouse with bin location, what is reserved and on order, the
 * interchangeable alternatives with their stock, vehicle fitment, how fast it sells and — for users allowed to see
 * costs — cost and margin.
 */
class PartInquiry
{
    public function __construct(protected CrossReferenceService $xref) {}

    public function for(Part $part, ?int $warehouseId = null, ?int $customerId = null, string $priceLevel = 'retail', bool $withCost = false): array
    {
        $part->loadMissing('brand', 'category', 'supplier');

        // stock per warehouse with bin location
        $stock = DB::table('stock_levels as s')->join('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->leftJoin('warehouse_locations as l', 'l.id', '=', 's.location_id')
            ->where('s.part_id', $part->id)->where('s.company_id', tenant()->id())
            ->orderByRaw('CASE WHEN s.warehouse_id = ? THEN 0 ELSE 1 END', [$warehouseId ?? 0])->orderBy('w.name')
            ->get(['s.warehouse_id', 'w.name as warehouse', 'l.code as location', 's.qty_on_hand', 's.qty_reserved', 's.last_movement_at'])
            ->map(fn ($r) => ['warehouse_id' => (int) $r->warehouse_id, 'warehouse' => $r->warehouse, 'location' => $r->location,
                'on_hand' => (string) $r->qty_on_hand, 'reserved' => (string) $r->qty_reserved,
                'available' => Money::max('0', Money::sub($r->qty_on_hand, $r->qty_reserved)), 'here' => (int) $r->warehouse_id === (int) $warehouseId,
                'last_movement' => $r->last_movement_at])->all();
        $here = collect($stock)->firstWhere('here', true);
        $totalAvailable = Money::sum(array_column($stock, 'available'));

        // on order from suppliers
        $incoming = DB::table('purchase_order_items as i')->join('purchase_orders as o', 'o.id', '=', 'i.purchase_order_id')
            ->where('i.part_id', $part->id)->where('o.company_id', tenant()->id())->whereIn('o.status', ['approved', 'sent', 'partially_received'])
            ->whereColumn('i.received_qty', '<', 'i.qty')->orderBy('o.expected_date')
            ->get(['o.number', 'o.expected_date', DB::raw('i.qty - i.received_qty as qty')])
            ->map(fn ($r) => ['number' => $r->number, 'expected' => $r->expected_date, 'qty' => (string) $r->qty])->all();

        // sales velocity (posted invoices)
        $sold = fn (int $days) => (string) DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->where('ii.part_id', $part->id)->where('i.company_id', tenant()->id())->whereIn('i.status', ['posted', 'partially_paid', 'paid'])
            ->where('i.issue_date', '>=', now()->subDays($days)->toDateString())->sum('ii.qty');
        $lastSale = DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->where('ii.part_id', $part->id)->where('i.company_id', tenant()->id())->whereIn('i.status', ['posted', 'partially_paid', 'paid'])
            ->max('i.issue_date');
        $customerLast = $customerId ? DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->where('ii.part_id', $part->id)->where('i.customer_id', $customerId)->where('i.company_id', tenant()->id())
            ->whereIn('i.status', ['posted', 'partially_paid', 'paid'])->orderByDesc('i.issue_date')->orderByDesc('ii.id')
            ->first(['i.issue_date', 'i.number', 'ii.unit_price', 'ii.discount', 'ii.qty']) : null;
        $sold90 = $sold(90);

        // interchangeable parts with their stock and price
        $alternatives = $this->xref->group($part)->reject(fn ($p) => $p->id === $part->id)->take(12)->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->displayName(), 'sku' => $p->sku, 'oem' => $p->oem_number, 'brand' => $p->brand?->name,
            'type' => $p->part_type, 'grade' => $p->condition_grade, 'price' => $p->priceFor($priceLevel),
            'available' => Money::max('0', Money::sub($p->on_hand, $p->reserved)), 'active' => (bool) $p->is_active,
        ])->values()->all();

        $fits = $part->compatibilities()->with('make', 'vehicleModel')->limit(30)->get()->map(fn ($c) => [
            'vehicle' => trim(($c->make?->name ?? '').' '.($c->vehicleModel?->name ?? '')) ?: '—',
            'years' => $c->year_from || $c->year_to ? ($c->year_from ?: '…').'–'.($c->year_to ?: '…') : null, 'engine' => $c->engine, 'notes' => $c->notes,
        ])->all();

        $price = $part->priceFor($priceLevel);
        $cost = null;
        if ($withCost) {
            $avg = Money::isPositive($part->average_cost) ? (string) $part->average_cost : (string) $part->purchase_price;
            $lastBuy = DB::table('part_supplier as ps')->join('suppliers as s', 's.id', '=', 'ps.supplier_id')->where('ps.part_id', $part->id)
                ->orderByDesc('ps.updated_at')->first(['s.name', 'ps.last_price', 'ps.lead_days', 'ps.updated_at']);
            $cost = ['average' => $avg, 'margin' => Money::isPositive($price) ? round((1 - (float) $avg / (float) $price) * 100, 1) : null,
                'last_supplier' => $lastBuy?->name, 'last_price' => $lastBuy ? (string) $lastBuy->last_price : null, 'lead_days' => $lastBuy?->lead_days];
        }

        return [
            'part' => $part,
            'price' => $price,
            'prices' => ['retail' => $part->priceFor('retail'), 'wholesale' => $part->priceFor('wholesale'), 'workshop' => $part->priceFor('workshop'),
                'min' => (string) $part->min_sale_price, 'tax_rate' => (string) $part->tax_rate],
            'customer_last' => $customerLast ? ['date' => $customerLast->issue_date, 'number' => $customerLast->number,
                'price' => (string) $customerLast->unit_price, 'qty' => (string) $customerLast->qty,
                'discount' => (string) $customerLast->discount] : null,
            'stock' => $stock,
            'here' => $here,
            'total_available' => $totalAvailable,
            'low' => Money::isPositive($part->min_stock) && Money::cmp($totalAvailable, $part->min_stock) <= 0,
            'reserved' => Money::sum(array_column($stock, 'reserved')),
            'incoming' => $incoming,
            'sales' => ['30' => $sold(30), '90' => $sold90, 'last' => $lastSale,
                'per_month' => round((float) $sold90 / 3, 1),
                'cover_days' => (float) $sold90 > 0 ? (int) round((float) $totalAvailable / ((float) $sold90 / 90)) : null],
            'alternatives' => $alternatives,
            'fits' => $fits,
            'cost' => $cost,
        ];
    }
}
