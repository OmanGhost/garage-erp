<?php

namespace App\Modules\Warranty\Services;

use App\Exceptions\BusinessException;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Warranty\Models\Warranty;
use App\Modules\Warranty\Models\WarrantyClaim;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Services\JobCardService;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Warranties are created automatically from posted invoices (parts & services
 * with a warranty period). Claims are validated by date and mileage, and can
 * spawn a zero-priced rework job card whose cost is tracked as warranty cost.
 */
class WarrantyService
{
    public function createFromInvoice(Invoice $invoice): void
    {
        if (Warranty::query()->where('invoice_id', $invoice->id)->exists()) {
            return;
        }
        $jc = $invoice->job_card_id ? JobCard::query()->find($invoice->job_card_id) : null;
        foreach ($invoice->items as $item) {
            if ((int) $item->warranty_days <= 0) {
                continue;
            }
            $serial = null;
            if ($item->part_id && $item->part?->track_serial) {
                $serial = \App\Modules\Inventory\Models\StockMovement::query()->where('reference_type', $invoice->getMorphClass())
                    ->where('reference_id', $invoice->id)->where('part_id', $item->part_id)->first()?->batch?->serial_number;
            }
            Warranty::create([
                'invoice_id' => $invoice->id, 'invoice_item_id' => $item->id, 'job_card_id' => $jc?->id,
                'customer_id' => $invoice->customer_id, 'vehicle_id' => $jc?->vehicle_id,
                'part_id' => $item->part_id, 'service_id' => $item->service_id, 'description' => $item->description,
                'start_date' => $invoice->issue_date, 'end_date' => $invoice->issue_date->copy()->addDays((int) $item->warranty_days),
                'start_odometer' => $jc?->odometer_out ?? $jc?->odometer_in, 'km_limit' => $item->warranty_km ?: null,
                'status' => 'active', 'supplier_id' => $item->part?->default_supplier_id, 'serial_number' => $serial,
            ]);
        }
    }

    public function openClaim(Warranty $warranty, array $data): WarrantyClaim
    {
        $odo = $data['odometer'] ?? null;
        if (! $warranty->isValid($odo !== null ? (int) $odo : null, $data['claim_date'] ?? null)) {
            throw new BusinessException(__('This warranty is expired or exceeded its mileage limit.'));
        }

        return WarrantyClaim::create($data + [
            'warranty_id' => $warranty->id, 'number' => DocumentNumber::next('warranty_claim'),
            'claim_date' => now()->toDateString(), 'status' => 'open', 'created_by' => auth()->id(),
        ]);
    }

    /** Approve a claim and create a rework job card (zero priced). */
    public function approveWithRework(WarrantyClaim $claim, array $resolution = []): JobCard
    {
        return DB::transaction(function () use ($claim, $resolution) {
            $w = $claim->warranty;
            if (! $w->vehicle_id) {
                throw new BusinessException(__('A vehicle is required to create a rework job card.'));
            }
            $jc = app(JobCardService::class)->create([
                'customer_id' => $w->customer_id, 'vehicle_id' => $w->vehicle_id, 'job_type' => 'warranty',
                'customer_complaint' => __('Warranty claim').' '.$claim->number.': '.$claim->description,
                'warranty_claim_id' => $claim->id, 'priority' => 'high',
            ]);
            $claim->update(['status' => 'approved', 'resolution' => $resolution['resolution'] ?? 'repair', 'rework_job_card_id' => $jc->id,
                'inspection_result' => $resolution['inspection_result'] ?? $claim->inspection_result]);
            $w->update(['status' => 'claimed']);

            return $jc;
        });
    }

    /** Close the claim with the actual cost of the rework job card. */
    public function complete(WarrantyClaim $claim): void
    {
        $cost = $claim->reworkJobCard?->total_cost ?? $claim->cost;
        $claim->update(['status' => 'completed', 'cost' => Money::round($cost)]);
    }

    public function expireOld(): int
    {
        return Warranty::query()->where('status', 'active')->whereDate('end_date', '<', now())->update(['status' => 'expired']);
    }
}
