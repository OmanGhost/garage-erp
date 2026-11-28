<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Core\Models\RoleChangeRequest;
use App\Modules\Core\Services\RoleChangeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Role change requests: list, new request, decision (approve / reject with notes), printable form. */
class RoleRequestController extends Controller
{
    public function __construct(protected RoleChangeService $svc) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    public function index(Request $request)
    {
        $this->can('role_requests.view');
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected', 'cancelled'], true) ? $request->query('status') : null;

        return view('role-requests.index', [
            'requests' => RoleChangeRequest::query()->with('user', 'requester', 'decider')->when($status, fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => RoleChangeRequest::query()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        $this->can('role_requests.create');
        $users = User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->whereKeyNot(auth()->id())->orderBy('name')->get();
        setPermissionsTeamId(tenant()->id());
        $users->load('roles');

        return view('role-requests.form', ['users' => $users, 'roles' => RoleChangeService::roles(), 'selected' => (int) $request->query('user')]);
    }

    public function store(Request $request)
    {
        $this->can('role_requests.create');
        $d = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('company_id', tenant()->id())->whereNull('customer_id')],
            'requested_role' => ['required', 'string', Rule::in(RoleChangeService::roles())],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'effective_date' => ['nullable', 'date'],
        ]);
        $r = $this->svc->submit(User::query()->findOrFail($d['user_id']), $d['requested_role'], $d['reason'], $d['effective_date'] ?? null);

        return redirect()->route('role-requests.show', $r)->with('success', __('Request :n submitted for approval.', ['n' => $r->number]));
    }

    public function show(RoleChangeRequest $roleRequest)
    {
        $this->can('role_requests.view');
        $roleRequest->load('user', 'requester', 'decider', 'branch');

        return view('role-requests.show', ['r' => $roleRequest, 'denyReason' => $this->svc->denyReason($roleRequest, auth()->user()),
            'approvers' => $roleRequest->status === 'pending' ? $this->svc->approvers($roleRequest) : collect()]);
    }

    public function decide(Request $request, RoleChangeRequest $roleRequest)
    {
        $d = $request->validate(['decision' => ['required', 'in:approve,reject'], 'decision_notes' => ['nullable', 'string', 'max:2000']]);
        $r = $this->svc->decide($roleRequest, $d['decision'] === 'approve', $d['decision_notes'] ?? null);

        return back()->with('success', $r->status === 'approved' ? __('Approved. The new role is active now.') : __('Request rejected.'))
            ->with('print_voucher', route('role-requests.print', $r))->with('print_label', __('Print the decision'));
    }

    public function cancel(RoleChangeRequest $roleRequest)
    {
        $this->can('role_requests.create');
        $this->svc->cancel($roleRequest);

        return back()->with('success', __('Request cancelled.'));
    }

    public function print(RoleChangeRequest $roleRequest)
    {
        $this->can('role_requests.view');

        return view('role-requests.print', ['r' => $roleRequest->load('user.branch', 'requester', 'decider', 'branch'), 'paper' => 'A4']);
    }
}
