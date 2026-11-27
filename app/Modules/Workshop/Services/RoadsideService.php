<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Modules\Core\Models\NotificationTemplate;
use App\Modules\Core\Services\NotificationService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Loyalty\Models\Membership;
use App\Modules\Loyalty\Services\MembershipService;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Workshop\Models\RoadsideRequest;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Roadside assistance: a call is logged, dispatched to a driver who moves it en route → on site → completed.
 * The customer gets a secret link to share their location and follow the driver's progress. The price is the base
 * fee of the service type + a rate per km beyond the included distance + extras. A membership roadside call covers
 * the base fee (distance and extras are still charged). A tow into the workshop opens a job card.
 */
class RoadsideService
{
    public static function pricing(string $type): array
    {
        $d = config('erp.defaults');

        return ['base' => (string) Settings::get('roadside.base_'.$type, $d['roadside.base_'.$type] ?? '0'),
            'km_rate' => (string) Settings::get('roadside.km_rate', $d['roadside.km_rate'] ?? '0'),
            'free_km' => (float) Settings::get('roadside.free_km', $d['roadside.free_km'] ?? 0)];
    }

    public function create(array $data): RoadsideRequest
    {
        return DB::transaction(function () use ($data) {
            $customer = ! empty($data['customer_id']) ? Customer::query()->find($data['customer_id'])
                : Customer::query()->where('phone', $data['caller_phone'])->first();
            if ($customer?->isWalkIn()) {
                $customer = null;
            }
            $vehicleId = $data['vehicle_id'] ?? null;
            if (! $vehicleId && $customer && ! empty($data['plate'])) {
                $vehicleId = $customer->vehicles()->where('plate_number', $data['plate'])->value('id');
            }
            $type = in_array($data['service_type'] ?? 'tow', RoadsideRequest::TYPES, true) ? $data['service_type'] : 'other';
            $price = self::pricing($type);
            $membership = $customer && tenant()->company()?->moduleEnabled('memberships')
                ? app(MembershipService::class)->activeFor($customer->id, $vehicleId) : null;
            $r = RoadsideRequest::create([
                'number' => DocumentNumber::next('roadside'), 'branch_id' => tenant()->branchId(),
                'customer_id' => $customer?->id, 'vehicle_id' => $vehicleId,
                'caller_name' => $data['caller_name'], 'caller_phone' => $data['caller_phone'], 'plate' => $data['plate'] ?? null,
                'service_type' => $type, 'location_text' => $data['location_text'] ?? null, 'destination_text' => $data['destination_text'] ?? null,
                'lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null, 'location_at' => isset($data['lat']) ? now() : null,
                'base_fee' => $price['base'], 'km_rate' => $price['km_rate'],
                'membership_id' => $membership && $membership->roadsideLeft() > 0 ? $membership->id : null,
                'public_token' => Str::random(48), 'notes' => $data['notes'] ?? null, 'status' => 'new', 'created_by' => auth()->id(),
            ]);
            AuditLogger::log('roadside.created', $r, ['type' => $type]);

            return $r;
        });
    }

    public function assign(RoadsideRequest $r, int $driverId): void
    {
        $this->assertOpen($r);
        $r->update(['assigned_to' => $driverId, 'status' => $r->status === 'new' ? 'assigned' : $r->status, 'assigned_at' => $r->assigned_at ?? now()]);
        AuditLogger::log('roadside.assigned', $r, ['driver' => $driverId]);
        $this->notify($r);
    }

    public function progress(RoadsideRequest $r, string $to): void
    {
        $this->assertOpen($r);
        $order = ['new' => 0, 'assigned' => 1, 'en_route' => 2, 'on_site' => 3];
        if (! isset($order[$to]) || $order[$to] <= $order[$r->status]) {
            throw new BusinessException(__('Invalid status change.'));
        }
        if (! $r->assigned_to) {
            throw new BusinessException(__('Assign a driver first.'));
        }
        $r->update(['status' => $to] + match ($to) {
            'en_route' => ['en_route_at' => now()],
            'on_site' => ['arrived_at' => now(), 'en_route_at' => $r->en_route_at ?? now()],
            default => [],
        });
    }

    /** From the customer's public link. */
    public function updateLocation(RoadsideRequest $r, float $lat, float $lng, ?string $text = null): void
    {
        if (! $r->isOpen()) {
            throw new BusinessException(__('This request is closed.'));
        }
        $r->update(['lat' => round($lat, 7), 'lng' => round($lng, 7), 'location_at' => now()] + ($text ? ['location_text' => Str::limit($text, 180, '')] : []));
    }

    /** Price for a distance and extras, with or without membership coverage. */
    public function quote(RoadsideRequest $r, float $km, string $extras = '0', bool $covered = false): array
    {
        $free = self::pricing($r->service_type)['free_km'];
        $distance = Money::round(Money::mul((string) max(0, $km - $free), $r->km_rate));
        $total = Money::add($r->base_fee, $distance, $extras);
        $chargeable = $covered ? Money::sub($total, $r->base_fee) : $total;

        return ['base' => (string) $r->base_fee, 'distance' => $distance, 'extras' => Money::round($extras), 'total' => $total,
            'covered' => $covered ? (string) $r->base_fee : '0', 'chargeable' => Money::max('0', $chargeable)];
    }

    /**
     * Close the call: price it, consume the membership call once, bill the rest on a draft invoice and, for a tow into
     * the workshop, open a job card.
     *
     * @return array{request: RoadsideRequest, invoice: ?Invoice}
     */
    public function complete(RoadsideRequest $r, array $data): array
    {
        return DB::transaction(function () use ($r, $data) {
            $r = RoadsideRequest::query()->whereKey($r->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($r);
            if (! $r->assigned_to) {
                throw new BusinessException(__('Assign a driver first.'));
            }
            $useMembership = ! empty($data['use_membership']) && $r->membership_id;
            $q = $this->quote($r, (float) ($data['distance_km'] ?? 0), (string) ($data['extra_charges'] ?? '0'), (bool) $useMembership);
            if ($useMembership) {
                app(MembershipService::class)->useRoadsideCall(Membership::query()->findOrFail($r->membership_id));
            }
            $r->forceFill([
                'status' => 'completed', 'completed_at' => now(), 'arrived_at' => $r->arrived_at ?? now(),
                'distance_km' => $data['distance_km'] ?? 0, 'extra_charges' => $q['extras'], 'total' => $q['total'],
                'covered' => (bool) $useMembership, 'membership_id' => $useMembership ? $r->membership_id : null,
                'destination_text' => $data['destination_text'] ?? $r->destination_text,
            ])->save();

            $invoice = null;
            if (Money::isPositive($q['chargeable'])) {
                $customer = $r->customer ?? $this->customerFor($r);
                $lines = [];
                $vat = tenant()->company()?->vat_rate ?? config('erp.vat_rate');
                $label = __('Roadside assistance').' — '.status_label($r->service_type).' ('.$r->number.')';
                if (! $useMembership && Money::isPositive($r->base_fee)) {
                    $lines[] = ['item_type' => 'service', 'description' => $label, 'qty' => 1, 'unit_price' => $r->base_fee, 'tax_rate' => $vat];
                }
                if (Money::isPositive($q['distance'])) {
                    $lines[] = ['item_type' => 'service', 'description' => __('Towing distance :km km', ['km' => $r->distance_km]).' — '.$r->number, 'qty' => 1, 'unit_price' => $q['distance'], 'tax_rate' => $vat];
                }
                if (Money::isPositive($q['extras'])) {
                    $lines[] = ['item_type' => 'service', 'description' => __('Extra charges').' — '.$r->number, 'qty' => 1, 'unit_price' => $q['extras'], 'tax_rate' => $vat];
                }
                $invoice = app(InvoiceService::class)->createDraft(['customer_id' => $customer->id, 'notes' => $label], $lines);
                $r->forceFill(['invoice_id' => $invoice->id, 'customer_id' => $customer->id])->save();
            }

            if (! empty($data['to_workshop']) && $r->service_type === 'tow' && $r->vehicle_id && ! $r->job_card_id) {
                $jc = app(JobCardService::class)->create(['vehicle_id' => $r->vehicle_id, 'customer_id' => $r->customer_id ?? $r->vehicle?->customer_id,
                    'customer_complaint' => trim(__('Towed in by roadside assistance').' '.$r->number.'. '.($r->notes ?? ''))]);
                $r->forceFill(['job_card_id' => $jc->id])->save();
            }
            AuditLogger::log('roadside.completed', $r, ['total' => $q['total'], 'covered' => (bool) $useMembership]);

            return ['request' => $r, 'invoice' => $invoice];
        });
    }

    public function cancel(RoadsideRequest $r, string $reason): void
    {
        $this->assertOpen($r);
        $r->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
        AuditLogger::log('roadside.cancelled', $r, ['reason' => $reason]);
    }

    protected function assertOpen(RoadsideRequest $r): void
    {
        if (! $r->isOpen()) {
            throw new BusinessException(__('This request is closed.'));
        }
    }

    protected function customerFor(RoadsideRequest $r): Customer
    {
        $found = Customer::query()->where('phone', $r->caller_phone)->first();

        return ($found && ! $found->isWalkIn() ? $found : null) ?? Customer::create([
            'code' => DocumentNumber::next('customer', false), 'name' => $r->caller_name, 'phone' => $r->caller_phone, 'type' => 'individual',
        ]);
    }

    /** Send the tracking link to the customer once a driver is assigned. */
    protected function notify(RoadsideRequest $r): void
    {
        if (! $r->customer) {
            return;
        }
        foreach (['sms', 'whatsapp', 'database'] as $channel) {
            NotificationTemplate::query()->firstOrCreate(['event' => 'roadside.assigned', 'channel' => $channel], [
                'subject_ar' => 'المساعدة على الطريق', 'subject_en' => 'Roadside assistance',
                'body_ar' => 'عزيزي {{customer_name}}، تم تعيين سائق لطلبك {{number}}. شارك موقعك وتابع الطلب: {{link}}',
                'body_en' => 'Dear {{customer_name}}, a driver is assigned to your request {{number}}. Share your location and follow it here: {{link}}',
            ]);
        }
        try {
            app(NotificationService::class)->notifyCustomer($r->customer, 'roadside.assigned', ['number' => $r->number, 'link' => route('roadside.public', $r->public_token)],
                [$r->customer->preferred_channel ?: 'sms'], $r);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
