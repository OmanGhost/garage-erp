<?php

namespace App\Modules\Core\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];


    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
