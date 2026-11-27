<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Assets\Models\Equipment;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Notifications\SystemMessage;
use App\Modules\CRM\Services\ReminderService;
use App\Modules\HR\Models\Employee;
use App\Modules\Inventory\Services\ReorderService;
use App\Modules\Warranty\Services\WarrantyService;
use App\Modules\Workshop\Services\QuotationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Daily automation per tenant: maintenance & appointment reminders, no-shows,
 * expiries (quotations, warranties, documents, equipment), low-stock alerts,
 * subscription status.
 */
class ErpDaily extends Command
{
    protected $signature = 'erp:daily {--company= : Only one company id}';

    protected $description = 'Run daily reminders, expiries and alerts for every company';

    public function handle(): int
    {
        $companies = Company::query()->where('is_active', true)->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))->get();
        foreach ($companies as $company) {
            tenant()->run($company->id, function () use ($company) {
                $r = [
                    'service_reminders' => app(ReminderService::class)->sendDue(),
                    'appointment_reminders' => app(ReminderService::class)->sendAppointmentReminders(),
                    'no_shows' => app(AppointmentService::class)->markNoShows(),
                    'expired_reservations' => app(\App\Modules\Inventory\Services\ReservationService::class)->expire(),
                    'expired_quotations' => app(QuotationService::class)->expireOverdue(),
                    'expired_warranties' => app(WarrantyService::class)->expireOld(),
                ];
                $managers = User::query()->where('company_id', $company->id)->whereNull('customer_id')->where('is_active', true)->get()
                    ->filter(fn ($u) => $u->hasRole(['Company Admin', 'General Manager', 'Branch Manager', 'Storekeeper', 'HR Manager', 'Workshop Manager']));

                if (setting('inventory.low_stock_alerts')) {
                    $low = count(app(ReorderService::class)->suggestions(null, 50));
                    if ($low) {
                        Notification::send($managers->filter(fn ($u) => $u->can('stock.view')), new SystemMessage(__(':n parts need reordering', ['n' => $low]), '', route('inventory.reorder'), 'warning'));
                    }
                }
                // ABC classes refreshed monthly; automatic reordering when enabled
                $analytics = app(\App\Modules\Inventory\Services\InventoryAnalyticsService::class);
                $lastAbc = \App\Modules\Inventory\Models\Part::query()->max('abc_calculated_at');
                $hasAnalytics = $company->moduleEnabled('inventory_analytics');
                if ($hasAnalytics && (! $lastAbc || now()->diffInDays($lastAbc, true) >= 30)) {
                    $analytics->abc();
                }
                if ($hasAnalytics && setting('inventory.auto_reorder') && ($wh = (int) setting('inventory.auto_reorder_warehouse'))) {
                    $orders = $analytics->autoReorder($wh);
                    $r['auto_reorder_orders'] = count($orders);
                    if ($orders) {
                        Notification::send($managers->filter(fn ($u) => $u->can('purchase_orders.view')), new SystemMessage(__(':n draft purchase orders created.', ['n' => count($orders)]), '', route('crud.index', 'purchase-orders'), 'info'));
                    }
                }
                $docs = Employee::query()->where('status', 'active')->where(fn ($q) => $q->whereBetween('id_expiry', [now(), now()->addDays(30)])
                    ->orWhereBetween('visa_expiry', [now(), now()->addDays(30)])->orWhereBetween('passport_expiry', [now(), now()->addDays(30)]))->count();
                if ($docs) {
                    Notification::send($managers->filter(fn ($u) => $u->can('employees.view')), new SystemMessage(__(':n employee documents expire within 30 days', ['n' => $docs]), '', route('crud.index', 'employees'), 'warning'));
                }
                $equip = Equipment::query()->where('status', '!=', 'disposed')->where(fn ($q) => $q->whereDate('next_maintenance_at', '<=', now()->addDays(7))
                    ->orWhereDate('next_calibration_at', '<=', now()->addDays(7))->orWhereBetween('warranty_until', [now(), now()->addDays(14)]))->count();
                if ($equip) {
                    Notification::send($managers->filter(fn ($u) => $u->can('equipment.view')), new SystemMessage(__(':n equipment items need maintenance / calibration', ['n' => $equip]), '', route('crud.index', 'equipment'), 'info'));
                }
                // subscription status
                if ($company->subscription_status === 'trial' && $company->trial_ends_at?->isPast()) {
                    $company->update(['subscription_status' => 'past_due']);
                } elseif ($company->subscription_status === 'active' && $company->subscription_ends_at?->isPast()) {
                    $company->update(['subscription_status' => 'past_due']);
                }
                $this->line($company->name.': '.json_encode($r));
            });
        }

        return self::SUCCESS;
    }
}
