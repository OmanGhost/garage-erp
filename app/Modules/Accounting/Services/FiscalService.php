<?php

namespace App\Modules\Accounting\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\FiscalPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FiscalService
{
    /** Ensure a fiscal year (calendar year, 12 monthly periods) exists for the date. */
    public static function ensureYearFor(Carbon|string $date): FiscalYear
    {
        $date = Carbon::parse($date);
        $year = FiscalYear::query()->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->first();
        if ($year) {
            return $year;
        }

        return DB::transaction(function () use ($date) {
            $start = $date->copy()->startOfYear();
            $year = FiscalYear::create([
                'name' => (string) $start->year,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->endOfYear()->toDateString(),
                'status' => 'open',
            ]);
            for ($m = 0; $m < 12; $m++) {
                $p = $start->copy()->addMonths($m);
                FiscalPeriod::create([
                    'fiscal_year_id' => $year->id,
                    'name' => $p->format('Y-m'),
                    'start_date' => $p->toDateString(),
                    'end_date' => $p->copy()->endOfMonth()->toDateString(),
                    'status' => 'open',
                ]);
            }

            return $year;
        });
    }

    public static function assertOpen(Carbon|string $date): void
    {
        $date = Carbon::parse($date);
        $year = self::ensureYearFor($date);
        if ($year->status === 'closed') {
            throw new BusinessException(__('Fiscal year :y is closed. Posting is not allowed.', ['y' => $year->name]));
        }
        $period = FiscalPeriod::query()->where('fiscal_year_id', $year->id)
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->first();
        if ($period && $period->status === 'closed') {
            throw new BusinessException(__('Accounting period :p is closed. Posting is not allowed.', ['p' => $period->name]));
        }
    }

    public static function closePeriod(FiscalPeriod $period): void
    {
        $period->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()]);
        AuditLogger::log('fiscal_period.closed', $period);
    }

    public static function reopenPeriod(FiscalPeriod $period): void
    {
        if ($period->fiscalYear->status === 'closed') {
            throw new BusinessException(__('The fiscal year is closed.'));
        }
        $period->update(['status' => 'open', 'closed_at' => null, 'closed_by' => null]);
        AuditLogger::log('fiscal_period.reopened', $period);
    }

    /**
     * Close the fiscal year: transfer net income to retained earnings and lock all periods.
     */
    public static function closeYear(FiscalYear $year): void
    {
        DB::transaction(function () use ($year) {
            $net = FinancialReports::netIncome($year->start_date->toDateString(), $year->end_date->toDateString());
            $rows = FinancialReports::accountBalances($year->start_date->toDateString(), $year->end_date->toDateString(), ['revenue', 'expense']);
            $lines = [];
            foreach ($rows as $r) {
                $bal = \App\Support\Money::sub($r->debit, $r->credit);
                if (\App\Support\Money::isZero($bal)) {
                    continue;
                }
                // zero the P&L account
                $lines[] = \App\Support\Money::isPositive($bal)
                    ? ['account' => $r->id, 'credit' => $bal]
                    : ['account' => $r->id, 'debit' => \App\Support\Money::neg($bal)];
            }
            if ($lines) {
                $lines[] = \App\Support\Money::isPositive($net)
                    ? ['account' => 'retained_earnings', 'credit' => $net]
                    : ['account' => 'retained_earnings', 'debit' => \App\Support\Money::neg($net)];
                app(PostingService::class)->post([
                    'date' => $year->end_date->toDateString(),
                    'description' => __('Year end closing').' '.$year->name,
                    'source' => $year,
                    'purpose' => 'year_close',
                    'lines' => $lines,
                    'skip_period_check' => true,
                ]);
            }
            $year->periods()->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()]);
            $year->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()]);
            AuditLogger::log('fiscal_year.closed', $year, ['net_income' => $net]);
        });
    }
}
