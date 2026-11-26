<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Modules\Core\Models\NotificationTemplate;
use App\Modules\Core\Services\NotificationService;
use App\Modules\Workshop\Models\Inspection;
use App\Modules\Workshop\Models\InspectionFinding;
use App\Modules\Workshop\Models\InspectionTemplate;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\Service;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Digital vehicle inspection (DVI): checklist with green / yellow / red results and measurements, findings
 * placed on the vehicle diagram with photos, suggested services, before / after repair inspections,
 * technician and customer signatures, and an interactive report link where the customer approves or declines
 * each recommended repair. Approved findings become the quotation.
 */
class InspectionService
{
    public function __construct(protected JobCardService $jobCards) {}

    public function start(JobCard $jc, ?int $templateId = null, ?int $technicianId = null, string $stage = 'before'): Inspection
    {
        $stage = $stage === 'after' ? 'after' : 'before';

        return DB::transaction(function () use ($jc, $templateId, $technicianId, $stage) {
            $open = $jc->inspections()->where('status', 'in_progress')->where('stage', $stage)->first();
            if ($open) {
                return $open;
            }
            // the after-repair inspection repeats the checklist of the first one, for comparison
            $before = $stage === 'after' ? $jc->inspections()->where('stage', 'before')->latest('id')->first() : null;
            $templateId ??= $before?->inspection_template_id;
            $template = $templateId ? InspectionTemplate::query()->with('items')->find($templateId)
                : InspectionTemplate::query()->with('items')->where('is_active', true)->where('kind', 'inspection')
                    ->where(fn ($q) => $q->where('vehicle_type', $jc->vehicle->vehicle_type)->orWhereNull('vehicle_type'))
                    ->orderByRaw('vehicle_type IS NULL')->first();
            $inspection = Inspection::create([
                'job_card_id' => $jc->id, 'inspection_template_id' => $template?->id, 'stage' => $stage,
                'technician_id' => $technicianId ?? auth()->id(), 'status' => 'in_progress', 'started_at' => now(),
            ]);
            foreach ($template?->items?->sortBy('sort') ?? [] as $item) {
                $inspection->results()->create([
                    'template_item_id' => $item->id, 'section' => $item->section, 'unit' => $item->unit,
                    'label' => app()->getLocale() === 'en' && $item->label_en ? $item->label_en : $item->label, 'result' => 'ok',
                ]);
            }
            if ($stage === 'before' && in_array($jc->status, [JobCard::PENDING_INSPECTION, JobCard::ON_HOLD], true)) {
                $this->jobCards->changeStatus($jc, JobCard::UNDER_INSPECTION, __('Inspection started'), true);
            }

            return $inspection;
        });
    }

    /** @param array<int,array{result?:string,notes?:string,value?:mixed}> $results */
    public function saveResults(Inspection $inspection, array $results, ?string $summary = null, ?string $recommendations = null): void
    {
        $this->assertOpen($inspection);
        $rows = $inspection->results()->with('templateItem')->get()->keyBy('id');
        foreach ($results as $id => $r) {
            $row = $rows->get($id);
            if (! $row) {
                continue;
            }
            $value = isset($r['value']) && $r['value'] !== '' && is_numeric($r['value']) ? $r['value'] : null;
            $result = in_array($r['result'] ?? 'ok', ['ok', 'attention', 'urgent', 'na'], true) ? ($r['result'] ?? 'ok') : 'ok';
            // a measurement outside the limits of the checklist item sets the colour (the worse of the two wins)
            if ($value !== null && ($judged = $row->templateItem?->judge($value))) {
                $rank = ['na' => 0, 'ok' => 1, 'attention' => 2, 'urgent' => 3];
                $result = $rank[$judged] > $rank[$result] ? $judged : $result;
            }
            $row->update(['result' => $result, 'notes' => $r['notes'] ?? null, 'value' => $value]);
        }
        $inspection->update(array_filter(['summary' => $summary, 'recommendations' => $recommendations], fn ($v) => $v !== null));
    }

    /** @param array<int, UploadedFile> $photos */
    public function addFinding(Inspection $inspection, array $data, array $photos = []): InspectionFinding
    {
        $this->assertOpen($inspection);
        $zone = isset($data['zone']) && isset(Inspection::ZONES[$data['zone']]) ? $data['zone'] : null;
        $finding = $inspection->findings()->create([
            'description' => $data['description'], 'zone' => $zone, 'result_id' => $data['result_id'] ?? null,
            'dtc_code' => ! empty($data['dtc_code']) ? strtoupper(trim($data['dtc_code'])) : null,
            'severity' => $data['severity'] ?? 'medium',
            'service_id' => $data['service_id'] ?? null,
            'part_id' => $data['part_id'] ?? null,
            'qty' => $data['qty'] ?? 1,
            'estimated_price' => $data['estimated_price'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);
        foreach ($photos as $photo) {
            \App\Modules\Core\Services\FileStorage::attach($finding, $photo, 'inspection', $zone ? Inspection::zoneLabel($zone) : null);
        }

        return $finding;
    }

    /**
     * Recommended services: every yellow / red checklist item whose template item is linked to a service
     * becomes a finding (once), priced at the service price for this vehicle.
     */
    public function suggest(Inspection $inspection): int
    {
        $this->assertOpen($inspection);
        $jc = $inspection->jobCard;
        $done = $inspection->findings()->whereNotNull('result_id')->pluck('result_id')->all();
        $n = 0;
        foreach ($inspection->results()->with('templateItem.service')->whereIn('result', ['attention', 'urgent'])->get() as $r) {
            $service = $r->templateItem?->service;
            if (! $service || in_array($r->id, $done, true)) {
                continue;
            }
            $inspection->findings()->create([
                'description' => $r->label.($r->value !== null ? ' ('.rtrim(rtrim((string) $r->value, '0'), '.').' '.$r->unit.')' : '').($r->notes ? ' — '.$r->notes : ''),
                'result_id' => $r->id, 'severity' => $r->result === 'urgent' ? 'high' : 'medium', 'service_id' => $service->id, 'qty' => 1,
                'estimated_price' => $service->priceFor($jc->vehicle->vehicle_type),
            ]);
            $n++;
        }

        return $n;
    }

    public function complete(Inspection $inspection): void
    {
        DB::transaction(function () use ($inspection) {
            $inspection->update(['status' => 'completed', 'completed_at' => now()]);
            if ($inspection->stage === 'after') {
                return;
            }
            $jc = $inspection->jobCard;
            $diag = $inspection->findings->map(fn ($f) => ($f->dtc_code ? "[{$f->dtc_code}] " : '').$f->description)->implode("\n");
            $jc->forceFill(['diagnosis' => trim(($jc->diagnosis ? $jc->diagnosis."\n" : '').$diag)])->save();
            if (in_array($jc->status, [JobCard::UNDER_INSPECTION, JobCard::PENDING_INSPECTION], true)) {
                $this->jobCards->changeStatus($jc, JobCard::AWAITING_QUOTATION, __('Inspection completed'), true);
            }
        });
    }

    /* ---------------- report link, decisions, signatures ---------------- */

    /** Create the report link and send it to the customer (SMS / WhatsApp / e-mail when configured). Returns the link. */
    public function publish(Inspection $inspection, bool $notify = true): string
    {
        if (! $inspection->public_token) {
            $inspection->forceFill(['public_token' => Str::random(40)])->save();
        }
        $inspection->forceFill(['sent_at' => now()])->save();
        $jc = $inspection->jobCard;
        if ($notify) {
            $this->ensureTemplate();
            app(NotificationService::class)->notifyCustomer($jc->customer, 'inspection.report', [
                'number' => $jc->number, 'plate' => $jc->vehicle->plate(), 'link' => $inspection->publicUrl(),
            ], [$jc->customer->preferred_channel ?: 'sms'], $inspection);
        }
        AuditLogger::log('inspection.sent', $inspection, ['job_card' => $jc->number]);

        return $inspection->publicUrl();
    }

    /** Message template for existing companies (new companies get it when they are created). */
    protected function ensureTemplate(): void
    {
        foreach (['mail', 'sms', 'whatsapp', 'database'] as $channel) {
            NotificationTemplate::query()->firstOrCreate(['event' => 'inspection.report', 'channel' => $channel], [
                'subject_ar' => 'تقرير فحص المركبة', 'subject_en' => 'Vehicle inspection report',
                'body_ar' => 'عزيزي {{customer_name}}، تقرير فحص مركبتكم {{plate}} (أمر العمل {{number}}) جاهز مع الصور والتوصيات. اطلع عليه ووافق على الإصلاحات: {{link}}',
                'body_en' => 'Dear {{customer_name}}, the inspection report of your vehicle {{plate}} (job {{number}}) is ready with photos and recommendations. View and approve repairs: {{link}}',
            ]);
        }
    }

    /** Customer approves or declines a recommended repair (report link or in person). */
    public function decide(InspectionFinding $finding, string $decision): void
    {
        if (! in_array($decision, ['approved', 'declined'], true)) {
            throw new BusinessException(__('Invalid decision.'));
        }
        $jc = $finding->inspection->jobCard;
        if ($jc->isClosed()) {
            throw new BusinessException(__('The job card is closed.'));
        }
        $finding->update(['customer_decision' => $decision, 'decided_at' => now()]);
    }

    /** Store a drawn signature (PNG) for the technician or the customer. */
    public function sign(Inspection $inspection, string $who, string $png, ?string $name = null): void
    {
        if (strlen($png) > 1024 * 1024 || ! str_starts_with($png, "\x89PNG")) {
            throw new BusinessException(__('The signature could not be read. Please sign again.'));
        }
        $path = 'signatures/'.$inspection->company_id.'/'.Str::uuid().'.png';
        Storage::disk('local')->put($path, $png);
        $inspection->forceFill($who === 'customer'
            ? ['customer_signature' => $path, 'customer_signed_name' => $name ?: $inspection->jobCard->customer->name, 'customer_signed_at' => now()]
            : ['technician_signature' => $path, 'technician_signed_at' => now()])->save();
        AuditLogger::log('inspection.signed', $inspection, ['by' => $who]);
    }

    public static function signatureData(?string $path): ?string
    {
        return $path && Storage::disk('local')->exists($path) ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($path)) : null;
    }

    /** Pairs of checklist items before / after repair (matched by template item, else by label). */
    public function compare(Inspection $before, Inspection $after): array
    {
        $afterRows = $after->results->keyBy(fn ($r) => $r->template_item_id ?: 'l:'.$r->label);
        $rows = [];
        foreach ($before->results as $r) {
            $a = $afterRows->get($r->template_item_id ?: 'l:'.$r->label);
            $rank = ['na' => 0, 'ok' => 1, 'attention' => 2, 'urgent' => 3];
            $trend = $a ? (($rank[$a->result] ?? 0) < ($rank[$r->result] ?? 0) ? 'better' : (($rank[$a->result] ?? 0) > ($rank[$r->result] ?? 0) ? 'worse' : 'same')) : 'missing';
            $rows[] = ['section' => $r->section, 'label' => $r->label, 'before' => $r, 'after' => $a, 'trend' => $trend];
        }

        return $rows;
    }

    protected function assertOpen(Inspection $inspection): void
    {
        if ($inspection->status === 'completed') {
            throw new BusinessException(__('This inspection is already completed.'));
        }
    }
}
