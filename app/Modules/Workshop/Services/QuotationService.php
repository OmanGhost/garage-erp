<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Modules\Core\Services\NotificationService;
use App\Modules\Inventory\Models\Part;
use App\Modules\Workshop\Models\Inspection;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\Quotation;
use App\Modules\Workshop\Models\Service;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Settings;
use App\Support\Totals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Quotations with versions, repair options, partial approval and conversion
 * into job card tasks / part requests.
 */
class QuotationService
{
    public function __construct(protected JobCardService $jobCards) {}

    public function create(array $header, array $items): Quotation
    {
        return DB::transaction(function () use ($header, $items) {
            $q = Quotation::create(array_merge([
                'number' => DocumentNumber::next('quotation'),
                'type' => 'workshop', 'version' => 1, 'status' => 'draft',
                'valid_until' => now()->addDays((int) Settings::get('quotation.validity_days', 14))->toDateString(),
                'access_token' => Str::random(48), 'created_by' => auth()->id(),
            ], array_filter($header, fn ($v) => $v !== null)));
            $this->syncItems($q, $items);

            return $q->fresh('items');
        });
    }

    /** Draft from inspection findings (services & parts with vehicle-type pricing). */
    public function fromInspection(Inspection $inspection): Quotation
    {
        $jc = $inspection->jobCard;
        $vehicleType = $jc->vehicle->vehicle_type;
        $items = [];
        foreach ($inspection->findings as $f) {
            if ($f->service_id && ($s = Service::query()->with('vehiclePrices')->find($f->service_id))) {
                $items[] = ['item_type' => 'service', 'service_id' => $s->id, 'description' => $s->displayName().' — '.$f->description,
                    'qty' => 1, 'unit_price' => $s->priceFor($vehicleType), 'tax_rate' => $s->tax_rate];
            }
            if ($f->part_id && ($p = Part::query()->find($f->part_id))) {
                $items[] = ['item_type' => 'part', 'part_id' => $p->id, 'description' => $p->displayName(), 'qty' => $f->qty,
                    'unit_price' => $p->priceFor('workshop'), 'tax_rate' => $p->tax_rate,
                    'part_condition' => in_array($p->part_type, ['used', 'refurbished'], true) ? $p->part_type : 'new'];
            }
            if (! $f->service_id && ! $f->part_id && Money::isPositive($f->estimated_price)) {
                $items[] = ['item_type' => 'labor', 'description' => $f->description, 'qty' => 1, 'unit_price' => $f->estimated_price];
            }
        }

        return $this->create([
            'job_card_id' => $jc->id, 'inspection_id' => $inspection->id, 'customer_id' => $jc->customer_id,
            'vehicle_id' => $jc->vehicle_id, 'branch_id' => $jc->branch_id,
        ], $items);
    }

    /** Supplementary quotation for pending additional work on a job card. */
    public function forAdditionalWork(JobCard $jc): Quotation
    {
        return DB::transaction(function () use ($jc) {
            $tasks = $jc->tasks()->where('approval_status', 'pending')->get();
            $parts = $jc->parts()->where('approval_status', 'pending')->with('part')->get();
            if ($tasks->isEmpty() && $parts->isEmpty()) {
                throw new BusinessException(__('There is no pending additional work.'));
            }
            $items = [];
            foreach ($tasks as $t) {
                $items[] = ['item_type' => 'service', 'service_id' => $t->service_id, 'description' => $t->description, 'qty' => $t->qty,
                    'unit_price' => $t->unit_price, 'discount' => $t->discount, 'tax_rate' => $t->tax_rate, '_task' => $t->id];
            }
            foreach ($parts as $p) {
                $items[] = ['item_type' => 'part', 'part_id' => $p->part_id, 'description' => $p->description, 'qty' => $p->qty,
                    'unit_price' => $p->unit_price, 'discount' => $p->discount, 'tax_rate' => $p->tax_rate, 'part_condition' => $p->part_condition, '_part' => $p->id];
            }
            $q = $this->create(['job_card_id' => $jc->id, 'customer_id' => $jc->customer_id, 'vehicle_id' => $jc->vehicle_id,
                'branch_id' => $jc->branch_id, 'is_supplementary' => true, 'notes' => __('Additional work found during repair')], $items);
            // link back so approval updates the pending items
            foreach ($q->items as $i => $qi) {
                if (isset($items[$i]['_task'])) {
                    $jc->tasks()->whereKey($items[$i]['_task'])->update(['quotation_item_id' => $qi->id]);
                }
                if (isset($items[$i]['_part'])) {
                    $jc->parts()->whereKey($items[$i]['_part'])->update(['quotation_item_id' => $qi->id]);
                }
            }

            return $q;
        });
    }

    public function update(Quotation $q, array $header, array $items): Quotation
    {
        if (! $q->isEditable()) {
            throw new BusinessException(__('Only draft or sent quotations can be edited. Create a revision instead.'));
        }

        return DB::transaction(function () use ($q, $header, $items) {
            $q->fill(array_intersect_key($header, array_flip(['valid_until', 'notes', 'terms'])))->save();
            $q->items()->delete();
            $this->syncItems($q, $items);

            return $q->fresh('items');
        });
    }

    protected function syncItems(Quotation $q, array $items): void
    {
        $vat = tenant()->company()?->vat_rate ?? config('erp.vat_rate');
        foreach (array_values($items) as $sort => $i) {
            $qty = Money::round($i['qty'] ?? 1);
            if (! Money::isPositive($qty) || empty($i['description'])) {
                continue;
            }
            $c = Totals::line($qty, $i['unit_price'] ?? 0, $i['discount'] ?? 0, $i['tax_rate'] ?? $vat);
            $q->items()->create([
                'option_group' => $i['option_group'] ?? null, 'item_type' => $i['item_type'] ?? 'service',
                'service_id' => $i['service_id'] ?? null, 'part_id' => $i['part_id'] ?? null, 'description' => $i['description'],
                'part_condition' => $i['part_condition'] ?? null, 'qty' => $qty, 'unit_price' => Money::round($i['unit_price'] ?? 0),
                'discount' => $c['discount'], 'tax_rate' => $i['tax_rate'] ?? $vat, 'tax_amount' => $c['tax'], 'line_total' => $c['net'],
                'approval_status' => 'pending', 'sort' => $sort,
            ]);
        }
        $this->recalc($q);
    }

    public function recalc(Quotation $q): void
    {
        $items = $q->items()->get();
        // with repair options only the first option counts in the headline total
        $firstGroup = $items->whereNotNull('option_group')->pluck('option_group')->unique()->sort()->first();
        $counted = $items->filter(fn ($i) => $i->option_group === null || $i->option_group === $firstGroup);
        $t = Totals::document($counted->map(fn ($i) => ['qty' => $i->qty, 'unit_price' => $i->unit_price, 'discount' => $i->discount, 'tax_rate' => $i->tax_rate]));
        $q->forceFill(['subtotal' => $t['subtotal'], 'discount_total' => $t['discount_total'], 'tax_total' => $t['tax_total'], 'total' => $t['total']])->save();
    }

    public function send(Quotation $q, array $channels = ['mail', 'sms', 'whatsapp']): void
    {
        if ($q->items()->count() === 0) {
            throw new BusinessException(__('The quotation has no items.'));
        }
        $q->update(['status' => 'sent', 'sent_at' => now()]);
        if ($q->jobCard && in_array($q->jobCard->status, [JobCard::PENDING_INSPECTION, JobCard::UNDER_INSPECTION, JobCard::AWAITING_QUOTATION], true)) {
            $this->jobCards->changeStatus($q->jobCard, JobCard::AWAITING_APPROVAL, __('Quotation sent').' '.$q->number, true);
        }
        app(NotificationService::class)->notifyCustomer($q->customer, 'quotation.sent', [
            'number' => $q->number, 'total' => money($q->total), 'link' => route('public.quotation', $q->access_token),
            'valid_until' => $q->valid_until?->format('Y-m-d'),
        ], $channels, $q);
    }

    /**
     * Customer decision. $decisions = [quotation_item_id => 'approved'|'rejected'];
     * items not listed are rejected. Returns the updated quotation.
     */
    public function respond(Quotation $q, array $decisions, string $by, string $channel = 'in_person', ?string $notes = null, ?string $signaturePath = null): Quotation
    {
        return DB::transaction(function () use ($q, $decisions, $by, $channel, $notes, $signaturePath) {
            $q = Quotation::query()->whereKey($q->id)->lockForUpdate()->firstOrFail();
            if (! in_array($q->status, ['draft', 'sent'], true)) {
                throw new BusinessException(__('This quotation has already been answered.'));
            }
            if ($q->isExpired()) {
                $q->update(['status' => 'expired']);
                throw new BusinessException(__('This quotation has expired.'));
            }
            $approvedTotal = '0';
            $approved = 0;
            $items = $q->items()->get();
            foreach ($items as $item) {
                $d = ($decisions[$item->id] ?? 'rejected') === 'approved' ? 'approved' : 'rejected';
                $item->update(['approval_status' => $d]);
                if ($d === 'approved') {
                    $approved++;
                    $approvedTotal = Money::add($approvedTotal, $item->line_total, $item->tax_amount);
                }
            }
            $status = $approved === 0 ? 'rejected' : ($approved === $items->count() ? 'approved' : 'partially_approved');
            $q->update([
                'status' => $status, 'approved_total' => $approvedTotal, 'responded_at' => now(), 'response_by' => $by,
                'response_channel' => $channel, 'response_notes' => $notes, 'signature_path' => $signaturePath ?? $q->signature_path,
            ]);
            AuditLogger::log('quotation.responded', $q, ['status' => $status, 'by' => $by, 'channel' => $channel]);

            if ($q->job_card_id && $status !== 'rejected') {
                $q->is_supplementary ? $this->applySupplementary($q) : $this->convertToJobCard($q);
            } elseif ($q->job_card_id && $q->is_supplementary) {
                $this->applySupplementary($q);
            }

            return $q;
        });
    }

    /** Copy approved items to the job card as tasks / part requests and approve the job. */
    public function convertToJobCard(Quotation $q): JobCard
    {
        $jc = $q->jobCard;
        foreach ($q->items()->where('approval_status', 'approved')->get() as $item) {
            if ($item->item_type === 'part' && $item->part_id) {
                if (! $jc->parts()->where('quotation_item_id', $item->id)->exists()) {
                    $this->jobCards->requestPart($jc, ['part_id' => $item->part_id, 'qty' => $item->qty, 'unit_price' => $item->unit_price,
                        'discount' => $item->discount, 'tax_rate' => $item->tax_rate, 'description' => $item->description,
                        'part_condition' => $item->part_condition, 'quotation_item_id' => $item->id]);
                }
            } elseif (! $jc->tasks()->where('quotation_item_id', $item->id)->exists()) {
                $this->jobCards->addTask($jc, ['service_id' => $item->service_id, 'description' => $item->description, 'qty' => $item->qty,
                    'unit_price' => $item->unit_price, 'discount' => $item->discount, 'tax_rate' => $item->tax_rate, 'quotation_item_id' => $item->id]);
            }
        }
        $jc->forceFill(['approved_total' => Money::add($jc->approved_total, $q->approved_total)])->save();
        if (in_array($jc->fresh()->status, [JobCard::PENDING_INSPECTION, JobCard::UNDER_INSPECTION, JobCard::AWAITING_QUOTATION, JobCard::AWAITING_APPROVAL], true)) {
            $this->jobCards->changeStatus($jc->fresh(), JobCard::APPROVED, __('Quotation approved').' '.$q->number, true);
        }
        $q->update(['status' => $q->status === 'approved' ? 'approved' : $q->status]);

        return $jc->fresh();
    }

    protected function applySupplementary(Quotation $q): void
    {
        $jc = $q->jobCard;
        $taskDecisions = $partDecisions = [];
        foreach ($q->items as $item) {
            foreach ($jc->tasks()->where('quotation_item_id', $item->id)->where('approval_status', 'pending')->pluck('id') as $tid) {
                $taskDecisions[$tid] = $item->approval_status;
            }
            foreach ($jc->parts()->where('quotation_item_id', $item->id)->where('approval_status', 'pending')->pluck('id') as $pid) {
                $partDecisions[$pid] = $item->approval_status;
            }
        }
        $this->jobCards->decideAdditional($jc, $taskDecisions, $partDecisions, $q->response_by ?? '-', $q->response_channel ?? 'portal');
        $jc->forceFill(['approved_total' => Money::add($jc->approved_total, $q->approved_total)])->save();
    }

    /** New version: copies items, supersedes the previous version. */
    public function revise(Quotation $q): Quotation
    {
        return DB::transaction(function () use ($q) {
            if (in_array($q->status, ['converted'], true)) {
                throw new BusinessException(__('Converted quotations cannot be revised.'));
            }
            $root = $q->parent_id ?: $q->id;
            $version = (int) Quotation::query()->where(fn ($w) => $w->where('id', $root)->orWhere('parent_id', $root))->max('version') + 1;
            $new = $q->replicate(['access_token', 'status', 'sent_at', 'responded_at', 'response_by', 'response_channel', 'response_notes', 'approved_total', 'signature_path']);
            $new->fill(['version' => $version, 'parent_id' => $root, 'status' => 'draft', 'access_token' => Str::random(48), 'created_by' => auth()->id(),
                'valid_until' => now()->addDays((int) Settings::get('quotation.validity_days', 14))->toDateString(), 'approved_total' => 0]);
            $new->save();
            foreach ($q->items as $item) {
                $new->items()->create($item->replicate(['quotation_id'])->fill(['approval_status' => 'pending'])->toArray());
            }
            if (in_array($q->status, ['draft', 'sent', 'rejected', 'expired'], true)) {
                $q->update(['status' => 'superseded']);
            }
            $this->recalc($new);

            return $new->fresh('items');
        });
    }

    public function expireOverdue(): int
    {
        return Quotation::query()->whereIn('status', ['draft', 'sent'])->whereDate('valid_until', '<', now())->update(['status' => 'expired']);
    }
}
