<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes immutable audit records. Used automatically by the Auditable trait
 * and explicitly for sensitive operations (posting, approvals, reversals).
 */
class AuditLogger
{
    protected const EXCLUDE = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'updated_at', 'created_at', 'credentials', 'secret'];

    public static function model(string $event, Model $model): void
    {
        if (! config('erp.audit.enabled')) {
            return;
        }
        $exclude = array_merge(self::EXCLUDE, method_exists($model, 'auditExclude') ? $model->auditExclude() : []);
        $old = $new = null;
        if ($event === 'updated') {
            $changes = array_diff_key($model->getChanges(), array_flip($exclude));
            if (! $changes) {
                return;
            }
            $new = $changes;
            $old = array_intersect_key($model->getOriginal(), $changes);
        } elseif ($event === 'created') {
            $new = array_diff_key($model->getAttributes(), array_flip($exclude));
        } else {
            $old = array_diff_key($model->getAttributes(), array_flip($exclude));
        }

        static::write($event, $model, $old, $new);
    }

    public static function log(string $event, ?Model $model = null, array $data = []): void
    {
        static::write($event, $model, null, $data ?: null);
        // forward sensitive events to SIEM channel when configured
        if (config('logging.channels.siem.enabled')) {
            Log::channel('siem')->info($event, [
                'company_id' => tenant()->id(),
                'user_id' => auth()->id(),
                'model' => $model ? get_class($model).'#'.$model->getKey() : null,
                'ip' => request()?->ip(),
                'data' => $data,
            ]);
        }
    }

    protected static function write(string $event, ?Model $model, ?array $old, ?array $new): void
    {
        $req = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        DB::table('audit_logs')->insert([
            'company_id' => $model?->getAttribute('company_id') ?? tenant()->id(),
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $model ? $model->getMorphClass() : null,
            'auditable_id' => $model?->getKey(),
            'old_values' => $old ? json_encode(self::scalarize($old), JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new ? json_encode(self::scalarize($new), JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 255) : null,
            'url' => $req ? substr($req->fullUrl(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    protected static function scalarize(array $values): array
    {
        return array_map(fn ($v) => is_scalar($v) || $v === null ? $v : (string) json_encode($v), $values);
    }
}
