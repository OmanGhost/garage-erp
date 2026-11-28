<?php

namespace App\Modules\Core\Crud;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\CostCenter;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\Holiday;
use App\Modules\Core\Models\LoginHistory;
use App\Modules\Core\Models\NotificationTemplate;
use App\Modules\Core\Models\TaxRate;
use App\Modules\Core\Models\Webhook;
use App\Modules\Core\Models\WorkingHour;
use App\Modules\Core\Services\NotificationService;
use App\Modules\Core\Services\WebhookService;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class BranchResource extends CrudResource
{
    public static string $model = Branch::class;
    public static string $slug = 'branches';
    public static string $permission = 'branches';
    public static string $icon = 'bi-geo-alt';

    public function title(): string { return __('Branches'); }
    public function singular(): string { return __('Branch'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->unique('branches')->col(3),
            Field::text('name_ar')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(5),
            Field::text('name_en')->label(__('Name (English)'))->index()->searchable()->col(4),
            Field::tel('phone')->index(),
            Field::email('email'),
            Field::text('city')->index(),
            Field::text('address'),
            Field::select('cost_center_id', fn () => Options::costCenters())->label(__('Cost center'))->relation('costCenter', 'displayName'),
            Field::number('daily_capacity')->label(__('Daily workshop capacity'))->default(20)->col(3),
            Field::checkbox('is_main')->label(__('Main branch'))->col(3),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index()->filterable()->col(3),
        ];
    }

    public function saving(Model $m, array $data, bool $creating): void
    {
        $max = tenant()->company()?->plan?->max_branches;
        if ($creating && $max && Branch::query()->count() >= $max) {
            throw new BusinessException(__('Your subscription plan allows up to :n branches.', ['n' => $max]));
        }
    }
}

class UserResource extends CrudResource
{
    public static string $model = User::class;
    public static string $slug = 'users';
    public static string $permission = 'users';
    public static string $icon = 'bi-person-gear';
    public static array $with = ['branch', 'roles'];

    public function title(): string { return __('Users'); }
    public function singular(): string { return __('User'); }

    public function query(): Builder
    {
        return User::query()->with(static::$with)->where('company_id', tenant()->id())->whereNull('customer_id');
    }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable(),
            Field::email('email')->required()->index()->searchable()->unique('users', true),
            Field::tel('phone')->index(),
            Field::password('password')->label(__('Password'))->required()->help(__('Leave empty to keep the current password.'))->rules([\Illuminate\Validation\Rules\Password::min(10)->mixedCase()->numbers()]),
            Field::select('branch_id', fn () => Options::branches())->label(__('Default branch'))->relation('branch', 'displayName')->index(),
            Field::select('role', fn () => Role::query()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', tenant()->id()))
                ->whereNotIn('name', ['Super Admin', 'Customer'])->pluck('name', 'name')->map(fn ($n) => __($n))->all())->label(__('Role'))->required()
                ->display(fn ($u) => e($u->roles->pluck('name')->map(fn ($n) => __($n))->implode(', ')))->index(false)->hideOnShow(),
            Field::make('branches', 'text')->label(__('Additional branches'))->hideOnShow()->hideOnForm(),
            Field::select('locale', ['ar' => 'العربية', 'en' => 'English'])->label(__('Language'))->default('ar')->col(3),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index()->filterable()->col(3),
            Field::datetime('last_login_at')->label(__('Last login'))->hideOnForm()->index(),
        ];
    }

    public function virtualFields(): array { return ['role', 'branches']; }

    public function rules(?Model $m): array
    {
        $r = parent::rules($m);
        if ($m) {
            $r['password'] = array_values(array_filter($r['password'], fn ($rule) => $rule !== 'required'));
            array_unshift($r['password'], 'nullable');
            $r['role'] = array_values(array_filter($r['role'] ?? [], fn ($rule) => $rule !== 'required'));
            array_unshift($r['role'], 'nullable');
        }

        return $r;
    }

    public function saving(Model $m, array $data, bool $creating): void
    {
        $m->company_id = tenant()->id();
        if ($creating) {
            $max = tenant()->company()?->plan?->max_users;
            if ($max && User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->count() >= $max) {
                throw new BusinessException(__('Your subscription plan allows up to :n users.', ['n' => $max]));
            }
        }
        if (! empty($data['password'])) {
            $m->password_changed_at = now();
        }
        if ($m->id === auth()->id() && ! $m->is_active) {
            throw new BusinessException(__('You cannot deactivate your own account.'));
        }
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        setPermissionsTeamId(tenant()->id());
        if (! $created && ! empty($data['role']) && $m->roles()->pluck('name')->contains($data['role'])) {
            return;                                   // role unchanged
        }
        if (! $created && ! empty($data['role']) && ! auth()->user()->hasRole('Company Admin') && ! auth()->user()->is_super_admin) {
            throw new BusinessException(__('Changing the role of an existing user needs a role change request approved by a branch manager or higher.'));
        }
        if (! empty($data['role'])) {
            if ($data['role'] === 'Company Admin' && ! auth()->user()->hasRole('Company Admin') && ! auth()->user()->is_super_admin) {
                throw new BusinessException(__('Only company administrators can grant the Company Admin role.'));
            }
            $m->syncRoles([$data['role']]);
            \App\Support\Audit\AuditLogger::log('user.role_assigned', $m, ['role' => $data['role']]);
        }
    }

    public function deleteBlocker(Model $m): ?string
    {
        return $m->id === auth()->id() ? __('You cannot delete your own account.') : null;
    }

    public function rowActions(Model $m): array
    {
        $actions = [];
        if (auth()->user()->can('users.update')) {
            $actions[] = ['label' => __('Branch & warehouse access'), 'url' => route('users.access', $m), 'icon' => 'bi-diagram-2'];
        }
        if (auth()->user()->can('role_requests.create') && $m->id !== auth()->id()) {
            $actions[] = ['label' => __('Request role change'), 'url' => route('role-requests.create', ['user' => $m->id]), 'icon' => 'bi-person-up'];
        }

        return $actions;
    }
}

class CostCenterResource extends CrudResource
{
    public static string $model = CostCenter::class;
    public static string $slug = 'cost-centers';
    public static string $permission = 'cost_centers';
    public static ?string $module = 'accounting';
    public static string $icon = 'bi-pie-chart';
    public static string $defaultSort = 'code';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Cost Centers'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->unique('cost_centers')->col(3),
            Field::text('name_ar')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(5),
            Field::text('name_en')->label(__('Name (English)'))->index()->col(4),
            Field::select('parent_id', fn () => Options::costCenters())->label(__('Parent'))->relation('parent', 'displayName')->index(false),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index(),
        ];
    }
}

class WorkingHourResource extends CrudResource
{
    public static string $model = WorkingHour::class;
    public static string $slug = 'working-hours';
    public static string $permission = 'working_hours';
    public static string $icon = 'bi-clock';
    public static array $with = ['branch'];
    public static string $defaultSort = 'day_of_week';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Working Hours'); }

    public function fields(): array
    {
        $days = [0 => __('Sunday'), 1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday')];

        return [
            Field::select('branch_id', fn () => Options::branches())->label(__('Branch'))->relation('branch', 'displayName')->index()->filterable(),
            Field::select('day_of_week', $days)->label(__('Day'))->required()->index(),
            Field::time('opens_at')->label(__('Opens at'))->index()->col(3),
            Field::time('closes_at')->label(__('Closes at'))->index()->col(3),
            Field::checkbox('is_closed')->label(__('Closed'))->index(),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        foreach (['opens_at', 'closes_at'] as $k) {
            if (! empty($data[$k])) {
                $data[$k] = substr($data[$k], 0, 5);
            }
        }

        return $data;
    }
}

class HolidayResource extends CrudResource
{
    public static string $model = Holiday::class;
    public static string $slug = 'holidays';
    public static string $permission = 'holidays';
    public static string $icon = 'bi-calendar-heart';
    public static string $defaultSort = 'date';
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Official Holidays'); }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable(),
            Field::date('date')->label(__('From date'))->required()->index()->col(3),
            Field::date('end_date')->label(__('To date'))->index()->col(3)->rules(['after_or_equal:date']),
            Field::checkbox('is_recurring')->label(__('Repeats yearly'))->index(),
        ];
    }
}

class TaxRateResource extends CrudResource
{
    public static string $model = TaxRate::class;
    public static string $slug = 'tax-rates';
    public static string $permission = 'tax_rates';
    public static string $icon = 'bi-percent';

    public function title(): string { return __('Tax Rates'); }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable(),
            Field::decimal('rate')->label(__('Rate %'))->required()->index()->rules(['min:0', 'max:100'])->col(3),
            Field::checkbox('is_default')->label(__('Default'))->index()->col(3),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index()->col(3),
        ];
    }
}

class DocumentSequenceResource extends CrudResource
{
    public static string $model = DocumentSequence::class;
    public static string $slug = 'document-sequences';
    public static string $permission = 'document_sequences';
    public static string $icon = 'bi-123';
    public static string $defaultSort = 'type';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Document Numbering'); }

    public function fields(): array
    {
        return [
            Field::text('type')->required()->index()->searchable()->col(4)->help(__('e.g. invoice, job_card, quotation')),
            Field::text('prefix')->required()->index()->col(2)->rules(['alpha_dash', 'max:20']),
            Field::number('next_number')->label(__('Next number'))->required()->index()->col(2)->rules(['min:1']),
            Field::number('padding')->required()->default(6)->index()->col(2)->rules(['min:3', 'max:10']),
            Field::checkbox('yearly_reset')->label(__('Reset yearly'))->default(true)->index()->col(2),
        ];
    }
}

class NotificationTemplateResource extends CrudResource
{
    public static string $model = NotificationTemplate::class;
    public static string $slug = 'notification-templates';
    public static string $permission = 'notification_templates';
    public static string $icon = 'bi-chat-square-text';
    public static string $defaultSort = 'event';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Notification Templates'); }

    public function fields(): array
    {
        return [
            Field::select('event', fn () => array_map(fn ($l) => __($l), NotificationService::EVENTS))->required()->index()->filterable(),
            Field::select('channel', ['mail' => __('Email'), 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'database' => __('In-app')])->required()->index()->filterable(),
            Field::text('subject_ar')->label(__('Subject (Arabic)'))->index()->searchable(),
            Field::text('subject_en')->label(__('Subject (English)')),
            Field::textarea('body_ar')->label(__('Message (Arabic)'))->required()->col(6)->help(__('Placeholders: {{customer_name}}, {{number}}, {{total}}, {{link}}, {{plate}}, {{status}}, {{date}}, {{time}}, {{company_name}}')),
            Field::textarea('body_en')->label(__('Message (English)'))->required()->col(6),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index(),
        ];
    }

    /** One template per event + channel for the company (a validation message instead of a database error). */
    public function rules(?\Illuminate\Database\Eloquent\Model $m): array
    {
        $rules = parent::rules($m);
        $rules['channel'][] = \Illuminate\Validation\Rule::unique('notification_templates', 'channel')
            ->where('company_id', tenant()->id())->where('event', (string) request('event'))->ignore($m?->getKey());

        return $rules;
    }
}

class WebhookResource extends CrudResource
{
    public static string $model = Webhook::class;
    public static string $slug = 'webhooks';
    public static string $permission = 'webhooks';
    public static ?string $module = 'integrations';
    public static string $icon = 'bi-broadcast';

    public function title(): string { return __('Webhooks'); }

    public function fields(): array
    {
        return [
            Field::text('url')->label('URL')->required()->index()->searchable()->col(8)->rules(['url', 'starts_with:https://,http://']),
            Field::text('events_csv')->label(__('Events'))->required()->col(4)->help(implode(', ', WebhookService::EVENTS).', *')
                ->display(fn ($w) => e(implode(', ', $w->events ?? [])))->index(false),
            Field::text('secret')->label(__('Signing secret'))->hideOnShow()->help(__('Leave empty to generate automatically.')),
            Field::number('last_status')->label(__('Last HTTP status'))->hideOnForm()->index(),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index(),
        ];
    }

    public function virtualFields(): array { return ['events_csv']; }

    public function saving(Model $m, array $data, bool $creating): void
    {
        $m->events = array_values(array_filter(array_map('trim', explode(',', (string) ($data['events_csv'] ?? '')))));
        if (empty($m->secret)) {
            $m->secret = Str::random(40);
        }
    }
}

class AuditLogResource extends CrudResource
{
    public static string $model = AuditLog::class;
    public static string $slug = 'audit-logs';
    public static string $permission = 'audit_logs';
    public static string $icon = 'bi-shield-exclamation';
    public static bool $readonly = true;
    public static array $with = ['user'];
    public static string $defaultSort = 'created_at';
    public static ?string $dateColumn = 'created_at';

    public function title(): string { return __('Audit Log'); }

    public function query(): Builder
    {
        return AuditLog::query()->with('user')->where('company_id', tenant()->id());
    }

    public function fields(): array
    {
        return [
            Field::datetime('created_at')->label(__('Date'))->index(),
            Field::text('user_name')->label(__('User'))->display(fn ($a) => e($a->user?->name ?? __('System')))->index(false),
            Field::text('event')->label(__('Event'))->index()->searchable(),
            Field::text('auditable_type')->label(__('Record type'))->index()->searchable(),
            Field::number('auditable_id')->label(__('Record ID'))->index(),
            Field::text('ip_address')->label('IP')->index()->searchable(),
            Field::text('changes')->label(__('Changes'))->display(fn ($a) => '<pre class="small mb-0 text-wrap">'.e(json_encode(['old' => $a->old_values, 'new' => $a->new_values], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)).'</pre>'),
            Field::text('url')->label('URL'),
            Field::text('user_agent')->label(__('Device')),
        ];
    }
}

class LoginHistoryResource extends CrudResource
{
    public static string $model = LoginHistory::class;
    public static string $slug = 'login-histories';
    public static string $permission = 'audit_logs';
    public static string $icon = 'bi-door-closed';
    public static bool $readonly = true;
    public static string $defaultSort = 'created_at';
    public static ?string $dateColumn = 'created_at';

    public function title(): string { return __('Login History'); }

    public function query(): Builder
    {
        return LoginHistory::query()->where('company_id', tenant()->id());
    }

    public function fields(): array
    {
        return [
            Field::datetime('created_at')->label(__('Date'))->index(),
            Field::text('email')->index()->searchable(),
            Field::checkbox('successful')->label(__('Successful'))->index()->filterable(),
            Field::text('failure_reason')->label(__('Reason'))->index(),
            Field::text('ip_address')->label('IP')->index()->searchable(),
            Field::text('user_agent')->label(__('Device'))->index(),
        ];
    }
}
