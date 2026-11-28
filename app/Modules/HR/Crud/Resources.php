<?php

namespace App\Modules\HR\Crud;

use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeAdjustment;
use App\Modules\HR\Models\EmployeeContract;
use App\Modules\HR\Models\EmployeeLoan;
use App\Modules\HR\Models\HrDepartment;
use App\Modules\HR\Models\JobTitle;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Models\LeaveType;
use App\Modules\HR\Models\Payroll;
use App\Modules\HR\Models\PerformanceReview;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\DocumentNumber;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

abstract class HrResource extends CrudResource
{
    public static ?string $module = 'hr';
}

class EmployeeResource extends HrResource
{
    public static string $model = Employee::class;
    public static string $slug = 'employees';
    public static string $permission = 'employees';
    public static string $icon = 'bi-person-badge';
    public static array $with = ['department', 'jobTitle', 'branch', 'user'];
    public static bool $hasAttachments = true;

    public function title(): string { return __('Employees'); }
    public function singular(): string { return __('Employee'); }

    public function fields(): array
    {
        $expiry = fn ($col) => fn ($e) => $e->{$col} ? '<span class="'.($e->{$col}->isPast() ? 'text-danger fw-bold' : ($e->{$col}->lt(now()->addDays(30)) ? 'text-warning fw-bold' : '')).'">'.$e->{$col}->format('Y-m-d').'</span>' : '—';

        return [
            Field::text('code')->hideOnForm()->index()->searchable(),
            Field::text('name')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(6)->section(__('Personal information')),
            Field::text('name_en')->label(__('Name (English)'))->searchable()->col(6),
            Field::text('nationality')->col(3)->rules(['size:2'])->help('OM, IN, PK...'),
            Field::select('gender', ['male' => __('Male'), 'female' => __('Female')])->col(3),
            Field::date('birth_date')->label(__('Birth date'))->col(3),
            Field::tel('phone')->index()->col(3),
            Field::email('email')->col(4),
            Field::text('address')->col(8),
            Field::text('national_id')->label(__('Civil ID / Resident card'))->col(4)->section(__('Documents')),
            Field::date('id_expiry')->label(__('ID expiry'))->col(4)->display($expiry('id_expiry'))->index(false),
            Field::text('passport_number')->label(__('Passport'))->col(4),
            Field::date('passport_expiry')->label(__('Passport expiry'))->col(4)->display($expiry('passport_expiry')),
            Field::text('visa_number')->label(__('Visa / labour card'))->col(4),
            Field::date('visa_expiry')->label(__('Visa expiry'))->col(4)->display($expiry('visa_expiry'))->index(false),
            Field::date('driving_license_expiry')->label(__('Driving licence expiry'))->col(4)->display($expiry('driving_license_expiry')),
            Field::select('branch_id', fn () => Options::branches())->label(__('Branch'))->relation('branch', 'displayName')->index()->filterable()->col(4)->section(__('Employment')),
            Field::select('hr_department_id', fn () => HrDepartment::query()->pluck('name', 'id')->all())->label(__('Department'))->relation('department')->index()->filterable()->col(4),
            Field::select('job_title_id', fn () => JobTitle::query()->pluck('name', 'id')->all())->label(__('Job title'))->relation('jobTitle')->index()->col(4),
            Field::date('hire_date')->label(__('Hire date'))->col(4),
            Field::select('status', ['active', 'on_leave', 'terminated'])->default('active')->index()->filterable()->col(4),
            Field::date('termination_date')->label(__('Termination date'))->col(4),
            Field::select('user_id', fn () => Options::users())->label(__('System user'))->relation('user')->col(4),
            Field::checkbox('is_technician')->label(__('Technician'))->index()->filterable()->col(4),
            Field::textarea('skills')->label(__('Skills & certifications')),
            Field::money('basic_salary')->label(__('Basic salary'))->required()->section(__('Salary & allowances'))->col(3),
            Field::money('housing_allowance')->label(__('Housing allowance'))->col(3),
            Field::money('transport_allowance')->label(__('Transport allowance'))->col(3),
            Field::money('other_allowances')->label(__('Other allowances'))->col(3),
            Field::money('hourly_cost')->label(__('Hourly labour cost'))->col(3)->help(__('Used for job card costing.')),
            Field::decimal('commission_rate')->label(__('Sales commission %'))->col(3),
            Field::text('bank_name')->label(__('Bank'))->col(3),
            Field::text('iban')->label('IBAN')->col(3),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['code'] = DocumentNumber::next('employee', false);
        }
        if (empty($data['hourly_cost']) && ! empty($data['basic_salary'])) {
            $data['hourly_cost'] = \App\Support\Money::div(\App\Support\Money::add($data['basic_salary'], $data['housing_allowance'] ?? 0, $data['transport_allowance'] ?? 0), 208);
        }

        return $data;
    }
}

class SimpleHrResource extends HrResource
{
    public static string $model = HrDepartment::class;
    public static string $slug = 'hr-departments';
    public static string $permission = 'hr_departments';
    public static string $icon = 'bi-diagram-3';

    public function title(): string { return __('HR Departments'); }

    public function fields(): array
    {
        return [
            Field::text('name')->label(__('Name (Arabic)'))->required()->index()->searchable(),
            Field::text('name_en')->label(__('Name (English)'))->index(),
            Field::select('manager_id', fn () => Options::users())->label(__('Manager'))->relation('manager')->index(),
        ];
    }
}

class JobTitleResource extends HrResource
{
    public static string $model = JobTitle::class;
    public static string $slug = 'job-titles';
    public static string $permission = 'job_titles';
    public static string $icon = 'bi-person-vcard';

    public function title(): string { return __('Job Titles'); }

    public function fields(): array
    {
        return [
            Field::text('name')->label(__('Name (Arabic)'))->required()->index()->searchable(),
            Field::text('name_en')->label(__('Name (English)'))->index(),
        ];
    }
}

class LeaveTypeResource extends HrResource
{
    public static string $model = LeaveType::class;
    public static string $slug = 'leave-types';
    public static string $permission = 'leaves';
    public static string $icon = 'bi-list-ul';

    public function title(): string { return __('Leave Types'); }

    public function fields(): array
    {
        return [
            Field::text('name')->label(__('Name (Arabic)'))->required()->index()->searchable(),
            Field::text('name_en')->label(__('Name (English)'))->index(),
            Field::number('days_per_year')->label(__('Days per year'))->index(),
            Field::checkbox('is_paid')->label(__('Paid'))->default(true)->index(),
        ];
    }
}

class EmployeeContractResource extends HrResource
{
    public static string $model = EmployeeContract::class;
    public static string $slug = 'employee-contracts';
    public static string $permission = 'employees';
    public static string $icon = 'bi-file-earmark-person';
    public static array $with = ['employee'];
    public static bool $hasAttachments = true;
    public static string $defaultSort = 'end_date';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Employment Contracts'); }

    public function fields(): array
    {
        return [
            Field::lookup('employee_id', 'employees', 'employee')->label(__('Employee'))->required()->index(),
            Field::text('contract_number')->label(__('Contract no.'))->index()->searchable()->col(3),
            Field::select('type', ['limited' => __('Limited'), 'unlimited' => __('Unlimited'), 'part_time' => __('Part time')])->default('limited')->index()->col(3),
            Field::date('start_date')->label(__('Start'))->required()->index()->col(3),
            Field::date('end_date')->label(__('End'))->index()->col(3)
                ->display(fn ($c) => $c->end_date ? '<span class="'.($c->end_date->lt(now()->addDays(60)) ? 'text-danger fw-bold' : '').'">'.$c->end_date->format('Y-m-d').'</span>' : '—'),
            Field::money('basic_salary')->label(__('Basic salary'))->col(3),
            Field::select('status', ['active', 'expired', 'terminated'])->default('active')->index()->filterable()->col(3),
            Field::textarea('notes'),
        ];
    }
}

class AttendanceResource extends HrResource
{
    public static string $model = Attendance::class;
    public static string $slug = 'attendances';
    public static string $permission = 'attendance';
    public static string $icon = 'bi-fingerprint';
    public static array $with = ['employee'];
    public static string $defaultSort = 'date';
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Attendance'); }

    public function fields(): array
    {
        return [
            Field::lookup('employee_id', 'employees', 'employee')->label(__('Employee'))->required()->index(),
            Field::date('date')->required()->default(now()->toDateString())->index()->col(3),
            Field::datetime('check_in')->label(__('Check in'))->index()->col(3),
            Field::datetime('check_out')->label(__('Check out'))->index()->col(3)->rules(['nullable', 'after:check_in']),
            Field::select('status', ['present', 'late', 'absent', 'leave', 'holiday', 'weekend'])->default('present')->index()->filterable()->col(3),
            Field::number('worked_minutes')->label(__('Worked (min)'))->hideOnForm()->index(),
            Field::number('overtime_minutes')->label(__('Overtime (min)'))->index()->col(3),
            Field::number('late_minutes')->label(__('Late (min)'))->index()->col(3),
            Field::select('source', ['manual', 'device', 'api'])->default('manual')->hideOnForm()->index(),
            Field::text('notes')->col(6),
        ];
    }

    public function rules(?Model $m): array
    {
        $r = parent::rules($m);
        $r['date'][] = \Illuminate\Validation\Rule::unique('attendances')->where('employee_id', request('employee_id'))->ignore($m?->id);

        return $r;
    }

    public function saving(Model $m, array $data, bool $creating): void
    {
        if ($m->check_in && $m->check_out) {
            $m->worked_minutes = (int) Carbon::parse($m->check_in)->diffInMinutes(Carbon::parse($m->check_out));
            if (! $m->isDirty('overtime_minutes') || ! $m->overtime_minutes) {
                $m->overtime_minutes = max(0, $m->worked_minutes - (int) setting('hr.working_minutes_per_day', 480));
            }
        }
    }

    public function headerActions(): array
    {
        return [['label' => __('Bulk daily attendance'), 'url' => route('hr.attendance.daily'), 'icon' => 'bi-calendar-check']];
    }
}

class LeaveRequestResource extends HrResource
{
    public static string $model = LeaveRequest::class;
    public static string $slug = 'leave-requests';
    public static string $permission = 'leaves';
    public static string $icon = 'bi-airplane-engines';
    public static array $with = ['employee', 'type', 'approver'];
    public static ?string $dateColumn = 'start_date';

    public function title(): string { return __('Leave Requests'); }

    public function fields(): array
    {
        return [
            Field::lookup('employee_id', 'employees', 'employee')->label(__('Employee'))->required()->index(),
            Field::select('leave_type_id', fn () => LeaveType::query()->pluck('name', 'id')->all())->label(__('Leave type'))->relation('type')->required()->index()->filterable(),
            Field::date('start_date')->label(__('From'))->required()->index()->col(3),
            Field::date('end_date')->label(__('To'))->required()->index()->col(3)->rules(['after_or_equal:start_date']),
            Field::decimal('days')->hideOnForm()->index(),
            Field::text('reason')->col(6),
            Field::select('status', ['pending', 'approved', 'rejected', 'cancelled'])->hideOnForm()->index()->filterable(),
            Field::text('appr')->label(__('Approved by'))->display(fn ($l) => e($l->approver?->name))->hideOnForm(),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        $data['days'] = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;
        if (! $m) {
            $data['status'] = 'pending';
        }

        return $data;
    }

    public function canEditRecord(Model $m): bool { return $m->status === 'pending'; }

    public function rowActions(Model $m): array
    {
        return $m->status === 'pending' && auth()->user()->can('leaves.approve') ? [
            ['label' => __('Approve'), 'url' => route('hr.leaves.decide', [$m, 'approve']), 'icon' => 'bi-check-lg', 'method' => 'post', 'class' => 'btn-success'],
            ['label' => __('Reject'), 'url' => route('hr.leaves.decide', [$m, 'reject']), 'icon' => 'bi-x-lg', 'method' => 'post', 'class' => 'btn-danger'],
        ] : [];
    }
}

class EmployeeLoanResource extends HrResource
{
    public static string $model = EmployeeLoan::class;
    public static string $slug = 'employee-loans';
    public static string $permission = 'loans';
    public static string $icon = 'bi-cash';
    public static array $with = ['employee'];
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Salary Advances & Loans'); }

    public function fields(): array
    {
        return [
            Field::lookup('employee_id', 'employees', 'employee')->label(__('Employee'))->required()->index(),
            Field::date('date')->index(),
            Field::select('loan_type', ['advance', 'financing'])->label(__('Type'))->index()->filterable(),
            Field::money('amount')->label(__('Principal'))->required()->index(),
            Field::money('total_payable')->label(__('Total payable'))->index(),
            Field::money('installment')->label(__('Monthly installment'))->required()->index(),
            Field::money('remaining')->label(__('Outstanding'))->index(),
            Field::select('status', ['active', 'settled'])->index()->filterable(),
            Field::text('notes'),
        ];
    }

    public function createUrl(): ?string { return route('hr.loans.create'); }
    public function showUrl(Model $m): ?string { return route('hr.loans.show', $m); }
    public function rowActions(Model $m): array { return [['label' => __('Print voucher'), 'url' => route('vouchers.loan', $m), 'icon' => 'bi-printer', 'blank' => true]]; }
    public function editUrl(Model $m): ?string { return null; }
    public function deleteBlocker(Model $m): ?string { return __('Loans are financial records and cannot be deleted.'); }
}

class EmployeeAdjustmentResource extends HrResource
{
    public static string $model = EmployeeAdjustment::class;
    public static string $slug = 'employee-adjustments';
    public static string $permission = 'payroll';
    public static string $icon = 'bi-plus-slash-minus';
    public static array $with = ['employee'];
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Bonuses, Incentives & Deductions'); }

    public function fields(): array
    {
        return [
            Field::lookup('employee_id', 'employees', 'employee')->label(__('Employee'))->required()->index(),
            Field::select('type', ['bonus', 'incentive', 'allowance', 'overtime', 'deduction', 'penalty'])->required()->index()->filterable()->col(3),
            Field::money('amount')->required()->index()->col(3)->rules(['gt:0']),
            Field::date('date')->required()->default(now()->toDateString())->index()->col(3),
            Field::text('description')->index()->col(12),
            Field::text('pay')->label(__('Payroll'))->display(fn ($a) => $a->payroll_id ? __('Included') : __('Pending'))->hideOnForm()->index(false),
        ];
    }

    public function canEditRecord(Model $m): bool { return ! $m->payroll_id; }
    public function deleteBlocker(Model $m): ?string { return $m->payroll_id ? __('Already included in a payroll.') : null; }
}

class PayrollResource extends HrResource
{
    public static string $model = Payroll::class;
    public static string $slug = 'payrolls';
    public static string $permission = 'payroll';
    public static string $icon = 'bi-cash-coin';
    public static string $defaultSort = 'period_month';

    public function title(): string { return __('Payroll'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('period_month')->label(__('Month'))->display(fn ($p) => e($p->period_month->format('Y-m')))->index(),
            Field::select('status', ['draft', 'approved', 'posted', 'paid'])->index()->filterable(),
            Field::money('total_gross')->label(__('Gross'))->index(),
            Field::money('total_deductions')->label(__('Deductions'))->index(),
            Field::money('total_net')->label(__('Net'))->index(),
        ];
    }

    public function createUrl(): ?string { return route('hr.payroll.create'); }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('hr.payroll.show', $m); }
    public function deleteBlocker(Model $m): ?string { return $m->status !== 'draft' ? __('Only draft payrolls can be deleted.') : null; }
}

class PerformanceReviewResource extends HrResource
{
    public static string $model = PerformanceReview::class;
    public static string $slug = 'performance-reviews';
    public static string $permission = 'performance_reviews';
    public static string $icon = 'bi-graph-up';
    public static array $with = ['employee', 'reviewer'];

    public function title(): string { return __('Performance Reviews'); }

    public function fields(): array
    {
        return [
            Field::lookup('employee_id', 'employees', 'employee')->label(__('Employee'))->required()->index(),
            Field::text('period')->required()->index()->col(3),
            Field::decimal('score')->label(__('Score (0-100)'))->required()->index()->col(3)->rules(['min:0', 'max:100']),
            Field::text('rev')->label(__('Reviewer'))->display(fn ($r) => e($r->reviewer?->name))->hideOnForm()->index(false),
            Field::textarea('strengths'),
            Field::textarea('improvements')->label(__('Areas to improve')),
            Field::textarea('notes'),
        ];
    }

    public function saving(Model $m, array $data, bool $creating): void
    {
        if ($creating) {
            $m->reviewer_id = auth()->id();
        }
    }
}
