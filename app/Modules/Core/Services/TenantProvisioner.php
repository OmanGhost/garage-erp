<?php

namespace App\Modules\Core\Services;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\Accounts;
use App\Modules\Accounting\Services\ChartOfAccounts;
use App\Modules\Accounting\Services\FiscalService;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\NotificationTemplate;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Models\Subscription;
use App\Modules\Core\Models\TaxRate;
use App\Modules\Core\Models\WorkingHour;
use App\Modules\CRM\Models\Customer;
use App\Modules\HR\Models\LeaveType;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Models\CashRegister;
use App\Modules\Workshop\Models\Department;
use App\Modules\Workshop\Models\InspectionTemplate;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a fully usable tenant: company, main branch, warehouse, chart of
 * accounts, fiscal year, cash/bank accounts, POS register, walk-in customer,
 * workshop departments, inspection checklist, notification templates and the
 * company administrator.
 */
class TenantProvisioner
{
    public const DEPARTMENTS = [
        ['MECH', 'الميكانيكا العامة', 'General Mechanics'],
        ['ELEC', 'كهرباء السيارات', 'Auto Electrical'],
        ['AC', 'التكييف والتبريد', 'A/C & Cooling'],
        ['ENG', 'إصلاح المحركات', 'Engine Repair'],
        ['TRANS', 'إصلاح ناقل الحركة', 'Transmission Repair'],
        ['BODY', 'السمكرة والصبغ', 'Body & Paint'],
        ['DIAG', 'الفحص بالكمبيوتر', 'Computer Diagnostics'],
        ['TIRE', 'الإطارات والميزان', 'Tyres & Alignment'],
        ['OIL', 'تغيير الزيوت والفلاتر', 'Oil & Filters'],
        ['BATT', 'البطاريات', 'Batteries'],
        ['PROG', 'برمجة المركبات والمفاتيح', 'Programming & Keys'],
        ['HEAVY', 'إصلاح المركبات الثقيلة', 'Heavy Vehicles'],
        ['WASH', 'غسيل وتلميع السيارات', 'Wash & Detailing'],
        ['EV', 'المركبات الهجينة والكهربائية', 'Hybrid & EV'],
    ];

    public function provision(array $data): Company
    {
        return DB::transaction(function () use ($data) {
            $plan = ! empty($data['plan_id']) ? Plan::query()->find($data['plan_id']) : Plan::query()->orderBy('sort')->first();
            $company = Company::create([
                'slug' => $data['slug'] ?? Str::slug($data['name_en'] ?? $data['name']).'-'.Str::lower(Str::random(4)),
                'name' => $data['name'], 'name_ar' => $data['name_ar'] ?? $data['name'], 'name_en' => $data['name_en'] ?? $data['name'],
                'cr_number' => $data['cr_number'] ?? null, 'vat_number' => $data['vat_number'] ?? null,
                'email' => $data['email'] ?? null, 'phone' => omani_phone($data['phone'] ?? null),
                'address' => $data['address'] ?? null, 'city' => $data['city'] ?? 'Muscat',
                'currency_code' => 'OMR', 'currency_decimals' => 3, 'vat_rate' => $data['vat_rate'] ?? 5,
                'timezone' => 'Asia/Muscat', 'locale' => $data['locale'] ?? 'ar', 'plan_id' => $plan?->id,
                'subscription_status' => $plan && $plan->trial_days > 0 ? 'trial' : 'active',
                'trial_ends_at' => $plan && $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                'settings' => [],
            ]);

            return tenant()->run($company->id, function () use ($company, $plan, $data) {
                Accounts::flush();
                $branch = Branch::create(['code' => 'MAIN', 'name_ar' => 'الفرع الرئيسي', 'name_en' => 'Main Branch', 'is_main' => true, 'city' => $company->city]);
                tenant()->setBranch($branch->id);
                $wh = Warehouse::create(['branch_id' => $branch->id, 'code' => 'WH-MAIN', 'name' => 'المستودع الرئيسي', 'name_en' => 'Main Warehouse', 'type' => 'main']);
                Warehouse::create(['branch_id' => $branch->id, 'code' => 'WH-SCRAP', 'name' => 'مستودع الخردة والتالف', 'name_en' => 'Scrap & Damaged', 'type' => 'scrap']);

                ChartOfAccounts::install();
                FiscalService::ensureYearFor(now());
                TaxRate::create(['name' => 'VAT 5%', 'rate' => $company->vat_rate, 'is_default' => true]);
                TaxRate::create(['name' => 'Zero rated', 'rate' => 0]);

                $acc = fn ($key) => Account::query()->where('system_key', $key)->value('id');
                $cash = BankAccount::create(['branch_id' => $branch->id, 'account_id' => $acc('cash'), 'type' => 'cash', 'name' => 'الصندوق الرئيسي / Main Cash']);
                $pos = BankAccount::create(['branch_id' => $branch->id, 'account_id' => $acc('pos_cash'), 'type' => 'cash', 'name' => 'صندوق نقطة البيع / POS Drawer']);
                BankAccount::create(['branch_id' => $branch->id, 'account_id' => $acc('bank'), 'type' => 'bank', 'name' => 'الحساب البنكي / Bank', 'bank_name' => 'Bank Muscat']);
                BankAccount::create(['branch_id' => $branch->id, 'account_id' => $acc('card_clearing'), 'type' => 'card', 'name' => 'مدفوعات البطاقات / Card Terminal']);
                CashRegister::create(['branch_id' => $branch->id, 'name' => 'POS 1', 'bank_account_id' => $pos->id, 'warehouse_id' => $wh->id]);

                $walkIn = Customer::create(['code' => 'WALKIN', 'name' => 'عميل نقدي', 'name_en' => 'Walk-in Customer', 'type' => 'individual']);
                Settings::set(['pos.walk_in_customer_id' => $walkIn->id]);

                foreach (self::DEPARTMENTS as $i => [$code, $ar, $en]) {
                    Department::create(['code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'sort' => $i, 'branch_id' => $branch->id]);
                }
                // standard cost centers, linked to the departments and to new/used parts sales
                \App\Modules\Accounting\Services\CostCenterTemplate::install();
                foreach ([0, 1, 2, 3, 4, 6] as $d) {
                    WorkingHour::create(['branch_id' => $branch->id, 'day_of_week' => $d, 'opens_at' => '08:00', 'closes_at' => '18:00']);
                }
                WorkingHour::create(['branch_id' => $branch->id, 'day_of_week' => 5, 'is_closed' => true]);
                LeaveType::create(['name' => 'إجازة سنوية', 'name_en' => 'Annual leave', 'days_per_year' => 30]);
                LeaveType::create(['name' => 'إجازة مرضية', 'name_en' => 'Sick leave', 'days_per_year' => 21]);
                LeaveType::create(['name' => 'إجازة بدون راتب', 'name_en' => 'Unpaid leave', 'days_per_year' => 0, 'is_paid' => false]);

                $this->inspectionTemplate();
                $this->notificationTemplates();

                if ($plan) {
                    Subscription::create([
                        'company_id' => $company->id, 'plan_id' => $plan->id, 'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
                        'status' => $plan->trial_days > 0 ? 'trial' : 'active', 'starts_at' => now()->toDateString(),
                        'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days)->toDateString() : null,
                        'ends_at' => now()->addMonth()->toDateString(), 'amount' => $plan->price_monthly,
                    ]);
                }

                if (! empty($data['admin_email'])) {
                    $admin = new User([
                        'name' => $data['admin_name'] ?? 'Administrator', 'email' => strtolower($data['admin_email']),
                        'password' => $data['admin_password'] ?? Str::password(16), 'locale' => $company->locale,
                    ]);
                    $admin->company_id = $company->id;
                    $admin->branch_id = $branch->id;
                    $admin->save();
                    setPermissionsTeamId($company->id);
                    $admin->assignRole('Company Admin');
                }

                return $company->fresh();
            });
        });
    }

    protected function inspectionTemplate(): void
    {
        $t = InspectionTemplate::create(['name' => 'فحص شامل للمركبة', 'name_en' => 'Full vehicle inspection']);
        $items = [
            'المحرك|Engine' => ['مستوى زيت المحرك|Engine oil level', 'تسريبات الزيت|Oil leaks', 'سير المحرك|Drive belts', 'سائل التبريد|Coolant level', 'الردياتير والخراطيم|Radiator & hoses'],
            'الفرامل|Brakes' => ['فحمات الأمام|Front pads', 'فحمات الخلف|Rear pads', 'الهوبات|Discs / rotors', 'زيت الفرامل|Brake fluid'],
            'التعليق والتوجيه|Suspension & steering' => ['المساعدات|Shock absorbers', 'المقصات والجلب|Control arms & bushings', 'الدركسون|Steering rack', 'الميزان|Wheel alignment'],
            'الإطارات|Tyres' => ['عمق النقشة|Tread depth', 'ضغط الهواء|Tyre pressure', 'تاريخ الصنع|Manufacture date'],
            'الكهرباء|Electrical' => ['البطارية|Battery', 'الإضاءة الأمامية والخلفية|Lights', 'الدينمو|Alternator', 'المكيف|A/C performance'],
            'ناقل الحركة|Transmission' => ['زيت القير|Transmission fluid', 'التعشيق|Gear engagement'],
            'الهيكل|Body' => ['الصدمات والخدوش|Dents & scratches', 'الزجاج والمرايا|Glass & mirrors'],
        ];
        $sort = 0;
        foreach ($items as $section => $labels) {
            [$secAr] = explode('|', $section);
            foreach ($labels as $l) {
                [$ar, $en] = explode('|', $l);
                $t->items()->create(['section' => $secAr, 'label' => $ar, 'label_en' => $en, 'sort' => $sort++]);
            }
        }
    }

    protected function notificationTemplates(): void
    {
        $tpl = [
            'job_card.received' => ['استلام المركبة', 'Vehicle received',
                'عزيزي {{customer_name}}، تم استلام مركبتكم {{plate}} برقم أمر عمل {{number}}. لمتابعة الحالة: {{link}}',
                'Dear {{customer_name}}, your vehicle {{plate}} was received under job card {{number}}. Track: {{link}}'],
            'job_card.status_changed' => ['تحديث حالة المركبة', 'Vehicle status update',
                'عزيزي {{customer_name}}، حالة أمر العمل {{number}}: {{status}}.', 'Dear {{customer_name}}, job card {{number}} is now: {{status}}.'],
            'job_card.ready' => ['المركبة جاهزة للتسليم', 'Vehicle ready',
                'عزيزي {{customer_name}}، مركبتكم {{plate}} جاهزة للتسليم. المبلغ المستحق {{total}}.', 'Dear {{customer_name}}, your vehicle {{plate}} is ready for pickup. Amount due {{total}}.'],
            'quotation.sent' => ['عرض سعر', 'Quotation',
                'عزيزي {{customer_name}}، عرض السعر {{number}} بقيمة {{total}} صالح حتى {{valid_until}}. للموافقة: {{link}}',
                'Dear {{customer_name}}, quotation {{number}} for {{total}} is valid until {{valid_until}}. Approve here: {{link}}'],
            'invoice.posted' => ['فاتورة ضريبية', 'Tax invoice',
                'عزيزي {{customer_name}}، تم إصدار الفاتورة {{number}} بمبلغ {{total}}. {{link}}', 'Dear {{customer_name}}, invoice {{number}} of {{total}} has been issued. {{link}}'],
            'payment.received' => ['سند قبض', 'Payment receipt',
                'شكراً {{customer_name}}، تم استلام دفعة بمبلغ {{amount}} (سند {{number}}).', 'Thank you {{customer_name}}, we received {{amount}} (receipt {{number}}).'],
            'appointment.reminder' => ['تذكير بالموعد', 'Appointment reminder',
                'تذكير: موعد صيانة مركبتكم بتاريخ {{date}} الساعة {{time}} في {{company_name}}.', 'Reminder: your service appointment is on {{date}} at {{time}} at {{company_name}}.'],
            'appointment.booked' => ['تأكيد الموعد', 'Appointment confirmed',
                'تم حجز موعدكم رقم {{number}} بتاريخ {{date}} الساعة {{time}}.', 'Your appointment {{number}} is booked for {{date}} at {{time}}.'],
            'service.reminder' => ['تذكير بالصيانة الدورية', 'Service reminder',
                'عزيزي {{customer_name}}، حان موعد {{service}} لمركبتكم {{plate}}. احجز الآن: {{link}}', 'Dear {{customer_name}}, {{service}} is due for {{plate}}. Book now: {{link}}'],
            'handover.code' => ['رمز اعتماد تفويض', 'Authorization code',
                'عزيزي {{customer_name}}، رمز اعتماد تفويض {{person}} لتسليم/استلام مركبتكم {{plate}} هو {{code}} (التفويض {{number}}). لا تشارك الرمز إلا إذا كنت توافق.',
                'Dear {{customer_name}}, the code to approve {{person}} to hand over your vehicle {{plate}} is {{code}} (authorization {{number}}). Share it only if you agree.'],
            'additional_work.approval' => ['موافقة على أعمال إضافية', 'Additional work approval',
                'عزيزي {{customer_name}}، يوجد أعمال إضافية لأمر العمل {{number}} بقيمة {{total}}. للموافقة: {{link}}',
                'Dear {{customer_name}}, additional work on job {{number}} costs {{total}}. Approve: {{link}}'],
        ];
        foreach ($tpl as $event => [$sAr, $sEn, $bAr, $bEn]) {
            foreach (['mail', 'sms', 'whatsapp', 'database'] as $channel) {
                NotificationTemplate::create(['event' => $event, 'channel' => $channel, 'subject_ar' => $sAr, 'subject_en' => $sEn, 'body_ar' => $bAr, 'body_en' => $bEn]);
            }
        }
    }
}
