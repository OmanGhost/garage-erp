<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends TenantModel
{
    protected $casts = [
        'is_group' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(\App\Modules\Accounting\Models\Account::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(\App\Modules\Accounting\Models\JournalLine::class);
    }

    public function displayName(): string
    {
        return local_name($this);
    }

    public function label(): string
    {
        return $this->code.' - '.$this->displayName();
    }
}
