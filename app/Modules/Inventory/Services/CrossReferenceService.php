<?php

namespace App\Modules\Inventory\Services;

use App\Exceptions\BusinessException;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\PartAlternative;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Part cross reference (interchange): parts are interchangeable when they share a factory number (OEM) or are
 * linked as alternatives (aftermarket brands, superseded numbers, used / remanufactured equivalents). Numbers are
 * compared without spaces, dashes and dots, so "04152-YZZA1" matches "04152YZZA1".
 */
class CrossReferenceService
{
    public const QUALITIES = ['oem', 'oes', 'aftermarket', 'reman', 'used'];

    public static function normalize(?string $n): string
    {
        return strtoupper(preg_replace('/[\s\-\.\/_]+/', '', (string) $n));
    }

    protected function normSql(string $col): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(UPPER($col), ' ', ''), '-', ''), '.', ''), '/', '')";
    }

    /** Parts whose OEM number, SKU or alternative numbers match one of the numbers. */
    protected function partsByNumbers(array $numbers): Collection
    {
        $numbers = array_values(array_unique(array_filter(array_map([self::class, 'normalize'], $numbers))));
        if (! $numbers) {
            return collect();
        }
        $marks = implode(',', array_fill(0, count($numbers), '?'));
        $viaAlt = DB::table('part_alternatives')->whereRaw($this->normSql('number')." IN ($marks)", $numbers)
            ->whereIn('part_id', Part::query()->select('id'))->pluck('part_id');

        return Part::query()->where(fn ($q) => $q->whereRaw($this->normSql('oem_number')." IN ($marks)", $numbers)
            ->orWhereRaw($this->normSql('sku')." IN ($marks)", $numbers)->orWhereIn('id', $viaAlt))->get();
    }

    /**
     * The interchange group of a part (itself included), with stock: walks shared numbers and alternative links.
     *
     * @return Collection<int, Part>
     */
    public function group(Part $part, int $depth = 3): Collection
    {
        $found = collect([$part->id => $part]);
        $frontier = collect([$part]);
        for ($i = 0; $i < $depth && $frontier->isNotEmpty(); $i++) {
            $ids = $frontier->pluck('id')->all();
            $alts = PartAlternative::query()->whereIn('part_id', $ids)->orWhereIn('alternative_part_id', $ids)->get();
            $numbers = $frontier->pluck('oem_number')->merge($alts->pluck('number'))->filter()->all();
            $linked = $alts->pluck('alternative_part_id')->merge($alts->pluck('part_id'))->filter()->unique()->diff($found->keys());
            $next = $this->partsByNumbers($numbers)->merge($linked->isNotEmpty() ? Part::query()->whereIn('id', $linked)->get() : collect())
                ->reject(fn ($p) => $found->has($p->id))->keyBy('id');
            $found = $found->union($next);
            $frontier = $next->values();
        }
        $ids = $found->keys()->all();
        $stock = DB::table('stock_levels')->whereIn('part_id', $ids)->groupBy('part_id')
            ->selectRaw('part_id, SUM(qty_on_hand) on_hand, SUM(qty_reserved) reserved')->get()->keyBy('part_id');
        $rank = ['genuine' => 0, 'oem' => 0, 'aftermarket' => 1, 'refurbished' => 2, 'used' => 3, 'damaged' => 4];

        return Part::query()->with('brand')->whereIn('id', $ids)->get()->each(function ($p) use ($stock) {
            $p->on_hand = (string) ($stock[$p->id]->on_hand ?? 0);
            $p->reserved = (string) ($stock[$p->id]->reserved ?? 0);
        })->sortBy(fn ($p) => [($p->id === $part->id ? -1 : ($rank[$p->part_type] ?? 5)), -(float) $p->on_hand])->values();
    }

    /** Free search by any number: the parts found and their interchange groups merged. */
    public function search(string $number): Collection
    {
        $hits = $this->partsByNumbers([$number]);
        $out = collect();
        foreach ($hits as $p) {
            $out = $out->merge($this->group($p))->unique('id');
        }

        return $out->values();
    }

    /** Numbers known for the group (for display): OEM numbers and alternatives with brand and quality. */
    public function numbers(Collection $group): Collection
    {
        $ids = $group->pluck('id');

        return PartAlternative::query()->whereIn('part_id', $ids)->get(['number', 'brand', 'type', 'quality'])
            ->merge($group->filter(fn ($p) => $p->oem_number)->map(fn ($p) => new PartAlternative(['number' => $p->oem_number, 'brand' => $p->brand?->name, 'type' => 'oem', 'quality' => 'oem'])))
            ->unique(fn ($a) => self::normalize($a->number))->values();
    }

    public function link(Part $part, array $data): PartAlternative
    {
        $altPart = ! empty($data['alternative_part_id']) ? Part::query()->findOrFail($data['alternative_part_id']) : null;
        if ($altPart && $altPart->id === $part->id) {
            throw new BusinessException(__('A part cannot be its own alternative.'));
        }
        $number = trim((string) ($data['number'] ?? '')) ?: ($altPart?->oem_number ?: $altPart?->sku);
        if (! $number) {
            throw new BusinessException(__('Enter the alternative number or choose a part.'));
        }

        return $part->alternatives()->create([
            'number' => $number, 'alternative_part_id' => $altPart?->id, 'brand' => $data['brand'] ?? $altPart?->brand?->name,
            'type' => in_array($data['type'] ?? null, ['alternative', 'superseded', 'cross_reference'], true) ? $data['type'] : 'alternative',
            'quality' => in_array($data['quality'] ?? null, self::QUALITIES, true) ? $data['quality'] : null, 'notes' => $data['notes'] ?? null,
        ]);
    }
}
