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
    'companies', 'branches', 'warehouses', 'cost_centers', 'users', 'roles', 'settings', 'audit_logs',
    'working_hours', 'holidays', 'tax_rates', 'document_sequences',
    // crm & vehicles
    'customers', 'customer_groups', 'complaints', 'service_reminders', 'vehicles', 'vehicle_makes',
    // workshop
    'departments', 'services', 'service_packages', 'promotions', 'workshop_bays', 'reception', 'handover_authorizations',
    'inspection_templates', 'inspections', 'quotations', 'job_cards', 'quality_checks', 'commissions',
    'workshop_planning', 'yard_locations', 'vehicle_tracking', 'test_drives',
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
        'inventory' => ['label' => 'Spare Parts & Inventory'],
        'purchasing' => ['label' => 'Purchasing & Suppliers'],
        'sales' => ['label' => 'Sales & POS'],
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
        'General Manager' => ['*.view', '*.print', '*.export', '*.approve', 'dashboard.*', 'reports.*', 'branches.all', 'job_cards.*'],
        'Branch Manager' => ['dashboard.*', 'quality.view', 'quality_sops.view', 'nonconformities.*', 'capa_actions.*', 'quality_audits.view', 'workshop_planning.*', 'vehicle_tracking.*', 'test_drives.*', 'yard_locations.view', 'reports.*', 'customers.*', 'vehicles.*', 'reception.*', 'handover_authorizations.*', 'inspections.*', 'quotations.*', 'job_cards.*', 'appointments.*', 'invoices.*', 'payments.*', 'parts.view', 'stock.view', 'purchase_requests.*', 'purchase_orders.view', 'employees.view', 'attendance.*', 'leaves.*', 'complaints.*', 'pos.*'],
        'Workshop Manager' => ['dashboard.view', 'quality.*', 'quality_sops.*', 'nonconformities.*', 'capa_actions.*', 'quality_audits.*', 'workshop_planning.*', 'yard_locations.*', 'vehicle_tracking.*', 'test_drives.*', 'customers.*', 'vehicles.*', 'departments.*', 'services.*', 'service_packages.*', 'workshop_bays.*', 'reception.*', 'handover_authorizations.*', 'inspection_templates.*', 'inspections.*', 'quotations.*', 'job_cards.*', 'quality_checks.*', 'appointments.*', 'parts.view', 'stock.view', 'purchase_requests.*', 'warranty_claims.*', 'warranties.view', 'equipment.*', 'reports.view', 'commissions.view', 'insurance_claims.*', 'fleets.view'],
        'Receptionist' => ['dashboard.view', 'workshop_planning.view', 'vehicle_tracking.*', 'test_drives.view', 'test_drives.create', 'yard_locations.view', 'customers.view', 'customers.create', 'customers.update', 'vehicles.view', 'vehicles.create', 'vehicles.update', 'reception.*', 'handover_authorizations.*', 'job_cards.view', 'job_cards.print', 'quotations.view', 'quotations.create', 'quotations.print', 'appointments.*', 'complaints.view', 'complaints.create', 'services.view', 'parts.view'],
        'Technician' => ['dashboard.view', 'quality_sops.view', 'capa_actions.view', 'capa_actions.update', 'workshop_planning.view', 'vehicle_tracking.view', 'vehicle_tracking.update', 'test_drives.view', 'test_drives.update', 'job_cards.view', 'job_cards.change_status', 'inspections.view', 'inspections.create', 'inspections.update', 'parts.view', 'vehicles.view', 'services.view'],
        'Quality Inspector' => ['dashboard.view', 'quality.*', 'quality_sops.*', 'nonconformities.*', 'capa_actions.*', 'quality_audits.*', 'inspection_templates.view', 'supplier_evaluations.view', 'job_cards.view', 'job_cards.change_status', 'quality_checks.*', 'inspections.view', 'vehicles.view'],
        'Storekeeper' => ['dashboard.view', 'parts.*', 'part_categories.*', 'brands.*', 'stock.*', 'stock_transfers.*', 'stock_counts.*', 'stock_adjustments.*', 'warehouses.view', 'goods_receipts.*', 'purchase_requests.view', 'purchase_requests.create', 'job_cards.view', 'dismantling.*', 'reports.view'],
        'Purchasing Officer' => ['dashboard.view', 'suppliers.*', 'purchase_requests.*', 'rfqs.*', 'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.print', 'goods_receipts.view', 'supplier_invoices.view', 'supplier_invoices.create', 'landed_costs.*', 'purchase_returns.*', 'supplier_evaluations.*', 'parts.view', 'stock.view', 'reports.view'],
        'Salesperson' => ['dashboard.view', 'customers.view', 'customers.create', 'parts.view', 'stock.view', 'pos.view', 'pos.create', 'invoices.view', 'invoices.create', 'invoices.print', 'quotations.view', 'quotations.create', 'sales_orders.*', 'store_orders.view'],
        'Cashier' => ['dashboard.view', 'pos.*', 'invoices.view', 'invoices.create', 'invoices.print', 'invoices.post', 'payments.view', 'payments.create', 'payments.print', 'customers.view', 'customers.create', 'parts.view', 'credit_notes.view', 'credit_notes.create'],
        'Accountant' => ['dashboard.view', 'accounts.*', 'journal_entries.*', 'fiscal_years.*', 'bank_accounts.*', 'expenses.*', 'fund_transfers.*', 'bank_reconciliations.*', 'fixed_assets.*', 'budgets.*', 'invoices.*', 'credit_notes.*', 'payments.*', 'supplier_invoices.*', 'customers.view', 'suppliers.view', 'reports.*', 'cost_centers.*', 'tax_rates.*', 'payroll.view', 'payroll.approve'],
        'HR Manager' => ['dashboard.view', 'employees.*', 'hr_departments.*', 'job_titles.*', 'attendance.*', 'leaves.*', 'loans.*', 'payroll.*', 'performance_reviews.*', 'commissions.view', 'reports.view', 'users.view'],
        'Customer' => [],
        'Auditor' => ['*.view', '*.export', '*.print', 'audit_logs.*', 'reports.*', 'branches.all'],
    ],

    'defaults' => [
        'inventory.valuation_method' => 'weighted_average', // weighted_average | fifo
        'inventory.allow_negative' => false,
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
    ],
];
