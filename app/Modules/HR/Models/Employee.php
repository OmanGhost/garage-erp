<?php

namespace App\Modules\HR\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends TenantModel
{
    use SoftDeletes, HasAttachments;

    protected $casts = [
        'basic_salary' => 'decimal:3',
        'housing_allowance' => 'decimal:3',
        'transport_allowance' => 'decimal:3',
        'other_allowances' => 'decimal:3',
        'hourly_cost' => 'decimal:3',
        'commission_rate' => 'decimal:3',
        'birth_date' => 'date',
        'id_expiry' => 'date',
        'passport_expiry' => 'date',
        'visa_expiry' => 'date',
        'driving_license_expiry' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'is_technician' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Core\Models\Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\HR\Models\HrDepartment::class, 'hr_department_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\HR\Models\JobTitle::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(\App\Modules\HR\Models\EmployeeContract::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(\App\Modules\HR\Models\Attendance::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(\App\Modules\HR\Models\EmployeeLoan::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(\App\Modules\HR\Models\EmployeeAdjustment::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(\App\Modules\HR\Models\LeaveRequest::class);
    }

    public function grossSalary(): string
    {
        return \App\Support\Money::add($this->basic_salary, $this->housing_allowance, $this->transport_allowance, $this->other_allowances);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo_path) : null;
    }
}
