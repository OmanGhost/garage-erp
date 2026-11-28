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
    protected static ?bool $sealable = null;

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
        $row = [
            'company_id' => $model?->getAttribute('company_id') ?? tenant()->id(),
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $model ? $model->getMorphClass() : null,
            'auditable_id' => $model?->getKey(),
            'old_values' => $old ? json_encode(self::scalarize($old), JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new ? json_encode(self::scalarize($new), JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 255) : null,
            'url' => $req ? mb_substr($req->fullUrl(), 0, 500) : null,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
        if (self::$sealable = self::$sealable ?: \Illuminate\Support\Facades\Schema::hasColumn('audit_logs', 'seal')) {
            $row['seal'] = self::seal($row);
        }
        DB::table('audit_logs')->insert($row);

        // internal fraud & loss prevention rules look at sensitive events
        \App\Modules\LossPrevention\Services\LossPreventionMonitor::observe($event, $model, $old, $new);
    }

    /** Fields covered by the seal, in a fixed order. */
    public const SEALED = ['company_id', 'user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent', 'url', 'created_at'];

    /** HMAC of an audit row (as stored) with the application key: any later change to the row breaks it. */
    public static function seal(array|object $row): string
    {
        $row = (array) $row;
        $parts = [];
        foreach (self::SEALED as $f) {
            $v = $row[$f] ?? null;
            if ($f === 'created_at' && $v !== null) {
                $v = \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i:s');
            }
            $parts[] = $v === null ? "\0" : (string) $v;
        }

        return hash_hmac('sha256', implode("\x1f", $parts), (string) config('app.key'));
    }

    /**
     * Check the seals of a company's audit log.
     *
     * @return array{checked: int, sealed: int, unsealed: int, tampered: array<int, int>}
     */
    public static function verify(?int $companyId, ?\DateTimeInterface $since = null): array
    {
        $out = ['checked' => 0, 'sealed' => 0, 'unsealed' => 0, 'tampered' => []];
        DB::table('audit_logs')->where('company_id', $companyId)->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->orderBy('id')->chunkById(1000, function ($rows) use (&$out) {
            foreach ($rows as $r) {
                $out['checked']++;
                if ($r->seal === null) {
                    $out['unsealed']++;        // written before sealing existed
                } elseif (hash_equals($r->seal, self::seal($r))) {
                    $out['sealed']++;
                } else {
                    $out['tampered'][] = (int) $r->id;
                }
            }
        });

        return $out;
    }

    protected static function scalarize(array $values): array
    {
        return array_map(fn ($v) => is_scalar($v) || $v === null ? $v : (string) json_encode($v), $values);
    }
}
