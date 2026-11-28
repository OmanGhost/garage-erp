<?php

namespace App\Support\Vouchers;

use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\FundTransfer;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\HR\Models\EmployeeLoan;
use App\Modules\HR\Models\Payroll;
use App\Modules\Sales\Models\Payment;
use App\Support\AmountInWords;

/**
 * Normalised data for an official receipt / payment / transfer voucher.
 * Every money document in the system (payments, refunds, expenses, fund transfers,
 * employee advances, salary payments) is printed through the same official template.
 */
final class Voucher
{
    public string $kind = 'payment';           // receipt | payment | transfer

    public string $titleAr = 'سند صرف';

    public string $titleEn = 'Payment Voucher';

    public string $number = '';

    public ?\DateTimeInterface $date = null;

    public string $partyLabelAr = 'صرفنا إلى السيد / السادة';

    public string $partyLabelEn = 'Paid to Mr. / Messrs.';

    public string $party = '';

    public ?string $partyExtra = null;         // phone, employee code, VAT no.

    public string $amount = '0';

    public string $purpose = '';              // Arabic

    public ?string $purposeEn = null;          // English (shown separately, left-to-right)

    public ?string $method = null;             // cash | cheque | bank_transfer | card | online | wallet

    public ?string $account = null;            // cash box / bank used

    public ?string $toAccount = null;          // transfers

    public ?string $chequeNumber = null;

    public ?string $chequeDate = null;

    public ?string $reference = null;

    public array $lines = [];                  // [[label, amount]] e.g. invoices settled

    public ?string $notes = null;

    public ?string $preparedBy = null;

    public bool $cancelled = false;

    public array $signatures = [];             // [[ar, en]]

    public function words(): array
    {
        return [AmountInWords::arabic($this->amount), AmountInWords::english($this->amount)];
    }

    public function split(): array
    {
        return AmountInWords::split($this->amount);
    }

    /** Text encoded in the QR code so a printed voucher can be checked against the system. */
    public function verification(string $company): string
    {
        return implode("\n", [$company, $this->titleEn.' '.$this->number, 'Date: '.$this->date?->format('Y-m-d'),
            'Amount: '.money($this->amount), 'Party: '.$this->party]);
    }

    protected static function receipt(self $v): self
    {
        $v->kind = 'receipt';
        $v->titleAr = 'سند قبض';
        $v->titleEn = 'Receipt Voucher';
        $v->partyLabelAr = 'استلمنا من السيد / السادة';
        $v->partyLabelEn = 'Received from Mr. / Messrs.';
        $v->signatures = [['المستلم (أمين الصندوق)', 'Received by (Cashier)'], ['المحاسب', 'Accountant'], ['المدير المالي', 'Finance Manager']];

        return $v;
    }

    protected static function payment(self $v): self
    {
        $v->signatures = [['المستلم', 'Beneficiary'], ['المحاسب', 'Accountant'], ['المعتمد / المدير المالي', 'Approved by']];

        return $v;
    }

    /** The cash box / bank credited in a journal entry (for documents that do not store it). */
    protected static function paidFrom(?JournalEntry $je): ?string
    {
        $line = $je?->lines()->with('account')->where('credit', '>', 0)->get()
            ->first(fn ($l) => in_array($l->account?->system_key, ['cash', 'pos_cash', 'bank', 'card_clearing'], true) || \App\Modules\Accounting\Models\BankAccount::query()->where('account_id', $l->account_id)->exists());

        return $line ? (\App\Modules\Accounting\Models\BankAccount::query()->where('account_id', $line->account_id)->value('name') ?? $line->account?->displayName()) : null;
    }

    public static function fromPayment(Payment $p): self
    {
        $v = $p->direction === 'in' ? self::receipt(new self) : self::payment(new self);
        if ($p->direction === 'out' && $p->party_type === 'customer') {
            $v->titleAr = 'سند صرف (استرداد)';
            $v->titleEn = 'Payment Voucher (Refund)';
        }
        $v->number = $p->number;
        $v->date = $p->date;
        $v->party = $p->party?->name ?? '—';
        $v->partyExtra = trim(($p->party?->phone ?? '').($p->party?->vat_number ? ' · VATIN '.$p->party->vat_number : ''), ' ·');
        $v->amount = (string) $p->amount;
        $v->method = $p->method;
        $v->account = $p->bankAccount?->name;
        $v->chequeNumber = $p->cheque_number;
        $v->chequeDate = $p->cheque_date?->format('Y-m-d');
        $v->reference = $p->reference;
        $v->notes = $p->notes;
        $v->preparedBy = $p->creator?->name;
        $v->cancelled = $p->status === 'cancelled';
        $settled = $p->allocations->map(fn ($a) => [($a->allocatable?->number ?? '#'.$a->allocatable_id), (string) $a->amount])->all();
        // the breakdown table is only useful when several invoices are settled by one payment
        $v->lines = count($settled) > 1 ? $settled : [];
        $docs = implode('، ', array_column($settled, 0));
        [$v->purpose, $v->purposeEn] = $settled
            ? ($p->direction === 'in' ? ['سداد الفواتير: '.$docs, 'Settlement of invoices'] : ['سداد فواتير المورد: '.$docs, 'Settlement of supplier invoices'])
            : ($p->direction === 'in' ? ['دفعة على الحساب', 'Payment on account'] : ['دفعة', 'Payment']);

        return $v;
    }

    public static function fromExpense(Expense $e): self
    {
        $v = self::payment(new self);
        $v->number = $e->number;
        $v->date = $e->date;
        $v->party = $e->supplier?->name ?? 'مصروف نقدي';
        $v->amount = (string) $e->total;
        $v->purpose = $e->description.' ('.$e->account?->displayName().')';
        $v->method = $e->bankAccount ? (in_array($e->bankAccount->type, ['bank'], true) ? 'bank_transfer' : 'cash') : null;
        $v->account = $e->bankAccount?->name ?? ($e->supplier ? 'على حساب المورد / On supplier account' : null);
        $v->reference = $e->reference;
        if ((float) $e->tax_amount > 0) {
            $v->lines = [['المبلغ قبل الضريبة', (string) $e->amount], ['ضريبة القيمة المضافة', (string) $e->tax_amount]];
        }
        $v->notes = $e->costCenter ? 'مركز التكلفة / Cost center: '.$e->costCenter->code.' '.$e->costCenter->name_ar : null;
        $v->cancelled = $e->status === 'cancelled';

        return $v;
    }

    public static function fromTransfer(FundTransfer $t): self
    {
        $v = new self;
        $v->kind = 'transfer';
        $v->titleAr = 'سند تحويل نقدية';
        $v->titleEn = 'Fund Transfer Voucher';
        $v->partyLabelAr = 'تم تحويل مبلغ من';
        $v->partyLabelEn = 'Transferred from';
        $v->number = $t->number;
        $v->date = $t->date;
        $v->party = (string) $t->fromAccount?->name;
        $v->account = $t->fromAccount?->name;
        $v->toAccount = $t->toAccount?->name;
        $v->amount = (string) $t->amount;
        $v->purpose = 'تحويل نقدية بين الحسابات';
        $v->purposeEn = 'Transfer between accounts';
        $v->method = 'bank_transfer';
        $v->reference = $t->reference;
        $v->notes = $t->notes;
        $v->signatures = [['المحوِّل', 'Transferred by'], ['المستلم', 'Received by'], ['المعتمد / المدير المالي', 'Approved by']];

        return $v;
    }

    public static function fromLoan(EmployeeLoan $l): self
    {
        $v = self::payment(new self);
        $v->titleAr = 'سند صرف سلفة';
        $v->titleEn = 'Employee Advance Voucher';
        $v->number = 'LN-'.str_pad((string) $l->id, 6, '0', STR_PAD_LEFT);
        $v->date = $l->date;
        $v->party = (string) $l->employee?->name;
        $v->partyExtra = $l->employee?->code ? 'الرقم الوظيفي / Employee no. '.$l->employee->code : null;
        $v->amount = (string) $l->amount;
        if ($l->loan_type === 'financing') {
            $rate = rtrim(rtrim((string) $l->profit_rate, '0'), '.');
            $v->titleAr = 'سند صرف تمويل موظف';
            $v->titleEn = 'Employee Financing Voucher';
            $v->purpose = 'تمويل داخلي بمبلغ '.money($l->amount, false).' ر.ع وربح '.$rate.'% ('.money($l->profit_amount, false).' ر.ع)، إجمالي المستحق '
                .money($l->total_payable, false).' ر.ع يسدد على '.$l->installments_count.' قسطاً شهرياً قدره '.money($l->installment, false).' ر.ع ابتداءً من '.$l->first_month?->format('m/Y');
            $v->purposeEn = 'Internal financing '.money($l->amount).' + '.$rate.'% profit ('.money($l->profit_amount).') = '.money($l->total_payable)
                .', '.$l->installments_count.' monthly installments of '.money($l->installment).' from '.$l->first_month?->format('m/Y');
        } else {
            $v->purpose = 'سلفة على الراتب تسدد بأقساط شهرية قدرها '.money($l->installment, false).' ر.ع'.($l->first_month ? ' ابتداءً من '.$l->first_month->format('m/Y') : '');
            $v->purposeEn = 'Salary advance, monthly installment '.money($l->installment).($l->first_month ? ' from '.$l->first_month->format('m/Y') : '');
        }
        $v->method = 'cash';
        $v->account = self::paidFrom($l->journal_entry_id ? JournalEntry::query()->find($l->journal_entry_id) : null);
        $v->notes = $l->notes;

        return $v;
    }

    public static function fromPayroll(Payroll $p): self
    {
        $v = self::payment(new self);
        $v->titleAr = 'سند صرف رواتب';
        $v->titleEn = 'Salaries Payment Voucher';
        $v->number = $p->number;
        $v->date = $p->paid_at ?? now();
        $v->partyLabelAr = 'صرفنا إلى';
        $v->partyLabelEn = 'Paid to';
        $v->party = 'موظفي الشركة حسب كشف الرواتب المرفق';
        $v->amount = (string) $p->total_net;
        $v->purpose = 'رواتب شهر '.$p->period_month->format('m/Y').' ('.$p->items()->count().' موظف)';
        $v->purposeEn = 'Salaries for '.$p->period_month->format('F Y');
        $v->method = 'bank_transfer';
        $je = JournalEntry::query()->where('source_type', $p->getMorphClass())->where('source_id', $p->id)->where('purpose', 'payment')->first();
        $v->account = self::paidFrom($je);
        $v->lines = [['إجمالي الاستحقاقات', (string) $p->total_gross], ['إجمالي الاستقطاعات', (string) $p->total_deductions], ['الصافي', (string) $p->total_net]];
        $v->signatures = [['أعده (الموارد البشرية)', 'Prepared by (HR)'], ['المحاسب', 'Accountant'], ['المدير العام', 'General Manager']];

        return $v;
    }
}
