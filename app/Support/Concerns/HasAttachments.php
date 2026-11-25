<?php

namespace App\Support\Concerns;

use App\Modules\Core\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function photos(): MorphMany
    {
        return $this->attachments()->where('mime', 'like', 'image/%');
    }
}
