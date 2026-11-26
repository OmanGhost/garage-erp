<?php

namespace App\Modules\Workshop\Models;

use App\Models\TenantModel;
use App\Support\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends TenantModel
{
    use HasAttachments;

    /** Clickable areas of the vehicle diagram: key => [Arabic, English]. */
    public const ZONES = [
        'front_bumper' => ['الصدام الأمامي', 'Front bumper'], 'headlight_l' => ['الشمعة الأمامية اليسرى', 'Left headlight'], 'headlight_r' => ['الشمعة الأمامية اليمنى', 'Right headlight'],
        'hood' => ['الكبوت', 'Bonnet'], 'windshield' => ['الزجاج الأمامي', 'Windscreen'], 'roof' => ['السقف', 'Roof'], 'rear_glass' => ['الزجاج الخلفي', 'Rear glass'],
        'trunk' => ['الشنطة', 'Boot'], 'taillight_l' => ['الشمعة الخلفية اليسرى', 'Left tail light'], 'taillight_r' => ['الشمعة الخلفية اليمنى', 'Right tail light'],
        'rear_bumper' => ['الصدام الخلفي', 'Rear bumper'], 'fender_fl' => ['الرفرف الأمامي الأيسر', 'Front left fender'], 'fender_fr' => ['الرفرف الأمامي الأيمن', 'Front right fender'],
        'door_fl' => ['الباب الأمامي الأيسر', 'Front left door'], 'door_fr' => ['الباب الأمامي الأيمن', 'Front right door'], 'door_rl' => ['الباب الخلفي الأيسر', 'Rear left door'],
        'door_rr' => ['الباب الخلفي الأيمن', 'Rear right door'], 'quarter_rl' => ['الرفرف الخلفي الأيسر', 'Rear left quarter'], 'quarter_rr' => ['الرفرف الخلفي الأيمن', 'Rear right quarter'],
        'mirror_l' => ['المرآة اليسرى', 'Left mirror'], 'mirror_r' => ['المرآة اليمنى', 'Right mirror'], 'wheel_fl' => ['العجلة الأمامية اليسرى', 'Front left wheel'],
        'wheel_fr' => ['العجلة الأمامية اليمنى', 'Front right wheel'], 'wheel_rl' => ['العجلة الخلفية اليسرى', 'Rear left wheel'], 'wheel_rr' => ['العجلة الخلفية اليمنى', 'Rear right wheel'],
        'engine' => ['المحرك', 'Engine'], 'transmission' => ['ناقل الحركة', 'Transmission'], 'brakes' => ['الفرامل', 'Brakes'], 'suspension' => ['التعليق', 'Suspension'],
        'underbody' => ['أسفل المركبة', 'Underbody'], 'electrical' => ['الكهرباء', 'Electrical'], 'interior' => ['المقصورة', 'Interior'], 'ac' => ['التكييف', 'A/C'],
    ];

    /** Zones drawn on the diagram (the others are shown as buttons below it). */
    public const DRAWN = ['front_bumper', 'headlight_l', 'headlight_r', 'hood', 'windshield', 'roof', 'rear_glass', 'trunk', 'taillight_l', 'taillight_r', 'rear_bumper',
        'fender_fl', 'fender_fr', 'door_fl', 'door_fr', 'door_rl', 'door_rr', 'quarter_rl', 'quarter_rr', 'mirror_l', 'mirror_r', 'wheel_fl', 'wheel_fr', 'wheel_rl', 'wheel_rr'];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'sent_at' => 'datetime',
        'customer_viewed_at' => 'datetime',
        'technician_signed_at' => 'datetime',
        'customer_signed_at' => 'datetime',
    ];

    public static function zoneLabel(?string $zone): string
    {
        $z = self::ZONES[$zone] ?? null;

        return $z ? (app()->getLocale() === 'ar' ? $z[0] : $z[1]) : (string) $zone;
    }

    /** Traffic-light colour of a finding severity / checklist result: green, yellow or red. */
    public static function light(?string $value): string
    {
        return match ($value) {
            'high', 'critical', 'urgent' => 'red',
            'medium', 'attention' => 'yellow',
            'ok', 'low' => 'green',
            default => 'grey',
        };
    }

    /** Worst light per zone from the findings. @return array<string, string> */
    public function zoneLights(): array
    {
        $rank = ['grey' => 0, 'green' => 1, 'yellow' => 2, 'red' => 3];
        $out = [];
        foreach ($this->findings as $f) {
            if (! $f->zone) {
                continue;
            }
            $l = self::light($f->severity);
            if (! isset($out[$f->zone]) || $rank[$l] > $rank[$out[$f->zone]]) {
                $out[$f->zone] = $l;
            }
        }

        return $out;
    }

    /** Counts of green / yellow / red checklist items and findings. */
    public function lightCounts(): array
    {
        $c = ['green' => 0, 'yellow' => 0, 'red' => 0];
        foreach ($this->results as $r) {
            $l = self::light($r->result);
            if (isset($c[$l])) {
                $c[$l]++;
            }
        }

        return $c;
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\JobCard::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Workshop\Models\InspectionTemplate::class, 'inspection_template_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'technician_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\InspectionResult::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(\App\Modules\Workshop\Models\InspectionFinding::class);
    }

    public function publicUrl(): ?string
    {
        return $this->public_token ? route('public.dvi', $this->public_token) : null;
    }
}
