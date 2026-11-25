<?php

namespace App\Modules\Workshop\Services;

use App\Modules\Workshop\Models\JobCard as J;

/**
 * Job card state machine: allowed transitions.
 * Business guards live in JobCardService::guard().
 */
final class JobCardWorkflow
{
    public const TRANSITIONS = [
        J::PENDING_INSPECTION => [J::UNDER_INSPECTION, J::AWAITING_QUOTATION, J::APPROVED, J::ON_HOLD, J::CANCELLED],
        J::UNDER_INSPECTION => [J::AWAITING_QUOTATION, J::APPROVED, J::ON_HOLD, J::CANCELLED],
        J::AWAITING_QUOTATION => [J::AWAITING_APPROVAL, J::APPROVED, J::ON_HOLD, J::CANCELLED],
        J::AWAITING_APPROVAL => [J::APPROVED, J::AWAITING_QUOTATION, J::ON_HOLD, J::CANCELLED],
        J::APPROVED => [J::AWAITING_PARTS, J::IN_PROGRESS, J::ON_HOLD, J::CANCELLED],
        J::AWAITING_PARTS => [J::IN_PROGRESS, J::APPROVED, J::ON_HOLD, J::CANCELLED],
        J::IN_PROGRESS => [J::AWAITING_PARTS, J::ON_HOLD, J::QUALITY_INSPECTION, J::READY, J::CANCELLED],
        J::ON_HOLD => [J::PENDING_INSPECTION, J::UNDER_INSPECTION, J::AWAITING_QUOTATION, J::AWAITING_APPROVAL, J::APPROVED, J::AWAITING_PARTS, J::IN_PROGRESS, J::CANCELLED],
        J::QUALITY_INSPECTION => [J::IN_PROGRESS, J::READY],
        J::READY => [J::DELIVERED, J::IN_PROGRESS],
        J::DELIVERED => [J::COMPLETED],
        J::COMPLETED => [],
        J::CANCELLED => [],
    ];

    /** Kanban columns (cancelled/completed are hidden by default). */
    public const BOARD = [
        J::PENDING_INSPECTION, J::UNDER_INSPECTION, J::AWAITING_QUOTATION, J::AWAITING_APPROVAL, J::APPROVED,
        J::AWAITING_PARTS, J::IN_PROGRESS, J::ON_HOLD, J::QUALITY_INSPECTION, J::READY, J::DELIVERED,
    ];

    public const COLORS = [
        J::PENDING_INSPECTION => '#6c757d', J::UNDER_INSPECTION => '#0dcaf0', J::AWAITING_QUOTATION => '#fd7e14',
        J::AWAITING_APPROVAL => '#ffc107', J::APPROVED => '#20c997', J::AWAITING_PARTS => '#d63384',
        J::IN_PROGRESS => '#0d6efd', J::ON_HOLD => '#adb5bd', J::QUALITY_INSPECTION => '#6f42c1',
        J::READY => '#198754', J::DELIVERED => '#146c43', J::COMPLETED => '#0f5132', J::CANCELLED => '#dc3545',
    ];

    public static function can(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function next(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }
}
