<?php

namespace App\Modules\Reports\Services;

use App\Modules\Inventory\Services\ReorderService;
use App\Modules\Workshop\Models\JobCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data-driven assistant (runs locally, no paid external service):
 *  - diagnosis suggestions learned from the company's own history
 *    (same DTC codes / similar complaints / same make-model -> services & parts used)
 *  - inventory demand forecasting (exponential smoothing, see ReorderService)
 */
class AiAssistant
{
    /** Suggestions for a job card based on historical similar jobs. */
    public function suggestForJob(JobCard $jc): array
    {
        $c = tenant()->id();
        $out = [];
        $dtcs = DB::table('inspection_findings as f')->join('inspections as i', 'i.id', '=', 'f.inspection_id')
            ->where('i.job_card_id', $jc->id)->whereNotNull('f.dtc_code')->pluck('f.dtc_code')->unique()->all();

        // 1) DTC -> services performed on other jobs with the same code
        if ($dtcs) {
            $rows = DB::table('inspection_findings as f')->join('inspections as i', 'i.id', '=', 'f.inspection_id')
                ->join('job_card_tasks as t', 't.job_card_id', '=', 'i.job_card_id')
                ->where('i.company_id', $c)->where('i.job_card_id', '!=', $jc->id)->whereIn('f.dtc_code', $dtcs)->where('t.status', 'done')
                ->groupBy('f.dtc_code', 't.description')->selectRaw('f.dtc_code, t.description, COUNT(*) n')->orderByDesc('n')->limit(6)->get();
            foreach ($rows as $r) {
                $out[] = ['title' => $r->description, 'reason' => __('Fixed :n times for code :c', ['n' => $r->n, 'c' => $r->dtc_code])];
            }
        }

        // 2) similar complaint keywords on the same make/model
        $words = collect(preg_split('/[\s،,.]+/u', mb_strtolower((string) $jc->customer_complaint)))->filter(fn ($w) => mb_strlen($w) >= 4)->unique()->take(6);
        if ($words->isNotEmpty()) {
            $similar = DB::table('job_cards as j')->join('vehicles as v', 'v.id', '=', 'j.vehicle_id')->where('j.company_id', $c)->where('j.id', '!=', $jc->id)
                ->where('v.vehicle_make_id', $jc->vehicle->vehicle_make_id)
                ->where(function ($q) use ($words) {
                    foreach ($words as $w) {
                        $q->orWhere('j.customer_complaint', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $w).'%');
                    }
                })->limit(200)->pluck('j.id');
            if ($similar->isNotEmpty()) {
                $tasks = DB::table('job_card_tasks')->whereIn('job_card_id', $similar)->where('status', 'done')
                    ->groupBy('description')->selectRaw('description, COUNT(*) n')->orderByDesc('n')->limit(5)->get();
                foreach ($tasks as $t) {
                    $out[] = ['title' => $t->description, 'reason' => __(':n similar complaints on the same make', ['n' => $t->n])];
                }
                $parts = DB::table('job_card_parts as p')->join('parts as pt', 'pt.id', '=', 'p.part_id')->whereIn('p.job_card_id', $similar)->where('p.qty_issued', '>', 0)
                    ->groupBy('pt.id', 'pt.name_ar', 'pt.sku')->selectRaw('pt.name_ar, pt.sku, COUNT(*) n')->orderByDesc('n')->limit(4)->get();
                foreach ($parts as $p) {
                    $out[] = ['title' => $p->sku.' — '.$p->name_ar, 'reason' => __('Part used in :n similar jobs', ['n' => $p->n])];
                }
            }
        }

        // 3) mileage-based maintenance due
        $odo = (int) ($jc->odometer_in ?? $jc->vehicle->odometer);
        if ($odo > 0) {
            foreach ([10000 => __('Oil & filter service'), 40000 => __('Brake pads inspection / replacement'), 60000 => __('Transmission fluid service'), 100000 => __('Spark plugs & timing belt check')] as $km => $label) {
                if ($odo % $km >= $km - 2000 || $odo % $km <= 1000) {
                    $out[] = ['title' => $label, 'reason' => __('Due around :km km (odometer :o)', ['km' => number_format($km), 'o' => number_format($odo)])];
                }
            }
        }

        return collect($out)->unique('title')->take(10)->values()->all();
    }

    /** Free-text symptom search across history (AI page). */
    public function diagnose(string $symptom, ?int $makeId = null, ?string $dtc = null): array
    {
        $c = tenant()->id();
        $words = collect(preg_split('/[\s،,.]+/u', mb_strtolower($symptom)))->filter(fn ($w) => mb_strlen($w) >= 3)->unique()->take(8);
        $q = DB::table('job_cards as j')->join('vehicles as v', 'v.id', '=', 'j.vehicle_id')->where('j.company_id', $c)
            ->when($makeId, fn ($x) => $x->where('v.vehicle_make_id', $makeId));
        if ($dtc) {
            $q->whereExists(fn ($e) => $e->from('inspection_findings as f')->join('inspections as i', 'i.id', '=', 'f.inspection_id')
                ->whereColumn('i.job_card_id', 'j.id')->where('f.dtc_code', strtoupper($dtc)));
        } elseif ($words->isNotEmpty()) {
            $q->where(function ($w) use ($words) {
                foreach ($words as $word) {
                    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';
                    $w->orWhere('j.customer_complaint', 'like', $like)->orWhere('j.diagnosis', 'like', $like);
                }
            });
        }
        $ids = $q->limit(300)->pluck('j.id');
        $total = $ids->count();
        $services = DB::table('job_card_tasks')->whereIn('job_card_id', $ids)->where('status', 'done')->groupBy('description')
            ->selectRaw('description, COUNT(*) n, AVG(actual_minutes) minutes, AVG(line_total) price')->orderByDesc('n')->limit(10)->get();
        $parts = DB::table('job_card_parts as p')->join('parts as pt', 'pt.id', '=', 'p.part_id')->whereIn('p.job_card_id', $ids)->where('p.qty_issued', '>', 0)
            ->groupBy('pt.id', 'pt.sku', 'pt.name_ar')->selectRaw('pt.id, pt.sku, pt.name_ar, COUNT(*) n')->orderByDesc('n')->limit(10)->get();
        $diagnoses = DB::table('job_cards')->whereIn('id', $ids)->whereNotNull('diagnosis')->pluck('diagnosis')
            ->flatMap(fn ($d) => preg_split('/\R/', (string) $d))->map(fn ($l) => trim($l))->filter()->countBy()->sortDesc()->take(8);

        return ['matches' => $total, 'services' => $services, 'parts' => $parts, 'diagnoses' => $diagnoses,
            'confidence' => $total >= 20 ? 'high' : ($total >= 5 ? 'medium' : 'low')];
    }

    /** Top parts by forecast demand for the next month. */
    public function demandForecast(int $limit = 20): array
    {
        $svc = app(ReorderService::class);
        $parts = DB::table('stock_movements')->where('company_id', tenant()->id())->whereIn('type', ['sale', 'job_issue'])
            ->where('moved_at', '>=', now()->subMonths(6))->groupBy('part_id')->selectRaw('part_id, -SUM(qty) q')->orderByDesc('q')->limit($limit)->pluck('part_id');
        $out = [];
        foreach (\App\Modules\Inventory\Models\Part::query()->whereIn('id', $parts)->get() as $p) {
            $hist = $svc->monthlyConsumption($p->id);
            $out[] = ['part' => $p, 'history' => $hist, 'forecast' => $svc->forecast($hist), 'available' => $p->available(),
                'trend' => Str::of(array_sum(array_slice($hist, -3)) >= array_sum(array_slice($hist, 0, 3)) ? 'up' : 'down')->toString()];
        }

        return $out;
    }
}
