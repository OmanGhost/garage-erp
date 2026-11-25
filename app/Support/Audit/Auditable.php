<?php

namespace App\Support\Audit;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => AuditLogger::model('created', $m));
        static::updated(fn ($m) => AuditLogger::model('updated', $m));
        static::deleted(fn ($m) => AuditLogger::model('deleted', $m));
    }
}
