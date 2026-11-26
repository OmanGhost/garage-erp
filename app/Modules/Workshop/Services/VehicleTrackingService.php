<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Modules\Workshop\Events\WorkshopUpdated;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\TestDrive;
use App\Modules\Workshop\Models\VehicleTrackingEvent;
use App\Modules\Workshop\Models\YardLocation;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Where every vehicle stands in the yard and where its key is: on a numbered hook or with an employee.
 * Each job card gets a key tag code (printed as QR / barcode, or the id of an RFID tag) that opens its
 * tracking page when scanned. Test drives need a permit and record the odometer before and after.
 */
class VehicleTrackingService
{
    /** Give the job card a key tag code (an RFID tag id can be used instead of the generated one). */
    public function tag(JobCard $jc, ?string $code = null): string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            if ($jc->key_tag) {
                return $jc->key_tag;
            }
            do {
                $code = 'K'.random_int(100000, 999999);
            } while (JobCard::query()->where('key_tag', $code)->exists());
        } elseif (JobCard::query()->where('key_tag', $code)->whereKeyNot($jc->id)->whereIn('status', JobCard::OPEN_STATUSES)->exists()) {
            throw new BusinessException(__('This key tag is already used by another open job card.'));
        }
        $jc->forceFill(['key_tag' => $code])->save();
        $this->event($jc, 'tagged', ['notes' => $code]);

        return $code;
    }

    /** Job card for a scanned code: key tag / RFID, job card number or plate. */
    public function find(string $code): ?JobCard
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        // a scanned QR holds the tracking URL: keep its last part
        if (str_contains($code, '/')) {
            $code = basename(parse_url($code, PHP_URL_PATH) ?: $code);
        }

        return JobCard::query()->whereIn('status', JobCard::OPEN_STATUSES)
            ->where(fn ($q) => $q->where('key_tag', strtoupper($code))->orWhere('number', $code)
                ->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', $code)))->latest('id')->first()
            ?? (ctype_digit($code) ? JobCard::query()->whereIn('status', JobCard::OPEN_STATUSES)->find((int) $code) : null);
    }

    public function move(JobCard $jc, int $locationId, string $via = 'web', ?string $notes = null): void
    {
        $loc = YardLocation::query()->where('is_active', true)->findOrFail($locationId);
        $jc->forceFill(['yard_location_id' => $loc->id])->save();
        $this->event($jc, 'moved', ['yard_location_id' => $loc->id, 'via' => $via, 'notes' => $notes]);
    }

    /** Key handed to an employee. */
    public function keyOut(JobCard $jc, int $holderId, string $via = 'web', ?string $notes = null): void
    {
        $jc->forceFill(['key_holder_id' => $holderId, 'key_hook' => null])->save();
        $this->event($jc, 'key_out', ['holder_id' => $holderId, 'via' => $via, 'notes' => $notes]);
    }

    /** Key returned to the key cabinet (hook number). */
    public function keyIn(JobCard $jc, string $hook, string $via = 'web', ?string $notes = null): void
    {
        $hook = strtoupper(trim($hook));
        $taken = JobCard::query()->whereIn('status', JobCard::OPEN_STATUSES)->where('key_hook', $hook)->whereKeyNot($jc->id)->first();
        if ($taken) {
            throw new BusinessException(__('Hook :h already holds the key of :p.', ['h' => $hook, 'p' => $taken->vehicle?->plate()]));
        }
        $jc->forceFill(['key_holder_id' => null, 'key_hook' => $hook])->save();
        $this->event($jc, 'key_in', ['key_hook' => $hook, 'via' => $via, 'notes' => $notes]);
    }

    /* ---------------- test drives ---------------- */

    public function permit(JobCard $jc, array $data): TestDrive
    {
        if ($jc->testDrives()->whereIn('status', ['approved', 'out'])->exists()) {
            throw new BusinessException(__('This vehicle already has an open test-drive permit.'));
        }

        return TestDrive::create([
            'number' => DocumentNumber::next('test_drive'), 'job_card_id' => $jc->id, 'vehicle_id' => $jc->vehicle_id, 'branch_id' => $jc->branch_id,
            'driver_id' => $data['driver_id'], 'authorized_by' => auth()->id(), 'purpose' => $data['purpose'] ?? null, 'route' => $data['route'] ?? null,
            'max_km' => $data['max_km'] ?? null, 'customer_consent' => (bool) ($data['customer_consent'] ?? false), 'status' => 'approved', 'notes' => $data['notes'] ?? null,
        ]);
    }

    public function start(TestDrive $td, int $odometer, ?int $fuel = null): void
    {
        if ($td->status !== 'approved') {
            throw new BusinessException(__('This test drive cannot be started.'));
        }
        $min = (int) max($td->jobCard->odometer_in ?? 0, $td->vehicle->odometer ?? 0);
        if ($odometer < $min) {
            throw new BusinessException(__('The odometer cannot be lower than :n km.', ['n' => number_format($min)]));
        }
        DB::transaction(function () use ($td, $odometer, $fuel) {
            $td->update(['status' => 'out', 'odometer_out' => $odometer, 'fuel_out' => $fuel, 'out_at' => now()]);
            $jc = $td->jobCard;
            $jc->forceFill(['key_holder_id' => $td->driver_id, 'key_hook' => null])->save();
            $this->event($jc, 'test_out', ['holder_id' => $td->driver_id, 'odometer' => $odometer, 'notes' => $td->number]);
        });
    }

    public function finish(TestDrive $td, int $odometer, ?int $fuel = null, ?string $notes = null): void
    {
        if ($td->status !== 'out') {
            throw new BusinessException(__('This test drive is not out.'));
        }
        if ($odometer < (int) $td->odometer_out) {
            throw new BusinessException(__('The odometer cannot be lower than :n km.', ['n' => number_format($td->odometer_out)]));
        }
        DB::transaction(function () use ($td, $odometer, $fuel, $notes) {
            $td->update(['status' => 'returned', 'odometer_in' => $odometer, 'fuel_in' => $fuel, 'in_at' => now(),
                'notes' => trim(($td->notes ? $td->notes."\n" : '').($notes ?? '')) ?: null]);
            if ($odometer > (int) $td->vehicle->odometer) {
                $td->vehicle->forceFill(['odometer' => $odometer])->save();
            }
            $this->event($td->jobCard, 'test_in', ['odometer' => $odometer, 'notes' => $td->number.' · '.$td->distance().' km']);
            if ($td->overLimit()) {
                AuditLogger::log('test_drive.over_limit', $td, ['km' => $td->distance(), 'max' => $td->max_km]);
            }
        });
    }

    public function cancel(TestDrive $td): void
    {
        if ($td->status !== 'approved') {
            throw new BusinessException(__('Only permits that have not started can be cancelled.'));
        }
        $td->update(['status' => 'cancelled']);
    }

    protected function event(JobCard $jc, string $type, array $data = []): void
    {
        VehicleTrackingEvent::create($data + ['job_card_id' => $jc->id, 'vehicle_id' => $jc->vehicle_id, 'type' => $type, 'user_id' => auth()->id(),
            'key_hook' => $data['key_hook'] ?? $jc->key_hook, 'yard_location_id' => $data['yard_location_id'] ?? $jc->yard_location_id]);
        WorkshopUpdated::notify($jc->company_id, 'tracking');
    }
}
