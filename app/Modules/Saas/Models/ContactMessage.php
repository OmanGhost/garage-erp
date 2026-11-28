<?php

namespace App\Modules\Saas\Models;

use Illuminate\Database\Eloquent\Model;

/** A message sent from the public website contact form. */
class ContactMessage extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['new', 'replied', 'archived'];
}
