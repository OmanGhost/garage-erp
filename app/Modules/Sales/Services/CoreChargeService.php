<?php

namespace App\Modules\Sales\Services;

use App\Exceptions\BusinessException;
use App\Modules\Inventory\Models\Part;
use App\Modules\Sales\Models\CoreReturn;
use App\Modules\Sales\Models\Invoice;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Core charges: parts such as batteries, alternators or starters carry a refundable deposit. The deposit is added
 * to the invoice as its own line; when the customer brings the old part (the core) back, a credit note refunds that
 * line. A core that never comes back can be forfeited (the deposit is kept).
 */
class CoreChargeService
{
    public const RETURN_DAYS = 30;

    /** Deposit lines for the invoice lines (called when the lines are saved; old deposit lines are rebuilt). */
    public static function depositLines(array $items): array
    {
        $out = [];
        if (! tenant()->company()?->moduleEnabled('inventory_ops')) {
            return $out;
        }
        $parts = Part::query()->whereIn('id', array_column($items, 'part_id'))->where('core_charge', '>', 0)->get()->keyBy('id');
        foreach ($items as $i) {
            if (($i['item_type'] ?? null) === 'core' || empty($i['part_id'])) {
                continue;
            }
            $part = $parts->get($i['part_id']);
            if ($part) {
                $out[] = ['item_type' => 'core', 'core_part_id' => $part->id, 'description' => __('Core deposit (refundable): :p', ['p' => $part->displayName()]),
                    'qty' => $i['qty'] ?? 1, 'unit_price' => $part->core_charge, 'discount' => 0, 'tax_rate' => 0];
            }
        }

        return $out;
    }

    /** After posting: one pending core return per deposit line. */
    public function fromInvoice(Invoice $invoice): void
    {
        foreach ($invoice->items()->where('item_type', 'core')->whereNotNull('core_part_id')->get() as $line) {
            CoreReturn::query()->firstOrCreate(['invoice_item_id' => $line->id], [
                'invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id, 'part_id' => $line->core_part_id,
                'qty' => $line->qty, 'amount' => $line->line_total, 'status' => 'pending',
                'due_date' => $invoice->issue_date->copy()->addDays(self::RETURN_DAYS)->toDateString(),
            ]);
        }
    }

    /** The customer brought the old part back: refund the deposit with a credit note. */
    public function receive(CoreReturn $core, string $condition = 'good', ?string $notes = null): void
    {
        DB::transaction(function () use ($core, $condition, $notes) {
            $core = $this->pending($core);
            $cns = app(CreditNoteService::class);
            $cn = $cns->post($cns->create($core->invoice, [$core->invoice_item_id => $core->qty], false, __('Core returned: :p', ['p' => $core->part?->displayName()]), coreReturn: true));
            $core->update(['status' => 'returned', 'condition' => in_array($condition, ['good', 'damaged'], true) ? $condition : 'good',
                'returned_at' => now(), 'credit_note_id' => $cn->id, 'notes' => $notes]);
            AuditLogger::log('core.returned', $core, ['amount' => (string) $core->amount, 'credit_note' => $cn->number ?? $cn->id]);
        });
    }

    public function forfeit(CoreReturn $core, ?string $notes = null): void
    {
        DB::transaction(function () use ($core, $notes) {
            $core = $this->pending($core);
            app(\App\Modules\Accounting\Services\PostingService::class)->post([
                'source' => $core, 'purpose' => 'forfeit', 'description' => __('Forfeited core deposit').' '.$core->invoice->number,
                'lines' => [
                    ['account' => 'customer_advances', 'debit' => $core->amount, 'party' => $core->customer],
                    ['account' => 'other_revenue', 'credit' => $core->amount],
                ],
            ]);
            $core->update(['status' => 'forfeited', 'notes' => $notes]);
            AuditLogger::log('core.forfeited', $core, ['amount' => (string) $core->amount]);
        });
    }

    private function pending(CoreReturn $core): CoreReturn
    {
        $core = CoreReturn::query()->lockForUpdate()->findOrFail($core->id);
        if ($core->status !== 'pending') {
            throw new BusinessException(__('This core is already settled.'));
        }
        return $core;
    }
}
