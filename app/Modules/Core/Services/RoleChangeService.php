<?php

namespace App\Modules\Core\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Modules\Core\Models\RoleChangeRequest;
use App\Modules\Core\Notifications\SystemMessage;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Role change requests. Anyone allowed to request submits the new role with a reason; a branch manager or higher
 * decides. Rules: nobody decides their own request or a request about their own account; a branch manager only
 * decides for users of the branches they may access; the Company Admin and General Manager roles can only be granted
 * by a company administrator. Approval changes the role at once and everything is kept in the audit log.
 */
class RoleChangeService
{
    /** Roles that only a company administrator may grant. */
    public const SENSITIVE = ['Company Admin', 'General Manager'];

    public static function roles(): array
    {
        return Role::query()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', tenant()->id()))
            ->whereNotIn('name', ['Super Admin', 'Customer'])->orderBy('name')->pluck('name')->all();
    }

    public static function currentRole(User $u): ?string
    {
        setPermissionsTeamId(tenant()->id());

        return $u->roles()->pluck('name')->first();
    }

    protected static function isCompanyAdmin(User $u): bool
    {
        setPermissionsTeamId(tenant()->id());

        return $u->is_super_admin || $u->hasRole('Company Admin');
    }

    public function submit(User $target, string $role, string $reason, ?string $effectiveDate = null): RoleChangeRequest
    {
        if ((int) $target->company_id !== (int) tenant()->id() || $target->customer_id) {
            throw new BusinessException(__('This user cannot be changed.'));
        }
        if ($target->id === auth()->id()) {
            throw new BusinessException(__('You cannot request a role change for your own account.'));
        }
        if (! in_array($role, self::roles(), true)) {
            throw new BusinessException(__('Unknown role.'));
        }
        $current = self::currentRole($target);
        if ($current === $role) {
            throw new BusinessException(__('The user already has this role.'));
        }
        if (RoleChangeRequest::query()->where('user_id', $target->id)->where('status', 'pending')->exists()) {
            throw new BusinessException(__('There is already a pending request for this user.'));
        }
        $r = RoleChangeRequest::create([
            'number' => DocumentNumber::next('role_request'), 'branch_id' => $target->branch_id, 'user_id' => $target->id,
            'current_role' => $current, 'requested_role' => $role, 'reason' => $reason, 'effective_date' => $effectiveDate,
            'status' => 'pending', 'requested_by' => auth()->id(),
        ]);
        AuditLogger::log('role_request.submitted', $r, ['user' => $target->email, 'from' => $current, 'to' => $role]);
        $approvers = $this->approvers($r)->reject(fn ($u) => $u->id === auth()->id());
        if ($approvers->isNotEmpty()) {
            Notification::send($approvers, new SystemMessage(__('Role change request :n waiting for approval', ['n' => $r->number]),
                $target->name.': '.__($current ?? '—').' → '.__($role), route('role-requests.show', $r), 'warning'));
        }

        return $r;
    }

    /** Why this user may not decide the request, or null when allowed. */
    public function denyReason(RoleChangeRequest $r, User $u): ?string
    {
        if ($r->status !== 'pending') {
            return __('This request has already been decided.');
        }
        if (! $u->can('role_requests.approve')) {
            return __('Only a branch manager or higher can decide role change requests.');
        }
        if ((int) $r->requested_by === (int) $u->id) {
            return __('You cannot decide a request you submitted.');
        }
        if ((int) $r->user_id === (int) $u->id) {
            return __('You cannot decide a request about your own account.');
        }
        if (in_array($r->requested_role, self::SENSITIVE, true) && ! self::isCompanyAdmin($u)) {
            return __('Only a company administrator can grant the :r role.', ['r' => __($r->requested_role)]);
        }
        $allowed = $u->allowedBranchIds();
        if ($allowed !== null && $r->branch_id && ! in_array((int) $r->branch_id, array_map('intval', $allowed), true)) {
            return __('This user belongs to a branch you do not manage.');
        }

        return null;
    }

    public function decide(RoleChangeRequest $r, bool $approve, ?string $notes): RoleChangeRequest
    {
        return DB::transaction(function () use ($r, $approve, $notes) {
            $r = RoleChangeRequest::query()->with('user')->whereKey($r->id)->lockForUpdate()->firstOrFail();
            if ($why = $this->denyReason($r, auth()->user())) {
                throw new BusinessException($why);
            }
            if (! $approve && trim((string) $notes) === '') {
                throw new BusinessException(__('Write the reason for the rejection.'));
            }
            $r->forceFill(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => auth()->id(), 'decided_at' => now(), 'decision_notes' => $notes])->save();
            if ($approve) {
                setPermissionsTeamId(tenant()->id());
                $r->user->syncRoles([$r->requested_role]);
                AuditLogger::log('user.role_assigned', $r->user, ['role' => $r->requested_role, 'request' => $r->number]);
            }
            AuditLogger::log($approve ? 'role_request.approved' : 'role_request.rejected', $r, ['notes' => $notes]);
            if ($r->requester && $r->requester->id !== auth()->id()) {
                $r->requester->notify(new SystemMessage(__('Role change request :n :s', ['n' => $r->number, 's' => status_label($r->status)]),
                    (string) $notes, route('role-requests.show', $r), $approve ? 'success' : 'danger'));
            }

            return $r;
        });
    }

    public function cancel(RoleChangeRequest $r): void
    {
        if ($r->status !== 'pending') {
            throw new BusinessException(__('This request has already been decided.'));
        }
        if ((int) $r->requested_by !== (int) auth()->id() && ! self::isCompanyAdmin(auth()->user())) {
            throw new BusinessException(__('Only the person who submitted the request can cancel it.'));
        }
        $r->update(['status' => 'cancelled']);
        AuditLogger::log('role_request.cancelled', $r);
    }

    /** Users who may decide the request. */
    public function approvers(RoleChangeRequest $r)
    {
        return User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)->get()
            ->filter(fn ($u) => $u->id !== (int) $r->user_id && $this->denyReason($r, $u) === null);
    }
}
