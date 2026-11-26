<?php

namespace App\Modules\Quality\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Quality\Models\CapaAction;
use App\Modules\Quality\Models\Nonconformity;
use App\Modules\Quality\Models\QualityAudit;
use App\Modules\Quality\Models\QualitySop;
use App\Modules\Quality\Services\QualityService;
use App\Modules\Workshop\Models\JobCard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Quality management system: dashboard, nonconformities with CAPA, internal audits. */
class QualityController extends Controller
{
    public function __construct(protected QualityService $quality) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    protected function staff()
    {
        return User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }

    public function dashboard(Request $request)
    {
        $this->can('quality.view');
        $from = Carbon::parse($request->query('from', now()->subDays(89)->toDateString()));
        $to = Carbon::parse($request->query('to', today()->toDateString()));

        return view('quality::dashboard', [
            'from' => $from, 'to' => $to, 'k' => $this->quality->dashboard($from, $to),
            'openNcrs' => Nonconformity::query()->with('jobCard.vehicle')->where('status', '!=', 'closed')->latest('detected_at')->limit(10)->get(),
            'overdue' => CapaAction::query()->with('nonconformity', 'owner')->whereIn('status', ['open', 'in_progress'])->whereDate('due_date', '<', today())->orderBy('due_date')->limit(10)->get(),
            'sopsDue' => QualitySop::query()->where('status', 'active')->whereDate('review_date', '<=', today()->addDays(30))->orderBy('review_date')->get(),
            'audits' => QualityAudit::query()->where('status', '!=', 'completed')->orderBy('planned_date')->limit(6)->get(),
        ]);
    }

    /** Register a comeback (repeat repair) as a nonconformity. */
    public function ncrFromJob(Request $request, JobCard $jobCard)
    {
        $this->can('nonconformities.create');
        $ncr = $this->quality->openNcr([
            'source' => $request->input('source') === 'comeback' ? 'comeback' : 'job_card', 'job_card_id' => $jobCard->id, 'category' => 'workmanship', 'severity' => 'major',
            'technician_id' => $jobCard->tasks()->whereNotNull('technician_id')->latest('finished_at')->value('technician_id'),
            'title' => ($request->input('source') === 'comeback' ? __('Comeback: :p', ['p' => $jobCard->vehicle?->plate()]) : __('Nonconformity: :n', ['n' => $jobCard->number])),
        ]);

        return redirect()->route('quality.ncr.show', $ncr)->with('success', __('Nonconformity :n opened.', ['n' => $ncr->number]));
    }

    public function ncr(Nonconformity $ncr)
    {
        $this->can('nonconformities.view');

        return view('quality::ncr', ['ncr' => $ncr->load('actions.owner', 'actions.verifier', 'jobCard.vehicle', 'supplier', 'part', 'audit', 'technician', 'detector', 'responsible'), 'staff' => $this->staff()]);
    }

    public function updateNcr(Request $request, Nonconformity $ncr)
    {
        $this->can('nonconformities.update');
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:5000'], 'root_cause' => ['nullable', 'string', 'max:5000'],
            'analysis_method' => ['nullable', 'in:5why,fishbone,other'], 'category' => ['required', Rule::in(Nonconformity::CATEGORIES)],
            'severity' => ['required', 'in:minor,major,critical'], 'cost' => ['nullable', 'numeric', 'min:0'],
            'responsible_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', tenant()->id())],
        ]);
        $this->quality->update($ncr, $data);

        return back()->with('success', __('Saved.'));
    }

    public function addAction(Request $request, Nonconformity $ncr)
    {
        $this->can('capa_actions.create');
        $data = $request->validate([
            'type' => ['required', 'in:corrective,preventive'], 'description' => ['required', 'string', 'max:250'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', tenant()->id())], 'due_date' => ['nullable', 'date'],
        ]);
        $this->quality->addAction($ncr, $data);

        return back()->with('success', __('Action added.'));
    }

    public function actionStep(Request $request, CapaAction $action, string $step)
    {
        $this->can($step === 'verify' ? 'capa_actions.approve' : 'capa_actions.update');
        match ($step) {
            'complete' => $this->quality->completeAction($action),
            'verify' => $this->quality->verifyAction($action, $request->boolean('effective'), $request->input('notes')),
        };

        return back()->with('success', __('Saved.'));
    }

    public function closeNcr(Nonconformity $ncr)
    {
        $this->can('nonconformities.approve');
        $this->quality->close($ncr);

        return back()->with('success', __('Nonconformity closed.'));
    }

    public function audit(QualityAudit $audit)
    {
        $this->can('quality_audits.view');

        return view('quality::audit', ['audit' => $audit->load('auditor', 'nonconformities')]);
    }

    public function recordAudit(Request $request, QualityAudit $audit)
    {
        $this->can('quality_audits.update');
        $data = $request->validate(['answers' => ['nullable', 'array'], 'answers.*.result' => ['nullable', 'in:conform,nonconform,observation,na'],
            'answers.*.notes' => ['nullable', 'string', 'max:500'], 'summary' => ['nullable', 'string', 'max:5000']]);
        $this->quality->recordAudit($audit, $data['answers'] ?? [], $data['summary'] ?? null, $request->boolean('complete'));

        return back()->with('success', $request->boolean('complete') ? __('Audit completed. Nonconforming items were registered as NCRs.') : __('Saved.'));
    }
}
