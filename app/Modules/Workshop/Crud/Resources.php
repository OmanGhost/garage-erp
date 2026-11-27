<?php

namespace App\Modules\Workshop\Crud;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Workshop\Models\Commission;
use App\Modules\Workshop\Models\Department;
use App\Modules\Workshop\Models\Inspection;
use App\Modules\Workshop\Models\InspectionTemplate;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\Promotion;
use App\Modules\Workshop\Models\Quotation;
use App\Modules\Workshop\Models\Service;
use App\Modules\Workshop\Models\ServicePackage;
use App\Modules\Workshop\Models\VehicleGateLog;
use App\Modules\Workshop\Models\WorkshopBay;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JobCardResource extends CrudResource
{
    public static string $model = JobCard::class;
    public static string $slug = 'job-cards';
    public static string $permission = 'job_cards';
    public static ?string $module = 'workshop';
    public static string $icon = 'bi-clipboard-check';
    public static array $with = ['customer', 'vehicle.make', 'vehicle.vehicleModel', 'department', 'advisor'];
    public static ?string $dateColumn = 'received_at';

    public function title(): string { return __('Job Cards'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::text('vehicle_plate')->label(__('Vehicle'))->display(fn ($j) => '<span class="plate-badge"><span class="flag">'.e($j->vehicle?->plate_country).'</span>'.e($j->vehicle?->plate()).'</span> <small class="text-body-secondary">'.e($j->vehicle?->title()).'</small>')->index(false),
            Field::text('customer_name')->label(__('Customer'))->display(fn ($j) => e($j->customer?->name))->index(false),
            Field::select('status', JobCard::STATUSES)->index()->filterable(),
            Field::select('priority', Options::PRIORITIES)->index()->filterable(),
            Field::select('job_type', ['repair', 'maintenance', 'warranty', 'insurance', 'internal', 'fleet'])->label(__('Type'))->filterable(),
            Field::select('department_id', fn () => Options::departments())->label(__('Department'))->relation('department', 'displayName')->index()->filterable(),
            Field::number('progress')->label(__('Progress'))->display(fn ($j) => '<div class="progress" style="height:6px;width:70px"><div class="progress-bar" style="width:'.(int) $j->progress.'%"></div></div>')->index(false),
            Field::datetime('received_at')->label(__('Received'))->index(),
            Field::datetime('promised_at')->label(__('Promised'))->index(),
            Field::money('estimated_total')->label(__('Estimate'))->index(),
        ];
    }

    public function query(): Builder
    {
        return parent::query()->when(request('open') === '1', fn ($q) => $q->open());
    }

    public function createUrl(): ?string { return route('reception.create'); }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('job-cards.show', $m); }
    public function deleteBlocker(Model $m): ?string { return __('Job cards cannot be deleted. Cancel them instead.'); }

    public function headerActions(): array
    {
        return [['label' => __('Kanban board'), 'url' => route('workshop.board'), 'icon' => 'bi-kanban']];
    }
}

class QuotationResource extends CrudResource
{
    public static string $model = Quotation::class;
    public static string $slug = 'quotations';
    public static string $permission = 'quotations';
    public static string $icon = 'bi-file-earmark-text';
    public static array $with = ['customer', 'vehicle', 'jobCard'];
    public static ?string $dateColumn = 'created_at';

    public function title(): string { return __('Quotations'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable()->display(fn ($q) => e($q->number).($q->version > 1 ? ' <span class="badge text-bg-light">v'.$q->version.'</span>' : '')),
            Field::select('type', ['workshop', 'sales'])->index()->filterable(),
            Field::text('customer_name')->label(__('Customer'))->display(fn ($q) => e($q->customer?->name))->index(false),
            Field::text('job')->label(__('Job card'))->display(fn ($q) => e($q->jobCard?->number ?? '—'))->index(false),
            Field::checkbox('is_supplementary')->label(__('Additional work'))->index()->filterable(),
            Field::select('status', ['draft', 'sent', 'approved', 'partially_approved', 'rejected', 'expired', 'superseded', 'converted'])->index()->filterable(),
            Field::date('valid_until')->label(__('Valid until'))->index(),
            Field::money('total')->index(),
            Field::money('approved_total')->label(__('Approved'))->index(),
        ];
    }

    public function createUrl(): ?string { return route('quotations.sales.create'); }
    public function editUrl(Model $m): ?string { return $m->isEditable() ? route('quotations.edit', $m) : null; }
    public function showUrl(Model $m): ?string { return route('quotations.show', $m); }
    public function canEditRecord(Model $m): bool { return $m->isEditable(); }
    public function deleteBlocker(Model $m): ?string { return $m->status !== 'draft' ? __('Only draft quotations can be deleted.') : null; }
}

class InspectionResource extends CrudResource
{
    public static string $model = Inspection::class;
    public static string $slug = 'inspections';
    public static string $permission = 'inspections';
    public static ?string $module = 'workshop';
    public static string $icon = 'bi-search';
    public static array $with = ['jobCard.vehicle', 'technician', 'template'];
    public static ?string $dateColumn = 'started_at';

    public function title(): string { return __('Inspections & Diagnostics'); }

    public function fields(): array
    {
        return [
            Field::text('job')->label(__('Job card'))->display(fn ($i) => e($i->jobCard?->number.' — '.$i->jobCard?->vehicle?->plate()))->index(false),
            Field::text('template_name')->label(__('Checklist'))->display(fn ($i) => e($i->template?->name ?? '—'))->index(false),
            Field::text('tech')->label(__('Technician'))->display(fn ($i) => e($i->technician?->name))->index(false),
            Field::select('status', ['in_progress', 'completed'])->index()->filterable(),
            Field::datetime('started_at')->label(__('Started'))->index(),
            Field::datetime('completed_at')->label(__('Completed'))->index(),
        ];
    }

    public function createUrl(): ?string { return null; }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('inspections.show', $m); }
    public function deleteBlocker(Model $m): ?string { return $m->status === 'completed' ? __('Completed inspections cannot be deleted.') : null; }
}

class DepartmentResource extends CrudResource
{
    public static string $model = Department::class;
    public static string $slug = 'departments';
    public static string $permission = 'departments';
    public static ?string $module = 'workshop';
    public static string $icon = 'bi-diagram-3';
    public static array $with = ['supervisor', 'costCenter', 'technicians'];
    public static string $defaultSort = 'sort';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Workshop Departments'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->unique('departments')->col(3),
            Field::text('name_ar')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(5),
            Field::text('name_en')->label(__('Name (English)'))->index()->searchable()->col(4),
            Field::select('branch_id', fn () => Options::branches())->label(__('Branch'))->relation('branch', 'displayName'),
            Field::select('supervisor_id', fn () => Options::users())->label(__('Supervisor'))->relation('supervisor')->index(false),
            Field::select('cost_center_id', fn () => Options::costCenters())->label(__('Cost center'))->relation('costCenter', 'displayName')->index(false),
            Field::number('capacity')->label(__('Concurrent capacity'))->default(5)->col(3),
            Field::color('color')->col(3),
            Field::number('sort')->default(0)->col(3),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index()->col(3),
            Field::text('technicians_ids')->label(__('Technicians'))->display(fn ($d) => e($d->technicians->pluck('name')->implode(', ')))->hideOnForm(),
        ];
    }

    public function rowActions(Model $m): array
    {
        return [['label' => __('Technicians'), 'url' => route('departments.technicians', $m), 'icon' => 'bi-people']];
    }
}

class ServiceResource extends CrudResource
{
    public static string $model = Service::class;
    public static string $slug = 'services';
    public static string $permission = 'services';
    public static ?string $module = 'workshop';
    public static string $icon = 'bi-tools';
    public static array $with = ['department', 'vehiclePrices'];
    public static string $defaultSort = 'code';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Services & Prices'); }

    public function fields(): array
    {
        $fields = [
            Field::text('code')->required()->index()->searchable()->unique('services')->col(3),
            Field::text('name_ar')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(5),
            Field::text('name_en')->label(__('Name (English)'))->index()->searchable()->col(4),
            Field::select('department_id', fn () => Options::departments())->label(__('Department'))->relation('department', 'displayName')->index()->filterable(),
            Field::select('pricing_type', ['fixed' => __('Fixed price'), 'hourly' => __('Hourly')])->label(__('Pricing'))->default('fixed')->index()->col(3),
            Field::money('price')->label(__('Price'))->index()->col(3),
            Field::money('hourly_rate')->label(__('Hourly rate'))->col(3),
            Field::money('estimated_cost')->label(__('Estimated cost'))->col(3),
            Field::number('estimated_minutes')->label(__('Expected duration (min)'))->default(60)->index()->col(3),
            Field::decimal('tax_rate')->label(__('VAT %'))->default(5)->col(3),
            Field::number('warranty_days')->label(__('Warranty (days)'))->default(0)->col(3),
            Field::number('warranty_km')->label(__('Warranty (km)'))->default(0)->col(3),
            Field::select('commission_type', ['none' => __('None'), 'percent' => __('Percentage'), 'fixed' => __('Fixed amount')])->label(__('Technician commission'))->default('none')->col(3),
            Field::money('commission_value')->label(__('Commission value'))->col(3),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index()->filterable()->col(3),
            Field::textarea('description'),
        ];
        foreach (Options::VEHICLE_TYPES as $vt) {
            $fields[] = Field::money('vp_'.$vt)->label(__('Price for').' '.status_label($vt))->col(3)->section(__('Prices by vehicle type (optional)'))
                ->hideOnShow();
        }

        return $fields;
    }

    public function virtualFields(): array
    {
        return array_map(fn ($v) => 'vp_'.$v, Options::VEHICLE_TYPES);
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        foreach (Options::VEHICLE_TYPES as $vt) {
            $v = $data['vp_'.$vt] ?? null;
            if ($v === null || $v === '') {
                $m->vehiclePrices()->where('vehicle_type', $vt)->delete();
            } else {
                $m->vehiclePrices()->updateOrCreate(['vehicle_type' => $vt], ['price' => $v]);
            }
        }
    }

    public function showPanels(Model $m): array
    {
        return [['view' => 'workshop::partials.service-prices', 'data' => ['service' => $m]]];
    }
}

class ServicePackageResource extends CrudResource
{
    public static string $model = ServicePackage::class;
    public static string $slug = 'service-packages';
    public static string $permission = 'service_packages';
    public static ?string $module = 'workshop';
    public static string $icon = 'bi-box2-heart';

    public function title(): string { return __('Maintenance Packages'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->unique('service_packages')->col(3),
            Field::text('name_ar')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(5),
            Field::text('name_en')->label(__('Name (English)'))->index()->col(4),
            Field::money('price')->label(__('Package price'))->index()->col(3),
            Field::number('interval_km')->label(__('Every (km)'))->index()->col(3),
            Field::number('interval_months')->label(__('Every (months)'))->index()->col(3),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index()->col(3),
            Field::textarea('description'),
        ];
    }

    public function showPanels(Model $m): array
    {
        return [['view' => 'workshop::partials.package-items', 'data' => ['package' => $m]]];
    }
}

class PromotionResource extends CrudResource
{
    public static string $model = Promotion::class;
    public static string $slug = 'promotions';
    public static string $permission = 'promotions';
    public static string $icon = 'bi-percent';
    public static ?string $dateColumn = 'starts_at';

    public function title(): string { return __('Offers & Promotions'); }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable(),
            Field::text('code')->index()->searchable()->col(3),
            Field::select('type', ['percent' => __('Percentage'), 'fixed' => __('Fixed amount')])->default('percent')->index()->col(3),
            Field::money('value')->required()->index()->col(3),
            Field::select('applies_to', ['all' => __('All'), 'services' => __('Services'), 'parts' => __('Parts')])->default('all')->index()->col(3),
            Field::money('min_amount')->label(__('Minimum amount'))->col(3),
            Field::date('starts_at')->label(__('Starts'))->index()->col(3),
            Field::date('ends_at')->label(__('Ends'))->index()->col(3),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index()->col(3),
        ];
    }
}

class WorkshopBayResource extends CrudResource
{
    public static string $model = WorkshopBay::class;
    public static string $slug = 'workshop-bays';
    public static string $permission = 'workshop_bays';
    public static ?string $module = 'workshop';
    public static string $icon = 'bi-grid-3x3-gap';
    public static array $with = ['branch', 'department'];

    public function title(): string { return __('Workshop Bays'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->col(3),
            Field::text('name')->required()->index()->searchable()->col(5),
            Field::select('type', ['general', 'lift', 'pit', 'wash', 'paint_booth', 'alignment'])->default('general')->index()->col(4),
            Field::select('branch_id', fn () => Options::branches())->label(__('Branch'))->relation('branch', 'displayName')->index(),
            Field::select('department_id', fn () => Options::departments())->label(__('Department'))->relation('department', 'displayName')->index(),
            Field::checkbox('has_lift')->label(__('Has a lift'))->index()->col(4),
            Field::number('lift_capacity_kg')->label(__('Lift capacity (kg)'))->col(4),
            Field::number('sort')->label(__('Order on the planner'))->default(0)->col(4),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index(),
        ];
    }

    public function headerActions(): array
    {
        return auth()->user()?->can('workshop_planning.view') ? [['label' => __('Workshop planner'), 'url' => route('workshop.planner'), 'icon' => 'bi-calendar-range']] : [];
    }
}

class YardLocationResource extends CrudResource
{
    public static string $model = \App\Modules\Workshop\Models\YardLocation::class;
    public static string $slug = 'yard-locations';
    public static string $permission = 'yard_locations';
    public static ?string $module = 'vehicle_tracking';
    public static string $icon = 'bi-geo-alt';
    public static array $with = ['bay', 'branch'];
    public static string $defaultSort = 'code';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Yard locations'); }
    public function singular(): string { return __('Yard location'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->col(3)->help(__('Short code painted on the spot, e.g. P-01')),
            Field::text('name')->required()->index()->searchable()->col(5),
            Field::text('zone')->label(__('Zone'))->index()->filterable()->col(4),
            Field::select('type', array_combine(\App\Modules\Workshop\Models\YardLocation::TYPES, array_map('status_label', \App\Modules\Workshop\Models\YardLocation::TYPES)))->default('parking')->index()->filterable()->col(4),
            Field::select('bay_id', fn () => \App\Modules\Workshop\Models\WorkshopBay::query()->orderBy('code')->pluck('name', 'id')->all())->label(__('Workshop bay'))->relation('bay')->col(4),
            Field::select('branch_id', fn () => Options::branches())->label(__('Branch'))->relation('branch', 'displayName')->col(4),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index(),
        ];
    }

    public function headerActions(): array
    {
        return auth()->user()?->can('vehicle_tracking.view') ? [['label' => __('Vehicle & key tracking'), 'url' => route('tracking.index'), 'icon' => 'bi-key']] : [];
    }
}

class InspectionTemplateResource extends CrudResource
{
    public static string $model = InspectionTemplate::class;
    public static string $slug = 'inspection-templates';
    public static string $permission = 'inspection_templates';
    public static ?string $module = 'workshop';
    public static string $icon = 'bi-list-check';

    public function title(): string { return __('Inspection Checklists'); }

    public function query(): Builder
    {
        return InspectionTemplate::query()->withCount('items');
    }

    public function fields(): array
    {
        return [
            Field::text('name')->label(__('Name (Arabic)'))->required()->index()->searchable(),
            Field::text('name_en')->label(__('Name (English)'))->index(),
            Field::select('vehicle_type', Options::VEHICLE_TYPES)->label(__('Vehicle type'))->index()->help(__('Leave empty for all vehicle types.')),
            Field::select('kind', ['inspection' => __('Vehicle inspection (DVI)'), 'qc' => __('Quality control (QMS)')])->label(__('Used for'))->default('inspection')->index()->filterable(),
            Field::number('items_count')->label(__('Items'))->hideOnForm()->index(false),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index(),
            Field::textarea('items_text')->label(__('Checklist items'))->hideOnShow()
                ->help(__('One item per line: Section | Item (Arabic) | Item (English) | unit | min OK | max OK | service code. Unit, limits and service are optional (e.g. Brakes | سماكة الفحمات | Pad thickness | mm | 3 | | BRK-01). Saving replaces the list.')),
        ];
    }

    public function virtualFields(): array { return ['items_text']; }

    public function saved(Model $m, array $data, bool $created): void
    {
        $text = trim((string) ($data['items_text'] ?? ''));
        if ($text === '') {
            return;
        }
        $m->items()->delete();
        foreach (preg_split('/\R/u', $text) as $i => $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) >= 2 && $p[1] !== '') {
                $num = fn ($v) => isset($v) && is_numeric($v) ? $v : null;
                $m->items()->create(['section' => $p[0], 'label' => $p[1], 'label_en' => ($p[2] ?? '') ?: null, 'sort' => $i,
                    'unit' => ($p[3] ?? '') ?: null, 'min_ok' => $num($p[4] ?? null), 'max_ok' => $num($p[5] ?? null),
                    'service_id' => ! empty($p[6]) ? \App\Modules\Workshop\Models\Service::query()->where('code', $p[6])->value('id') : null]);
            }
        }
    }

    public function showPanels(Model $m): array
    {
        return [['view' => 'workshop::partials.template-items', 'data' => ['template' => $m]]];
    }
}

class GateLogResource extends CrudResource
{
    public static string $model = VehicleGateLog::class;
    public static string $slug = 'gate-logs';
    public static string $permission = 'reception';
    public static ?string $module = 'workshop';
    public static string $icon = 'bi-signpost-split';
    public static bool $readonly = true;
    public static array $with = ['vehicle', 'jobCard', 'user'];
    public static string $defaultSort = 'logged_at';
    public static ?string $dateColumn = 'logged_at';

    public function title(): string { return __('Vehicle Gate Log'); }

    public function indexUrl(): string { return route('gate-logs.index'); }

    public function fields(): array
    {
        return [
            Field::datetime('logged_at')->label(__('Time'))->index(),
            Field::select('direction', ['in' => __('In'), 'out' => __('Out')])->index()->filterable(),
            Field::text('vehicle_label')->label(__('Vehicle'))->display(fn ($g) => e($g->vehicle?->label()))->index(false),
            Field::text('job')->label(__('Job card'))->display(fn ($g) => e($g->jobCard?->number))->index(false),
            Field::number('odometer')->index(),
            Field::text('by')->label(__('By'))->display(fn ($g) => e($g->user?->name))->index(false),
        ];
    }

    public function rowActions(Model $m): array
    {
        return $m->direction === 'out' && $m->job_card_id && auth()->user()?->can('job_cards.print')
            ? [['label' => __('Gate pass'), 'url' => route('job-cards.gate-pass', $m->job_card_id), 'icon' => 'bi-door-open', 'blank' => true]]
            : [];
    }
}

class CommissionResource extends CrudResource
{
    public static string $model = Commission::class;
    public static string $slug = 'commissions';
    public static string $permission = 'commissions';
    public static string $icon = 'bi-cash-coin';
    public static bool $readonly = true;
    public static array $with = ['user', 'invoice'];
    public static string $defaultSort = 'earned_at';
    public static ?string $dateColumn = 'earned_at';

    public function title(): string { return __('Commissions'); }

    public function fields(): array
    {
        return [
            Field::date('earned_at')->label(__('Date'))->index(),
            Field::select('user_id', fn () => Options::users())->label(__('Employee'))->relation('user')->index()->filterable(),
            Field::select('type', ['technician' => __('Technician'), 'sales' => __('Sales')])->index()->filterable(),
            Field::text('inv')->label(__('Invoice'))->display(fn ($c) => e($c->invoice?->number))->index(false),
            Field::money('base_amount')->label(__('Base'))->index(),
            Field::decimal('rate')->label(__('Rate %'))->index(),
            Field::money('amount')->index(),
            Field::select('status', ['pending', 'paid', 'cancelled'])->index()->filterable(),
        ];
    }
}

class AppointmentResource extends CrudResource
{
    public static string $model = Appointment::class;
    public static string $slug = 'appointments';
    public static string $permission = 'appointments';
    public static ?string $module = 'appointments';
    public static string $icon = 'bi-calendar-week';
    public static array $with = ['customer', 'vehicle', 'department', 'technician'];
    public static string $defaultSort = 'starts_at';
    public static ?string $dateColumn = 'starts_at';

    public function title(): string { return __('Appointments'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::datetime('starts_at')->label(__('Starts'))->index(),
            Field::text('cust')->label(__('Customer'))->display(fn ($a) => e($a->customer?->name.' '.$a->customer?->phone))->index(false),
            Field::text('veh')->label(__('Vehicle'))->display(fn ($a) => e($a->vehicle?->label()))->index(false),
            Field::select('department_id', fn () => Options::departments())->label(__('Department'))->relation('department', 'displayName')->index()->filterable(),
            Field::text('tech')->label(__('Technician'))->display(fn ($a) => e($a->technician?->name))->index(false),
            Field::select('status', ['scheduled', 'confirmed', 'checked_in', 'completed', 'no_show', 'cancelled'])->index()->filterable(),
            Field::select('source', ['staff', 'portal', 'phone', 'whatsapp'])->index()->filterable(),
        ];
    }

    public function createUrl(): ?string { return route('appointments.calendar'); }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('appointments.show', $m); }
}
