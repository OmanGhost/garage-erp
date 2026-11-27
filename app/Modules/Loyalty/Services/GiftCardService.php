<?php

namespace App\Modules\Loyalty\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Loyalty\Models\GiftCard;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Services\PaymentService;
use App\Support\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Gift cards: sold for cash / card (Dr Cash or bank / Cr Gift cards outstanding), spent as a payment on invoices
 * (Dr Gift cards outstanding / Cr Receivables). An expired card's unused balance is released to other revenue.
 */
class GiftCardService
{
    public function __construct(protected PostingService $posting) {}

    public function issue(mixed $amount, int $bankAccountId, array $data = []): GiftCard
    {
        $amount = Money::round($amount);
        if (! Money::isPositive($amount)) {
            throw new BusinessException(__('Amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($amount, $bankAccountId, $data) {
            $bank = BankAccount::query()->findOrFail($bankAccountId);
            do {
                $code = 'GC-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 4).'-'.substr(bin2hex(random_bytes(4)), 0, 4));
            } while (GiftCard::query()->where('code', $code)->exists());
            $card = GiftCard::create(['code' => $code, 'initial_amount' => $amount, 'balance' => $amount, 'customer_id' => $data['customer_id'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null, 'recipient_phone' => $data['recipient_phone'] ?? null,
                'expires_at' => $data['expires_at'] ?? now()->addYear()->toDateString(), 'status' => 'active', 'bank_account_id' => $bank->id, 'created_by' => auth()->id()]);
            $card->transactions()->create(['type' => 'issue', 'amount' => $amount, 'created_by' => auth()->id()]);
            $this->posting->post(['date' => now()->toDateString(), 'description' => __('Gift card sold').' '.$code, 'source' => $card, 'purpose' => 'issue',
                'lines' => [['account' => $bank->account_id, 'debit' => $amount], ['account' => 'gift_card_liability', 'credit' => $amount]]]);
            AuditLogger::log('gift_card.issued', $card, ['amount' => $amount]);

            return $card;
        });
    }

    public function redeem(Invoice $invoice, string $code, mixed $amount = null): Payment
    {
        return DB::transaction(function () use ($invoice, $code, $amount) {
            $card = GiftCard::query()->where('code', strtoupper(trim($code)))->lockForUpdate()->first();
            if (! $card || ! $card->usable()) {
                throw new BusinessException(__('The gift card is not valid, expired or has no balance.'));
            }
            $amount = Money::round($amount ?? Money::min($card->balance, $invoice->balanceDue()));
            if (Money::cmp($amount, $card->balance) > 0 || Money::cmp($amount, $invoice->balanceDue()) > 0 || ! Money::isPositive($amount)) {
                throw new BusinessException(__('The amount exceeds the card balance or the balance due.'));
            }
            $payment = app(PaymentService::class)->settleFromLiability($invoice, $amount, 'gift_card', 'gift_card_liability', $card->code);
            $card->balance = Money::sub($card->balance, $amount);
            $card->status = Money::isPositive($card->balance) ? 'active' : 'used';
            $card->save();
            $card->transactions()->create(['type' => 'redeem', 'amount' => Money::neg($amount), 'payment_id' => $payment->id, 'notes' => $invoice->displayNumber(), 'created_by' => auth()->id()]);

            return $payment;
        });
    }

    /** The paying receipt was cancelled: the amount goes back on the card. */
    public function refundPayment(Payment $payment): void
    {
        $card = GiftCard::query()->where('code', $payment->reference)->lockForUpdate()->first();
        if (! $card) {
            return;
        }
        $card->balance = Money::add($card->balance, $payment->amount);
        $card->status = 'active';
        $card->save();
        $card->transactions()->create(['type' => 'refund', 'amount' => $payment->amount, 'payment_id' => $payment->id, 'notes' => $payment->number, 'created_by' => auth()->id()]);
    }

    /** Expired cards: the unused balance becomes other revenue. */
    public function expire(): int
    {
        $n = 0;
        foreach (GiftCard::query()->where('status', 'active')->whereDate('expires_at', '<', today())->get() as $card) {
            DB::transaction(function () use ($card) {
                if (Money::isPositive($card->balance)) {
                    $this->posting->post(['date' => now()->toDateString(), 'description' => __('Expired gift card').' '.$card->code, 'source' => $card, 'purpose' => 'expire',
                        'lines' => [['account' => 'gift_card_liability', 'debit' => $card->balance], ['account' => 'other_revenue', 'credit' => $card->balance]]]);
                    $card->transactions()->create(['type' => 'void', 'amount' => Money::neg($card->balance), 'notes' => __('Expired')]);
                }
                $card->update(['status' => 'expired', 'balance' => 0]);
            });
            $n++;
        }

        return $n;
    }
}
