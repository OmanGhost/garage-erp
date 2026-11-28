<?php

/*
|--------------------------------------------------------------------------
| ERP global configuration
|--------------------------------------------------------------------------
| Modules, permissions, default roles and company setting defaults.
*/

$actions = ['view', 'create', 'update', 'delete', 'approve', 'print', 'export'];

$resources = [
    // core
    'companies', 'branches', 'warehouses', 'cost_centers', 'users', 'roles', 'role_requests', 'settings', 'audit_logs',
    'working_hours', 'holidays', 'tax_rates', 'document_sequences',
    // crm & vehicles
    'customers', 'customer_groups', 'complaints', 'service_reminders', 'vehicles', 'vehicle_makes',
    // workshop
    'departments', 'services', 'service_packages', 'promotions', 'workshop_bays', 'reception', 'handover_authorizations',
    'inspection_templates', 'inspections', 'quotations', 'job_cards', 'quality_checks', 'commissions',
    'workshop_planning', 'yard_locations', 'vehicle_tracking', 'test_drives',
    // memberships & loyalty
    'memberships', 'membership_plans', 'loyalty', 'gift_cards',
    // subcontracting & roadside
    'subcontracts', 'roadside',
    // internal fraud & loss prevention
    'fraud_alerts',
    // quality management
    'quality', 'quality_sops', 'nonconformities', 'capa_actions', 'quality_audits',
    // inventory
    'parts', 'part_categories', 'brands', 'stock', 'stock_transfers', 'stock_counts', 'stock_adjustments',
    // dismantling
    'dismantling',
    // purchasing
    'suppliers', 'purchase_requests', 'rfqs', 'purchase_orders', 'goods_receipts', 'supplier_invoices',
    'landed_costs', 'purchase_returns', 'supplier_evaluations',
    // sales
    'pos', 'cash_registers', 'invoices', 'credit_notes', 'payments', 'sales_orders',
    // accounting
    'accounts', 'journal_entries', 'fiscal_years', 'bank_accounts', 'expenses', 'fund_transfers',
    'bank_reconciliations', 'fixed_assets', 'budgets',
    // hr
    'employees', 'hr_departments', 'job_titles', 'attendance', 'leaves', 'loans', 'payroll', 'performance_reviews',
    // advanced
    'insurance_companies', 'insurance_claims', 'fleets', 'warranties', 'warranty_claims', 'equipment',
    'appointments', 'store_orders', 'coupons', 'reports', 'dashboard',
    // platform
    'integrations', 'notification_templates', 'webhooks', 'plans', 'subscriptions', 'tenants', 'backups',
];

$permissions = [];
foreach ($resources as $r) {
    foreach ($actions as $a) {
        $permissions[] = "$r.$a";
    }
}

$permissions = array_merge($permissions, [
    'branches.all',
    'job_cards.change_status',
    'job_cards.override_workflow',
    'job_cards.approve_additional',
    'stock.issue', 'stock.receive', 'stock.allow_negative',
    'pos.discount_override', 'pos.below_min_price', 'pos.open_session', 'pos.close_session',
    'invoices.post', 'invoices.credit_override',
    'journal_entries.post', 'journal_entries.reverse',
    'fiscal_years.close_period',
    'purchase_orders.approve_unlimited',
]);

return [
    'currency' => env('ERP_CURRENCY', 'OMR'),
    'decimals' => (int) env('ERP_CURRENCY_DECIMALS', 3),
    'vat_rate' => env('ERP_DEFAULT_VAT', '5.000'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Muscat'),
    'phone_prefix' => '+968',
    'country' => 'OM',

    'audit' => ['enabled' => env('ERP_AUDIT', true)],

    'backup' => [
        'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
        'mysql' => env('MYSQL_PATH', 'mysql'),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
    ],

    'uploads' => [
        'max_kb' => (int) env('UPLOAD_MAX_KB', 10240),
        'mimes' => 'jpg,jpeg,png,webp,gif,pdf,mp4,mov,webm,doc,docx,xls,xlsx,csv,txt,zip',
        'image_mimes' => 'jpg,jpeg,png,webp,gif',
    ],

    'modules' => [
        'core' => ['label' => 'Core', 'core' => true],
        'crm' => ['label' => 'Customers & CRM', 'core' => true],
        'vehicles' => ['label' => 'Vehicles', 'core' => true],
        'workshop' => ['label' => 'Workshop & Job Cards'],
        'planning' => ['label' => 'Workshop planning & live screen', 'requires' => 'workshop', 'new' => true,
            'description' => 'Bay / technician planner, automatic scheduling without clashes, estimated completion, occupancy and productivity, TV screen.'],
        'vehicle_tracking' => ['label' => 'Vehicle & key tracking', 'requires' => 'workshop', 'new' => true,
            'description' => 'Yard locations, key hooks and holders, QR / RFID key tags, test-drive permits.'],
        'dvi' => ['label' => 'Digital vehicle inspection (DVI)', 'requires' => 'workshop', 'new' => true,
            'description' => 'Interactive diagram, fault photos, measurements, customer report link with approvals, signatures, before / after.'],
        'quality' => ['label' => 'Quality management (QMS)', 'requires' => 'workshop', 'new' => true,
            'description' => 'Nonconformities, CAPA, SOPs, internal audits, comebacks and first-time-fix dashboard.'],
        'subcontracting' => ['label' => 'Subcontracted repairs', 'requires' => 'workshop', 'new' => true,
            'description' => 'Send work to outside specialists (machining, transmission, ECU…) linked to the job card: cost, customer price, return date and outside warranty.'],
        'roadside' => ['label' => 'Roadside assistance', 'requires' => 'workshop', 'new' => true,
            'description' => 'Tow, battery, tyre and fuel calls: dispatch a driver, live customer location link, distance pricing and membership-covered calls.'],
        'loss_prevention' => ['label' => 'Fraud & loss prevention', 'new' => true,
            'description' => 'Watches unusual discounts, sales below cost, cancelled receipts, price cuts, stock write-offs, cash shortages and after-hours work; alerts managers and seals the audit log.'],
        'inventory' => ['label' => 'Spare Parts & Inventory'],
        'inventory_analytics' => ['label' => 'Inventory analytics & auto reorder', 'requires' => 'inventory', 'new' => true,
            'description' => 'ABC, stock aging, dead stock, demand forecast, automatic reordering, cycle counting, supplier price history.'],
        'inventory_ops' => ['label' => 'Advanced stock operations', 'requires' => 'inventory', 'new' => true,
            'description' => 'Stock reservations, kits, consignment stock, core charges, drop shipping.'],
        'purchasing' => ['label' => 'Purchasing & Suppliers'],
        'sales' => ['label' => 'Sales & POS'],
        'memberships' => ['label' => 'Maintenance memberships', 'requires' => 'sales', 'new' => true,
            'description' => 'Yearly plans with included services, parts / labour discounts, priority booking and roadside calls — recurring income.'],
        'loyalty' => ['label' => 'Loyalty & rewards', 'requires' => 'sales', 'new' => true,
            'description' => 'Points and tiers, referrals, gift cards, discount coupons, personal offers and win-back of inactive customers.'],
        'accounting' => ['label' => 'Accounting'],
        'reports' => ['label' => 'Reports & Analytics'],
        'dismantling' => ['label' => 'Vehicle Dismantling'],
        'hr' => ['label' => 'HR & Payroll'],
        'insurance' => ['label' => 'Insurance & Accidents'],
        'fleet' => ['label' => 'Fleet Management'],
        'warranty' => ['label' => 'Warranty'],
        'assets' => ['label' => 'Equipment & Assets'],
        'appointments' => ['label' => 'Appointments'],
        'portal' => ['label' => 'Customer Portal'],
        'store' => ['label' => 'Online Store'],
        'integrations' => ['label' => 'Integrations & API'],
    ],

    'permissions' => $permissions,

    // '*' = every permission; wildcards "resource.*" and "*.action" are expanded by the seeder.
    'roles' => [
        'Super Admin' => '*',
        'Company Admin' => '*',
        'General Manager' => ['fraud_alerts.*', 'role_requests.*', '*.view', '*.print', '*.export', '*.approve', 'dashboard.*', 'reports.*', 'branches.all', 'job_cards.*'],
        'Branch Manager' => ['dashboard.*', 'role_requests.view', 'role_requests.create', 'role_requests.approve', 'role_requests.print', 'users.view', 'subcontracts.*', 'roadside.*', 'memberships.*', 'membership_plans.view', 'loyalty.*', 'gift_cards.*', 'quality.view', 'quality_sops.view', 'nonconformities.*', 'capa_actions.*', 'quality_audits.view', 'workshop_planning.*', 'vehicle_tracking.*', 'test_drives.*', 'yard_locations.view', 'reports.*', 'customers.*', 'vehicles.*', 'reception.*', 'handover_authorizations.*', 'inspections.*', 'quotations.*', 'job_cards.*', 'appointments.*', 'invoices.*', 'payments.*', 'parts.view', 'stock.view', 'purchase_requests.*', 'purchase_orders.view', 'employees.view', 'attendance.*', 'leaves.*', 'complaints.*', 'pos.*'],
        'Workshop Manager' => ['dashboard.view', 'role_requests.view', 'role_requests.create', 'role_requests.print', 'users.view', 'subcontracts.*', 'roadside.*', 'quality.*', 'quality_sops.*', 'nonconformities.*', 'capa_actions.*', 'quality_audits.*', 'workshop_planning.*', 'yard_locations.*', 'vehicle_tracking.*', 'test_drives.*', 'customers.*', 'vehicles.*', 'departments.*', 'services.*', 'service_packages.*', 'workshop_bays.*', 'reception.*', 'handover_authorizations.*', 'inspection_templates.*', 'inspections.*', 'quotations.*', 'job_cards.*', 'quality_checks.*', 'appointments.*', 'parts.view', 'stock.view', 'purchase_requests.*', 'warranty_claims.*', 'warranties.view', 'equipment.*', 'reports.view', 'commissions.view', 'insurance_claims.*', 'fleets.view'],
        'Receptionist' => ['dashboard.view', 'subcontracts.view', 'roadside.view', 'roadside.create', 'roadside.update', 'memberships.view', 'memberships.create', 'loyalty.view', 'workshop_planning.view', 'vehicle_tracking.*', 'test_drives.view', 'test_drives.create', 'yard_locations.view', 'customers.view', 'customers.create', 'customers.update', 'vehicles.view', 'vehicles.create', 'vehicles.update', 'reception.*', 'handover_authorizations.*', 'job_cards.view', 'job_cards.print', 'quotations.view', 'quotations.create', 'quotations.print', 'appointments.*', 'complaints.view', 'complaints.create', 'services.view', 'parts.view'],
        'Technician' => ['dashboard.view', 'subcontracts.view', 'roadside.view', 'roadside.update', 'quality_sops.view', 'capa_actions.view', 'capa_actions.update', 'workshop_planning.view', 'vehicle_tracking.view', 'vehicle_tracking.update', 'test_drives.view', 'test_drives.update', 'job_cards.view', 'job_cards.change_status', 'inspections.view', 'inspections.create', 'inspections.update', 'parts.view', 'vehicles.view', 'services.view'],
        'Quality Inspector' => ['dashboard.view', 'quality.*', 'quality_sops.*', 'nonconformities.*', 'capa_actions.*', 'quality_audits.*', 'inspection_templates.view', 'supplier_evaluations.view', 'job_cards.view', 'job_cards.change_status', 'quality_checks.*', 'inspections.view', 'vehicles.view'],
        'Storekeeper' => ['dashboard.view', 'parts.*', 'part_categories.*', 'brands.*', 'stock.*', 'stock_transfers.*', 'stock_counts.*', 'stock_adjustments.*', 'warehouses.view', 'goods_receipts.*', 'purchase_requests.view', 'purchase_requests.create', 'job_cards.view', 'dismantling.*', 'reports.view'],
        'Purchasing Officer' => ['dashboard.view', 'subcontracts.view', 'subcontracts.update', 'suppliers.*', 'purchase_requests.*', 'rfqs.*', 'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.print', 'goods_receipts.view', 'supplier_invoices.view', 'supplier_invoices.create', 'landed_costs.*', 'purchase_returns.*', 'supplier_evaluations.*', 'parts.view', 'stock.view', 'reports.view'],
        'Salesperson' => ['dashboard.view', 'memberships.view', 'memberships.create', 'loyalty.view', 'gift_cards.view', 'customers.view', 'customers.create', 'parts.view', 'stock.view', 'pos.view', 'pos.create', 'invoices.view', 'invoices.create', 'invoices.print', 'quotations.view', 'quotations.create', 'sales_orders.*', 'store_orders.view'],
        'Cashier' => ['dashboard.view', 'memberships.view', 'memberships.create', 'loyalty.view', 'gift_cards.view', 'gift_cards.create', 'pos.*', 'invoices.view', 'invoices.create', 'invoices.print', 'invoices.post', 'payments.view', 'payments.create', 'payments.print', 'customers.view', 'customers.create', 'parts.view', 'credit_notes.view', 'credit_notes.create'],
        'Accountant' => ['dashboard.view', 'accounts.*', 'journal_entries.*', 'fiscal_years.*', 'bank_accounts.*', 'expenses.*', 'fund_transfers.*', 'bank_reconciliations.*', 'fixed_assets.*', 'budgets.*', 'invoices.*', 'credit_notes.*', 'payments.*', 'supplier_invoices.*', 'customers.view', 'suppliers.view', 'reports.*', 'cost_centers.*', 'tax_rates.*', 'payroll.view', 'payroll.approve'],
        'HR Manager' => ['dashboard.view', 'role_requests.view', 'role_requests.create', 'role_requests.print', 'users.view', 'employees.*', 'hr_departments.*', 'job_titles.*', 'attendance.*', 'leaves.*', 'loans.*', 'payroll.*', 'performance_reviews.*', 'commissions.view', 'reports.view', 'users.view'],
        'Customer' => [],
        'Auditor' => ['*.view', '*.export', '*.print', 'audit_logs.*', 'reports.*', 'branches.all'],
    ],

    'defaults' => [
        // fraud & loss prevention thresholds
        'fraud.max_discount_percent' => '15', 'fraud.price_override_percent' => '10', 'fraud.price_drop_percent' => '20', 'fraud.large_amount' => '100',
        'fraud.write_off_min' => '20', 'fraud.adjustments_per_week' => '3', 'fraud.part_adjustments_30d' => '3', 'fraud.cash_difference_min' => '1',
        'fraud.after_hours_grace_minutes' => '30',
        // roadside assistance price = base fee by service type + rate per km beyond the included distance + extras
        'roadside.base_tow' => '25', 'roadside.base_battery' => '10', 'roadside.base_tyre' => '10', 'roadside.base_fuel' => '8',
        'roadside.base_lockout' => '15', 'roadside.base_jumpstart' => '8', 'roadside.base_other' => '10',
        'roadside.km_rate' => '0.5', 'roadside.free_km' => '10',
        'inventory.valuation_method' => 'weighted_average', // weighted_average | fifo
        'inventory.allow_negative' => false,
        'inventory.auto_reorder' => false,
        'loyalty.enabled' => true,
        'loyalty.points_per_omr' => '1',
        'loyalty.point_value' => '0.010',
        'loyalty.min_redeem' => 100,
        'loyalty.expiry_months' => 12,
        'loyalty.referral_points' => 200,
        'loyalty.silver_min' => 300,
        'loyalty.silver_multiplier' => '1.25',
        'loyalty.gold_min' => 1000,
        'loyalty.gold_multiplier' => '1.5',
        'inventory.low_stock_alerts' => true,
        'workshop.require_quotation_approval' => true,
        'workshop.default_labor_cost_per_hour' => '3.000',
        'workshop.require_qc' => true,
        'workshop.require_payment_before_delivery' => false,
        'sales.allow_credit_without_limit' => false,
        'invoice.footer_ar' => 'شكراً لتعاملكم معنا',
        'invoice.footer_en' => 'Thank you for your business',
        'invoice.terms' => '',
        'quotation.validity_days' => 14,
        'purchasing.approval_limit' => '500.000',
        'hr.overtime_rate' => '1.25',
        'hr.working_minutes_per_day' => 480,
        'hr.self_attendance' => true,        // check-in / check-out button in the top bar
        'hr.late_grace_minutes' => 10,
        'hr.financing_profit_rate' => 5,     // default profit % of internal financing
    ],
];
