<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Workshop\Models\RoadsideRequest;
use App\Modules\Workshop\Services\RoadsideService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Roadside assistance dispatch board, request screen, pricing settings and the customer's public location link. */
class RoadsideController extends Controller
{
    public function __construct(protected RoadsideService $svc) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    protected function drivers()
    {
        return User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }

    public function index(Request $request)
    {
        $this->can('roadside.view');
        $month = now()->startOfMonth();
        $done = RoadsideRequest::query()->where('status', 'completed')->where('completed_at', '>=', $month)->get();

        return view('workshop::roadside.index', [
            'open' => RoadsideRequest::query()->with('driver', 'customer', 'vehicle')->whereIn('status', RoadsideRequest::OPEN)->orderBy('id')->get(),
            'recent' => RoadsideRequest::query()->with('driver', 'customer', 'invoice')->whereNotIn('status', RoadsideRequest::OPEN)->latest('id')->limit(20)->get(),
            'stats' => [
                'month' => $done->count(), 'revenue' => (float) $done->sum('total'), 'covered' => $done->where('covered', true)->count(),
                'response' => $done->map->responseMinutes()->filter()->avg(),
            ],
            'drivers' => $this->drivers(),
            'pricing' => collect(RoadsideRequest::TYPES)->mapWithKeys(fn ($t) => [$t => RoadsideService::pricing($t)])->all(),
        ]);
    }

    public function store(Request $request)
    {
        $this->can('roadside.create');
        $d = $request->validate([
            'caller_name' => ['required', 'string', 'max:120'], 'caller_phone' => ['required', 'string', 'max:30'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', tenant()->id())],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('company_id', tenant()->id())],
            'plate' => ['nullable', 'string', 'max:30'], 'service_type' => ['required', Rule::in(RoadsideRequest::TYPES)],
            'location_text' => ['nullable', 'string', 'max:190'], 'destination_text' => ['nullable', 'string', 'max:190'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'], 'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $r = $this->svc->create($d);

        return redirect()->route('roadside.show', $r)->with('success', __('Roadside request :n created.', ['n' => $r->number]));
    }

    public function show(RoadsideRequest $roadside)
    {
        $this->can('roadside.view');
        $r = $roadside->load('driver', 'customer', 'vehicle', 'membership.plan', 'invoice', 'jobCard');

        return view('workshop::roadside.show', ['r' => $r, 'drivers' => $this->drivers(), 'free_km' => RoadsideService::pricing($r->service_type)['free_km']]);
    }

    public function action(Request $request, RoadsideRequest $roadside, string $action)
    {
        $this->can('roadside.update');
        switch ($action) {
            case 'assign':
                $d = $request->validate(['assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where('company_id', tenant()->id())->whereNull('customer_id')]]);
                $this->svc->assign($roadside, (int) $d['assigned_to']);

                return back()->with('success', __('Driver assigned.'));
            case 'en_route':
            case 'on_site':
                $this->svc->progress($roadside, $action);

                return back()->with('success', __('Status updated.'));
            case 'complete':
                $d = $request->validate(['distance_km' => ['required', 'numeric', 'min:0', 'max:5000'], 'extra_charges' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                    'destination_text' => ['nullable', 'string', 'max:190'], 'use_membership' => ['nullable', 'boolean'], 'to_workshop' => ['nullable', 'boolean']]);
                ['invoice' => $inv, 'request' => $r] = $this->svc->complete($roadside, $d);
                if ($r->job_card_id) {
                    return redirect()->route('job-cards.show', $r->job_card_id)->with('success', __('Request completed. A job card was opened for the towed vehicle.'));
                }
                if ($inv) {
                    return redirect()->route('invoices.show', $inv)->with('success', __('Request completed. Review and post the invoice.'));
                }

                return back()->with('success', __('Request completed — fully covered, nothing to invoice.'));
            case 'cancel':
                $this->svc->cancel($roadside, (string) $request->validate(['reason' => ['required', 'string', 'max:190']])['reason']);

                return back()->with('success', __('Request cancelled.'));
        }
        abort(404);
    }

    public function saveSettings(Request $request)
    {
        $this->can('settings.update');
        $rules = ['km_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'free_km' => ['required', 'numeric', 'min:0', 'max:1000']];
        foreach (RoadsideRequest::TYPES as $t) {
            $rules['base_'.$t] = ['required', 'numeric', 'min:0', 'max:10000'];
        }
        Settings::set(collect($request->validate($rules))->mapWithKeys(fn ($v, $k) => ['roadside.'.$k => (string) $v])->all());

        return back()->with('success', __('Saved.'));
    }

    /* ---------------- customer's public link ---------------- */

    protected function findPublic(string $token): RoadsideRequest
    {
        abort_unless(strlen($token) >= 40, 404);
        $r = RoadsideRequest::query()->withoutGlobalScopes()->where('public_token', $token)->firstOrFail();
        tenant()->forget();
        tenant()->set($r->company_id);
        app()->setLocale(session('locale', tenant()->company()?->locale ?? 'ar'));
        abort_unless(tenant()->company()?->moduleEnabled('roadside'), 404);

        return RoadsideRequest::query()->withoutGlobalScopes()->with('driver')->findOrFail($r->id);
    }

    public function publicShow(string $token)
    {
        $r = $this->findPublic($token);

        return view('workshop::public.roadside', ['r' => $r, 'company' => tenant()->company(), 'token' => $token,
            'driver' => $r->driver ? strtok(trim($r->driver->name), ' ') : null]);
    }

    public function publicLocation(Request $request, string $token)
    {
        $r = $this->findPublic($token);
        $d = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180'], 'location_text' => ['nullable', 'string', 'max:180']]);
        try {
            $this->svc->updateLocation($r, (float) $d['lat'], (float) $d['lng'], $d['location_text'] ?? null);
        } catch (BusinessException $e) {
            return $request->expectsJson() ? response()->json(['message' => $e->getMessage()], 422) : back()->with('error', $e->getMessage());
        }

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', __('Thank you — your location was sent.'));
    }
}
