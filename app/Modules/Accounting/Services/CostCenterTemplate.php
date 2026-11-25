<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Core\Models\CostCenter;
use App\Modules\Workshop\Models\Department;
use App\Support\Settings;

/**
 * Standard cost centers for a workshop & spare parts business, linked to the workshop
 * departments so revenue, parts and labour post to the right cost center automatically.
 * Safe to run more than once: existing codes, names and department links are kept.
 */
final class CostCenterTemplate
{
    /** code => [Arabic, English] */
    public const CENTERS = [
        'CC-001' => ['قسم الميكانيكا', 'Mechanical'],
        'CC-002' => ['قسم كهرباء المركبات', 'Auto electrical'],
        'CC-003' => ['قسم السمكرة', 'Body work'],
        'CC-004' => ['قسم الصبغ', 'Paint'],
        'CC-005' => ['قسم التكييف', 'Air conditioning'],
        'CC-006' => ['قسم الإطارات', 'Tyres'],
        'CC-007' => ['قسم الفحص والتشخيص', 'Inspection & diagnostics'],
        'CC-008' => ['قسم قطع الغيار الجديدة', 'New spare parts'],
        'CC-009' => ['قسم قطع الغيار المستعملة', 'Used spare parts'],
        'CC-010' => ['قسم غسيل المركبات', 'Car wash'],
        'CC-011' => ['الإدارة العامة', 'General administration'],
        'CC-012' => ['المخازن', 'Stores'],
    ];

    /** workshop department code => cost center code */
    public const DEPARTMENTS = [
        'MECH' => 'CC-001', 'ENG' => 'CC-001', 'TRANS' => 'CC-001', 'OIL' => 'CC-001', 'HEAVY' => 'CC-001',
        'ELEC' => 'CC-002', 'BATT' => 'CC-002', 'PROG' => 'CC-002', 'EV' => 'CC-002',
        'BODY' => 'CC-003', 'PAINT' => 'CC-004',
        'AC' => 'CC-005', 'TIRE' => 'CC-006', 'DIAG' => 'CC-007', 'WASH' => 'CC-010',
    ];

    /** Install for the current tenant. Returns [created cost centers, linked departments]. */
    public static function install(): array
    {
        $ids = [];
        $created = 0;
        foreach (self::CENTERS as $code => [$ar, $en]) {
            $cc = CostCenter::query()->firstOrCreate(['code' => $code], ['name_ar' => $ar, 'name_en' => $en, 'is_active' => true]);
            $created += $cc->wasRecentlyCreated ? 1 : 0;
            $ids[$code] = $cc->id;
        }

        $linked = 0;
        foreach (Department::query()->whereNull('cost_center_id')->get() as $dept) {
            if ($code = self::DEPARTMENTS[$dept->code] ?? null) {
                $dept->update(['cost_center_id' => $ids[$code]]);
                $linked++;
            }
        }

        // direct parts sales (POS / sales invoices) without a job card
        Settings::set(array_filter([
            'accounting.cost_center_parts_new' => Settings::get('accounting.cost_center_parts_new') ? null : $ids['CC-008'],
            'accounting.cost_center_parts_used' => Settings::get('accounting.cost_center_parts_used') ? null : $ids['CC-009'],
        ]));

        return [$created, $linked];
    }
}
