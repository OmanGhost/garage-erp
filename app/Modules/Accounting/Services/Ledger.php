<?php

namespace App\Modules\Accounting\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Ledger queries (party balances / statements / account ledgers).
 * All queries filter by the current company explicitly.
 */
class Ledger
{
    public static function base()
    {
        return DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.company_id', tenant()->id())
            ->whereIn('e.status', ['posted', 'reversed']);
    }

    /** debit - credit for a party (customer AR positive = owes us). */
    public static function partyBalance(string $partyType, int $partyId, ?string $until = null): string
    {
        $q = static::base()->where('l.party_type', $partyType)->where('l.party_id', $partyId);
        if ($until) {
            $q->where('e.date', '<=', $until);
        }
        $r = $q->selectRaw('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')->first();

        return Money::sub($r->d, $r->c);
    }

    /** Statement rows with running balance. */
    public static function partyStatement(string $partyType, int $partyId, ?string $from = null, ?string $to = null): array
    {
        $opening = $from ? static::partyBalance($partyType, $partyId, date('Y-m-d', strtotime($from.' -1 day'))) : '0.000';
        $q = static::base()->where('l.party_type', $partyType)->where('l.party_id', $partyId)
            ->select('e.date', 'e.number', 'e.description', 'e.source_type', 'e.source_id', 'l.debit', 'l.credit', 'l.description as line_description')
            ->orderBy('e.date')->orderBy('e.id');
        if ($from) {
            $q->where('e.date', '>=', $from);
        }
        if ($to) {
            $q->where('e.date', '<=', $to);
        }
        $bal = $opening;
        $rows = [];
        foreach ($q->get() as $r) {
            $bal = Money::add($bal, Money::sub($r->debit, $r->credit));
            $r->balance = $bal;
            $rows[] = $r;
        }

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $bal];
    }

    public static function accountBalance(int $accountId, ?string $from = null, ?string $to = null): string
    {
        $q = static::base()->where('l.account_id', $accountId);
        if ($from) {
            $q->where('e.date', '>=', $from);
        }
        if ($to) {
            $q->where('e.date', '<=', $to);
        }
        $r = $q->selectRaw('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')->first();

        return Money::sub($r->d, $r->c);
    }

    public static function accountLedger(int $accountId, ?string $from = null, ?string $to = null): array
    {
        $opening = $from ? static::accountBalance($accountId, null, date('Y-m-d', strtotime($from.' -1 day'))) : '0.000';
        $q = static::base()->where('l.account_id', $accountId)
            ->select('e.id as entry_id', 'e.date', 'e.number', 'e.description', 'l.debit', 'l.credit', 'l.party_type', 'l.party_id', 'l.description as line_description')
            ->orderBy('e.date')->orderBy('e.id');
        if ($from) {
            $q->where('e.date', '>=', $from);
        }
        if ($to) {
            $q->where('e.date', '<=', $to);
        }
        $bal = $opening;
        $rows = [];
        foreach ($q->get() as $r) {
            $bal = Money::add($bal, Money::sub($r->debit, $r->credit));
            $r->balance = $bal;
            $rows[] = $r;
        }

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $bal];
    }
}
