<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Fleet\Services\FleetService;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Events\JobCardStatusChanged;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\JobCardPart;
use App\Modules\Workshop\Models\JobCardTask;
use App\Modules\Workshop\Models\JobCardTimeLog;
use App\Modules\Workshop\Models\QualityCheck;
use App\Modules\Workshop\Models\Service;
use App\Modules\Workshop\Models\VehicleGateLog;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Settings;
use App\Support\Totals;
use Illuminate\Support\Facades\DB;

class JobCardService
{
    public function __construct(
        protected StockService $stock,
        protected PostingService $posting,
        protected JobCostingService $costing,
    ) {}

    public function create(array $data): JobCard
    {
        return DB::transaction(function () use ($data) {
            $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
            $jc = JobCard::create(array_merge([
                'number' => DocumentNumber::next('job_card'),
                'status' => JobCard::PENDING_INSPECTION,
                'priority' => 'normal',
                'job_type' => $vehicle->fleet_id ? 'fleet' : 'repair',
                'fleet_id' => $vehicle->fleet_id,
                'received_at' => now(),
                'odometer_in' => $vehicle->odometer,
                'service_advisor_id' => auth()->id(),
                'created_by' => auth()->id(),
            ], array_filter($data, fn ($v) => $v !== null)));
            $jc->histories()->create(['to_status' => $jc->status, 'user_id' => auth()->id(), 'notes' => __('Job card opened'), 'created_at' => now()]);

            return $jc;
        });
    }

    // ------------------------------------------------------------ workflow

    /**
     * @param  array{receiver?: string, receiver_name?: ?string, receiver_id_number?: ?string, handover_authorization_id?: ?int}  $handover
     *         who collects the vehicle when moving to "delivered": the owner (default) or a person holding an active pick-up authorization
     */
    public function changeStatus(JobCard $jc, string $to, ?string $notes = null, bool $force = false, array $handover = []): JobCard
    {
        return DB::transaction(function () use ($jc, $to, $notes, $force, $handover) {
            $jc = JobCard::query()->whereKey($jc->id)->lockForUpdate()->firstOrFail();
            $from = $jc->status;
            if ($from === $to) {
                return $jc;
            }
            $override = $force || (auth()->user()?->can('job_cards.override_workflow') ?? false);
            if (! JobCardWorkflow::can($from, $to) && ! ($override && ! in_array($from, [JobCard::COMPLETED, JobCard::CANCELLED], true))) {
                throw new BusinessException(__('Cannot move job card from :a to :b.', ['a' => status_label($from), 'b' => status_label($to)]));
            }
            // Override skips workflow/QC rules only; financial guards (invoice & payment
            // before delivery, nothing issued/invoiced on cancellation) always apply.
            if (! $override || in_array($to, [JobCard::DELIVERED, JobCard::CANCELLED], true)) {
                $this->guard($jc, $to);
            }
            // the customer's part is physically out with a subcontractor: no override
            if (in_array($to, [JobCard::READY, JobCard::DELIVERED], true) && $jc->subcontracts()->where('status', 'sent_out')->exists()) {
                throw new BusinessException(__('Outside work is still with the subcontractor. Receive it back first.'));
            }

            $jc->status = $to;
            match ($to) {
                JobCard::IN_PROGRESS => $jc->started_at ??= now(),
                JobCard::READY => $jc->completed_at = now(),
                JobCard::DELIVERED => $this->deliver($jc, $handover),
                JobCard::COMPLETED => $jc->closed_at = now(),
                JobCard::CANCELLED => $this->cancelEffects($jc, $notes),
                default => null,
            };
            $jc->save();
            $jc->histories()->create(['from_status' => $from, 'to_status' => $to, 'user_id' => auth()->id(), 'notes' => $notes, 'created_at' => now()]);
            AuditLogger::log('job_card.status', $jc, ['from' => $from, 'to' => $to, 'override' => $override && ! JobCardWorkflow::can($from, $to)]);
            DB::afterCommit(fn () => event(new JobCardStatusChanged($jc, $from, $to)));

            return $jc;
        });
    }

    /** Business rules per target status. */
    public function guard(JobCard $jc, string $to): void
    {
        switch ($to) {
            case JobCard::APPROVED:
                $needsQuote = Settings::bool('workshop.require_quotation_approval');
                $hasApprovedQuote = $jc->quotations()->whereIn('status', ['approved', 'partially_approved', 'converted'])->exists();
                if ($needsQuote && ! $hasApprovedQuote && ! auth()->user()?->can('job_cards.approve')) {
                    throw new BusinessException(__('Customer approval of a quotation is required before approving the job.'));
                }
                if ($jc->fleet_id) {
                    app(FleetService::class)->assertJobAllowed($jc);
                }
                break;
            case JobCard::IN_PROGRESS:
                if (! $jc->tasks()->where('approval_status', 'approved')->where('status', '!=', 'cancelled')->exists()) {
                    throw new BusinessException(__('Add at least one approved task before starting work.'));
                }
                break;
            case JobCard::QUALITY_INSPECTION:
            case JobCard::READY:
                $open = $jc->tasks()->where('approval_status', 'approved')->whereNotIn('status', ['done', 'cancelled'])->count();
                if ($open > 0) {
                    throw new BusinessException(__(':n task(s) are not finished yet.', ['n' => $open]));
                }
                if ($to === JobCard::READY && Settings::bool('workshop.require_qc')) {
                    $qc = $jc->qualityChecks()->latest('checked_at')->first();
                    if (! $qc || $qc->result !== 'pass') {
                        throw new BusinessException(__('A passed quality inspection is required before delivery.'));
                    }
                }
                $pendingParts = $jc->parts()->where('approval_status', 'approved')->whereColumn('qty_issued', '<', 'qty')->where('status', '!=', 'cancelled')->count();
                if ($pendingParts > 0 && $to === JobCard::READY) {
                    throw new BusinessException(__('Some requested parts were not issued. Issue or cancel them first.'));
                }
                break;
            case JobCard::DELIVERED:
                $posted = $jc->invoices()->whereIn('status', ['posted', 'partially_paid', 'paid'])->get();
                // warranty/internal jobs are free; fleet jobs are billed in the consolidated monthly invoice
                $zeroJob = in_array($jc->job_type, ['warranty', 'internal'], true) || $jc->fleet_id;
                if ($posted->isEmpty() && ! $zeroJob) {
                    throw new BusinessException(__('The job card must be invoiced before delivery.'));
                }
                if (Settings::bool('workshop.require_payment_before_delivery') && ! $jc->fleet_id && ! $jc->insurance_claim_id) {
                    $due = Money::sum($posted->map(fn (Invoice $i) => $i->balanceDue()));
                    if (Money::isPositive($due) && (int) $jc->customer->payment_terms_days === 0) {
                        throw new BusinessException(__('Outstanding balance :a must be paid before delivery.', ['a' => money($due)]));
                    }
                }
                break;
            case JobCard::CANCELLED:
                if ($jc->invoices()->whereIn('status', ['posted', 'partially_paid', 'paid'])->exists()) {
                    throw new BusinessException(__('Invoiced job cards cannot be cancelled. Issue a credit note first.'));
                }
                if ($jc->subcontracts()->where('status', 'sent_out')->exists()) {
                    throw new BusinessException(__('Outside work is still with the subcontractor. Receive it back first.'));
                }
                foreach ($jc->parts as $p) {
                    if (Money::isPositive($p->netQty())) {
                        throw new BusinessException(__('Return issued parts to stock before cancelling (:p).', ['p' => $p->part->label()]));
                    }
                }
                break;
        }
    }

    protected function deliver(JobCard $jc, array $handover = []): void
    {
        $jc->delivered_at = now();
        $jc->odometer_out ??= $jc->odometer_in;
        // who collects the vehicle: the owner, or an authorized person whose ID must match an active pick-up authorization
        if (($handover['receiver'] ?? 'owner') === 'authorized') {
            $auth = app(HandoverService::class)->use((int) ($handover['handover_authorization_id'] ?? 0), $jc, 'out', (string) ($handover['receiver_id_number'] ?? ''));
            $jc->forceFill(['delivered_to_type' => 'authorized', 'delivered_to_name' => $auth->person_name, 'delivered_to_id_number' => $auth->id_number, 'handover_authorization_id' => $auth->id]);
        } else {
            $jc->loadMissing('customer');
            $jc->forceFill(['delivered_to_type' => 'owner', 'delivered_to_name' => ($handover['receiver_name'] ?? null) ?: $jc->customer?->name,
                'delivered_to_id_number' => $handover['receiver_id_number'] ?? null, 'handover_authorization_id' => null]);
        }
        VehicleGateLog::create([
            'branch_id' => $jc->branch_id, 'vehicle_id' => $jc->vehicle_id, 'job_card_id' => $jc->id,
            'direction' => 'out', 'logged_at' => now(), 'user_id' => auth()->id(), 'odometer' => $jc->odometer_out,
            'person_name' => $jc->delivered_to_name, 'person_id_number' => $jc->delivered_to_id_number, 'handover_authorization_id' => $jc->handover_authorization_id,
        ]);
        if ($jc->odometer_out) {
            Vehicle::query()->whereKey($jc->vehicle_id)->where('odometer', '<', $jc->odometer_out)->update(['odometer' => $jc->odometer_out]);
        }
        app(\App\Modules\CRM\Services\ReminderService::class)->scheduleAfterJob($jc);
    }

    protected function cancelEffects(JobCard $jc, ?string $reason): void
    {
        $jc->cancelled_reason = $reason;
        foreach ($jc->parts()->where('status', '!=', 'cancelled')->get() as $p) {
            $this->cancelPart($p);
        }
        $jc->tasks()->whereNotIn('status', ['done'])->update(['status' => 'cancelled']);
        $jc->subcontracts()->where('status', 'draft')->update(['status' => 'cancelled']);
        $jc->invoices()->whereIn('status', ['draft', 'held'])->get()->each(fn ($i) => $i->items()->delete() && $i->delete());
    }

    /** Recalculated progress percentage based on finished tasks. */
    public function refreshProgress(JobCard $jc): void
    {
        $tasks = $jc->tasks()->where('status', '!=', 'cancelled')->where('approval_status', 'approved')->get();
        $total = $tasks->count();
        $jc->forceFill(['progress' => $total ? (int) round($tasks->where('status', 'done')->count() * 100 / $total) : 0])->saveQuietly();
    }

    protected function isApprovedStage(JobCard $jc): bool
    {
        return in_array($jc->status, [JobCard::APPROVED, JobCard::AWAITING_PARTS, JobCard::IN_PROGRESS, JobCard::ON_HOLD, JobCard::QUALITY_INSPECTION, JobCard::READY], true);
    }

    protected function assertEditable(JobCard $jc): void
    {
        if ($jc->isClosed()) {
            throw new BusinessException(__('This job card is closed.'));
        }
    }

    // --------------------------------------------------------------- tasks

    public function addTask(JobCard $jc, array $data): JobCardTask
    {
        $this->assertEditable($jc);
        $service = ! empty($data['service_id']) ? Service::query()->with('vehiclePrices')->find($data['service_id']) : null;
        $additional = $this->isApprovedStage($jc);
        $preApproved = $additional && ! empty($data['pre_approved']) && auth()->user()?->can('job_cards.approve_additional');
        $price = $data['unit_price'] ?? ($service ? $service->priceFor($jc->vehicle->vehicle_type) : 0);
        $taxRate = $data['tax_rate'] ?? ($service?->tax_rate ?? tenant()->company()?->vat_rate ?? 5);
        $qty = $data['qty'] ?? 1;
        $c = Totals::line($qty, $price, $data['discount'] ?? 0, $taxRate);

        $task = $jc->tasks()->create([
            'service_id' => $service?->id,
            'department_id' => $data['department_id'] ?? $service?->department_id ?? $jc->department_id,
            'technician_id' => $data['technician_id'] ?? null,
            'description' => $data['description'] ?? $service?->displayName() ?? '-',
            'qty' => $qty, 'unit_price' => Money::round($price), 'discount' => $c['discount'], 'tax_rate' => $taxRate, 'line_total' => $c['net'],
            'estimated_minutes' => $data['estimated_minutes'] ?? $service?->estimated_minutes ?? 0,
            'is_additional' => $additional, 'approval_status' => $additional && ! $preApproved ? 'pending' : 'approved',
            'quotation_item_id' => $data['quotation_item_id'] ?? null, 'notes' => $data['notes'] ?? null,
        ]);
        $this->refreshProgress($jc);

        return $task;
    }

    public function startTask(JobCardTask $task, ?int $technicianId = null): void
    {
        DB::transaction(function () use ($task, $technicianId) {
            $task = JobCardTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $jc = $task->jobCard;
            if ($task->approval_status !== 'approved') {
                throw new BusinessException(__('This additional work has not been approved by the customer yet.'));
            }
            if ($task->is_subcontract) {
                throw new BusinessException(__('Outside work is finished when it is received back from the subcontractor.'));
            }
            if (! in_array($jc->status, [JobCard::APPROVED, JobCard::AWAITING_PARTS, JobCard::IN_PROGRESS, JobCard::QUALITY_INSPECTION], true)) {
                throw new BusinessException(__('Work can only start on approved job cards.'));
            }
            if ($task->status === 'in_progress') {
                return;
            }
            $tech = $technicianId ?? $task->technician_id ?? auth()->id();
            $task->update(['status' => 'in_progress', 'technician_id' => $tech, 'started_at' => $task->started_at ?? now()]);
            JobCardTimeLog::create(['job_card_task_id' => $task->id, 'technician_id' => $tech, 'started_at' => now()]);
            if ($jc->status !== JobCard::IN_PROGRESS) {
                $this->changeStatus($jc, JobCard::IN_PROGRESS, __('Work started'), true);
            }
        });
    }

    public function pauseTask(JobCardTask $task): void
    {
        DB::transaction(function () use ($task) {
            $this->closeTimeLogs($task);
            $task->update(['status' => 'paused']);
        });
    }

    public function finishTask(JobCardTask $task): void
    {
        DB::transaction(function () use ($task) {
            if ($task->is_subcontract) {
                throw new BusinessException(__('Outside work is finished when it is received back from the subcontractor.'));
            }
            $this->closeTimeLogs($task);
            $task->update(['status' => 'done', 'finished_at' => now()]);
            $this->refreshProgress($task->jobCard);
            $this->costing->recalculate($task->jobCard);
        });
    }

    protected function closeTimeLogs(JobCardTask $task): void
    {
        foreach ($task->timeLogs()->whereNull('ended_at')->get() as $log) {
            $minutes = max(1, (int) round($log->started_at->diffInSeconds(now()) / 60));
            $log->update(['ended_at' => now(), 'minutes' => $minutes]);
        }
        $minutes = (int) $task->timeLogs()->sum('minutes');
        $rate = $task->technician?->employee?->hourly_cost;
        $rate = $rate && Money::isPositive($rate) ? $rate : Settings::get('workshop.default_labor_cost_per_hour', '0');
        $task->forceFill(['actual_minutes' => $minutes, 'labor_cost' => Money::mul(Money::div($minutes, 60, 6), $rate)])->save();
    }

    public function updateTask(JobCardTask $task, array $data): void
    {
        $this->assertEditable($task->jobCard);
        $fill = array_intersect_key($data, array_flip(['technician_id', 'department_id', 'description', 'notes', 'estimated_minutes']));
        if (($task->approval_status === 'pending' || ! $this->isApprovedStage($task->jobCard)) && ! $task->is_subcontract) {
            $fill += array_intersect_key($data, array_flip(['qty', 'unit_price', 'discount', 'tax_rate']));
        }
        $task->fill($fill);
        $c = Totals::line($task->qty, $task->unit_price, $task->discount, $task->tax_rate);
        $task->line_total = $c['net'];
        $task->save();
    }

    public function cancelTask(JobCardTask $task): void
    {
        if ($task->status === 'done') {
            throw new BusinessException(__('Finished tasks cannot be cancelled.'));
        }
        if ($task->is_subcontract && \App\Modules\Workshop\Models\SubcontractOrder::query()->where('job_card_task_id', $task->id)->where('status', '!=', 'cancelled')->exists()) {
            throw new BusinessException(__('Cancel the outside work order instead.'));
        }
        $this->closeTimeLogs($task);
        $task->update(['status' => 'cancelled']);
        $this->refreshProgress($task->jobCard);
    }

    // --------------------------------------------------------------- parts

    public function requestPart(JobCard $jc, array $data): JobCardPart
    {
        $this->assertEditable($jc);

        return DB::transaction(function () use ($jc, $data) {
            $part = Part::query()->findOrFail($data['part_id']);
            $warehouseId = $data['warehouse_id'] ?? Warehouse::query()->where('branch_id', $jc->branch_id)->value('id') ?? Warehouse::query()->value('id');
            $additional = $this->isApprovedStage($jc);
            $preApproved = $additional && ! empty($data['pre_approved']) && auth()->user()?->can('job_cards.approve_additional');
            $jcp = $jc->parts()->create([
                'part_id' => $part->id, 'warehouse_id' => $warehouseId, 'description' => $data['description'] ?? $part->displayName(),
                'part_condition' => $data['part_condition'] ?? (in_array($part->part_type, ['used', 'refurbished'], true) ? $part->part_type : 'new'),
                'qty' => Money::round($data['qty'] ?? 1), 'unit_price' => Money::round($data['unit_price'] ?? $part->priceFor('workshop')),
                'discount' => Money::round($data['discount'] ?? 0), 'tax_rate' => $data['tax_rate'] ?? $part->tax_rate,
                'status' => 'requested', 'is_additional' => $additional,
                'approval_status' => $additional && ! $preApproved ? 'pending' : 'approved',
                'quotation_item_id' => $data['quotation_item_id'] ?? null, 'requested_by' => auth()->id(),
            ]);
            if ($warehouseId && ! $part->is_service_item) {
                $reserved = $this->stock->reserve($part->id, $warehouseId, $jcp->qty, true);
                $jcp->update(['qty_reserved' => $reserved, 'status' => Money::cmp($reserved, $jcp->qty) >= 0 ? 'reserved' : 'requested']);
            }

            return $jcp;
        });
    }

    /** Issue reserved/requested parts to a technician: stock out + WIP posting. */
    public function issuePart(JobCardPart $jcp, mixed $qty = null, ?int $technicianId = null): void
    {
        DB::transaction(function () use ($jcp, $qty, $technicianId) {
            $jcp = JobCardPart::query()->whereKey($jcp->id)->lockForUpdate()->firstOrFail();
            $jc = $jcp->jobCard;
            $this->assertEditable($jc);
            if ($jcp->approval_status !== 'approved') {
                throw new BusinessException(__('This additional part has not been approved by the customer yet.'));
            }
            if (! in_array($jc->status, [JobCard::APPROVED, JobCard::AWAITING_PARTS, JobCard::IN_PROGRESS, JobCard::QUALITY_INSPECTION, JobCard::ON_HOLD], true)) {
                throw new BusinessException(__('Parts can only be issued to approved job cards.'));
            }
            $remaining = Money::sub($jcp->qty, $jcp->qty_issued);
            $qty = Money::round($qty ?? $remaining);
            if (! Money::isPositive($qty) || Money::cmp($qty, $remaining) > 0) {
                throw new BusinessException(__('Invalid issue quantity.'));
            }
            if (! $jcp->warehouse_id) {
                throw new BusinessException(__('Select a warehouse for this part.'));
            }
            $fromReserved = Money::min($qty, $jcp->qty_reserved);
            $r = $this->stock->issue($jcp->part_id, $jcp->warehouse_id, $qty, [
                'type' => 'job_issue', 'reference' => $jc, 'reference_number' => $jc->number, 'from_reserved' => $fromReserved,
            ]);
            $newIssued = Money::add($jcp->qty_issued, $qty);
            $totalCost = Money::add(Money::mul($jcp->qty_issued, $jcp->unit_cost), $r['total_cost']);
            $jcp->update([
                'qty_issued' => $newIssued, 'qty_reserved' => Money::max(0, Money::sub($jcp->qty_reserved, $fromReserved)),
                'unit_cost' => Money::div($totalCost, $newIssued, 4), 'total_cost' => $totalCost,
                'status' => Money::cmp($newIssued, $jcp->qty) >= 0 ? 'issued' : 'partially_issued',
                'issued_by' => auth()->id(), 'issued_to' => $technicianId ?? $jcp->issued_to, 'issued_at' => now(),
            ]);
            $this->posting->post([
                'date' => now()->toDateString(),
                'description' => __('Parts issued to job card').' '.$jc->number,
                'source' => $jcp, 'purpose' => 'issue:'.$r['movement']->id, 'branch_id' => $jc->branch_id,
                'lines' => [
                    ['account' => 'wip', 'debit' => $r['total_cost'], 'cost_center_id' => $jc->department?->cost_center_id],
                    ['account' => 'inventory', 'credit' => $r['total_cost']],
                ],
            ]);
            $this->costing->recalculate($jc);
        });
    }

    /** Return unused parts from a job card back to stock (at issue cost). */
    public function returnPart(JobCardPart $jcp, mixed $qty): void
    {
        DB::transaction(function () use ($jcp, $qty) {
            $jcp = JobCardPart::query()->whereKey($jcp->id)->lockForUpdate()->firstOrFail();
            $jc = $jcp->jobCard;
            if ($jc->invoices()->whereIn('status', ['posted', 'partially_paid', 'paid'])->exists()) {
                throw new BusinessException(__('The job card is invoiced. Use a credit note to return parts.'));
            }
            $qty = Money::round($qty);
            if (! Money::isPositive($qty) || Money::cmp($qty, $jcp->netQty()) > 0) {
                throw new BusinessException(__('Invalid return quantity.'));
            }
            $r = $this->stock->receive($jcp->part_id, $jcp->warehouse_id, $qty, $jcp->unit_cost, [
                'type' => 'job_return', 'reference' => $jc, 'reference_number' => $jc->number, 'update_purchase_price' => false,
            ]);
            $jcp->update(['qty_returned' => Money::add($jcp->qty_returned, $qty), 'status' => Money::isZero(Money::sub($jcp->netQty(), $qty)) ? 'returned' : $jcp->status]);
            $this->posting->post([
                'date' => now()->toDateString(), 'description' => __('Parts returned from job card').' '.$jc->number,
                'source' => $jcp, 'purpose' => 'return:'.$r['movement']->id, 'branch_id' => $jc->branch_id,
                'lines' => [['account' => 'inventory', 'debit' => $r['total_cost']], ['account' => 'wip', 'credit' => $r['total_cost']]],
            ]);
            $this->costing->recalculate($jc);
        });
    }

    public function cancelPart(JobCardPart $jcp): void
    {
        if (Money::isPositive($jcp->netQty())) {
            throw new BusinessException(__('Return the issued quantity to stock first.'));
        }
        if (Money::isPositive($jcp->qty_reserved) && $jcp->warehouse_id) {
            $this->stock->release($jcp->part_id, $jcp->warehouse_id, $jcp->qty_reserved);
        }
        $jcp->update(['status' => 'cancelled', 'qty_reserved' => 0]);
    }

    // ------------------------------------------------- additional approvals

    /** Record customer approval/rejection of pending additional tasks and parts. */
    public function decideAdditional(JobCard $jc, array $taskDecisions, array $partDecisions, string $by, string $channel = 'in_person'): void
    {
        DB::transaction(function () use ($jc, $taskDecisions, $partDecisions, $by, $channel) {
            foreach ($taskDecisions as $id => $decision) {
                $t = $jc->tasks()->where('approval_status', 'pending')->find($id);
                $t?->update(['approval_status' => $decision === 'approved' ? 'approved' : 'rejected', 'status' => $decision === 'approved' ? $t->status : 'cancelled']);
            }
            foreach ($partDecisions as $id => $decision) {
                $p = $jc->parts()->where('approval_status', 'pending')->find($id);
                if (! $p) {
                    continue;
                }
                if ($decision === 'approved') {
                    $p->update(['approval_status' => 'approved']);
                } else {
                    $p->update(['approval_status' => 'rejected']);
                    $this->cancelPart($p);
                }
            }
            $jc->notes()->create(['user_id' => auth()->id(), 'note' => __('Additional work decision recorded by :by via :c', ['by' => $by, 'c' => $channel]), 'visible_to_customer' => true]);
            AuditLogger::log('job_card.additional_decision', $jc, ['by' => $by, 'channel' => $channel, 'tasks' => $taskDecisions, 'parts' => $partDecisions]);
            $this->refreshProgress($jc);
        });
    }

    // ----------------------------------------------------------------- QC

    public function qualityCheck(JobCard $jc, string $result, array $checklist = [], ?string $notes = null): QualityCheck
    {
        return DB::transaction(function () use ($jc, $result, $checklist, $notes) {
            if ($jc->status !== JobCard::QUALITY_INSPECTION) {
                $this->changeStatus($jc, JobCard::QUALITY_INSPECTION, __('Sent to quality inspection'));
            }
            $qc = QualityCheck::create([
                'job_card_id' => $jc->id, 'inspector_id' => auth()->id(), 'result' => $result,
                'checklist' => $checklist, 'notes' => $notes, 'checked_at' => now(),
            ]);
            $jc->forceFill(['quality_status' => $result])->save();
            $result === 'pass'
                ? $this->changeStatus($jc->fresh(), JobCard::READY, __('Quality inspection passed'))
                : $this->changeStatus($jc->fresh(), JobCard::IN_PROGRESS, __('Quality inspection failed: rework').($notes ? ' - '.$notes : ''));
            if ($result === 'fail' && tenant()->company()?->moduleEnabled('quality')) {
                app(\App\Modules\Quality\Services\QualityService::class)->fromFailedQc($qc);
            }

            return $qc;
        });
    }
}
