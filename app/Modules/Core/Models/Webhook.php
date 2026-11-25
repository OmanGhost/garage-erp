<?php

namespace App\Modules\Core\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends TenantModel
{
    protected $casts = [
        'is_active' => 'boolean',
        'events' => 'array',
        'secret' => 'encrypted',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(\App\Modules\Core\Models\WebhookDelivery::class);
    }
}
