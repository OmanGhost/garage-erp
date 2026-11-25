<?php

namespace App\Modules\Core\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'yearly_reset' => 'boolean',
    ];
}
