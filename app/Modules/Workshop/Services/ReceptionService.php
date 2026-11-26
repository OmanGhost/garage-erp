<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\CRM\Models\Customer;
use App\Modules\Core\Services\FileStorage;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\VehicleCheckIn;
use App\Modules\Workshop\Models\VehicleGateLog;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Vehicle reception: find/create customer & vehicle, open the job card,
 * record condition (fuel, odometer, damages, belongings), photos,
 * customer e-signature and gate-in log.
 */
class ReceptionService
{
    public function __construct(protected JobCardService $jobCards) {}

    public function checkIn(array $data, array $photos = []): JobCard
    {
        return DB::transaction(function () use ($data, $photos) {
            $customer = ! empty($data['customer_id'])
                ? Customer::query()->findOrFail($data['customer_id'])
                : Customer::create([
                    'code' => DocumentNumber::next('customer', false),
                    'name' => $data['customer_name'] ?? throw new BusinessException(__('Customer name is required.')),
                    'phone' => omani_phone($data['customer_phone'] ?? null),
                    'email' => $data['customer_email'] ?? null,
                    'type' => $data['customer_type'] ?? 'individual',
                ]);

            if (! empty($data['vehicle_id'])) {
                $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
                if ($vehicle->customer_id !== $customer->id) {
                    throw new BusinessException(__('The selected vehicle belongs to another customer. Transfer ownership first.'));
                }
            } else {
                $vehicle = Vehicle::create([
                    'customer_id' => $customer->id,
                    'plate_number' => $data['plate_number'] ?? null, 'plate_code' => $data['plate_code'] ?? null,
                    'plate_country' => $data['plate_country'] ?? 'OM', 'vin' => isset($data['vin']) ? strtoupper($data['vin']) : null,
                    'vehicle_make_id' => $data['vehicle_make_id'] ?? null, 'vehicle_model_id' => $data['vehicle_model_id'] ?? null,
                    'year' => $data['year'] ?? null, 'color' => $data['color'] ?? null,
                    'vehicle_type' => $data['vehicle_type'] ?? 'car', 'fuel_type' => $data['fuel_type'] ?? null,
                ]);
            }
            if (! empty($data['odometer']) && (int) $data['odometer'] > (int) $vehicle->odometer) {
                $vehicle->update(['odometer' => (int) $data['odometer']]);
            }
            if (JobCard::query()->where('vehicle_id', $vehicle->id)->open()->exists() && empty($data['allow_duplicate'])) {
                throw new BusinessException(__('This vehicle already has an open job card.'));
            }

            $jc = $this->jobCards->create([
                'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
                'priority' => $data['priority'] ?? 'normal', 'job_type' => $data['job_type'] ?? null,
                'department_id' => $data['department_id'] ?? null, 'service_advisor_id' => $data['service_advisor_id'] ?? auth()->id(),
                'promised_at' => $data['expected_delivery_at'] ?? null, 'odometer_in' => $data['odometer'] ?? $vehicle->odometer,
                'fuel_level' => $data['fuel_level'] ?? null, 'customer_complaint' => $data['reported_issues'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null, 'insurance_claim_id' => $data['insurance_claim_id'] ?? null,
            ]);

            // the signature arrives as a PNG file (browsers since 1.9.1: web application firewalls block long base64
            // text fields) or, from older clients / the API, as a data URL
            $signaturePath = null;
            $png = null;
            if (($data['signature_file'] ?? null) instanceof \Illuminate\Http\UploadedFile && $data['signature_file']->isValid()) {
                $png = (string) file_get_contents($data['signature_file']->getRealPath());
            } elseif (! empty($data['signature']) && str_starts_with($data['signature'], 'data:image/png;base64,')) {
                $png = base64_decode(substr($data['signature'], 22), true) ?: null;
            }
            if ($png !== null && strlen($png) < 1024 * 1024 && str_starts_with($png, "\x89PNG")) {
                $signaturePath = 'signatures/'.tenant()->id().'/'.Str::uuid().'.png';
                Storage::disk('local')->put($signaturePath, $png);
            }

            $checkIn = VehicleCheckIn::create([
                'branch_id' => $jc->branch_id, 'job_card_id' => $jc->id, 'vehicle_id' => $vehicle->id, 'customer_id' => $customer->id,
                'received_at' => now(), 'received_by' => auth()->id(), 'odometer' => $data['odometer'] ?? null,
                'fuel_level' => $data['fuel_level'] ?? null, 'reported_issues' => $data['reported_issues'] ?? null,
                'existing_damages' => array_values(array_filter($data['damages'] ?? [], fn ($d) => ! empty($d['area']) || ! empty($d['description']))),
                'personal_items' => array_values(array_filter(array_map('trim', (array) ($data['personal_items'] ?? [])))),
                'checklist' => $data['checklist'] ?? [],
                'expected_delivery_at' => $data['expected_delivery_at'] ?? null,
                'signature_path' => $signaturePath, 'customer_signed_name' => $data['customer_signed_name'] ?? $customer->name,
                'receipt_token' => Str::random(40), 'notes' => $data['notes'] ?? null,
            ]);

            foreach ($photos as $i => $photo) {
                FileStorage::attach($checkIn, $photo, 'check_in', $data['photo_captions'][$i] ?? null);
            }

            VehicleGateLog::create([
                'branch_id' => $jc->branch_id, 'vehicle_id' => $vehicle->id, 'job_card_id' => $jc->id, 'direction' => 'in',
                'logged_at' => now(), 'user_id' => auth()->id(), 'odometer' => $data['odometer'] ?? null,
            ]);

            if (! empty($data['appointment_id'])) {
                Appointment::query()->whereKey($data['appointment_id'])->update(['status' => 'checked_in', 'job_card_id' => $jc->id]);
            }

            return $jc;
        });
    }
}
