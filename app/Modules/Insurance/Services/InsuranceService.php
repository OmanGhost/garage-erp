<?php

namespace App\Modules\Insurance\Services;

use App\Exceptions\BusinessException;
use App\Modules\Insurance\Models\InsuranceClaim;
use App\Modules\Sales\Services\InvoiceService;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Insurance claims lifecycle and split billing:
 *   insurer invoice  = approved job lines less the deductible (proportional discount)
 *   customer invoice = deductible amount
 */
class InsuranceService
{
    public const STATUSES = ['draft', 'submitted', 'under_review', 'approved', 'partially_approved', 'rejected', 'invoiced', 'paid', 'closed'];

    public function create(array $data): InsuranceClaim
    {
        return InsuranceClaim::create($data + ['number' => DocumentNumber::next('insurance_claim'), 'status' => 'draft']);
    }

    public function setStatus(InsuranceClaim $c, string $status, array $data = []): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new BusinessException(__('Invalid status.'));
        }
        $c->fill(array_intersect_key($data, array_flip(['approved_amount', 'deductible_amount', 'claim_number', 'surveyor_name', 'notes'])));
        $c->status = $status;
        if ($status === 'submitted') {
            $c->submitted_at = now();
        }
        if (in_array($status, ['approved', 'partially_approved'], true)) {
            $c->approved_at = now();
        }
        $c->save();
    }

    /** @return array{insurer: \App\Modules\Sales\Models\Invoice, customer: ?\App\Modules\Sales\Models\Invoice} */
    public function invoice(InsuranceClaim $c): array
    {
        if (! in_array($c->status, ['approved', 'partially_approved'], true)) {
            throw new BusinessException(__('The claim must be approved by the insurance company first.'));
        }
        $insurerCustomer = $c->insuranceCompany->customer_id;
        if (! $insurerCustomer) {
            throw new BusinessException(__('Link the insurance company to a customer (billing) account first.'));
        }
        $jc = $c->jobCard ?? throw new BusinessException(__('The claim has no job card.'));

        return DB::transaction(function () use ($c, $jc, $insurerCustomer) {
            $svc = app(InvoiceService::class);
            $insurer = $svc->fromJobCard($jc, ['customer_id' => $insurerCustomer, 'type' => 'insurance', 'force_new' => true]);
            $deductible = Money::round($c->deductible_amount);
            $customerInv = null;
            if (Money::isPositive($deductible)) {
                // spread the deductible as discount over the insurer lines (net of VAT, proportional)
                $items = $insurer->items()->get();
                $net = Money::sum($items->pluck('line_total'));
                $allocated = '0';
                foreach ($items->values() as $i => $item) {
                    $share = $i === $items->count() - 1 ? Money::sub($deductible, $allocated) : Money::round(bcdiv(bcmul(Money::n($deductible), Money::n($item->line_total), 10), Money::n($net ?: 1), 10));
                    $share = Money::min($share, $item->line_total);
                    $allocated = Money::add($allocated, $share);
                    $newDiscount = Money::add($item->discount, $share);
                    $lineNet = Money::sub(Money::mul($item->qty, $item->unit_price), $newDiscount);
                    $item->update(['discount' => $newDiscount, 'line_total' => $lineNet, 'tax_amount' => Money::percent($lineNet, $item->tax_rate)]);
                }
                $svc->recalc($insurer);
                $customerInv = $svc->createDraft([
                    'type' => 'insurance', 'customer_id' => $jc->customer_id, 'job_card_id' => $jc->id, 'insurance_claim_id' => $c->id,
                    'payment_terms' => 'cash', 'notes' => __('Insurance deductible').' '.$c->number,
                ], [['item_type' => 'other', 'description' => __('Insurance deductible').' - '.$c->claim_number, 'qty' => 1, 'unit_price' => $deductible]]);
            }
            $insurer->forceFill(['insurance_claim_id' => $c->id, 'due_date' => now()->addDays(60)->toDateString()])->save();
            $c->update(['status' => 'invoiced']);

            return ['insurer' => $insurer->fresh('items'), 'customer' => $customerInv];
        });
    }
}
