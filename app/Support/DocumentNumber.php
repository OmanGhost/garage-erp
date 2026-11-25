<?php

namespace App\Support;

use App\Modules\Core\Models\DocumentSequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free, concurrency-safe document numbering per company & document type.
 * Format: PREFIX-YYYY-000001 (yearly reset) or PREFIX-000001.
 */
class DocumentNumber
{
    public const PREFIXES = [
        'job_card' => 'JC', 'quotation' => 'QT', 'invoice' => 'INV', 'pos' => 'POS', 'credit_note' => 'CN',
        'payment_in' => 'RV', 'payment_out' => 'PV', 'journal' => 'JE', 'purchase_request' => 'PR', 'rfq' => 'RFQ',
        'purchase_order' => 'PO', 'goods_receipt' => 'GRN', 'supplier_invoice' => 'SI', 'landed_cost' => 'LC',
        'purchase_return' => 'PRT', 'stock_transfer' => 'TR', 'stock_count' => 'SC', 'stock_adjustment' => 'ADJ',
        'dismantled_vehicle' => 'DV', 'expense' => 'EXP', 'fund_transfer' => 'FT', 'customer' => 'C', 'supplier' => 'S',
        'employee' => 'EMP', 'insurance_claim' => 'IC', 'warranty_claim' => 'WC', 'appointment' => 'AP', 'store_order' => 'SO',
        'sales_order' => 'SLO', 'payroll' => 'PAY', 'part' => 'P', 'fixed_asset' => 'FA', 'equipment' => 'EQ', 'reception' => 'RC',
        'serial' => 'SN',
    ];

    public static function next(string $type, bool $yearly = true): string
    {
        $attempts = 0;
        while (true) {
            try {
                return DB::transaction(function () use ($type, $yearly) {
                    $seq = DocumentSequence::query()->where('type', $type)->lockForUpdate()->first();
                    if (! $seq) {
                        $seq = DocumentSequence::create([
                            'type' => $type,
                            'prefix' => self::PREFIXES[$type] ?? strtoupper(substr($type, 0, 3)),
                            'next_number' => 1,
                            'padding' => 6,
                            'yearly_reset' => $yearly,
                            'year' => (int) now()->format('Y'),
                        ]);
                        $seq = DocumentSequence::query()->whereKey($seq->id)->lockForUpdate()->first();
                    }
                    $year = (int) now()->format('Y');
                    if ($seq->yearly_reset && (int) $seq->year !== $year) {
                        $seq->year = $year;
                        $seq->next_number = 1;
                    }
                    $n = (int) $seq->next_number;
                    $seq->next_number = $n + 1;
                    $seq->save();

                    return $seq->prefix.'-'.($seq->yearly_reset ? $seq->year.'-' : '').str_pad((string) $n, (int) $seq->padding, '0', STR_PAD_LEFT);
                });
            } catch (QueryException $e) {
                // concurrent first-time creation of the sequence row
                if (++$attempts > 3) {
                    throw $e;
                }
            }
        }
    }
}
