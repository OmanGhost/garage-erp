<?php

namespace App\Modules\Insurance\Crud;

use App\Modules\Assets\Models\Equipment;
use App\Modules\Assets\Models\EquipmentLog;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetApproval;
use App\Modules\Insurance\Models\InsuranceClaim;
use App\Modules\Insurance\Models\InsuranceCompany;
use App\Modules\Warranty\Models\Warranty;
use App\Modules\Warranty\Models\WarrantyClaim;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\DocumentNumber;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;

class InsuranceCompanyResource extends CrudResource
{
    public static string $model = InsuranceCompany::class;
    public static string $slug = 'insurance-companies';
    public static string $permission = 'insurance_companies';
    public static ?string $module = 'insurance';
    public static string $icon = 'bi-buildings';
    public static array $with = ['customer'];

    public function title(): string { return __('Insurance Companies'); }

    public function fields(): array
    {
        return [
            Field::text('name')->label(__('Name (Arabic)'))->required()->index()->searchable(),
            Field::text('name_en')->label(__('Name (English)'))->index()->searchable(),
            Field::text('contact_person')->label(__('Contact person'))->col(4),
            Field::tel('phone')->index()->col(4),
            Field::email('email')->col(4),
            Field::text('address')->col(12),
            Field::lookup('customer_id', 'customers', 'customer')->label(__('Billing account (customer)'))->help(__('Leave empty to create one automatically.')),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index(),
        ];
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        if (! $m->customer_id) {
            $c = \App\Modules\CRM\Models\Customer::create(['code' => DocumentNumber::next('customer', false), 'type' => 'insurance', 'name' => $m->name,
                'name_en' => $m->name_en, 'phone' => $m->phone, 'email' => $m->email, 'payment_terms_days' => 60, 'credit_limit' => 1000000]);
            $m->forceFill(['customer_id' => $c->id])->save();
        }
    }
}

class InsuranceClaimResource extends CrudResource
{
    public static string $model = InsuranceClaim::class;
    public static string $slug = 'insurance-claims';
    public static string $permission = 'insurance_claims';
    public static ?string $module = 'insurance';
    public static string $icon = 'bi-shield-check';
    public static array $with = ['insuranceCompany', 'customer', 'vehicle', 'jobCard'];
    public static ?string $dateColumn = 'accident_date';
    public static bool $hasAttachments = true;

    public function title(): string { return __('Insurance Claims'); }

    public function fields(): array
    {
        return [
            Field::text('number')->hideOnForm()->index()->searchable(),
            Field::select('insurance_company_id', fn () => InsuranceCompany::query()->pluck('name', 'id')->all())->label(__('Insurance company'))->relation('insuranceCompany')->required()->index()->filterable(),
            Field::lookup('customer_id', 'customers', 'customer')->label(__('Customer'))->required()->index(),
            Field::lookup('vehicle_id', 'vehicles', 'vehicle', 'label')->label(__('Vehicle'))->required()->index(),
            Field::lookup('job_card_id', 'job_cards', 'jobCard', 'number')->label(__('Job card'))->index(),
            Field::text('claim_number')->label(__('Insurer claim no.'))->index()->searchable()->col(4),
            Field::text('policy_number')->label(__('Policy no.'))->col(4),
            Field::text('police_report_number')->label(__('Police report no.'))->col(4),
            Field::date('accident_date')->label(__('Accident date'))->index()->col(4)->section(__('Accident')),
            Field::text('accident_location')->label(__('Location'))->col(8),
            Field::textarea('accident_description')->label(__('Accident description')),
            Field::text('surveyor_name')->label(__('Surveyor'))->col(3)->section(__('Amounts')),
            Field::money('estimated_cost')->label(__('Estimated repair cost'))->index()->col(3),
            Field::money('approved_amount')->label(__('Approved by insurer'))->index()->col(3),
            Field::money('deductible_amount')->label(__('Deductible (customer)'))->col(3),
            Field::select('status', \App\Modules\Insurance\Services\InsuranceService::STATUSES)->default('draft')->index()->filterable()->col(3),
            Field::textarea('notes'),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['number'] = DocumentNumber::next('insurance_claim');
        }

        return $data;
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        if ($m->job_card_id) {
            \App\Modules\Workshop\Models\JobCard::query()->whereKey($m->job_card_id)->update(['insurance_claim_id' => $m->id, 'job_type' => 'insurance']);
        }
    }

    public function showUrl(Model $m): ?string { return route('insurance.claims.show', $m); }
    public function canEditRecord(Model $m): bool { return ! in_array($m->status, ['invoiced', 'paid', 'closed'], true); }
}

class FleetResource extends CrudResource
{
    public static string $model = Fleet::class;
    public static string $slug = 'fleets';
    public static string $permission = 'fleets';
    public static ?string $module = 'fleet';
    public static string $icon = 'bi-truck-front';
    public static array $with = ['customer'];

    public function title(): string { return __('Fleets & Maintenance Contracts'); }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable(),
            Field::lookup('customer_id', 'customers', 'customer')->label(__('Customer (company)'))->required()->index(),
            Field::text('contract_number')->label(__('Contract no.'))->index()->col(4),
            Field::date('contract_start')->label(__('Contract start'))->col(4),
            Field::date('contract_end')->label(__('Contract end'))->index()->col(4),
            Field::money('monthly_spend_limit')->label(__('Monthly spend limit'))->col(4)->section(__('Limits & approvals')),
            Field::money('per_job_limit')->label(__('Per job limit'))->col(4),
            Field::decimal('discount_percent')->label(__('Contract discount %'))->col(4),
            Field::checkbox('requires_approval')->label(__('Every job needs approval'))->col(4),
            Field::text('approver_name')->label(__('Approver name'))->col(4),
            Field::email('approver_email')->label(__('Approver email'))->col(4),
            Field::tel('approver_phone')->label(__('Approver phone'))->col(4),
            Field::number('maintenance_interval_km')->label(__('Service every (km)'))->col(4)->section(__('Preventive maintenance')),
            Field::number('maintenance_interval_days')->label(__('Service every (days)'))->col(4),
            Field::number('billing_day')->label(__('Monthly billing day'))->default(1)->col(4)->rules(['min:1', 'max:28']),
            Field::select('status', ['active', 'suspended', 'expired'])->default('active')->index()->filterable(),
            Field::textarea('notes'),
        ];
    }

    public function showUrl(Model $m): ?string { return route('fleets.show', $m); }
}

class FleetApprovalResource extends CrudResource
{
    public static string $model = FleetApproval::class;
    public static string $slug = 'fleet-approvals';
    public static string $permission = 'fleets';
    public static ?string $module = 'fleet';
    public static string $icon = 'bi-check2-circle';
    public static bool $readonly = true;
    public static array $with = ['fleet', 'jobCard.vehicle'];

    public function title(): string { return __('Fleet Approval Requests'); }

    public function fields(): array
    {
        return [
            Field::datetime('requested_at')->label(__('Requested'))->index(),
            Field::text('flt')->label(__('Fleet'))->display(fn ($a) => e($a->fleet?->name))->index(false),
            Field::text('job')->label(__('Job card'))->display(fn ($a) => '<a href="'.route('job-cards.show', $a->job_card_id).'">'.e($a->jobCard?->number).'</a> '.e($a->jobCard?->vehicle?->plate()))->index(false),
            Field::money('amount')->index(),
            Field::select('status', ['pending', 'approved', 'rejected'])->index()->filterable(),
            Field::text('approver_name')->label(__('Approver'))->index(),
            Field::text('link')->label(__('Approval link'))->display(fn ($a) => '<input class="form-control form-control-sm" readonly value="'.e(route('public.fleet-approval', $a->token)).'">'),
        ];
    }

    public function rowActions(Model $m): array
    {
        return $m->status === 'pending' && auth()->user()->can('fleets.approve') ? [
            ['label' => __('Approve'), 'url' => route('fleets.approvals.decide', [$m, 'approve']), 'icon' => 'bi-check-lg', 'method' => 'post', 'class' => 'btn-success'],
            ['label' => __('Reject'), 'url' => route('fleets.approvals.decide', [$m, 'reject']), 'icon' => 'bi-x-lg', 'method' => 'post', 'class' => 'btn-danger'],
        ] : [];
    }
}

class WarrantyResource extends CrudResource
{
    public static string $model = Warranty::class;
    public static string $slug = 'warranties';
    public static string $permission = 'warranties';
    public static ?string $module = 'warranty';
    public static string $icon = 'bi-patch-check';
    public static bool $readonly = true;
    public static array $with = ['customer', 'vehicle', 'invoice', 'part'];
    public static string $defaultSort = 'end_date';
    public static ?string $dateColumn = 'start_date';

    public function title(): string { return __('Warranties'); }

    public function searchColumns(): array { return ['description', 'serial_number', 'customer.name', 'invoice.number']; }

    public function fields(): array
    {
        return [
            Field::text('description')->index(),
            Field::text('cust')->label(__('Customer'))->display(fn ($w) => e($w->customer?->name))->index(false),
            Field::text('veh')->label(__('Vehicle'))->display(fn ($w) => e($w->vehicle?->plate()))->index(false),
            Field::text('inv')->label(__('Invoice'))->display(fn ($w) => e($w->invoice?->number))->index(false),
            Field::text('serial_number')->label(__('Serial'))->index(),
            Field::date('start_date')->label(__('From'))->index(),
            Field::date('end_date')->label(__('To'))->index(),
            Field::number('km_limit')->label(__('Km limit'))->index(),
            Field::select('status', ['active', 'expired', 'claimed', 'void'])->index()->filterable(),
            Field::text('terms'),
        ];
    }

    public function rowActions(Model $m): array
    {
        return $m->status === 'active' && auth()->user()->can('warranty_claims.create')
            ? [['label' => __('Open claim'), 'url' => route('warranty.claims.create', ['warranty_id' => $m->id]), 'icon' => 'bi-patch-exclamation', 'class' => 'btn-outline-warning']] : [];
    }
}

class WarrantyClaimResource extends CrudResource
{
    public static string $model = WarrantyClaim::class;
    public static string $slug = 'warranty-claims';
    public static string $permission = 'warranty_claims';
    public static ?string $module = 'warranty';
    public static string $icon = 'bi-patch-exclamation';
    public static array $with = ['warranty.customer', 'reworkJobCard'];
    public static ?string $dateColumn = 'claim_date';
    public static bool $hasAttachments = true;

    public function title(): string { return __('Warranty Claims'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('claim_date')->label(__('Date'))->index(),
            Field::text('w')->label(__('Warranty'))->display(fn ($c) => e($c->warranty?->description.' — '.$c->warranty?->customer?->name))->index(false),
            Field::number('odometer')->index(),
            Field::text('description')->index(),
            Field::select('resolution', ['replace', 'repair', 'refund', 'reject'])->index()->filterable(),
            Field::select('status', ['open', 'inspecting', 'approved', 'rejected', 'completed'])->index()->filterable(),
            Field::text('rw')->label(__('Rework job'))->display(fn ($c) => $c->reworkJobCard ? '<a href="'.route('job-cards.show', $c->rework_job_card_id).'">'.e($c->reworkJobCard->number).'</a>' : '—')->index(false),
            Field::money('cost')->label(__('Warranty cost'))->index(),
            Field::checkbox('supplier_claim')->label(__('Claimed from supplier'))->index(),
            Field::money('supplier_recovered_amount')->label(__('Recovered from supplier')),
            Field::text('inspection_result')->label(__('Inspection result')),
        ];
    }

    public function createUrl(): ?string { return route('crud.index', 'warranties'); }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('warranty.claims.show', $m); }
}

class EquipmentResource extends CrudResource
{
    public static string $model = Equipment::class;
    public static string $slug = 'equipment';
    public static string $permission = 'equipment';
    public static ?string $module = 'assets';
    public static string $icon = 'bi-wrench-adjustable';
    public static array $with = ['branch', 'custodian'];
    public static bool $hasAttachments = true;

    public function title(): string { return __('Workshop Equipment & Tools'); }

    public function fields(): array
    {
        $due = fn ($col) => fn ($e) => $e->{$col} ? '<span class="'.($e->{$col}->isPast() ? 'text-danger fw-bold' : ($e->{$col}->lt(now()->addDays(14)) ? 'text-warning fw-bold' : '')).'">'.$e->{$col}->format('Y-m-d').'</span>' : '—';

        return [
            Field::text('code')->required()->index()->searchable()->unique('equipment')->col(3),
            Field::text('name')->required()->index()->searchable()->col(5),
            Field::select('category', ['lift', 'diagnostic', 'maintenance', 'tool', 'computer', 'company_vehicle', 'other'])->required()->index()->filterable()->col(4),
            Field::text('brand')->col(3),
            Field::text('model')->col(3),
            Field::text('serial_number')->label(__('Serial number'))->col(3),
            Field::select('branch_id', fn () => Options::branches())->label(__('Branch'))->relation('branch', 'displayName')->col(3),
            Field::date('purchase_date')->label(__('Purchase date'))->col(3),
            Field::money('purchase_cost')->label(__('Purchase cost'))->col(3),
            Field::date('warranty_until')->label(__('Warranty until'))->col(3)->display($due('warranty_until')),
            Field::lookup('custodian_employee_id', 'employees', 'custodian')->label(__('Custodian (custody)'))->col(3),
            Field::text('location')->col(3),
            Field::select('status', ['active', 'under_maintenance', 'out_of_service', 'disposed'])->default('active')->index()->filterable()->col(3),
            Field::number('maintenance_interval_days')->label(__('Maintenance every (days)'))->col(3)->section(__('Maintenance & calibration')),
            Field::date('next_maintenance_at')->label(__('Next maintenance'))->col(3)->display($due('next_maintenance_at'))->index(false),
            Field::number('calibration_interval_days')->label(__('Calibration every (days)'))->col(3),
            Field::date('next_calibration_at')->label(__('Next calibration'))->col(3)->display($due('next_calibration_at'))->index(false),
            Field::money('operating_cost_per_hour')->label(__('Operating cost / hour'))->col(3),
            Field::textarea('notes'),
        ];
    }

    public function showPanels(Model $m): array
    {
        return [['view' => 'assets.partials.equipment-logs', 'data' => ['equipment' => $m]]];
    }
}

class EquipmentLogResource extends CrudResource
{
    public static string $model = EquipmentLog::class;
    public static string $slug = 'equipment-logs';
    public static string $permission = 'equipment';
    public static ?string $module = 'assets';
    public static string $icon = 'bi-journal-medical';
    public static array $with = ['equipment'];
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Maintenance, Breakdown & Calibration Log'); }

    public function fields(): array
    {
        return [
            Field::select('equipment_id', fn () => Equipment::query()->orderBy('code')->get()->mapWithKeys(fn ($e) => [$e->id => $e->code.' - '.$e->name])->all())->label(__('Equipment'))->relation('equipment')->required()->index()->filterable(),
            Field::select('type', ['maintenance', 'breakdown', 'calibration', 'inspection', 'custody'])->required()->index()->filterable()->col(3),
            Field::date('date')->required()->default(now()->toDateString())->index()->col(3),
            Field::money('cost')->index()->col(3),
            Field::decimal('downtime_hours')->label(__('Downtime (hours)'))->col(3),
            Field::text('performed_by')->label(__('Performed by'))->col(6),
            Field::date('next_due_date')->label(__('Next due'))->col(6),
            Field::textarea('description'),
        ];
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        $e = $m->equipment;
        if ($m->type === 'maintenance') {
            $e->next_maintenance_at = $m->next_due_date ?? ($e->maintenance_interval_days ? $m->date->copy()->addDays($e->maintenance_interval_days) : $e->next_maintenance_at);
        }
        if ($m->type === 'calibration') {
            $e->next_calibration_at = $m->next_due_date ?? ($e->calibration_interval_days ? $m->date->copy()->addDays($e->calibration_interval_days) : $e->next_calibration_at);
        }
        if ($m->type === 'breakdown') {
            $e->status = 'under_maintenance';
        }
        $e->save();
    }
}
