<?php

namespace App\Modules\Sales\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Inventory\Models\Part;
use App\Modules\Sales\Models\CashRegister;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Models\PosSession;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Point of sale: cash sessions, checkout with split payments, hold/resume,
 * cash count with over/short posting.
 */
class PosService
{
    public function __construct(protected InvoiceService $invoices, protected PaymentService $payments, protected PostingService $posting) {}

    public function openSession(CashRegister $register, mixed $openingCash): PosSession
    {
        return DB::transaction(function () use ($register, $openingCash) {
            $register = CashRegister::query()->whereKey($register->id)->lockForUpdate()->firstOrFail();
            if (PosSession::query()->where('cash_register_id', $register->id)->where('status', 'open')->exists()) {
                throw new BusinessException(__('This register already has an open session.'));
            }
            $s = PosSession::create([
                'cash_register_id' => $register->id, 'user_id' => auth()->id(), 'branch_id' => $register->branch_id,
                'number' => DocumentNumber::next('pos_session', false),
                'opened_at' => now(), 'opening_cash' => Money::round($openingCash), 'status' => 'open',
            ]);
            AuditLogger::log('pos.session_opened', $s, ['opening_cash' => $openingCash]);

            return $s;
        });
    }

    public function currentSession(?int $userId = null): ?PosSession
    {
        return PosSession::query()->where('user_id', $userId ?? auth()->id())->where('status', 'open')->latest('id')->first();
    }

    /**
     * @param  array  $cart  customer_id, price_level, items:[part_id, qty, unit_price, discount], notes, salesperson_id, invoice_discount
     * @param  array  $payments  [[method, amount, bank_account_id?, reference?]]
     * @return array{invoice:Invoice, change:string, payments:Payment[]}
     */
    public function checkout(PosSession $session, array $cart, array $payments): array
    {
        if ($session->status !== 'open') {
            throw new BusinessException(__('The POS session is closed.'));
        }
        if (empty($cart['items'])) {
            throw new BusinessException(__('The cart is empty.'));
        }

        return DB::transaction(function () use ($session, $cart, $payments) {
            $register = $session->register;
            $customer = Customer::query()->findOrFail($cart['customer_id']);
            $level = $cart['price_level'] ?? $customer->price_level ?? 'retail';

            $items = [];
            foreach ($cart['items'] as $line) {
                $part = Part::query()->findOrFail($line['part_id']);
                $price = Money::round($line['unit_price'] ?? $part->priceFor($level));
                $discount = Money::round($line['discount'] ?? 0);
                $net = Money::div(Money::sub(Money::mul($line['qty'], $price), $discount), $line['qty'], 3);
                if (Money::isPositive($part->min_sale_price) && Money::cmp($net, $part->min_sale_price) < 0 && ! auth()->user()?->can('pos.below_min_price')) {
                    throw new BusinessException(__('Price of :p is below the minimum allowed (:m).', ['p' => $part->label(), 'm' => money($part->min_sale_price)]));
                }
                $listPrice = $part->priceFor($level);
                if ((Money::cmp($price, $listPrice) < 0 || Money::isPositive($discount)) && ! auth()->user()?->can('pos.discount_override')) {
                    $maxDiscount = Money::percent(Money::mul($line['qty'], $listPrice), $customer->discount_percent);
                    if (Money::cmp(Money::add(Money::sub(Money::mul($line['qty'], $listPrice), Money::mul($line['qty'], $price)), $discount), $maxDiscount) > 0) {
                        throw new BusinessException(__('You are not allowed to give this discount.'));
                    }
                }
                $items[] = ['item_type' => 'part', 'part_id' => $part->id, 'qty' => $line['qty'], 'unit_price' => $price, 'discount' => $discount, 'warehouse_id' => $register->warehouse_id];
            }

            $invoice = ! empty($cart['held_invoice_id'])
                ? $this->invoices->updateDraft(Invoice::query()->where('status', 'held')->findOrFail($cart['held_invoice_id']), ['customer_id' => $customer->id, 'price_level' => $level, 'notes' => $cart['notes'] ?? null], $items)
                : $this->invoices->createDraft([
                    'type' => 'pos', 'customer_id' => $customer->id, 'pos_session_id' => $session->id, 'branch_id' => $session->branch_id,
                    'warehouse_id' => $register->warehouse_id, 'price_level' => $level, 'notes' => $cart['notes'] ?? null,
                    'salesperson_id' => $cart['salesperson_id'] ?? auth()->id(),
                ], $items);

            $paid = Money::sum(array_column($payments, 'amount'));
            $due = $invoice->total;
            $isCredit = Money::cmp($paid, $due) < 0;
            $walkIn = (int) setting('pos.walk_in_customer_id') === $customer->id || $customer->code === 'WALKIN';
            if ($isCredit && $walkIn) {
                throw new BusinessException(__('Walk-in customers must pay the full amount. Select a registered customer for credit sales.'));
            }
            $invoice->forceFill(['payment_terms' => $isCredit ? 'credit' : 'cash', 'pos_session_id' => $session->id, 'status' => 'draft'])->save();
            if ($isCredit) {
                $this->invoices->assertCredit($customer, Money::sub($due, $paid));
            }
            $invoice = $this->invoices->post($invoice, true);

            // split payments; cash over the due amount becomes change
            $change = Money::max(0, Money::sub($paid, $due));
            $remaining = $due;
            $created = [];
            foreach ($payments as $p) {
                $amount = Money::round($p['amount'] ?? 0);
                if (! Money::isPositive($amount) || ! Money::isPositive($remaining)) {
                    continue;
                }
                $apply = Money::min($amount, $remaining);
                $bankId = $p['bank_account_id'] ?? ($p['method'] === 'cash'
                    ? $register->bank_account_id
                    : BankAccount::query()->where('type', $p['method'] === 'card' ? 'card' : 'bank')->value('id') ?? $register->bank_account_id);
                $created[] = $this->payments->receiveFromCustomer($customer, $apply, $bankId, $p['method'], [$invoice->id => $apply], [
                    'pos_session_id' => $session->id, 'reference' => $p['reference'] ?? null, 'branch_id' => $session->branch_id,
                ]);
                $remaining = Money::sub($remaining, $apply);
            }

            // cash handed over and change given back, for the receipt
            $cashTendered = Money::sum(array_map(fn ($p) => ($p['method'] ?? '') === 'cash' ? Money::round($p['amount'] ?? 0) : '0', $payments));
            if (Money::isPositive($cashTendered)) {
                $invoice->forceFill(['cash_tendered' => $cashTendered, 'change_given' => $change])->save();
            }

            return ['invoice' => $invoice->fresh(['items', 'customer']), 'change' => $change, 'payments' => $created];
        });
    }

    public function hold(PosSession $session, array $cart, string $label = ''): Invoice
    {
        $items = array_map(fn ($l) => ['item_type' => 'part', 'part_id' => $l['part_id'], 'qty' => $l['qty'], 'unit_price' => $l['unit_price'] ?? null, 'discount' => $l['discount'] ?? 0, 'warehouse_id' => $session->register->warehouse_id], $cart['items'] ?? []);
        if (! $items) {
            throw new BusinessException(__('The cart is empty.'));
        }
        if (! empty($cart['held_invoice_id'])) {
            $inv = Invoice::query()->where('status', 'held')->findOrFail($cart['held_invoice_id']);

            return $this->invoices->updateDraft($inv, ['customer_id' => $cart['customer_id'], 'held_label' => $label], $items);
        }
        $inv = $this->invoices->createDraft([
            'type' => 'pos', 'customer_id' => $cart['customer_id'], 'pos_session_id' => $session->id, 'branch_id' => $session->branch_id,
            'warehouse_id' => $session->register->warehouse_id, 'price_level' => $cart['price_level'] ?? 'retail',
            'held_label' => $label ?: Str::upper(Str::random(4)),
        ], $items);
        $inv->forceFill(['status' => 'held'])->save();

        return $inv;
    }

    /** Expected cash = opening + cash receipts - cash refunds of the session. */
    public function expectedCash(PosSession $session): string
    {
        $cashBox = $session->register->bank_account_id;
        $in = Payment::query()->where('pos_session_id', $session->id)->where('bank_account_id', $cashBox)->where('status', 'posted')->where('direction', 'in')->sum('amount');
        $out = Payment::query()->where('pos_session_id', $session->id)->where('bank_account_id', $cashBox)->where('status', 'posted')->where('direction', 'out')->sum('amount');

        return Money::sub(Money::add($session->opening_cash, $in), $out);
    }

    /**
     * What the session took, for the closing count: sales, receipts and refunds per payment method (and whether
     * they sit in the drawer), and the cash that should be in the drawer.
     *
     * @return array{invoices: int, sales: string, methods: array<int, array{method: string, in: string, out: string, net: string, drawer: bool}>, opening: string, cash_in: string, cash_out: string, expected: string, other_total: string}
     */
    public function summary(PosSession $session): array
    {
        $cashBox = $session->register->bank_account_id;
        $invoices = Invoice::query()->where('pos_session_id', $session->id)->whereNotIn('status', ['draft', 'held', 'cancelled'])->get(['id', 'total']);
        $rows = Payment::query()->where('pos_session_id', $session->id)->where('status', 'posted')
            ->selectRaw('method, direction, bank_account_id, SUM(amount) total, SUM(fee_amount) fees')->groupBy('method', 'direction', 'bank_account_id')->get();
        $methods = [];
        $cashIn = '0';
        $cashOut = '0';
        $other = '0';
        foreach ($rows as $r) {
            $drawer = $cashBox && (int) $r->bank_account_id === (int) $cashBox;
            $key = $r->method.($drawer ? ':drawer' : '');
            $methods[$key] ??= ['method' => (string) $r->method, 'in' => '0', 'out' => '0', 'net' => '0', 'drawer' => $drawer, 'fee' => '0'];
            $methods[$key]['fee'] = Money::add($methods[$key]['fee'], Money::round($r->fees ?? 0));
            $amount = Money::round($r->total);
            if ($r->direction === 'out') {
                $methods[$key]['out'] = Money::add($methods[$key]['out'], $amount);
                $drawer ? $cashOut = Money::add($cashOut, $amount) : $other = Money::sub($other, $amount);
            } else {
                $methods[$key]['in'] = Money::add($methods[$key]['in'], $amount);
                $drawer ? $cashIn = Money::add($cashIn, $amount) : $other = Money::add($other, $amount);
            }
            $methods[$key]['net'] = Money::sub($methods[$key]['in'], $methods[$key]['out']);
        }
        $methods = collect($methods)->sortByDesc(fn ($m) => [$m['drawer'], (float) $m['in']])->values()->all();

        return ['invoices' => $invoices->count(), 'sales' => Money::sum($invoices->pluck('total')), 'methods' => $methods,
            'fees' => Money::sum(array_column($methods, 'fee')),
            'opening' => Money::round($session->opening_cash), 'cash_in' => $cashIn, 'cash_out' => $cashOut,
            'expected' => Money::sub(Money::add($session->opening_cash, $cashIn), $cashOut), 'other_total' => $other];
    }

    public function closeSession(PosSession $session, mixed $countedCash, ?string $notes = null, ?string $differenceReason = null): PosSession
    {
        return DB::transaction(function () use ($session, $countedCash, $notes, $differenceReason) {
            $session = PosSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== 'open') {
                throw new BusinessException(__('Session already closed.'));
            }
            if (Invoice::query()->where('pos_session_id', $session->id)->where('status', 'held')->exists()) {
                throw new BusinessException(__('There are held invoices in this session. Complete or delete them first.'));
            }
            $expected = $this->expectedCash($session);
            $counted = Money::round($countedCash);
            $diff = Money::sub($counted, $expected);
            $differenceReason = trim((string) $differenceReason);
            if (! Money::isZero($diff) && $differenceReason === '') {
                throw new BusinessException(__('The counted cash differs from the expected :e by :d. State the reason for the difference before closing.', ['e' => money($expected), 'd' => money($diff)]));
            }
            $session->fill(['closed_at' => now(), 'expected_cash' => $expected, 'counted_cash' => $counted, 'difference' => $diff, 'status' => 'closed', 'notes' => $notes,
                'difference_reason' => Money::isZero($diff) ? null : $differenceReason])->save();

            if (! Money::isZero($diff)) {
                $cashAccount = $session->register->bankAccount->account_id;
                $je = $this->posting->post([
                    'date' => now()->toDateString(),
                    'description' => __('Cash over/short').' '.$session->number,
                    'source' => $session,
                    'branch_id' => $session->branch_id,
                    'lines' => Money::isPositive($diff)
                        ? [['account' => $cashAccount, 'debit' => $diff], ['account' => 'cash_over', 'credit' => $diff]]
                        : [['account' => 'cash_short', 'debit' => Money::neg($diff)], ['account' => $cashAccount, 'credit' => Money::neg($diff)]],
                ]);
                $session->forceFill(['journal_entry_id' => $je->id])->save();
            }
            AuditLogger::log('pos.session_closed', $session, ['expected' => $expected, 'counted' => $counted, 'difference' => $diff, 'reason' => $session->difference_reason]);

            return $session;
        });
    }
}
