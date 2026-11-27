<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use App\Support\Tenancy\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobCard extends TenantModel
{
    use SoftDeletes, BelongsToBranch, HasAttachments;

    protected $casts = [
        'estimated_total' => 'decimal:3',
        'approved_total' => 'decimal:3',
        'labor_cost' => 'decimal:3',
        'parts_cost' => 'decimal:3',
        'subcontract_cost' => 'decimal:3',
        'total_cost' => 'decimal:3',
        'revenue' => 'decimal:3',
        'profit' => 'decimal:3',
        'received_at' => 'datetime',
        'promised_at' => 'datetime',
        'estimated_completion_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Customer::class);
    }

    public function keyHolder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'key_holder_id');
    }

    public function yardLocation(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\YardLocation::class);
    }

    public function testDrives(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\TestDrive::class);
    }

    public function trackingEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\VehicleTrackingEvent::class);
    }

    /** Running late: promised (or estimated) time passed and the vehicle is not ready yet. */
    public function isLate(): bool
    {
        $due = $this->promised_at ?? $this->estimated_completion_at;

        return $due && $due->isPast() && in_array($this->status, array_diff(self::OPEN_STATUSES, [self::READY]), true);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Vehicles\Models\Vehicle::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'service_advisor_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\Department::class);
    }

    public function bay(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\WorkshopBay::class, 'bay_id');
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Fleet\Models\Fleet::class);
    }

    public function insuranceClaim(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Insurance\Models\InsuranceClaim::class);
    }

    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Warranty\Models\WarrantyClaim::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Appointments\Models\Appointment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Sales\Models\Invoice::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Sales\Models\Invoice::class);
    }

    public function subcontracts(): HasMany
    {
        return $this->hasMany(SubcontractOrder::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\JobCardTask::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\JobCardPart::class);
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(HandoverAuthorization::class, 'handover_authorization_id');
    }

    public function checkIn(): HasOne
    {
        return $this->hasOne(\App\Modules\Workshop\Models\VehicleCheckIn::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\Inspection::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\Quotation::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\JobCardStatusHistory::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\JobCardNote::class);
    }

    public function qualityChecks(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\QualityCheck::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(\App\Modules\CRM\Models\ServiceRating::class);
    }

    public const PENDING_INSPECTION = 'pending_inspection';
    public const UNDER_INSPECTION = 'under_inspection';
    public const AWAITING_QUOTATION = 'awaiting_quotation';
    public const AWAITING_APPROVAL = 'awaiting_customer_approval';
    public const APPROVED = 'approved';
    public const AWAITING_PARTS = 'awaiting_spare_parts';
    public const IN_PROGRESS = 'in_progress';
    public const ON_HOLD = 'on_hold';
    public const QUALITY_INSPECTION = 'quality_inspection';
    public const READY = 'ready_for_delivery';
    public const DELIVERED = 'delivered';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::PENDING_INSPECTION, self::UNDER_INSPECTION, self::AWAITING_QUOTATION, self::AWAITING_APPROVAL,
        self::APPROVED, self::AWAITING_PARTS, self::IN_PROGRESS, self::ON_HOLD, self::QUALITY_INSPECTION,
        self::READY, self::DELIVERED, self::COMPLETED, self::CANCELLED,
    ];

    public const OPEN_STATUSES = [
        self::PENDING_INSPECTION, self::UNDER_INSPECTION, self::AWAITING_QUOTATION, self::AWAITING_APPROVAL,
        self::APPROVED, self::AWAITING_PARTS, self::IN_PROGRESS, self::ON_HOLD, self::QUALITY_INSPECTION, self::READY,
    ];

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::DELIVERED, self::COMPLETED, self::CANCELLED], true);
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', self::OPEN_STATUSES);
    }
}
