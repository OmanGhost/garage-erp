<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Modules\Workshop\Models\Inspection;
use App\Modules\Workshop\Models\InspectionFinding;
use App\Modules\Workshop\Models\InspectionTemplate;
use App\Modules\Workshop\Models\JobCard;
use Illuminate\Support\Facades\DB;

class InspectionService
{
    public function __construct(protected JobCardService $jobCards) {}

    public function start(JobCard $jc, ?int $templateId = null, ?int $technicianId = null): Inspection
    {
        return DB::transaction(function () use ($jc, $templateId, $technicianId) {
            $open = $jc->inspections()->where('status', 'in_progress')->first();
            if ($open) {
                return $open;
            }
            $template = $templateId ? InspectionTemplate::query()->with('items')->find($templateId)
                : InspectionTemplate::query()->with('items')->where('is_active', true)
                    ->where(fn ($q) => $q->where('vehicle_type', $jc->vehicle->vehicle_type)->orWhereNull('vehicle_type'))
                    ->orderByRaw('vehicle_type IS NULL')->first();
            $inspection = Inspection::create([
                'job_card_id' => $jc->id, 'inspection_template_id' => $template?->id,
                'technician_id' => $technicianId ?? auth()->id(), 'status' => 'in_progress', 'started_at' => now(),
            ]);
            foreach ($template?->items?->sortBy('sort') ?? [] as $item) {
                $inspection->results()->create([
                    'template_item_id' => $item->id, 'section' => $item->section,
                    'label' => app()->getLocale() === 'en' && $item->label_en ? $item->label_en : $item->label, 'result' => 'ok',
                ]);
            }
            if (in_array($jc->status, [JobCard::PENDING_INSPECTION, JobCard::ON_HOLD], true)) {
                $this->jobCards->changeStatus($jc, JobCard::UNDER_INSPECTION, __('Inspection started'), true);
            }

            return $inspection;
        });
    }

    /** @param array<int,array{result:string,notes?:string}> $results */
    public function saveResults(Inspection $inspection, array $results, ?string $summary = null, ?string $recommendations = null): void
    {
        $this->assertOpen($inspection);
        foreach ($results as $id => $r) {
            $inspection->results()->whereKey($id)->update([
                'result' => in_array($r['result'] ?? 'ok', ['ok', 'attention', 'urgent', 'na'], true) ? $r['result'] : 'ok',
                'notes' => $r['notes'] ?? null,
            ]);
        }
        $inspection->update(array_filter(['summary' => $summary, 'recommendations' => $recommendations], fn ($v) => $v !== null));
    }

    public function addFinding(Inspection $inspection, array $data): InspectionFinding
    {
        $this->assertOpen($inspection);

        return $inspection->findings()->create([
            'description' => $data['description'],
            'dtc_code' => isset($data['dtc_code']) ? strtoupper(trim($data['dtc_code'])) : null,
            'severity' => $data['severity'] ?? 'medium',
            'service_id' => $data['service_id'] ?? null,
            'part_id' => $data['part_id'] ?? null,
            'qty' => $data['qty'] ?? 1,
            'estimated_price' => $data['estimated_price'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function complete(Inspection $inspection): void
    {
        DB::transaction(function () use ($inspection) {
            $inspection->update(['status' => 'completed', 'completed_at' => now()]);
            $jc = $inspection->jobCard;
            $diag = $inspection->findings->map(fn ($f) => ($f->dtc_code ? "[{$f->dtc_code}] " : '').$f->description)->implode("\n");
            $jc->forceFill(['diagnosis' => trim(($jc->diagnosis ? $jc->diagnosis."\n" : '').$diag)])->save();
            if (in_array($jc->status, [JobCard::UNDER_INSPECTION, JobCard::PENDING_INSPECTION], true)) {
                $this->jobCards->changeStatus($jc, JobCard::AWAITING_QUOTATION, __('Inspection completed'), true);
            }
        });
    }

    protected function assertOpen(Inspection $inspection): void
    {
        if ($inspection->status === 'completed') {
            throw new BusinessException(__('This inspection is already completed.'));
        }
    }
}
