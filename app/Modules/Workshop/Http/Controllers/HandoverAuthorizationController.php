<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\HandoverAuthorization;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Services\HandoverService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Vehicle handover authorizations: issue, owner approval (code or signed letter), revoke, print. */
class HandoverAuthorizationController extends Controller
{
    public function __construct(protected HandoverService $service) {}

    protected function can(string $perm): void
    {
        abort_unless(auth()->user()->can($perm), 403);
    }

    public function index(Request $request)
    {
        $this->can('handover_authorizations.view');
        $status = $request->query('status');
        $q = trim((string) $request->query('q'));
        $list = HandoverAuthorization::query()->with('customer', 'vehicle', 'jobCard')
            ->when(in_array($status, ['pending', 'active', 'used', 'revoked'], true), fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%$q%")->orWhere('person_name', 'like', "%$q%")->orWhere('id_number', 'like', "%$q%")
                ->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', "%$q%"))->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$q%"))))
            ->latest('id')->paginate(30)->withQueryString();

        return view('workshop::authorizations', [
            'list' => $list,
            'counts' => HandoverAuthorization::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        $this->can('handover_authorizations.create');
        $jc = $request->query('job_card_id') ? JobCard::query()->with('vehicle', 'customer')->find($request->query('job_card_id')) : null;
        $vehicle = $jc?->vehicle ?? ($request->query('vehicle_id') ? Vehicle::query()->with('customer')->find($request->query('vehicle_id')) : null);

        return view('workshop::authorization-form', ['jc' => $jc, 'vehicle' => $vehicle]);
    }

    public function store(Request $request)
    {
        $this->can('handover_authorizations.create');
        $c = tenant()->id();
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('company_id', $c)],
            'job_card_id' => ['nullable', 'integer', Rule::exists('job_cards', 'id')->where('company_id', $c)],
            'type' => ['required', Rule::in(HandoverAuthorization::TYPES)],
            'person_name' => ['required', 'string', 'max:150'],
            'id_type' => ['nullable', Rule::in(HandoverAuthorization::ID_TYPES)],
            'id_number' => ['required', 'string', 'min:4', 'max:40'],
            'person_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s()-]+$/'],
            'relationship' => ['nullable', Rule::in(HandoverAuthorization::RELATIONSHIPS)],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'single_use' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        if (! empty($data['job_card_id']) && JobCard::query()->whereKey($data['job_card_id'])->value('vehicle_id') !== $vehicle->id) {
            return back()->withInput()->withErrors(['job_card_id' => __('The job card is not for this vehicle.')]);
        }
        $a = $this->service->issue($data + ['customer_id' => $vehicle->customer_id, 'single_use' => $request->boolean('single_use')]);

        return redirect()->route('handover-authorizations.show', $a)
            ->with('success', __('Authorization :n issued. The owner must approve it before it can be used.', ['n' => $a->number]));
    }

    public function show(HandoverAuthorization $authorization)
    {
        $this->can('handover_authorizations.view');
        $authorization->load('customer', 'vehicle.make', 'vehicle.vehicleModel', 'jobCard', 'creator', 'revoker');
        $uses = \App\Modules\Workshop\Models\VehicleGateLog::query()->with('jobCard', 'user')->where('handover_authorization_id', $authorization->id)->latest('logged_at')->get();
        $channel = $authorization->customer->preferred_channel ?: 'sms';

        return view('workshop::authorization', [
            'a' => $authorization, 'uses' => $uses,
            'canSendCode' => app(\App\Modules\Core\Services\NotificationService::class)->channelEnabled($channel === 'mail' ? 'mail' : $channel),
        ]);
    }

    public function sendCode(HandoverAuthorization $authorization)
    {
        $this->can('handover_authorizations.approve');
        $this->service->sendCode($authorization);

        return back()->with('success', __('A verification code was sent to the owner :p. Ask the owner for the code and enter it.', ['p' => $authorization->customer->phone]));
    }

    public function approve(Request $request, HandoverAuthorization $authorization)
    {
        $this->can('handover_authorizations.approve');
        $data = $request->validate(['method' => ['required', Rule::in(['code', 'signature'])], 'code' => ['required_if:method,code', 'nullable', 'digits:6'], 'signed' => ['required_if:method,signature', 'accepted_if:method,signature']]);
        $data['method'] === 'code'
            ? $this->service->approveWithCode($authorization, (string) $data['code'])
            : $this->service->approveWithSignature($authorization);

        return back()->with('success', __('Authorization :n is now active.', ['n' => $authorization->number]));
    }

    public function revoke(Request $request, HandoverAuthorization $authorization)
    {
        $this->can('handover_authorizations.update');
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $this->service->revoke($authorization, $data['reason']);

        return back()->with('success', __('Authorization :n revoked.', ['n' => $authorization->number]));
    }

    public function print(HandoverAuthorization $authorization, string $format = 'a4')
    {
        $this->can('handover_authorizations.print');
        $authorization->load('customer', 'vehicle.make', 'vehicle.vehicleModel', 'jobCard', 'creator');
        $html = view('workshop::print.authorization', ['a' => $authorization])->render();
        if ($format === 'pdf') {
            return response(\App\Support\Export\Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$authorization->number.'.pdf"']);
        }

        return response($html);
    }
}
