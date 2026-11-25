<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Assets\Models\Equipment;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Services\TenantProvisioner;
use App\Modules\CRM\Models\Customer;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\HrDepartment;
use App\Modules\HR\Models\JobTitle;
use App\Modules\Insurance\Models\InsuranceCompany;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\PartCategory;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryDocuments;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Modules\Workshop\Models\Department;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\Service;
use App\Modules\Workshop\Services\JobCardService;
use App\Modules\Workshop\Services\QuotationService;
use App\Support\DocumentNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Demo company "Al Noor Auto Services" with one user per role (password Demo@12345)
 * and a realistic data set created through the real business services
 * (so stock, accounting and reports are consistent).
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo@12345';

    public function run(): void
    {
        if (Company::query()->where('slug', 'alnoor-auto')->exists()) {
            $this->command?->warn('Demo company already exists.');

            return;
        }
        $company = app(TenantProvisioner::class)->provision([
            'slug' => 'alnoor-auto', 'name' => 'النور لخدمات السيارات', 'name_ar' => 'النور لخدمات السيارات', 'name_en' => 'Al Noor Auto Services',
            'cr_number' => '1234567', 'vat_number' => 'OM1100123456', 'email' => 'info@alnoor-auto.om', 'phone' => '24123456',
            'address' => 'Al Khuwair, Way 3020', 'city' => 'Muscat', 'plan_id' => Plan::query()->where('code', 'enterprise')->value('id'),
            'admin_name' => 'مدير النظام', 'admin_email' => 'admin@alnoor-auto.om', 'admin_password' => self::PASSWORD,
        ]);
        $company->update(['subscription_status' => 'active', 'subscription_ends_at' => now()->addYear()]);

        tenant()->run($company->id, function () use ($company) {
            $admin = User::query()->where('email', 'admin@alnoor-auto.om')->first();
            Auth::setUser($admin);
            $branch = $company->branches()->first();
            tenant()->setBranch($branch->id);
            $wh = Warehouse::query()->where('code', 'WH-MAIN')->first();

            // ---------------------------------------------------------- users
            $people = [
                ['manager', 'أحمد البلوشي', 'General Manager', false],
                ['branch', 'سالم الحارثي', 'Branch Manager', false],
                ['workshop', 'خالد الريامي', 'Workshop Manager', false],
                ['reception', 'مريم الكندي', 'Receptionist', false],
                ['tech1', 'راجو كومار', 'Technician', true],
                ['tech2', 'محمد عارف', 'Technician', true],
                ['tech3', 'جوزيف توماس', 'Technician', true],
                ['qc', 'يوسف الهنائي', 'Quality Inspector', false],
                ['store', 'سعيد الشكيلي', 'Storekeeper', false],
                ['buyer', 'هدى المعمري', 'Purchasing Officer', false],
                ['sales', 'علي الوهيبي', 'Salesperson', false],
                ['cashier', 'فاطمة السيابي', 'Cashier', false],
                ['accountant', 'ناصر العبري', 'Accountant', false],
                ['hr', 'عائشة الرواحي', 'HR Manager', false],
                ['auditor', 'Auditor', 'Auditor', false],
            ];
            $hrDept = HrDepartment::create(['name' => 'الورشة', 'name_en' => 'Workshop']);
            $title = JobTitle::create(['name' => 'فني', 'name_en' => 'Technician']);
            $users = [];
            foreach ($people as [$key, $name, $role, $tech]) {
                $u = new User(['name' => $name, 'email' => "$key@alnoor-auto.om", 'password' => self::PASSWORD, 'locale' => 'ar']);
                $u->company_id = $company->id;
                $u->branch_id = $branch->id;
                $u->save();
                setPermissionsTeamId($company->id);
                $u->assignRole($role);
                $users[$key] = $u;
                Employee::create([
                    'code' => DocumentNumber::next('employee', false), 'name' => $name, 'branch_id' => $branch->id, 'user_id' => $u->id,
                    'hr_department_id' => $hrDept->id, 'job_title_id' => $tech ? $title->id : null, 'hire_date' => now()->subYears(2), 'status' => 'active',
                    'basic_salary' => $tech ? 350 : 600, 'housing_allowance' => $tech ? 80 : 150, 'transport_allowance' => 30,
                    'hourly_cost' => $tech ? '2.500' : '4.000', 'is_technician' => $tech, 'commission_rate' => $key === 'sales' ? 2 : 0,
                    'nationality' => $tech ? 'IN' : 'OM', 'visa_expiry' => $tech ? now()->addDays(20) : null, 'phone' => '+9689'.rand(1000000, 9999999),
                ]);
            }
            foreach (Department::query()->get() as $d) {
                $d->technicians()->sync([$users['tech1']->id, $users['tech2']->id, $users['tech3']->id]);
            }
            Department::query()->where('code', 'MECH')->update(['supervisor_id' => $users['workshop']->id]);

            // ------------------------------------------------- catalog & stock
            $cats = [];
            foreach (['فلاتر|Filters', 'زيوت|Oils & fluids', 'فرامل|Brakes', 'كهرباء|Electrical', 'تعليق|Suspension', 'محرك|Engine', 'إطارات|Tyres'] as $c) {
                [$ar, $en] = explode('|', $c);
                $cats[$en] = PartCategory::create(['name_ar' => $ar, 'name_en' => $en]);
            }
            $brands = [];
            foreach (['Toyota Genuine' => true, 'Denso' => false, 'Bosch' => false, 'Mobil' => false, 'Brembo' => false, 'Bridgestone' => false, 'Varta' => false] as $b => $oem) {
                $brands[$b] = Brand::create(['name' => $b, 'is_oem' => $oem]);
            }
            $sup1 = Supplier::create(['code' => DocumentNumber::next('supplier', false), 'name' => 'شركة الخليج لقطع الغيار', 'name_en' => 'Gulf Spare Parts LLC', 'phone' => '+96824556677', 'payment_terms_days' => 30, 'vat_number' => 'OM1100999888']);
            $sup2 = Supplier::create(['code' => DocumentNumber::next('supplier', false), 'name' => 'مؤسسة الزيوت الحديثة', 'name_en' => 'Modern Lubricants', 'phone' => '+96824998877', 'payment_terms_days' => 45]);
            Supplier::create(['code' => DocumentNumber::next('supplier', false), 'name' => 'Dubai Auto Parts Trading', 'country' => 'AE', 'phone' => '+97142223344', 'payment_terms_days' => 60]);

            $toyota = VehicleMake::query()->where('name', 'Toyota')->first();
            $partsData = [
                ['04152-YZZA1', 'فلتر زيت تويوتا', 'Toyota oil filter', 'Filters', 'Toyota Genuine', 'genuine', 1.2, 2.5, 20, 60],
                ['17801-0C010', 'فلتر هواء تويوتا', 'Toyota air filter', 'Filters', 'Toyota Genuine', 'genuine', 4.0, 7.5, 10, 25],
                ['87139-YZZ08', 'فلتر مكيف', 'Cabin A/C filter', 'Filters', 'Denso', 'oem', 2.0, 4.5, 10, 30],
                ['MOB-5W30-4L', 'زيت محرك موبيل 5W-30 4 لتر', 'Mobil 1 5W-30 4L', 'Oils & fluids', 'Mobil', 'aftermarket', 9.0, 15.0, 15, 40],
                ['ATF-WS-1L', 'زيت قير أوتوماتيك WS 1 لتر', 'ATF WS 1L', 'Oils & fluids', 'Toyota Genuine', 'genuine', 3.2, 5.5, 20, 50],
                ['COOL-LLC-1L', 'سائل تبريد 1 لتر', 'Coolant LLC 1L', 'Oils & fluids', 'Toyota Genuine', 'genuine', 2.1, 3.8, 20, 40],
                ['04465-60320', 'فحمات فرامل أمامية لاندكروزر', 'Front brake pads Land Cruiser', 'Brakes', 'Toyota Genuine', 'genuine', 18.0, 32.0, 6, 15],
                ['BRM-P83140', 'فحمات فرامل بريمبو', 'Brembo brake pads', 'Brakes', 'Brembo', 'aftermarket', 11.0, 21.0, 6, 12],
                ['43512-60191', 'هوب فرامل أمامي', 'Front brake disc', 'Brakes', 'Toyota Genuine', 'genuine', 22.0, 38.0, 4, 8],
                ['VAR-70AH', 'بطارية فارتا 70 أمبير', 'Varta battery 70Ah', 'Electrical', 'Varta', 'aftermarket', 26.0, 42.0, 5, 12],
                ['90919-01253', 'بواجي دينسو', 'Denso spark plug', 'Engine', 'Denso', 'oem', 2.4, 4.8, 16, 48],
                ['16100-39466', 'طرمبة ماء', 'Water pump', 'Engine', 'Toyota Genuine', 'genuine', 35.0, 58.0, 2, 4],
                ['48510-69675', 'مساعد أمامي', 'Front shock absorber', 'Suspension', 'Denso', 'oem', 28.0, 49.0, 4, 8],
                ['BS-265-65R17', 'إطار بريجستون 265/65R17', 'Bridgestone 265/65R17 tyre', 'Tyres', 'Bridgestone', 'aftermarket', 38.0, 55.0, 8, 24],
                ['BOSCH-WB-24', 'مساحات بوش 24 إنش', 'Bosch wiper 24"', 'Electrical', 'Bosch', 'aftermarket', 2.5, 5.0, 10, 30],
            ];
            $parts = [];
            $adjItems = [];
            foreach ($partsData as [$sku, $ar, $en, $cat, $brand, $type, $cost, $price, $min, $openQty]) {
                $p = Part::create([
                    'sku' => $sku, 'barcode' => '628'.str_pad((string) crc32($sku), 10, '0', STR_PAD_LEFT), 'oem_number' => $sku, 'name_ar' => $ar, 'name_en' => $en,
                    'part_category_id' => $cats[$cat]->id, 'brand_id' => $brands[$brand]->id, 'part_type' => $type, 'purchase_price' => $cost, 'sale_price' => $price,
                    'wholesale_price' => round($price * .9, 3), 'workshop_price' => $price, 'min_sale_price' => round($cost * 1.1, 3), 'min_stock' => $min,
                    'reorder_qty' => $min * 2, 'tax_rate' => 5, 'warranty_days' => in_array($cat, ['Brakes', 'Electrical', 'Engine', 'Suspension'], true) ? 180 : 0,
                    'default_supplier_id' => $type === 'genuine' ? $sup1->id : $sup2->id, 'is_online' => true,
                ]);
                $p->compatibilities()->create(['vehicle_make_id' => $toyota->id, 'year_from' => 2012, 'year_to' => 2024]);
                $parts[$sku] = $p;
                $adjItems[] = ['part_id' => $p->id, 'qty' => $openQty, 'unit_cost' => $cost];
            }
            $adj = StockAdjustment::create(['number' => DocumentNumber::next('stock_adjustment'), 'warehouse_id' => $wh->id, 'date' => now()->subMonths(2)->toDateString(),
                'reason' => 'opening', 'status' => 'draft', 'notes' => 'Opening stock', 'created_by' => $admin->id]);
            foreach ($adjItems as $i) {
                $adj->items()->create($i);
            }
            app(InventoryDocuments::class)->postAdjustment($adj);

            // -------------------------------------------------------- services
            $svc = fn ($code, $ar, $en, $dep, $price, $min, $war = 0, $comm = 'percent', $cv = 5) => Service::create([
                'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'department_id' => Department::query()->where('code', $dep)->value('id'),
                'price' => $price, 'estimated_minutes' => $min, 'estimated_cost' => round($min / 60 * 2.5, 3), 'tax_rate' => 5, 'warranty_days' => $war,
                'commission_type' => $comm, 'commission_value' => $cv,
            ]);
            $services = [
                'OIL' => $svc('S-OIL', 'تغيير زيت وفلتر', 'Oil & filter change', 'OIL', 5, 30),
                'DIAG' => $svc('S-DIAG', 'فحص كمبيوتر', 'Computer diagnostics', 'DIAG', 10, 45),
                'BRK' => $svc('S-BRK', 'تغيير فحمات فرامل (محور)', 'Brake pads replacement (axle)', 'MECH', 12, 60, 90),
                'AC' => $svc('S-AC', 'فحص وتعبئة غاز المكيف', 'A/C check & regas', 'AC', 18, 60, 30),
                'ALIGN' => $svc('S-ALIGN', 'ميزان أذرعة', 'Wheel alignment', 'TIRE', 8, 40),
                'BAL' => $svc('S-BAL', 'ترصيص إطارات (4)', 'Wheel balancing (4)', 'TIRE', 6, 30),
                'BATT' => $svc('S-BATT', 'فحص وتركيب بطارية', 'Battery test & installation', 'BATT', 2, 15, 0, 'none', 0),
                'ENG' => $svc('S-ENG', 'صيانة محرك شاملة', 'Engine full service', 'ENG', 45, 240, 90),
                'WASH' => $svc('S-WASH', 'غسيل وتلميع', 'Wash & polish', 'WASH', 7, 60, 0, 'fixed', 1),
                'PAINT' => $svc('S-PAINT', 'صبغ قطعة', 'Panel paint', 'BODY', 35, 300, 180),
            ];
            $services['OIL']->vehiclePrices()->create(['vehicle_type' => 'truck', 'price' => 12]);
            $services['OIL']->vehiclePrices()->create(['vehicle_type' => 'suv', 'price' => 7]);

            // -------------------------------------------- customers & vehicles
            $nissan = VehicleMake::query()->where('name', 'Nissan')->first();
            $custs = [
                ['سعيد بن راشد العامري', '+96899112233', 'individual', $toyota, 'Land Cruiser', 2019, '12345', 'AB', 'suv'],
                ['منى الزدجالية', '+96892334455', 'individual', $toyota, 'Camry', 2021, '45678', 'R', 'car'],
                ['شركة الأفق للمقاولات', '+96824778899', 'company', $toyota, 'Hilux', 2020, '7788', 'KS', 'pickup'],
                ['عبدالله البوسعيدي', '+96895667788', 'individual', $nissan, 'Patrol', 2018, '9090', 'H', 'suv'],
            ];
            $vehicles = [];
            foreach ($custs as $i => [$name, $phone, $type, $make, $model, $year, $plate, $code, $vt]) {
                $c = Customer::create(['code' => DocumentNumber::next('customer', false), 'name' => $name, 'phone' => $phone, 'type' => $type,
                    'credit_limit' => $type === 'company' ? 2000 : 300, 'payment_terms_days' => $type === 'company' ? 30 : 0, 'classification' => $i === 0 ? 'vip' : 'regular']);
                $vehicles[] = Vehicle::create(['customer_id' => $c->id, 'plate_number' => $plate, 'plate_code' => $code, 'plate_country' => 'OM',
                    'vin' => 'JTM'.strtoupper(substr(md5($plate), 0, 14)), 'vehicle_make_id' => $make->id,
                    'vehicle_model_id' => $make->models()->where('name', $model)->value('id'), 'year' => $year, 'odometer' => 45000 + $i * 17000,
                    'vehicle_type' => $vt, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'color' => ['أبيض', 'فضي', 'أسود', 'رمادي'][$i]]);
                if ($i === 0) { // demo customer portal account
                    $c->update(['email' => 'customer@alnoor-auto.om']);
                    $pu = new User(['name' => $name, 'email' => 'customer@alnoor-auto.om', 'phone' => $phone, 'password' => 'Demo@12345', 'locale' => 'ar']);
                    $pu->company_id = $company->id;
                    $pu->customer_id = $c->id;
                    $pu->is_active = true;
                    $pu->save();
                    setPermissionsTeamId($company->id);
                    $pu->syncRoles(['Customer']);
                }
            }
            $fleetCustomer = Customer::query()->where('type', 'company')->first();
            $fleet = Fleet::create(['customer_id' => $fleetCustomer->id, 'name' => 'أسطول الأفق', 'contract_number' => 'FC-2026-01', 'contract_start' => now()->startOfYear(),
                'contract_end' => now()->endOfYear(), 'monthly_spend_limit' => 1500, 'per_job_limit' => 400, 'discount_percent' => 10, 'maintenance_interval_km' => 10000, 'maintenance_interval_days' => 120]);
            Vehicle::query()->where('customer_id', $fleetCustomer->id)->update(['fleet_id' => $fleet->id]);
            InsuranceCompany::create(['name' => 'الشركة العمانية المتحدة للتأمين', 'name_en' => 'Oman United Insurance', 'phone' => '+96824477300']);

            // ------------------------- full workshop cycle (reception -> paid)
            $jobs = app(JobCardService::class);
            $quotes = app(QuotationService::class);
            Auth::setUser($users['reception']);
            $jc = $jobs->create(['customer_id' => $vehicles[0]->customer_id, 'vehicle_id' => $vehicles[0]->id, 'customer_complaint' => 'صوت في الفرامل الأمامية وتغيير الزيت',
                'department_id' => Department::query()->where('code', 'MECH')->value('id'), 'promised_at' => now()->addHours(6)]);
            $q = $quotes->create(['job_card_id' => $jc->id, 'customer_id' => $jc->customer_id, 'vehicle_id' => $jc->vehicle_id], [
                ['item_type' => 'service', 'service_id' => $services['OIL']->id, 'description' => $services['OIL']->name_ar, 'qty' => 1, 'unit_price' => 7, 'tax_rate' => 5],
                ['item_type' => 'service', 'service_id' => $services['BRK']->id, 'description' => $services['BRK']->name_ar, 'qty' => 1, 'unit_price' => 12, 'tax_rate' => 5],
                ['item_type' => 'part', 'part_id' => $parts['04152-YZZA1']->id, 'description' => $parts['04152-YZZA1']->name_ar, 'qty' => 1, 'unit_price' => 2.5, 'tax_rate' => 5],
                ['item_type' => 'part', 'part_id' => $parts['MOB-5W30-4L']->id, 'description' => $parts['MOB-5W30-4L']->name_ar, 'qty' => 2, 'unit_price' => 15, 'tax_rate' => 5],
                ['item_type' => 'part', 'part_id' => $parts['04465-60320']->id, 'description' => $parts['04465-60320']->name_ar, 'qty' => 1, 'unit_price' => 32, 'tax_rate' => 5],
            ]);
            $quotes->send($q, []);
            $quotes->respond($q, $q->items->mapWithKeys(fn ($i) => [$i->id => 'approved'])->all(), 'سعيد العامري', 'in_person');
            Auth::setUser($users['store']);
            foreach ($jc->parts()->get() as $p) {
                $jobs->issuePart($p, null, $users['tech1']->id);
            }
            Auth::setUser($users['tech1']);
            foreach ($jc->tasks()->get() as $t) {
                $t->update(['technician_id' => $users['tech1']->id]);
                $jobs->startTask($t);
                $t->timeLogs()->update(['started_at' => now()->subMinutes(50)]);
                $jobs->finishTask($t);
            }
            Auth::setUser($users['qc']);
            $jobs->qualityCheck($jc->fresh(), 'pass', ['road_test' => 1], 'OK');
            Auth::setUser($users['cashier']);
            $inv = app(InvoiceService::class)->fromJobCard($jc->fresh());
            app(InvoiceService::class)->post($inv);
            app(PaymentService::class)->receiveFromCustomer($jc->customer, $inv->fresh()->total, BankAccount::query()->where('type', 'cash')->value('id'), 'cash', [$inv->id => $inv->fresh()->total]);
            $jobs->changeStatus($jc->fresh(), JobCard::DELIVERED);

            // a second job in progress and one waiting for approval
            Auth::setUser($users['reception']);
            $jc2 = $jobs->create(['customer_id' => $vehicles[1]->customer_id, 'vehicle_id' => $vehicles[1]->id, 'customer_complaint' => 'المكيف لا يبرد', 'priority' => 'high',
                'department_id' => Department::query()->where('code', 'AC')->value('id'), 'promised_at' => now()->addDay()]);
            $jobs->changeStatus($jc2, JobCard::UNDER_INSPECTION, null, true);
            $jobs->changeStatus($jc2->fresh(), JobCard::AWAITING_QUOTATION, null, true);
            $q2 = $quotes->create(['job_card_id' => $jc2->id, 'customer_id' => $jc2->customer_id, 'vehicle_id' => $jc2->vehicle_id], [
                ['item_type' => 'service', 'service_id' => $services['AC']->id, 'description' => $services['AC']->name_ar, 'qty' => 1, 'unit_price' => 18, 'tax_rate' => 5],
                ['item_type' => 'part', 'part_id' => $parts['87139-YZZ08']->id, 'description' => $parts['87139-YZZ08']->name_ar, 'qty' => 1, 'unit_price' => 4.5, 'tax_rate' => 5],
            ]);
            $quotes->send($q2, []);
            $jc3 = $jobs->create(['customer_id' => $vehicles[3]->customer_id, 'vehicle_id' => $vehicles[3]->id, 'customer_complaint' => 'فحص دوري وتبديل البطارية',
                'department_id' => Department::query()->where('code', 'BATT')->value('id'), 'promised_at' => now()->addHours(3)]);

            // ------------------------------------------- purchasing cycle: PO -> GRN -> supplier invoice
            $purch = app(\App\Modules\Purchasing\Services\PurchasingService::class);
            $po = $purch->createOrder(['supplier_id' => $sup1->id, 'warehouse_id' => $wh->id, 'branch_id' => $branch->id, 'expected_date' => now()->addDays(3)->toDateString()], [
                ['part_id' => $parts['04152-YZZA1']->id, 'qty' => 20, 'unit_cost' => 1.2, 'tax_rate' => 5],
                ['part_id' => $parts['04465-60320']->id, 'qty' => 4, 'unit_cost' => 18, 'tax_rate' => 5],
            ]);
            $purch->approveOrder($po);
            $grn = $purch->receive($po, $po->items->mapWithKeys(fn ($i) => [$i->id => ['qty' => $i->qty]])->all(), ['supplier_reference' => 'DN-5521']);
            $purch->postSupplierInvoice($purch->invoiceFromReceipt($grn, 'GSP-88231'));

            // ------------------------------------------- dismantling: vehicle bought for used parts
            $dis = app(\App\Modules\Dismantling\Services\DismantlingService::class);
            $dv = $dis->purchase(['branch_id' => $branch->id, 'vin' => 'JTMHV05J804012345', 'vehicle_make_id' => $toyota->id,
                'vehicle_model_id' => $toyota->models()->where('name', 'Land Cruiser')->value('id'), 'year' => 2012, 'mileage' => 310000,
                'source' => 'auction', 'supplier_id' => $sup1->id, 'purchase_date' => now()->subDays(10)->toDateString(), 'purchase_cost' => 1200,
                'warehouse_id' => $wh->id, 'notes' => 'حادث أمامي - المحرك سليم']);
            $dis->addCost($dv, 'transport_cost', 60);
            $dis->addPart($dv, ['name' => 'محرك 1GR-FE مستعمل', 'grade' => 'A', 'estimated_value' => 900]);
            $dis->addPart($dv, ['name' => 'ناقل حركة أوتوماتيك مستعمل', 'grade' => 'B', 'estimated_value' => 450]);
            $dis->addPart($dv, ['name' => 'باب خلفي يسار', 'grade' => 'B', 'estimated_value' => 80]);
            $dis->moveToStock($dv);

            // ------------------------------------------- assets & equipment
            $equipAccount = Account::query()->where('system_key', 'fixed_assets')->value('id');
            $asset = FixedAsset::create(['code' => 'FA-001', 'name' => 'رافعة هيدروليكية 4 طن', 'category' => 'equipment', 'asset_account_id' => $equipAccount,
                'accumulated_account_id' => Account::query()->where('system_key', 'accumulated_depreciation')->value('id'),
                'expense_account_id' => Account::query()->where('system_key', 'depreciation_expense')->value('id'),
                'purchase_date' => now()->subYear(), 'depreciation_start' => now()->subYear(), 'cost' => 2400, 'salvage_value' => 200, 'useful_life_months' => 60, 'book_value' => 2400]);
            Equipment::create(['code' => 'EQ-LIFT-01', 'name' => 'رافعة سيارات 4 طن', 'category' => 'lift', 'branch_id' => $branch->id, 'purchase_cost' => 2400,
                'purchase_date' => now()->subYear(), 'fixed_asset_id' => $asset->id, 'maintenance_interval_days' => 90, 'next_maintenance_at' => now()->addDays(5)]);
            Equipment::create(['code' => 'EQ-DIAG-01', 'name' => 'جهاز فحص لونش X431', 'category' => 'diagnostic', 'branch_id' => $branch->id, 'purchase_cost' => 950,
                'purchase_date' => now()->subMonths(8), 'calibration_interval_days' => 365, 'next_calibration_at' => now()->addMonths(4), 'warranty_until' => now()->addMonths(4)]);

            Auth::forgetUser();
        });
        $this->command?->info('Demo company created: admin@alnoor-auto.om / '.self::PASSWORD.' (and manager@, reception@, tech1@, store@, cashier@, accountant@ ... same password)');
    }
}
