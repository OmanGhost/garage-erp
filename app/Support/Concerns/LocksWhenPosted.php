<?php

namespace App\Support\Concerns;

use App\Exceptions\BusinessException;

/**
 * Posted financial documents are immutable: only whitelisted bookkeeping
 * columns (paid amount, status, links) may change, and they cannot be deleted.
 * Corrections are made through reversal / credit notes.
 *
 * Models may define:
 *   protected array $lockedStatuses = [...];
 *   protected array $mutableWhenLocked = [...];
 */
trait LocksWhenPosted
{
    public static function bootLocksWhenPosted(): void
    {
        static::updating(function ($model) {
            if (! $model->wasLocked()) {
                return;
            }
            $allowed = array_merge(['status', 'updated_at', 'paid_amount'], $model->mutableWhenLocked ?? []);
            $dirty = array_diff(array_keys($model->getDirty()), $allowed);
            if ($dirty) {
                throw new BusinessException(__('Posted documents cannot be modified. Use a reversal or credit note instead.').' ('.implode(', ', $dirty).')');
            }
        });

        static::deleting(function ($model) {
            if ($model->wasLocked()) {
                throw new BusinessException(__('Posted documents cannot be deleted. Use a reversal or credit note instead.'));
            }
        });
    }

    public function wasLocked(): bool
    {
        $statuses = $this->lockedStatuses ?? ['posted', 'paid', 'partially_paid', 'reversed', 'cancelled'];

        return in_array($this->getOriginal('status'), $statuses, true);
    }

    public function isLocked(): bool
    {
        $statuses = $this->lockedStatuses ?? ['posted', 'paid', 'partially_paid', 'reversed', 'cancelled'];

        return in_array($this->status, $statuses, true);
    }
}
