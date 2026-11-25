<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Workshop\Models\Commission;
use App\Modules\Workshop\Models\JobCardTask;
use App\Support\Money;

/**
 * Technician commissions (per service rules) and salesperson commissions
 * (employee commission rate on parts sales), created when invoices post.
 */
class CommissionService
{
    public function fromInvoice(Invoice $invoice): void
    {
        if (Commission::query()->where('invoice_id', $invoice->id)->exists()) {
            return; // idempotent
        }
        $partsNet = '0';
        foreach ($invoice->items as $item) {
            if ($item->item_type === 'part') {
                $partsNet = Money::add($partsNet, $item->line_total);
            }
            if (! $item->job_card_task_id) {
                continue;
            }
            $task = JobCardTask::query()->with('service')->find($item->job_card_task_id);
            if (! $task || ! $task->technician_id || ! $task->service || $task->service->commission_type === 'none') {
                continue;
            }
            $s = $task->service;
            $amount = $s->commission_type === 'percent'
                ? Money::percent($item->line_total, $s->commission_value)
                : Money::mul($s->commission_value, $item->qty);
            if (! Money::isPositive($amount)) {
                continue;
            }
            Commission::create([
                'user_id' => $task->technician_id, 'type' => 'technician', 'source_type' => 'job_card_task', 'source_id' => $task->id,
                'invoice_id' => $invoice->id, 'base_amount' => $item->line_total,
                'rate' => $s->commission_type === 'percent' ? $s->commission_value : 0, 'amount' => $amount, 'status' => 'pending',
                'earned_at' => $invoice->issue_date,
            ]);
            $task->forceFill(['commission_amount' => $amount])->save();
        }

        if ($invoice->salesperson_id && Money::isPositive($partsNet)) {
            $rate = User::query()->find($invoice->salesperson_id)?->employee?->commission_rate;
            if ($rate && Money::isPositive($rate)) {
                Commission::create([
                    'user_id' => $invoice->salesperson_id, 'type' => 'sales', 'source_type' => 'invoice', 'source_id' => $invoice->id,
                    'invoice_id' => $invoice->id, 'base_amount' => $partsNet, 'rate' => $rate,
                    'amount' => Money::percent($partsNet, $rate), 'status' => 'pending', 'earned_at' => $invoice->issue_date,
                ]);
            }
        }
    }
}
