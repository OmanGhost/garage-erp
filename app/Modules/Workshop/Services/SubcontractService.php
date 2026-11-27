<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\SubcontractOrder;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Outside (subcontracted) work for a job card. Creating an order adds the customer charge as a job card task
 * (additional work needs the customer's approval like any other). The customer's part leaves without any stock
 * movement. When the work comes back the task is finished, the cost enters the job costing and — with purchasing
 * enabled — a draft supplier invoice is prepared on the "subcontracted work cost" account.
 */
class SubcontractService
{
    public function __construct(protected JobCardService $jobs, protected JobCostingService $costing) {}

    public function create(JobCard $jc, array $data): SubcontractOrder
    {
        if ($jc->isClosed()) {
            throw new BusinessException(__('This job card is closed.'));
        }

        return DB::transaction(function () use ($jc, $data) {
            $task = $this->jobs->addTask($jc, [
                'description' => __('Outside work').': '.$data['description'], 'qty' => 1, 'unit_price' => $data['sell_price'] ?? 0,
                'estimated_minutes' => 0, 'pre_approved' => ! empty($data['pre_approved']),
            ]);
            $task->forceFill(['is_subcontract' => true])->save();
            $o = SubcontractOrder::create([
                'number' => DocumentNumber::next('subcontract'), 'branch_id' => $jc->branch_id, 'job_card_id' => $jc->id,
                'supplier_id' => $data['supplier_id'], 'work_type' => $data['work_type'] ?? 'other', 'description' => $data['description'],
                'item_sent' => $data['item_sent'] ?? null, 'quoted_cost' => $data['quoted_cost'] ?? 0, 'sell_price' => $data['sell_price'] ?? 0,
                'expected_at' => $data['expected_at'] ?? null, 'warranty_days' => (int) ($data['warranty_days'] ?? 0), 'notes' => $data['notes'] ?? null,
                'status' => 'draft', 'job_card_task_id' => $task->id, 'created_by' => auth()->id(),
            ]);
            AuditLogger::log('subcontract.created', $o, ['job_card' => $jc->number]);

            return $o;
        });
    }

    public function send(SubcontractOrder $o): void
    {
        DB::transaction(function () use ($o) {
            $o = SubcontractOrder::query()->with('task', 'jobCard')->whereKey($o->id)->lockForUpdate()->firstOrFail();
            if ($o->status !== 'draft') {
                throw new BusinessException(__('Only draft orders can be sent.'));
            }
            if ($o->task && $o->task->approval_status !== 'approved') {
                throw new BusinessException(__('The customer has not approved this outside work yet.'));
            }
            if ($o->jobCard->isClosed()) {
                throw new BusinessException(__('This job card is closed.'));
            }
            $o->update(['status' => 'sent_out', 'sent_at' => now()]);
            $this->costing->recalculate($o->jobCard);
            AuditLogger::log('subcontract.sent', $o, ['item' => $o->item_sent]);
        });
    }

    /** The work is back: finish the task, cost the job and prepare the supplier's invoice. */
    public function receive(SubcontractOrder $o, string $actualCost, ?string $supplierInvoiceNo = null, ?string $taxRate = null): SubcontractOrder
    {
        return DB::transaction(function () use ($o, $actualCost, $supplierInvoiceNo, $taxRate) {
            $o = SubcontractOrder::query()->with('task', 'jobCard', 'supplier')->whereKey($o->id)->lockForUpdate()->firstOrFail();
            if ($o->status !== 'sent_out') {
                throw new BusinessException(__('Only orders that are out with the subcontractor can be received.'));
            }
            if (Money::cmp($actualCost, '0') < 0) {
                throw new BusinessException(__('The cost cannot be negative.'));
            }
            $o->forceFill(['status' => 'back_in', 'returned_at' => now(), 'actual_cost' => Money::round($actualCost),
                'warranty_until' => $o->warranty_days ? today()->addDays($o->warranty_days)->toDateString() : null])->save();
            if ($o->task && ! in_array($o->task->status, ['done', 'cancelled'], true)) {
                $o->task->update(['status' => 'done', 'finished_at' => now()]);
                $this->jobs->refreshProgress($o->jobCard);
            }
            if (Money::isPositive($o->actual_cost) && tenant()->company()?->moduleEnabled('purchasing')) {
                $si = app(PurchasingService::class)->createSupplierInvoice([
                    'supplier_id' => $o->supplier_id, 'branch_id' => $o->branch_id,
                    'supplier_invoice_number' => $supplierInvoiceNo ?: $o->number,
                    'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays($o->supplier->payment_terms_days ?? 30)->toDateString(),
                    'notes' => __('Outside work').' '.$o->number.' — '.$o->jobCard->number,
                ], [['account_id' => Account::query()->where('system_key', 'subcontract_cost')->value('id'), 'description' => $o->description,
                    'qty' => 1, 'unit_cost' => $o->actual_cost, 'tax_rate' => $taxRate ?? 0]]);
                $o->forceFill(['supplier_invoice_id' => $si->id])->save();
            }
            $this->costing->recalculate($o->jobCard);
            AuditLogger::log('subcontract.received', $o, ['cost' => (string) $o->actual_cost]);

            return $o;
        });
    }

    public function cancel(SubcontractOrder $o, ?string $reason = null): void
    {
        DB::transaction(function () use ($o, $reason) {
            $o = SubcontractOrder::query()->with('task', 'jobCard')->whereKey($o->id)->lockForUpdate()->firstOrFail();
            if (! in_array($o->status, ['draft', 'sent_out'], true)) {
                throw new BusinessException(__('This order can no longer be cancelled.'));
            }
            $o->update(['status' => 'cancelled', 'notes' => trim(($o->notes ? $o->notes."\n" : '').($reason ?? ''))]);
            if ($o->task && ! in_array($o->task->status, ['done', 'cancelled'], true)) {
                $this->jobs->cancelTask($o->task);
            }
            $this->costing->recalculate($o->jobCard);
            AuditLogger::log('subcontract.cancelled', $o, ['reason' => $reason]);
        });
    }
}
