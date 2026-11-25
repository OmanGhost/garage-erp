<?php

namespace App\Modules\Fleet\Services;

use App\Exceptions\BusinessException;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetApproval;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Workshop\Models\JobCard;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fleet contracts: spend limits, administrative approvals and
 * consolidated monthly invoicing.
 */
class FleetService
{
    public function monthSpend(Fleet $fleet, ?Carbon $month = null): string
    {
        $month ??= now();

        return Money::sum(Invoice::query()->where('fleet_id', $fleet->id)->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->whereBetween('issue_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->pluck('total'));
    }

    /** Called before a fleet job card is approved. */
    public function assertJobAllowed(JobCard $jc): void
    {
        $fleet = $jc->fleet;
        if (! $fleet || $fleet->status !== 'active') {
            throw new BusinessException(__('The fleet contract is not active.'));
        }
        if ($fleet->contract_end && $fleet->contract_end->isPast()) {
            throw new BusinessException(__('The fleet maintenance contract has expired.'));
        }
        $amount = $jc->estimated_total;
        $needsApproval = $fleet->requires_approval
            || ($fleet->per_job_limit && Money::cmp($amount, $fleet->per_job_limit) > 0)
            || ($fleet->monthly_spend_limit && Money::cmp(Money::add($this->monthSpend($fleet), $amount), $fleet->monthly_spend_limit) > 0);
        if (! $needsApproval) {
            return;
        }
        $approval = FleetApproval::query()->where('job_card_id', $jc->id)->latest('id')->first();
        if ($approval?->status === 'approved' && Money::cmp($amount, $approval->amount) <= 0) {
            return;
        }
        if (! $approval || $approval->status !== 'pending') {
            FleetApproval::create(['fleet_id' => $fleet->id, 'job_card_id' => $jc->id, 'amount' => $amount, 'status' => 'pending',
                'requested_at' => now(), 'token' => Str::random(48)]);
        }
        throw new BusinessException(__('Fleet administrative approval is required (limit exceeded or approval mandatory). An approval request was created.'));
    }

    public function decide(FleetApproval $a, bool $approve, string $by, ?string $notes = null): void
    {
        $a->update(['status' => $approve ? 'approved' : 'rejected', 'responded_at' => now(), 'approver_name' => $by, 'notes' => $notes]);
    }

    /** One consolidated invoice for all delivered, uninvoiced fleet jobs in a month. */
    public function monthlyInvoice(Fleet $fleet, Carbon $month): Invoice
    {
        return DB::transaction(function () use ($fleet, $month) {
            $jobs = JobCard::query()->where('fleet_id', $fleet->id)->whereNull('invoice_id')
                ->whereIn('status', [JobCard::READY, JobCard::DELIVERED, JobCard::COMPLETED])
                ->whereBetween('completed_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->get();
            if ($jobs->isEmpty()) {
                throw new BusinessException(__('No uninvoiced fleet jobs for this month.'));
            }
            $items = [];
            foreach ($jobs as $jc) {
                foreach ($jc->tasks()->where('approval_status', 'approved')->where('status', '!=', 'cancelled')->get() as $t) {
                    $items[] = ['item_type' => 'service', 'service_id' => $t->service_id, 'job_card_task_id' => $t->id,
                        'description' => $jc->number.' / '.$jc->vehicle->plate().' — '.$t->description, 'qty' => $t->qty,
                        'unit_price' => $t->unit_price, 'discount' => Money::add($t->discount, Money::percent(Money::mul($t->qty, $t->unit_price), $fleet->discount_percent)),
                        'tax_rate' => $t->tax_rate];
                }
                foreach ($jc->parts()->where('approval_status', 'approved')->get() as $p) {
                    if (! Money::isPositive($p->netQty())) {
                        continue;
                    }
                    $items[] = ['item_type' => 'part', 'part_id' => $p->part_id, 'job_card_part_id' => $p->id, 'warehouse_id' => $p->warehouse_id,
                        'description' => $jc->number.' / '.$jc->vehicle->plate().' — '.$p->description, 'qty' => $p->netQty(),
                        'unit_price' => $p->unit_price, 'discount' => Money::percent(Money::mul($p->netQty(), $p->unit_price), $fleet->discount_percent),
                        'tax_rate' => $p->tax_rate, 'unit_cost' => $p->unit_cost];
                }
            }
            $service = app(InvoiceService::class);
            $invoice = $service->createDraft([
                'type' => 'fleet', 'customer_id' => $fleet->customer_id, 'fleet_id' => $fleet->id, 'payment_terms' => 'credit',
                'due_date' => now()->addDays($fleet->customer->payment_terms_days ?: 30)->toDateString(),
                'notes' => __('Consolidated fleet invoice').' '.$month->format('Y-m'),
            ], $items);
            $invoice = $service->post($invoice, true);
            JobCard::query()->whereIn('id', $jobs->pluck('id'))->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    /** Per-vehicle maintenance cost for a fleet. */
    public function vehicleCosts(Fleet $fleet, ?string $from = null, ?string $to = null)
    {
        return DB::table('vehicles as v')->leftJoin('job_cards as j', function ($j) use ($from, $to) {
            $j->on('j.vehicle_id', '=', 'v.id')->whereNull('j.deleted_at')->where('j.status', '!=', 'cancelled');
            if ($from) {
                $j->where('j.received_at', '>=', $from);
            }
            if ($to) {
                $j->where('j.received_at', '<=', $to.' 23:59:59');
            }
        })->where('v.fleet_id', $fleet->id)->groupBy('v.id', 'v.plate_number', 'v.plate_code', 'v.odometer')
            ->selectRaw('v.id, v.plate_number, v.plate_code, v.odometer, COUNT(j.id) jobs, COALESCE(SUM(j.revenue),0) revenue, COALESCE(SUM(j.total_cost),0) cost')
            ->get();
    }
}
