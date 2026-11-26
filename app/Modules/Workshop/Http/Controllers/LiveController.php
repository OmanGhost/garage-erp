<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Core\Models\Company;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\JobCardTask;
use App\Support\Audit\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Live workshop screen for TVs in the workshop: job cards by stage, today's appointments and late work.
 * Updates in real time over Laravel Reverb (signed-in screen) and refreshes every 30 s as a fallback; a secret
 * link lets a TV show the screen without signing in (no customer names on that link).
 */
class LiveController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('job_cards.view'), 403);

        return $this->render($request, false, route('workshop.live'));
    }

    public function tv(Request $request, string $token)
    {
        abort_if(strlen($token) < 32, 404);
        $company = Company::query()->where('settings->workshop_tv_token', $token)->where('is_active', true)->firstOrFail();
        tenant()->set($company->id, $company->branches()->value('id'));
        abort_unless($company->moduleEnabled('workshop'), 404);

        return $this->render($request, true, route('workshop.tv', $token));
    }

    /** Create or replace the secret TV link. */
    public function rotateToken()
    {
        abort_unless(auth()->user()->can('settings.update'), 403);
        Settings::set(['workshop_tv_token' => Str::random(48)]);
        AuditLogger::log('workshop.tv_link_rotated', tenant()->company());

        return back()->with('success', __('A new TV link was created. The old link no longer works.'));
    }

    protected function render(Request $request, bool $public, string $url)
    {
        $data = $this->data($public) + ['public' => $public, 'url' => $url, 'company' => tenant()->company()];
        if ($request->boolean('partial')) {
            return view('workshop::live._board', $data);
        }

        return view('workshop::live.index', $data);
    }

    public function data(bool $public = false): array
    {
        $cards = JobCard::query()->with(['vehicle.make', 'vehicle.vehicleModel', 'customer', 'bay', 'tasks.technician'])
            ->whereIn('status', JobCard::OPEN_STATUSES)->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")->orderBy('received_at')->limit(80)->get();
        $working = [JobCard::IN_PROGRESS, JobCard::ON_HOLD, JobCard::QUALITY_INSPECTION];
        $lateTasks = JobCardTask::query()->with('jobCard.vehicle', 'technician')->whereHas('jobCard', fn ($j) => $j->whereIn('status', JobCard::OPEN_STATUSES))
            ->whereNotIn('status', ['done', 'cancelled'])->whereNotNull('scheduled_end')->where('scheduled_end', '<', now())->orderBy('scheduled_end')->limit(12)->get();

        return [
            'working' => $cards->whereIn('status', $working)->values(),
            'waiting' => $cards->whereNotIn('status', array_merge($working, [JobCard::READY]))->values(),
            'ready' => $cards->where('status', JobCard::READY)->values(),
            'late' => $cards->filter->isLate()->values(),
            'lateTasks' => $lateTasks,
            'appointments' => Appointment::query()->with('customer', 'vehicle', 'service')->whereDate('starts_at', today())
                ->whereNotIn('status', ['cancelled'])->orderBy('starts_at')->get(),
            'deliveredToday' => JobCard::query()->whereDate('delivered_at', today())->count(),
            'updatedAt' => now(),
        ];
    }
}
