<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for every tenant-owned model: company isolation + audit trail.
 * company_id is never mass assignable.
 */
abstract class TenantModel extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $guarded = ['id', 'company_id'];
}
