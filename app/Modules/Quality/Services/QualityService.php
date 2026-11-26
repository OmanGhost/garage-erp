<?php

namespace App\Modules\Quality\Services;

use App\Exceptions\BusinessException;
use App\Modules\Quality\Models\CapaAction;
use App\Modules\Quality\Models\Nonconformity;
use App\Modules\Quality\Models\QualityAudit;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\QualityCheck;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Quality management: nonconformities (NCR) with root cause and corrective / preventive actions (CAPA),
 * internal audits, comeback (repeat repair) detection and the quality indicators of the dashboard.
 */
class QualityService
{
    public const COMEBACK_DAYS = 30;

    public function openNcr(array $data): Nonconformity
    {
        $ncr = Nonconformity::create($data + [
            'number' => DocumentNumber::next('nonconformity'), 'detected_at' => today()->toDateString(), 'detected_by' => auth()->id(),
            'status' => 'open', 'branch_id' => tenant()->branchId(),
        ]);
        AuditLogger::log('quality.ncr_opened', $ncr, ['number' => $ncr->number, 'source' => $ncr->source]);

        return $ncr;
    }

    /** A failed quality check opens a nonconformity against the job card (workmanship). */
    public function fromFailedQc(QualityCheck $qc): Nonconformity
    {
        $jc = $qc->jobCard;
        $tech = $jc->tasks()->whereNotNull('technician_id')->latest('finished_at')->value('technician_id');

        return $this->openNcr([
            'source' => 'qc_fail', 'job_card_id' => $jc->id, 'technician_id' => $tech, 'category' => 'workmanship', 'severity' => 'minor',
            'title' => __('Quality check failed: :n', ['n' => $jc->number]), 'description' => $qc->notes,
        ]);
    }

    public function update(Nonconformity $ncr, array $data): void
    {
        $this->assertOpen($ncr);
        $ncr->update($data);
        if ($ncr->status === 'open' && filled($ncr->root_cause)) {
            $ncr->update(['status' => 'investigating']);
        }
    }

    public function addAction(Nonconformity $ncr, array $data): CapaAction
    {
        $this->assertOpen($ncr);
        $a = $ncr->actions()->create($data + ['status' => 'open']);
        if (in_array($ncr->status, ['open', 'investigating'], true)) {
            $ncr->update(['status' => 'action']);
        }

        return $a;
    }

    public function completeAction(CapaAction $a): void
    {
        if (! in_array($a->status, ['open', 'in_progress'], true)) {
            throw new BusinessException(__('This action is already completed.'));
        }
        $a->update(['status' => 'done', 'completed_at' => now()]);
        $this->syncStatus($a->nonconformity);
    }

    /** Effectiveness check by someone else than the owner. */
    public function verifyAction(CapaAction $a, bool $effective, ?string $notes = null): void
    {
        if ($a->status !== 'done') {
            throw new BusinessException(__('Complete the action before verifying it.'));
        }
        $a->update(['status' => $effective ? 'verified' : 'open', 'effective' => $effective, 'verification_notes' => $notes,
            'verified_by' => auth()->id(), 'verified_at' => now(), 'completed_at' => $effective ? $a->completed_at : null]);
        $this->syncStatus($a->nonconformity);
    }

    protected function syncStatus(Nonconformity $ncr): void
    {
        $actions = $ncr->actions()->where('status', '!=', 'cancelled')->get();
        if ($actions->isNotEmpty() && $actions->every(fn ($x) => in_array($x->status, ['done', 'verified'], true))) {
            $ncr->update(['status' => 'verification']);
        } elseif ($ncr->status === 'verification') {
            $ncr->update(['status' => 'action']);
        }
    }

    public function close(Nonconformity $ncr): void
    {
        $this->assertOpen($ncr);
        if (blank($ncr->root_cause)) {
            throw new BusinessException(__('Record the root cause before closing.'));
        }
        $open = $ncr->actions()->whereNotIn('status', ['verified', 'cancelled'])->count();
        if ($open || ! $ncr->actions()->where('status', 'verified')->exists()) {
            throw new BusinessException(__('All corrective actions must be completed and verified as effective before closing.'));
        }
        $ncr->update(['status' => 'closed', 'closed_at' => now()]);
        AuditLogger::log('quality.ncr_closed', $ncr, ['number' => $ncr->number]);
    }

    protected function assertOpen(Nonconformity $ncr): void
    {
        if ($ncr->status === 'closed') {
            throw new BusinessException(__('This nonconformity is closed.'));
        }
    }

    /* ---------------- internal audits ---------------- */

    /** @param array<int, array{result?: string, notes?: string}> $answers */
    public function recordAudit(QualityAudit $audit, array $answers, ?string $summary, bool $complete): void
    {
        if ($audit->status === 'completed') {
            throw new BusinessException(__('This audit is completed.'));
        }
        DB::transaction(function () use ($audit, $answers, $summary, $complete) {
            $list = $audit->checklist ?? [];
            foreach ($list as $i => $row) {
                $ans = $answers[$i] ?? [];
                $list[$i]['result'] = in_array($ans['result'] ?? null, ['conform', 'nonconform', 'observation', 'na'], true) ? $ans['result'] : ($row['result'] ?? null);
                $list[$i]['notes'] = $ans['notes'] ?? ($row['notes'] ?? null);
            }
            $scored = collect($list)->whereIn('result', ['conform', 'nonconform']);
            $score = $scored->count() ? round($scored->where('result', 'conform')->count() * 100 / $scored->count(), 1) : null;
            $audit->update(['checklist' => $list, 'summary' => $summary, 'score' => $score, 'status' => $complete ? 'completed' : 'in_progress',
                'performed_at' => $audit->performed_at ?? now(), 'auditor_id' => $audit->auditor_id ?? auth()->id()]);
            if ($complete) {
                foreach ($list as $row) {
                    if (($row['result'] ?? null) === 'nonconform') {
                        $this->openNcr(['source' => 'internal_audit', 'quality_audit_id' => $audit->id, 'category' => 'procedure', 'severity' => 'minor',
                            'title' => mb_substr($audit->number.': '.$row['q'], 0, 250), 'description' => $row['notes'] ?? null]);
                    }
                }
            }
        });
    }

    /* ---------------- indicators ---------------- */

    /** Job cards on the same vehicle within N days after a completed one (repeat repair = comeback). */
    public function comebacks(Carbon $from, Carbon $to)
    {
        return JobCard::query()->with('vehicle', 'customer')->whereBetween('received_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', '!=', JobCard::CANCELLED)->where('job_type', '!=', 'maintenance')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('job_cards as p')->whereColumn('p.vehicle_id', 'job_cards.vehicle_id')
                ->whereColumn('p.id', '<', 'job_cards.id')->whereNotNull('p.completed_at')->where('p.status', '!=', JobCard::CANCELLED)
                ->whereRaw('p.completed_at >= '.$this->minusDays('job_cards.received_at', self::COMEBACK_DAYS)))
            ->latest('received_at')->get();
    }

    protected function minusDays(string $col, int $days): string
    {
        return DB::getDriverName() === 'sqlite' ? "datetime($col, '-$days days')" : "DATE_SUB($col, INTERVAL $days DAY)";
    }

    public function dashboard(Carbon $from, Carbon $to): array
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $qc = QualityCheck::query()->whereBetween('checked_at', $range)->get(['result', 'job_card_id']);
        $completed = JobCard::query()->whereBetween('completed_at', $range)->count();
        $comebacks = $this->comebacks($from, $to);
        $ratings = DB::table('service_ratings')->where('company_id', tenant()->id())->whereBetween('created_at', $range)->pluck('rating');
        $ncrs = Nonconformity::query()->whereDate('detected_at', '>=', $from->toDateString())->whereDate('detected_at', '<=', $to->toDateString());

        return [
            'open_ncr' => Nonconformity::query()->where('status', '!=', 'closed')->count(),
            'overdue_capa' => CapaAction::query()->whereIn('status', ['open', 'in_progress'])->whereDate('due_date', '<', today())->count(),
            'qc_total' => $qc->count(),
            'qc_pass_rate' => $qc->count() ? round($qc->where('result', 'pass')->count() * 100 / $qc->count(), 1) : null,
            'first_time_fix' => $completed ? round(max(0, $completed - $comebacks->count()) * 100 / $completed, 1) : null,
            'comebacks' => $comebacks,
            'csat' => $ratings->count() ? round($ratings->avg(), 2) : null,
            'csat_pct' => $ratings->count() ? round($ratings->filter(fn ($r) => $r >= 4)->count() * 100 / $ratings->count(), 1) : null,
            'ratings' => $ratings->count(),
            'ncr_by_category' => (clone $ncrs)->selectRaw('category, COUNT(*) n, SUM(cost) cost')->groupBy('category')->orderByDesc('n')->get(),
            'ncr_by_source' => (clone $ncrs)->selectRaw('source, COUNT(*) n')->groupBy('source')->orderByDesc('n')->pluck('n', 'source'),
            'ncr_by_technician' => (clone $ncrs)->whereNotNull('technician_id')->selectRaw('technician_id, COUNT(*) n')->groupBy('technician_id')->orderByDesc('n')->limit(8)->get()
                ->map(fn ($r) => ['name' => \App\Models\User::query()->find($r->technician_id)?->name ?? '#'.$r->technician_id, 'n' => $r->n]),
            'repeat_parts' => DB::table('warranty_claims as c')->join('warranties as w', 'w.id', '=', 'c.warranty_id')->leftJoin('parts as p', 'p.id', '=', 'w.part_id')
                ->where('c.company_id', tenant()->id())->whereBetween('c.created_at', $range)->groupBy('w.description')
                ->selectRaw('w.description d, COUNT(*) n')->orderByDesc('n')->limit(8)->get(),
            'suppliers' => DB::table('nonconformities as n')->join('suppliers as s', 's.id', '=', 'n.supplier_id')->where('n.company_id', tenant()->id())
                ->groupBy('s.id', 's.name')->selectRaw('s.id, s.name, COUNT(*) n')->orderByDesc('n')->limit(8)->get(),
            'audits' => QualityAudit::query()->where('status', 'completed')->whereBetween('performed_at', $range)->avg('score'),
        ];
    }
}
