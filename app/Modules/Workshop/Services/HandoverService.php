<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Modules\Core\Services\NotificationService;
use App\Modules\Workshop\Models\HandoverAuthorization;
use App\Modules\Workshop\Models\JobCard;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Vehicle handover authorizations.
 *
 *  1. issue()    staff records the authorized person (name, ID, phone, relationship, drop off / pick up / both,
 *                validity, single use) → status "pending".
 *  2. approval   the owner confirms either with a 6-digit code sent to the owner's phone (sendCode + approveWithCode,
 *                5 attempts, 30 minutes) or by signing the printed authorization letter (approveWithSignature).
 *                → status "active".
 *  3. use()      at reception (drop off) or delivery (pick up) the staff selects the authorization and checks the
 *                ID number shown by the person; it must match. A single-use authorization becomes "used".
 *  4. revoke()   at any time on the owner's request (reason kept).
 */
class HandoverService
{
    public const CODE_TTL_MINUTES = 30;

    public const MAX_CODE_ATTEMPTS = 5;

    public function issue(array $data): HandoverAuthorization
    {
        return DB::transaction(function () use ($data) {
            $a = HandoverAuthorization::create([
                'number' => DocumentNumber::next('handover_authorization'),
                'customer_id' => $data['customer_id'], 'vehicle_id' => $data['vehicle_id'], 'job_card_id' => $data['job_card_id'] ?? null,
                'type' => $data['type'], 'person_name' => trim($data['person_name']), 'id_type' => $data['id_type'] ?? null,
                'id_number' => $this->normalizeId($data['id_number']), 'person_phone' => isset($data['person_phone']) ? omani_phone($data['person_phone']) : null,
                'relationship' => $data['relationship'] ?? null,
                'valid_from' => $data['valid_from'] ?? now(), 'valid_until' => $data['valid_until'] ?? null,
                'single_use' => (bool) ($data['single_use'] ?? true), 'notes' => $data['notes'] ?? null,
                'status' => 'pending', 'created_by' => auth()->id(),
            ]);
            AuditLogger::log('handover_authorization.issued', $a, ['person' => $a->person_name, 'type' => $a->type]);

            return $a;
        });
    }

    /** Send a one-time approval code to the vehicle owner. Returns the code (shown to staff only in local/testing). */
    public function sendCode(HandoverAuthorization $a): string
    {
        $this->assertPending($a);
        $code = (string) random_int(100000, 999999);
        $a->forceFill(['approval_code_hash' => Hash::make($code), 'code_sent_at' => now(), 'code_attempts' => 0])->save();
        $a->loadMissing('customer', 'vehicle');
        app(NotificationService::class)->notifyCustomer($a->customer, 'handover.code', [
            'code' => $code, 'person' => $a->person_name, 'plate' => $a->vehicle->plate(), 'number' => $a->number,
        ], [$a->customer->preferred_channel ?: 'sms'], $a);
        AuditLogger::log('handover_authorization.code_sent', $a);

        return $code;
    }

    public function approveWithCode(HandoverAuthorization $a, string $code): HandoverAuthorization
    {
        $this->assertPending($a);
        if (! $a->approval_code_hash || ! $a->code_sent_at) {
            throw new BusinessException(__('No approval code has been sent to the owner yet.'));
        }
        if ($a->code_sent_at->lt(now()->subMinutes(self::CODE_TTL_MINUTES))) {
            throw new BusinessException(__('The approval code has expired. Send a new code.'));
        }
        if ($a->code_attempts >= self::MAX_CODE_ATTEMPTS) {
            throw new BusinessException(__('Too many wrong codes. Send a new code.'));
        }
        if (! Hash::check(trim($code), $a->approval_code_hash)) {
            $a->increment('code_attempts');
            throw new BusinessException(__('The approval code is not correct.'));
        }
        $a->forceFill(['status' => 'active', 'approval_method' => 'code', 'approved_at' => now(), 'approval_code_hash' => null])->save();
        AuditLogger::log('handover_authorization.approved', $a, ['method' => 'code']);

        return $a;
    }

    /** The owner signed the printed authorization letter in front of the staff (or it was received signed). */
    public function approveWithSignature(HandoverAuthorization $a): HandoverAuthorization
    {
        $this->assertPending($a);
        $a->forceFill(['status' => 'active', 'approval_method' => 'signature', 'approved_at' => now(), 'approval_code_hash' => null])->save();
        AuditLogger::log('handover_authorization.approved', $a, ['method' => 'signature']);

        return $a;
    }

    public function revoke(HandoverAuthorization $a, ?string $reason): HandoverAuthorization
    {
        if (in_array($a->status, ['used', 'revoked'], true)) {
            throw new BusinessException(__('This authorization can no longer be revoked.'));
        }
        $a->forceFill(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => auth()->id(), 'revoke_reason' => $reason])->save();
        AuditLogger::log('handover_authorization.revoked', $a, ['reason' => $reason]);

        return $a;
    }

    /**
     * Use an authorization for a handover. $direction: "in" (drop off at reception) or "out" (pick up at delivery).
     * The ID number shown by the person must match the authorization.
     */
    public function use(int $authorizationId, JobCard $jc, string $direction, string $shownIdNumber): HandoverAuthorization
    {
        $a = HandoverAuthorization::query()->whereKey($authorizationId)->lockForUpdate()->first();
        if (! $a || $a->vehicle_id !== $jc->vehicle_id || ($a->job_card_id && $a->job_card_id !== $jc->id)) {
            throw new BusinessException(__('This authorization is not for this vehicle.'));
        }
        if ($a->effectiveStatus() !== 'active') {
            throw new BusinessException(__('The authorization :n is not valid (:s).', ['n' => $a->number, 's' => status_label($a->effectiveStatus())]));
        }
        if (! $a->allows($direction)) {
            throw new BusinessException($direction === 'in'
                ? __('The authorization :n does not allow dropping off the vehicle.', ['n' => $a->number])
                : __('The authorization :n does not allow picking up the vehicle.', ['n' => $a->number]));
        }
        if ($this->normalizeId($shownIdNumber) !== $a->id_number) {
            AuditLogger::log('handover_authorization.id_mismatch', $a, ['shown' => $shownIdNumber, 'job_card' => $jc->number]);
            throw new BusinessException(__('The ID number shown does not match the authorization. Do not hand over the vehicle.'));
        }
        $a->uses_count++;
        $a->last_used_at = now();
        if ($a->single_use) {
            $a->status = 'used';
        }
        $a->save();
        AuditLogger::log('handover_authorization.used', $a, ['job_card' => $jc->number, 'direction' => $direction]);

        return $a;
    }

    public function normalizeId(string $id): string
    {
        return strtoupper(preg_replace('/[\s\-\/]+/', '', $id));
    }

    protected function assertPending(HandoverAuthorization $a): void
    {
        if ($a->status !== 'pending') {
            throw new BusinessException(__('This authorization is already :s.', ['s' => status_label($a->status)]));
        }
    }
}
