<?php

namespace App\Modules\Workshop\Services;

use App\Modules\Workshop\Models\JobCard;
use App\Support\Money;

/**
 * Actual job costing & profitability: labor (technician time x hourly cost)
 * + parts (issued net of returns at cost) vs. net invoiced revenue.
 */
class JobCostingService
{
    public function recalculate(JobCard $jc): JobCard
    {
        $labor = Money::sum($jc->tasks()->where('status', '!=', 'cancelled')->pluck('labor_cost'));
        $parts = '0';
        foreach ($jc->parts()->get() as $p) {
            $parts = Money::add($parts, Money::mul($p->netQty(), $p->unit_cost));
        }
        $revenue = '0';
        foreach ($jc->invoices()->whereIn('status', ['posted', 'partially_paid', 'paid'])->with('creditNotes')->get() as $inv) {
            $net = Money::sub($inv->total, $inv->tax_total);
            $credited = Money::sum($inv->creditNotes->where('status', 'posted')->pluck('subtotal'));
            $revenue = Money::add($revenue, Money::sub($net, $credited));
        }
        $estimated = '0';
        foreach ($jc->tasks()->where('status', '!=', 'cancelled')->where('approval_status', '!=', 'rejected')->get() as $t) {
            $estimated = Money::add($estimated, $t->line_total, Money::percent($t->line_total, $t->tax_rate));
        }
        foreach ($jc->parts()->where('status', '!=', 'cancelled')->where('approval_status', '!=', 'rejected')->get() as $p) {
            $net = Money::sub(Money::mul($p->qty, $p->unit_price), $p->discount);
            $estimated = Money::add($estimated, $net, Money::percent($net, $p->tax_rate));
        }
        $total = Money::add($labor, $parts);
        $jc->forceFill([
            'labor_cost' => $labor, 'parts_cost' => $parts, 'total_cost' => $total,
            'revenue' => $revenue, 'profit' => Money::sub($revenue, $total), 'estimated_total' => $estimated,
        ])->saveQuietly();

        return $jc;
    }
}
