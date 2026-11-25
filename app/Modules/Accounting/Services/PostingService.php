<?php

namespace App\Modules\Accounting\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Double-entry posting engine.
 *
 *  post([
 *    'date' => '2026-01-31', 'description' => '...', 'source' => $invoice, 'purpose' => 'main',
 *    'branch_id' => 1,
 *    'lines' => [
 *       ['account' => 'ar', 'debit' => '10.500', 'party' => $customer],
 *       ['account' => 'sales_parts', 'credit' => '10.000'],
 *       ['account' => 'vat_output', 'credit' => '0.500'],
 *    ],
 *  ]);
 *
 * - accounts may be a system key, an Account model or an id
 * - zero lines are dropped; debit must equal credit
 * - the same (source, purpose) is never posted twice (returns the existing entry)
 * - closed periods reject postings
 */
class PostingService
{
    public function post(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            $date = $data['date'] ?? now()->toDateString();
            $source = $data['source'] ?? null;
            $purpose = $data['purpose'] ?? 'main';
            $sourceType = $source instanceof Model ? $source->getMorphClass() : ($data['source_type'] ?? null);
            $sourceId = $source instanceof Model ? $source->getKey() : ($data['source_id'] ?? null);

            if ($sourceType && $sourceId) {
                $existing = JournalEntry::query()->where('source_type', $sourceType)->where('source_id', $sourceId)
                    ->where('purpose', $purpose)->lockForUpdate()->first();
                if ($existing) {
                    return $existing; // idempotent
                }
            }

            if (empty($data['skip_period_check'])) {
                FiscalService::assertOpen($date);
            }

            $lines = [];
            $dr = $cr = '0';
            foreach ($data['lines'] as $l) {
                $debit = Money::round($l['debit'] ?? 0);
                $credit = Money::round($l['credit'] ?? 0);
                // normalise negative amounts to the other side
                if (Money::cmp($debit, 0) < 0) {
                    $credit = Money::add($credit, Money::neg($debit));
                    $debit = '0.000';
                }
                if (Money::cmp($credit, 0) < 0) {
                    $debit = Money::add($debit, Money::neg($credit));
                    $credit = '0.000';
                }
                if (Money::isZero($debit) && Money::isZero($credit)) {
                    continue;
                }
                $accountId = $this->resolveAccount($l['account']);
                [$pType, $pId] = $this->party($l['party'] ?? null);
                $lines[] = [
                    'account_id' => $accountId,
                    'debit' => $debit,
                    'credit' => $credit,
                    'cost_center_id' => $l['cost_center_id'] ?? null,
                    'branch_id' => $l['branch_id'] ?? $data['branch_id'] ?? tenant()->branchId(),
                    'party_type' => $pType,
                    'party_id' => $pId,
                    'description' => isset($l['description']) ? mb_substr($l['description'], 0, 250) : null,
                ];
                $dr = Money::add($dr, $debit);
                $cr = Money::add($cr, $credit);
            }

            if (! $lines) {
                throw new BusinessException(__('Journal entry has no amounts.'));
            }
            if (Money::cmp($dr, $cr) !== 0) {
                throw new BusinessException(__('Journal entry is not balanced (debit :d / credit :c).', ['d' => $dr, 'c' => $cr]));
            }

            $entry = new JournalEntry([
                'number' => DocumentNumber::next('journal'),
                'date' => $date,
                'description' => isset($data['description']) ? mb_substr($data['description'], 0, 250) : null,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'purpose' => $purpose,
                'status' => 'draft',
                'is_opening' => (bool) ($data['is_opening'] ?? false),
                'total_debit' => $dr,
                'total_credit' => $cr,
                'branch_id' => $data['branch_id'] ?? tenant()->branchId(),
                'created_by' => auth()->id(),
                'reversal_of_id' => $data['reversal_of_id'] ?? null,
            ]);
            $entry->save();
            foreach ($lines as $line) {
                $line['journal_entry_id'] = $entry->id;
                $line['company_id'] = $entry->company_id;
                JournalLine::create($line);
            }
            $entry->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()])->save();

            return $entry;
        });
    }

    /** Reverse a posted entry with a mirrored entry dated $date (default: today). */
    public function reverse(JournalEntry $entry, ?string $date = null, string $reason = ''): JournalEntry
    {
        return DB::transaction(function () use ($entry, $date, $reason) {
            $entry = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->status !== 'posted') {
                throw new BusinessException(__('Only posted entries can be reversed.'));
            }
            $lines = $entry->lines()->get()->map(fn ($l) => [
                'account' => $l->account_id,
                'debit' => $l->credit,
                'credit' => $l->debit,
                'party' => $l->party_type ? [$l->party_type, $l->party_id] : null,
                'cost_center_id' => $l->cost_center_id,
                'branch_id' => $l->branch_id,
                'description' => $l->description,
            ])->all();

            $rev = $this->post([
                'date' => $date ?? now()->toDateString(),
                'description' => __('Reversal of').' '.$entry->number.($reason ? ' - '.$reason : ''),
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'purpose' => 'reversal:'.$entry->id,
                'branch_id' => $entry->branch_id,
                'lines' => $lines,
                'reversal_of_id' => $entry->id,
            ]);
            $entry->forceFill(['status' => 'reversed', 'reversed_by_id' => $rev->id])->save();
            AuditLogger::log('journal.reversed', $entry, ['reversal' => $rev->number, 'reason' => $reason]);

            return $rev;
        });
    }

    protected function resolveAccount(mixed $account): int
    {
        if ($account instanceof Account) {
            $id = $account->id;
        } elseif (is_numeric($account)) {
            $id = (int) $account;
        } else {
            return Accounts::id((string) $account);
        }
        $acc = Account::query()->find($id);
        if (! $acc) {
            throw new BusinessException(__('Account not found.'));
        }
        if ($acc->is_group) {
            throw new BusinessException(__('Cannot post to group account :a', ['a' => $acc->label()]));
        }

        return $id;
    }

    /** @return array{0:?string,1:?int} */
    protected function party(mixed $party): array
    {
        if ($party instanceof Model) {
            return [$party->getMorphClass(), $party->getKey()];
        }
        if (is_array($party) && count($party) === 2) {
            return [$party[0], (int) $party[1]];
        }

        return [null, null];
    }
}
