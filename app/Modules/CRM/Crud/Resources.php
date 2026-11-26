<?php

namespace App\Modules\CRM\Crud;

use App\Modules\CRM\Models\Complaint;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\CustomerGroup;
use App\Modules\CRM\Models\ServiceReminder;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Vehicles\Models\VehicleAccident;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\DocumentNumber;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerResource extends CrudResource
{
    public static string $model = Customer::class;
    public static string $slug = 'customers';
    public static string $permission = 'customers';
    public static string $icon = 'bi-people';
    public static array $with = ['group'];
    public static bool $hasAttachments = true;

    public function title(): string { return __('Customers'); }
    public function singular(): string { return __('Customer'); }

    public function query(): Builder
    {
        return parent::query()->withCount('vehicles');
    }

    public function fields(): array
    {
        return [
            Field::text('code')->hideOnForm()->index()->searchable(),
            Field::select('type', Options::CUSTOMER_TYPES)->required()->default('individual')->index()->filterable()->col(3)->section(__('Basic information')),
            Field::text('name')->label(__('Name'))->required()->index()->searchable()->col(5),
            Field::text('name_en')->label(__('Name (English)'))->searchable()->col(4),
            Field::tel('phone')->label(__('Mobile'))->required()->index()->searchable()->col(4),
            Field::tel('phone2')->label(__('Alternative phone'))->col(4),
            Field::email('email')->searchable()->col(4),
            Field::text('national_id')->label(__('Civil ID'))->col(4),
            Field::text('cr_number')->label(__('C.R. number'))->col(4),
            Field::text('vat_number')->label(__('VAT number'))->col(4),
            Field::text('city')->col(4),
            Field::text('address')->col(8),
            Field::select('customer_group_id', fn () => CustomerGroup::query()->pluck('name', 'id')->all())->label(__('Customer group'))->relation('group')->section(__('Commercial terms'))->col(4),
            Field::select('classification', Options::CLASSIFICATIONS)->default('regular')->index()->filterable()->col(4),
            Field::select('price_level', Options::PRICE_LEVELS)->label(__('Price level'))->default('retail')->col(4),
            Field::money('credit_limit')->label(__('Credit limit'))->default(0)->col(4),
            Field::number('payment_terms_days')->label(__('Payment terms (days)'))->default(0)->col(4),
            Field::decimal('discount_percent')->label(__('Special discount %'))->default(0)->col(4)->rules(['min:0', 'max:100']),
            Field::text('balance')->label(__('Balance'))->hideOnForm()->display(fn ($c) => '<span class="font-monospace">'.e(money($c->balance())).'</span>')->index(false),
            Field::number('vehicles_count')->label(__('Vehicles'))->hideOnForm()->hideOnShow()->index(false),
            Field::select('preferred_channel', ['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'mail' => __('Email')])->label(__('Preferred channel'))->default('sms')->col(4)->section(__('Communication')),
            Field::checkbox('marketing_opt_in')->label(__('Accepts reminders & offers'))->default(true)->col(4),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->filterable()->col(4),
            Field::textarea('notes'),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['code'] = DocumentNumber::next('customer', false);
        }

        return $data;
    }

    public function rules(?Model $m): array
    {
        $r = parent::rules($m);
        $r['phone'][] = \Illuminate\Validation\Rule::unique('customers', 'phone')->where('company_id', tenant()->id())->whereNull('deleted_at')->ignore($m?->id);

        return $r;
    }

    public function showUrl(Model $m): ?string { return route('customers.show', $m); }

    public function deleteBlocker(Model $m): ?string
    {
        if ($m->code === 'WALKIN') {
            return __('The walk-in customer cannot be deleted.');
        }

        return $m->invoices()->whereNotIn('status', ['draft', 'held'])->exists() || $m->jobCards()->exists()
            ? __('Customers with invoices or job cards cannot be deleted. Deactivate instead.') : null;
    }
}

class CustomerGroupResource extends CrudResource
{
    public static string $model = CustomerGroup::class;
    public static string $slug = 'customer-groups';
    public static string $permission = 'customer_groups';
    public static string $icon = 'bi-collection';

    public function title(): string { return __('Customer Groups'); }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable(),
            Field::decimal('discount_percent')->label(__('Discount %'))->default(0)->index()->rules(['min:0', 'max:100']),
            Field::select('price_level', Options::PRICE_LEVELS)->label(__('Price level'))->default('retail')->index(),
        ];
    }
}

class ComplaintResource extends CrudResource
{
    public static string $model = Complaint::class;
    public static string $slug = 'complaints';
    public static string $permission = 'complaints';
    public static string $icon = 'bi-chat-left-dots';
    public static array $with = ['customer', 'assignee', 'jobCard'];
    public static ?string $dateColumn = 'created_at';
    public static bool $hasAttachments = true;

    public function title(): string { return __('Complaints & Feedback'); }

    public function fields(): array
    {
        return [
            Field::text('number')->label(__('Reference'))->hideOnForm()->index()->searchable()
                ->display(fn ($c) => '<span class="font-monospace fw-semibold">'.e($c->displayNumber()).'</span>'),
            Field::lookup('customer_id', 'customers', 'customer')->label(__('Customer'))->required()->index(),
            Field::lookup('job_card_id', 'job_cards', 'jobCard', 'number')->label(__('Job card'))->index(),
            Field::text('subject')->required()->index()->searchable()->col(12),
            Field::textarea('description'),
            Field::select('priority', Options::PRIORITIES)->default('normal')->index()->filterable()->col(3),
            Field::select('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open')->index()->filterable()->col(3),
            Field::select('assigned_to', fn () => Options::users())->label(__('Assigned to'))->relation('assignee')->index(false)->col(3),
            Field::select('source', ['staff', 'portal', 'phone', 'whatsapp'])->default('staff')->col(3)->index(),
            Field::textarea('resolution'),
            Field::datetime('created_at')->label(__('Date'))->hideOnForm()->index(),
        ];
    }

    public function saving(Model $m, array $data, bool $creating): void
    {
        if (in_array($m->status, ['resolved', 'closed'], true) && ! $m->resolved_at) {
            $m->resolved_at = now();
        }
    }

    public function showUrl(Model $m): ?string { return route('complaints.show', $m); }

    public function rowActions(Model $m): array
    {
        return auth()->user()?->can('complaints.print')
            ? [['label' => __('Print'), 'url' => route('complaints.print', $m), 'icon' => 'bi-printer', 'blank' => true]]
            : [];
    }
}

class ServiceReminderResource extends CrudResource
{
    public static string $model = ServiceReminder::class;
    public static string $slug = 'service-reminders';
    public static string $permission = 'service_reminders';
    public static string $icon = 'bi-bell';
    public static array $with = ['customer', 'vehicle.make', 'vehicle.vehicleModel'];
    public static string $defaultSort = 'due_date';
    public static string $defaultDir = 'asc';
    public static ?string $dateColumn = 'due_date';

    public function title(): string { return __('Periodic Maintenance Reminders'); }

    public function fields(): array
    {
        return [
            Field::lookup('customer_id', 'customers', 'customer')->label(__('Customer'))->required()->index(),
            Field::lookup('vehicle_id', 'vehicles', 'vehicle', 'label')->label(__('Vehicle'))->required()->index(),
            Field::text('title')->required()->index()->searchable(),
            Field::date('due_date')->label(__('Due date'))->index()->col(3),
            Field::number('due_odometer')->label(__('Due odometer (km)'))->index()->col(3),
            Field::select('status', ['scheduled', 'sent', 'done', 'cancelled'])->default('scheduled')->index()->filterable()->col(3),
            Field::datetime('sent_at')->label(__('Sent at'))->hideOnForm()->index(),
            Field::text('notes')->col(12),
        ];
    }

    public function indexUrl(): string { return route('reminders.index'); }
    public function showUrl(Model $m): ?string { return route('reminders.show', $m); }
}

class VehicleResource extends CrudResource
{
    public static string $model = Vehicle::class;
    public static string $slug = 'vehicles';
    public static string $permission = 'vehicles';
    public static string $icon = 'bi-car-front';
    public static array $with = ['customer', 'make', 'vehicleModel'];
    public static bool $hasAttachments = true;

    public function title(): string { return __('Vehicles'); }
    public function singular(): string { return __('Vehicle'); }

    public function fields(): array
    {
        return [
            Field::lookup('customer_id', 'customers', 'customer')->label(__('Owner'))->required()->index()->section(__('Ownership & registration'))->col(6),
            Field::select('fleet_id', fn () => \App\Modules\Fleet\Models\Fleet::query()->orderBy('name')->pluck('name', 'id')->all())->label(__('Fleet (optional)'))->relation('fleet')->col(6)
                ->help(__('Company fleet this vehicle belongs to (contract billing, approvals and limits).')),
            Field::text('plate_number')->label(__('Plate number'))->index()->searchable()->col(3)->rules(['max:20']),
            Field::text('plate_code')->label(__('Plate code'))->index()->col(3)->help(__('Omani plates: letters code e.g. A, AB')),
            Field::select('plate_country', ['OM' => __('Oman'), 'AE' => __('UAE'), 'SA' => __('Saudi Arabia'), 'QA' => __('Qatar'), 'KW' => __('Kuwait'), 'BH' => __('Bahrain'), 'XX' => __('Other')])->label(__('Registration country'))->default('OM')->col(3),
            Field::text('vin')->label(__('VIN (chassis)'))->index()->searchable()->col(5)->rules(['nullable', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'])->attrs(['data-vin' => '1', 'style' => 'text-transform:uppercase']),
            Field::date('registration_expiry')->label(__('Registration expiry'))->col(4),
            Field::select('vehicle_make_id', fn () => VehicleMake::query()->orderBy('name')->pluck('name', 'id')->all())->label(__('Make'))->relation('make')->index()->section(__('Specifications'))->col(4),
            Field::lookup('vehicle_model_id', 'vehicle_models', 'vehicleModel')->label(__('Model'))->index()->col(4)->attrs(['params' => 'make_id=#f_vehicle_make_id']),
            Field::text('trim')->label(__('Trim / category'))->col(4),
            Field::number('year')->label(__('Year'))->index()->col(3)->rules(['min:1950', 'max:'.(date('Y') + 1)]),
            Field::select('vehicle_type', Options::VEHICLE_TYPES)->label(__('Vehicle type'))->default('car')->index()->filterable()->col(3),
            Field::select('fuel_type', Options::FUEL_TYPES)->label(__('Fuel'))->col(3),
            Field::select('transmission', Options::TRANSMISSIONS)->label(__('Transmission'))->col(3),
            Field::text('engine_number')->label(__('Engine number'))->col(4),
            Field::text('engine_type')->label(__('Engine type'))->col(4),
            Field::text('engine_capacity')->label(__('Engine capacity'))->col(4),
            Field::text('color')->col(4),
            Field::number('odometer')->label(__('Odometer (km)'))->index()->col(4),
            Field::select('insurance_company_id', fn () => \App\Modules\Insurance\Models\InsuranceCompany::query()->pluck('name', 'id')->all())->label(__('Insurance company'))->relation('insuranceCompany')->section(__('Insurance'))->col(4),
            Field::text('insurance_policy_number')->label(__('Policy number'))->col(4),
            Field::date('insurance_expiry')->label(__('Insurance expiry'))->col(4),
            Field::date('next_service_date')->label(__('Next service date'))->hideOnForm(),
            Field::textarea('notes'),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! empty($data['vin'])) {
            $data['vin'] = strtoupper($data['vin']);
        }
        if (! empty($data['plate_code'])) {
            $data['plate_code'] = strtoupper($data['plate_code']);
        }

        return $data;
    }

    public function rules(?Model $m): array
    {
        $r = parent::rules($m);
        $r['vin'][] = \Illuminate\Validation\Rule::unique('vehicles', 'vin')->where('company_id', tenant()->id())->whereNull('deleted_at')->ignore($m?->id);
        $r['plate_number'][] = function ($attr, $value, $fail) use ($m) {
            if ($value && Vehicle::query()->where('plate_number', $value)->where('plate_code', strtoupper((string) request('plate_code')))
                ->where('plate_country', request('plate_country', 'OM'))->when($m, fn ($q) => $q->whereKeyNot($m->id))->exists()) {
                $fail(__('A vehicle with this plate is already registered.'));
            }
        };

        return $r;
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        // ownership change log
        if (! $created && $m->wasChanged('customer_id')) {
            \App\Modules\Vehicles\Models\VehicleOwnership::create([
                'vehicle_id' => $m->id, 'from_customer_id' => $m->getOriginal('customer_id'), 'to_customer_id' => $m->customer_id,
                'changed_at' => now()->toDateString(), 'user_id' => auth()->id(),
            ]);
        }
    }

    public function formFields(?Model $m = null): array
    {
        $fields = parent::formFields($m);
        if (! tenant()->company()?->moduleEnabled('fleet')) {
            $fields = array_values(array_filter($fields, fn ($f) => $f->name !== 'fleet_id'));
        }

        return $fields;
    }

    public function showUrl(Model $m): ?string { return route('vehicles.show', $m); }

    public function deleteBlocker(Model $m): ?string
    {
        return $m->jobCards()->exists() ? __('Vehicles with job cards cannot be deleted.') : null;
    }
}

class VehicleMakeResource extends CrudResource
{
    public static string $model = VehicleMake::class;
    public static string $slug = 'vehicle-makes';
    public static string $permission = 'vehicle_makes';
    public static string $icon = 'bi-tags';
    public static string $defaultSort = 'name';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Vehicle Makes & Models'); }

    public function query(): Builder
    {
        return VehicleMake::query()->withCount('models');
    }

    public function can(string $action, ?Model $m = null): bool
    {
        // global reference data: only super admins may change it
        return in_array($action, ['view', 'export', 'print'], true) ? parent::can($action) : (bool) auth()->user()?->is_super_admin;
    }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable()->unique('vehicle_makes', true),
            Field::text('name_ar')->label(__('Name (Arabic)'))->index()->searchable(),
            Field::text('country')->rules(['size:2']),
            Field::number('models_count')->label(__('Models'))->hideOnForm()->index(false),
            Field::textarea('models_text')->label(__('Models (one per line)'))->hideOnShow()->help(__('New models are added; existing ones are kept.')),
        ];
    }

    public function virtualFields(): array { return ['models_text']; }

    public function saved(Model $m, array $data, bool $created): void
    {
        foreach (preg_split('/\R/u', (string) ($data['models_text'] ?? '')) as $line) {
            if ($line = trim($line)) {
                $m->models()->firstOrCreate(['name' => $line]);
            }
        }
    }

    public function showPanels(Model $m): array
    {
        return [['view' => 'vehicles.partials.make-models', 'data' => ['make' => $m]]];
    }
}

class VehicleAccidentResource extends CrudResource
{
    public static string $model = VehicleAccident::class;
    public static string $slug = 'vehicle-accidents';
    public static string $permission = 'insurance_claims';
    public static ?string $module = 'insurance';
    public static string $icon = 'bi-exclamation-octagon';
    public static array $with = ['vehicle', 'claim'];
    public static string $defaultSort = 'accident_date';
    public static ?string $dateColumn = 'accident_date';
    public static bool $hasAttachments = true;

    public function title(): string { return __('Accident Records'); }

    public function fields(): array
    {
        return [
            Field::lookup('vehicle_id', 'vehicles', 'vehicle', 'label')->label(__('Vehicle'))->required()->index(),
            Field::date('accident_date')->label(__('Accident date'))->required()->index()->col(3),
            Field::text('police_report_number')->label(__('Police report no.'))->index()->col(3),
            Field::text('location')->index()->col(12),
            Field::textarea('description'),
            Field::textarea('damage_description')->label(__('Damage description')),
            Field::text('claim_number')->label(__('Insurance claim'))->hideOnForm()->display(fn ($a) => e($a->claim?->number ?? '—'))->index(false),
        ];
    }
}
