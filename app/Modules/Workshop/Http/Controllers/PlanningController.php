<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\JobCardTask;
use App\Modules\Workshop\Services\CapacityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Workshop resource planning: day planner per bay / technician, scheduling queue and utilization. */
class PlanningController extends Controller
{
    public function __construct(protected CapacityService $capacity) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    public function index(Request $request)
    {
        $this->can('workshop_planning.view');
        $day = Carbon::parse($request->query('date', today()->toDateString()))->startOfDay();
        $view = $request->query('view') === 'technicians' ? 'technicians' : 'bays';
        $period = $request->query('period') === 'month' ? 'month' : 'week';
        [$pFrom, $pTo] = $period === 'month' ? [now()->startOfMonth(), now()->endOfMonth()] : [now()->startOfWeek(Carbon::SATURDAY), now()->startOfWeek(Carbon::SATURDAY)->addDays(6)];

        $queue = JobCard::query()->with(['vehicle', 'customer', 'tasks' => fn ($q) => $q->whereIn('status', ['pending', 'paused'])->where('approval_status', 'approved')->where('is_subcontract', false)->whereNull('scheduled_start')->orderBy('sort')])
            ->whereIn('status', array_diff(JobCard::OPEN_STATUSES, [JobCard::READY]))
            ->whereHas('tasks', fn ($q) => $q->whereIn('status', ['pending', 'paused'])->where('approval_status', 'approved')->where('is_subcontract', false)->whereNull('scheduled_start'))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")->orderBy('received_at')->limit(40)->get();

        return view('workshop::planner', [
            'day' => $day, 'view' => $view, 'period' => $period, 'plan' => $this->capacity->day($day),
            'bays' => $this->capacity->bays(), 'technicians' => $this->capacity->technicians(),
            'queue' => $queue, 'stats' => $this->capacity->utilization($pFrom, $pTo), 'today' => $this->capacity->utilization(today(), today()),
        ]);
    }

    public function autoSchedule(Request $request, JobCard $jobCard)
    {
        $this->can('workshop_planning.update');
        $plan = $this->capacity->autoSchedule($jobCard, true, $request->boolean('replan'));
        $jobCard->refresh();
        if (! $plan) {
            return back()->with('error', __('No free slot was found in the next 14 days, or there is nothing to schedule.'));
        }

        return back()->with('success', __(':n tasks scheduled. Estimated completion: :d.', ['n' => count($plan), 'd' => $jobCard->estimated_completion_at?->format('Y-m-d H:i') ?? '—']));
    }

    public function autoScheduleAll()
    {
        $this->can('workshop_planning.update');
        $n = 0;
        $cards = JobCard::query()->whereIn('status', array_diff(JobCard::OPEN_STATUSES, [JobCard::READY]))
            ->whereHas('tasks', fn ($q) => $q->whereIn('status', ['pending', 'paused'])->where('approval_status', 'approved')->where('is_subcontract', false)->whereNull('scheduled_start'))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")->orderBy('received_at')->limit(100)->get();
        foreach ($cards as $jc) {
            $n += count($this->capacity->autoSchedule($jc));
        }

        return back()->with('success', __(':n tasks scheduled automatically.', ['n' => $n]));
    }

    public function schedule(Request $request, JobCardTask $task)
    {
        $this->can('workshop_planning.update');
        abort_unless($task->jobCard, 404); // tenant scope
        $data = $request->validate([
            'technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', tenant()->id())],
            'bay_id' => ['nullable', 'integer', Rule::exists('workshop_bays', 'id')->where('company_id', tenant()->id())],
            'start' => ['required', 'date'],
        ]);
        $this->capacity->scheduleTask($task, $data['technician_id'] ?? null, $data['bay_id'] ?? null, Carbon::parse($data['start']));

        return back()->with('success', __('Task scheduled.'));
    }

    public function unschedule(JobCardTask $task)
    {
        $this->can('workshop_planning.update');
        abort_unless($task->jobCard, 404);
        $this->capacity->unschedule($task);

        return back()->with('success', __('Task removed from the schedule.'));
    }
}
