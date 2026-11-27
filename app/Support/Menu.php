<?php

namespace App\Support;

/**
 * Sidebar navigation: filtered by permission and SaaS module activation.
 */
class Menu
{
    public static function crud(string $slug): string
    {
        return route('crud.index', $slug);
    }

    public static function groups(): array
    {
        return [
            ['label' => __('Main'), 'items' => [
                ['label' => __('Dashboard'), 'icon' => 'bi-speedometer2', 'route' => 'dashboard', 'perm' => 'dashboard.view'],
                ['label' => __('Workshop Board'), 'icon' => 'bi-kanban', 'route' => 'workshop.board', 'perm' => 'job_cards.view', 'module' => 'workshop'],
                ['label' => __('Vehicle Reception'), 'icon' => 'bi-box-arrow-in-down', 'route' => 'reception.create', 'perm' => 'reception.create', 'module' => 'workshop'],
                ['label' => __('Point of Sale'), 'icon' => 'bi-upc-scan', 'route' => 'pos.index', 'perm' => 'pos.view', 'module' => 'sales'],
                ['label' => __('Appointments'), 'icon' => 'bi-calendar-week', 'route' => 'appointments.calendar', 'perm' => 'appointments.view', 'module' => 'appointments'],
            ]],
            ['label' => __('Workshop'), 'module' => 'workshop', 'paths' => ['job-cards', 'inspections', 'quotations', 'gate-log', 'handover-authorizations', 'departments', 'workshop/planner', 'workshop/tracking', 'test-drives', 'workshop/subcontracts', 'subcontracts', 'roadside'], 'items' => [
                ['label' => __('Workshop planner'), 'icon' => 'bi-calendar-range', 'route' => 'workshop.planner', 'perm' => 'workshop_planning.view', 'module' => 'planning'],
                ['label' => __('Live workshop screen'), 'icon' => 'bi-tv', 'route' => 'workshop.live', 'perm' => 'job_cards.view', 'module' => 'planning'],
                ['label' => __('Vehicle & key tracking'), 'icon' => 'bi-key', 'route' => 'tracking.index', 'perm' => 'vehicle_tracking.view', 'module' => 'vehicle_tracking'],
                ['label' => __('Subcontracted repairs'), 'icon' => 'bi-buildings', 'route' => 'subcontracts.index', 'perm' => 'subcontracts.view', 'module' => 'subcontracting'],
                ['label' => __('Roadside assistance'), 'icon' => 'bi-truck-flatbed', 'route' => 'roadside.index', 'perm' => 'roadside.view', 'module' => 'roadside'],
                ['label' => __('Yard locations'), 'icon' => 'bi-geo-alt', 'url' => self::crud('yard-locations'), 'perm' => 'yard_locations.view', 'module' => 'vehicle_tracking'],
                ['label' => __('Job Cards'), 'icon' => 'bi-clipboard-check', 'url' => self::crud('job-cards'), 'perm' => 'job_cards.view'],
                ['label' => __('Quotations'), 'icon' => 'bi-file-earmark-text', 'url' => self::crud('quotations'), 'perm' => 'quotations.view'],
                ['label' => __('Inspections'), 'icon' => 'bi-search', 'url' => self::crud('inspections'), 'perm' => 'inspections.view'],
                ['label' => __('Departments'), 'icon' => 'bi-diagram-3', 'url' => self::crud('departments'), 'perm' => 'departments.view'],
                ['label' => __('Services & Prices'), 'icon' => 'bi-tools', 'url' => self::crud('services'), 'perm' => 'services.view'],
                ['label' => __('Service Packages'), 'icon' => 'bi-box2-heart', 'url' => self::crud('service-packages'), 'perm' => 'service_packages.view'],
                ['label' => __('Promotions'), 'icon' => 'bi-percent', 'url' => self::crud('promotions'), 'perm' => 'promotions.view'],
                ['label' => __('Workshop Bays'), 'icon' => 'bi-grid-3x3-gap', 'url' => self::crud('workshop-bays'), 'perm' => 'workshop_bays.view'],
                ['label' => __('Inspection Checklists'), 'icon' => 'bi-list-check', 'url' => self::crud('inspection-templates'), 'perm' => 'inspection_templates.view'],
                ['label' => __('Gate Log'), 'icon' => 'bi-signpost-split', 'url' => route('gate-logs.index'), 'perm' => 'reception.view'],
                ['label' => __('Handover Authorizations'), 'icon' => 'bi-person-badge', 'url' => route('handover-authorizations.index'), 'perm' => 'handover_authorizations.view'],
                ['label' => __('Commissions'), 'icon' => 'bi-cash-coin', 'url' => self::crud('commissions'), 'perm' => 'commissions.view'],
            ]],
            ['label' => __('Quality'), 'module' => 'quality', 'paths' => ['quality', 'm/nonconformities', 'm/capa-actions', 'm/quality-sops', 'm/quality-audits'], 'items' => [
                ['label' => __('Quality management'), 'icon' => 'bi-award', 'route' => 'quality.dashboard', 'perm' => 'quality.view'],
                ['label' => __('Nonconformities (NCR)'), 'icon' => 'bi-exclamation-diamond', 'url' => self::crud('nonconformities'), 'perm' => 'nonconformities.view'],
                ['label' => __('Corrective & preventive actions (CAPA)'), 'icon' => 'bi-check2-square', 'url' => self::crud('capa-actions'), 'perm' => 'capa_actions.view'],
                ['label' => __('Standard procedures (SOP)'), 'icon' => 'bi-journal-check', 'url' => self::crud('quality-sops'), 'perm' => 'quality_sops.view'],
                ['label' => __('Internal audits'), 'icon' => 'bi-clipboard2-data', 'url' => self::crud('quality-audits'), 'perm' => 'quality_audits.view'],
                ['label' => __('Inspection Checklists'), 'icon' => 'bi-list-check', 'url' => self::crud('inspection-templates'), 'perm' => 'inspection_templates.view'],
                ['label' => __('Supplier evaluations'), 'icon' => 'bi-star-half', 'url' => self::crud('supplier-evaluations'), 'perm' => 'supplier_evaluations.view'],
            ]],
            ['label' => __('Customers & Vehicles'), 'paths' => ['customers', 'vehicles', 'complaints', 'reminders', 'loyalty', 'memberships', 'gift-cards'], 'items' => [
                ['label' => __('Customers'), 'icon' => 'bi-people', 'url' => self::crud('customers'), 'perm' => 'customers.view'],
                ['label' => __('Vehicles'), 'icon' => 'bi-car-front', 'url' => self::crud('vehicles'), 'perm' => 'vehicles.view'],
                ['label' => __('Customer Groups'), 'icon' => 'bi-collection', 'url' => self::crud('customer-groups'), 'perm' => 'customer_groups.view'],
                ['label' => __('Loyalty & rewards'), 'icon' => 'bi-stars', 'route' => 'loyalty.dashboard', 'perm' => 'loyalty.view', 'module' => 'loyalty'],
                ['label' => __('Memberships'), 'icon' => 'bi-person-vcard', 'url' => self::crud('memberships'), 'perm' => 'memberships.view', 'module' => 'memberships'],
                ['label' => __('Membership plans'), 'icon' => 'bi-patch-check', 'url' => self::crud('membership-plans'), 'perm' => 'membership_plans.view', 'module' => 'memberships'],
                ['label' => __('Gift cards'), 'icon' => 'bi-gift', 'url' => self::crud('gift-cards'), 'perm' => 'gift_cards.view', 'module' => 'loyalty'],
                ['label' => __('Complaints'), 'icon' => 'bi-chat-left-dots', 'url' => self::crud('complaints'), 'perm' => 'complaints.view'],
                ['label' => __('Service Reminders'), 'icon' => 'bi-bell', 'url' => route('reminders.index'), 'perm' => 'service_reminders.view'],
                ['label' => __('Vehicle Makes'), 'icon' => 'bi-tags', 'url' => self::crud('vehicle-makes'), 'perm' => 'vehicle_makes.view'],
            ]],
            ['label' => __('Spare Parts & Inventory'), 'module' => 'inventory', 'paths' => ['inventory', 'parts'], 'items' => [
                ['label' => __('Parts Catalog'), 'icon' => 'bi-gear-wide-connected', 'url' => self::crud('parts'), 'perm' => 'parts.view'],
                ['label' => __('Advanced Part Search'), 'icon' => 'bi-binoculars', 'route' => 'parts.search', 'perm' => 'parts.view'],
                ['label' => __('Cross reference'), 'icon' => 'bi-arrow-left-right', 'route' => 'inventory.cross-reference', 'perm' => 'parts.view'],
                ['label' => __('Stock reservations'), 'icon' => 'bi-bookmark', 'route' => 'inventory.reservations', 'perm' => 'stock.view', 'module' => 'inventory_ops'],
                ['label' => __('Consignment stock'), 'icon' => 'bi-boxes', 'route' => 'inventory.consignment', 'perm' => 'stock.view', 'module' => 'inventory_ops'],
                ['label' => __('Core returns'), 'icon' => 'bi-arrow-return-left', 'route' => 'inventory.cores', 'perm' => 'invoices.view', 'module' => 'inventory_ops'],
                ['label' => __('Inventory analytics'), 'icon' => 'bi-graph-up', 'route' => 'inventory.analytics', 'perm' => 'stock.view', 'module' => 'inventory_analytics'],
                ['label' => __('Stock Levels'), 'icon' => 'bi-boxes', 'url' => self::crud('stock-levels'), 'perm' => 'stock.view'],
                ['label' => __('Stock Movements'), 'icon' => 'bi-arrow-left-right', 'url' => self::crud('stock-movements'), 'perm' => 'stock.view'],
                ['label' => __('Transfers'), 'icon' => 'bi-truck', 'url' => self::crud('stock-transfers'), 'perm' => 'stock_transfers.view'],
                ['label' => __('Stock Counts'), 'icon' => 'bi-clipboard-data', 'url' => self::crud('stock-counts'), 'perm' => 'stock_counts.view'],
                ['label' => __('Adjustments'), 'icon' => 'bi-sliders', 'url' => self::crud('stock-adjustments'), 'perm' => 'stock_adjustments.view'],
                ['label' => __('Reorder Suggestions'), 'icon' => 'bi-graph-up-arrow', 'route' => 'inventory.reorder', 'perm' => 'stock.view'],
                ['label' => __('Categories'), 'icon' => 'bi-folder2', 'url' => self::crud('part-categories'), 'perm' => 'part_categories.view'],
                ['label' => __('Brands / Manufacturers'), 'icon' => 'bi-award', 'url' => self::crud('brands'), 'perm' => 'brands.view'],
                ['label' => __('Warehouses'), 'icon' => 'bi-building', 'url' => self::crud('warehouses'), 'perm' => 'warehouses.view'],
                ['label' => __('Shelves & Locations'), 'icon' => 'bi-bookshelf', 'url' => self::crud('warehouse-locations'), 'perm' => 'warehouses.view'],
            ]],
            ['label' => __('Dismantling'), 'module' => 'dismantling', 'paths' => ['dismantling'], 'items' => [
                ['label' => __('Dismantled Vehicles'), 'icon' => 'bi-car-front-fill', 'url' => self::crud('dismantled-vehicles'), 'perm' => 'dismantling.view'],
            ]],
            ['label' => __('Purchasing'), 'module' => 'purchasing', 'paths' => ['purchase-orders', 'goods-receipts', 'supplier-invoices', 'rfqs', 'purchase-returns', 'purchase-requests', 'landed-costs', 'suppliers'], 'items' => [
                ['label' => __('Suppliers'), 'icon' => 'bi-shop', 'url' => self::crud('suppliers'), 'perm' => 'suppliers.view'],
                ['label' => __('Purchase Requests'), 'icon' => 'bi-journal-plus', 'url' => self::crud('purchase-requests'), 'perm' => 'purchase_requests.view'],
                ['label' => __('RFQs'), 'icon' => 'bi-envelope-paper', 'url' => self::crud('rfqs'), 'perm' => 'rfqs.view'],
                ['label' => __('Purchase Orders'), 'icon' => 'bi-cart-check', 'url' => self::crud('purchase-orders'), 'perm' => 'purchase_orders.view'],
                ['label' => __('Goods Receipts'), 'icon' => 'bi-box-seam', 'url' => self::crud('goods-receipts'), 'perm' => 'goods_receipts.view'],
                ['label' => __('Supplier Invoices'), 'icon' => 'bi-receipt', 'url' => self::crud('supplier-invoices'), 'perm' => 'supplier_invoices.view'],
                ['label' => __('Landed Costs'), 'icon' => 'bi-airplane', 'url' => self::crud('landed-costs'), 'perm' => 'landed_costs.view'],
                ['label' => __('Purchase Returns'), 'icon' => 'bi-arrow-return-left', 'url' => self::crud('purchase-returns'), 'perm' => 'purchase_returns.view'],
                ['label' => __('Supplier Evaluations'), 'icon' => 'bi-star-half', 'url' => self::crud('supplier-evaluations'), 'perm' => 'supplier_evaluations.view'],
            ]],
            ['label' => __('Sales'), 'module' => 'sales', 'paths' => ['invoices', 'credit-notes', 'payments', 'sales-orders', 'quotations/sales'], 'items' => [
                ['label' => __('Invoices'), 'icon' => 'bi-receipt-cutoff', 'url' => self::crud('invoices'), 'perm' => 'invoices.view'],
                ['label' => __('Credit Notes'), 'icon' => 'bi-file-earmark-minus', 'url' => self::crud('credit-notes'), 'perm' => 'credit_notes.view'],
                ['label' => __('Payments'), 'icon' => 'bi-wallet2', 'url' => self::crud('payments'), 'perm' => 'payments.view'],
                ['label' => __('Sales Orders'), 'icon' => 'bi-bag-check', 'url' => self::crud('sales-orders'), 'perm' => 'sales_orders.view'],
                ['label' => __('Sales Quotations'), 'icon' => 'bi-file-earmark-richtext', 'route' => 'quotations.sales.create', 'perm' => 'quotations.create'],
                ['label' => __('POS Sessions'), 'icon' => 'bi-cash-stack', 'url' => self::crud('pos-sessions'), 'perm' => 'pos.view'],
                ['label' => __('Cash Registers'), 'icon' => 'bi-pc-display-horizontal', 'url' => self::crud('cash-registers'), 'perm' => 'cash_registers.view'],
            ]],
            ['label' => __('Accounting'), 'module' => 'accounting', 'paths' => ['accounting', 'vouchers'], 'items' => [
                ['label' => __('Chart of Accounts'), 'icon' => 'bi-diagram-2', 'url' => self::crud('accounts'), 'perm' => 'accounts.view'],
                ['label' => __('Journal Entries'), 'icon' => 'bi-journal-text', 'url' => self::crud('journal-entries'), 'perm' => 'journal_entries.view'],
                ['label' => __('Cash & Banks'), 'icon' => 'bi-bank', 'url' => self::crud('bank-accounts'), 'perm' => 'bank_accounts.view'],
                ['label' => __('Expenses'), 'icon' => 'bi-credit-card-2-back', 'url' => self::crud('expenses'), 'perm' => 'expenses.view'],
                ['label' => __('Fund Transfers'), 'icon' => 'bi-arrow-repeat', 'url' => self::crud('fund-transfers'), 'perm' => 'fund_transfers.view'],
                ['label' => __('Bank Reconciliation'), 'icon' => 'bi-check2-square', 'url' => self::crud('bank-reconciliations'), 'perm' => 'bank_reconciliations.view'],
                ['label' => __('Fixed Assets'), 'icon' => 'bi-building-gear', 'url' => self::crud('fixed-assets'), 'perm' => 'fixed_assets.view'],
                ['label' => __('Budgets'), 'icon' => 'bi-piggy-bank', 'url' => self::crud('budgets'), 'perm' => 'budgets.view'],
                ['label' => __('Fiscal Years & Periods'), 'icon' => 'bi-calendar3', 'url' => self::crud('fiscal-years'), 'perm' => 'fiscal_years.view'],
                ['label' => __('Cost Centers'), 'icon' => 'bi-pie-chart', 'url' => self::crud('cost-centers'), 'perm' => 'cost_centers.view'],
                ['label' => __('Opening Balances'), 'icon' => 'bi-door-open', 'route' => 'accounting.opening', 'perm' => 'journal_entries.create'],
                ['label' => __('Financial Statements'), 'icon' => 'bi-file-bar-graph', 'route' => 'accounting.statements', 'perm' => 'reports.view'],
            ]],
            ['label' => __('HR & Payroll'), 'module' => 'hr', 'paths' => ['hr'], 'items' => [
                ['label' => __('Employees'), 'icon' => 'bi-person-badge', 'url' => self::crud('employees'), 'perm' => 'employees.view'],
                ['label' => __('Attendance'), 'icon' => 'bi-fingerprint', 'url' => self::crud('attendances'), 'perm' => 'attendance.view'],
                ['label' => __('Leave Requests'), 'icon' => 'bi-airplane-engines', 'url' => self::crud('leave-requests'), 'perm' => 'leaves.view'],
                ['label' => __('Advances & Loans'), 'icon' => 'bi-cash', 'url' => self::crud('employee-loans'), 'perm' => 'loans.view'],
                ['label' => __('Bonuses & Deductions'), 'icon' => 'bi-plus-slash-minus', 'url' => self::crud('employee-adjustments'), 'perm' => 'payroll.view'],
                ['label' => __('Payroll'), 'icon' => 'bi-cash-coin', 'url' => self::crud('payrolls'), 'perm' => 'payroll.view'],
                ['label' => __('Contracts'), 'icon' => 'bi-file-earmark-person', 'url' => self::crud('employee-contracts'), 'perm' => 'employees.view'],
                ['label' => __('Performance Reviews'), 'icon' => 'bi-graph-up', 'url' => self::crud('performance-reviews'), 'perm' => 'performance_reviews.view'],
                ['label' => __('HR Departments'), 'icon' => 'bi-diagram-3', 'url' => self::crud('hr-departments'), 'perm' => 'hr_departments.view'],
                ['label' => __('Job Titles'), 'icon' => 'bi-person-vcard', 'url' => self::crud('job-titles'), 'perm' => 'job_titles.view'],
                ['label' => __('Leave Types'), 'icon' => 'bi-list-ul', 'url' => self::crud('leave-types'), 'perm' => 'leaves.view'],
            ]],
            ['label' => __('Insurance & Fleet'), 'paths' => ['insurance', 'fleets'], 'items' => [
                ['label' => __('Insurance Claims'), 'icon' => 'bi-shield-check', 'url' => self::crud('insurance-claims'), 'perm' => 'insurance_claims.view', 'module' => 'insurance'],
                ['label' => __('Insurance Companies'), 'icon' => 'bi-buildings', 'url' => self::crud('insurance-companies'), 'perm' => 'insurance_companies.view', 'module' => 'insurance'],
                ['label' => __('Accidents'), 'icon' => 'bi-exclamation-octagon', 'url' => self::crud('vehicle-accidents'), 'perm' => 'insurance_claims.view', 'module' => 'insurance'],
                ['label' => __('Fleets'), 'icon' => 'bi-truck-front', 'url' => self::crud('fleets'), 'perm' => 'fleets.view', 'module' => 'fleet'],
                ['label' => __('Fleet Approvals'), 'icon' => 'bi-check2-circle', 'url' => self::crud('fleet-approvals'), 'perm' => 'fleets.view', 'module' => 'fleet'],
            ]],
            ['label' => __('Warranty & Assets'), 'paths' => ['warranty'], 'items' => [
                ['label' => __('Warranties'), 'icon' => 'bi-patch-check', 'url' => self::crud('warranties'), 'perm' => 'warranties.view', 'module' => 'warranty'],
                ['label' => __('Warranty Claims'), 'icon' => 'bi-patch-exclamation', 'url' => self::crud('warranty-claims'), 'perm' => 'warranty_claims.view', 'module' => 'warranty'],
                ['label' => __('Equipment & Tools'), 'icon' => 'bi-wrench-adjustable', 'url' => self::crud('equipment'), 'perm' => 'equipment.view', 'module' => 'assets'],
                ['label' => __('Equipment Logs'), 'icon' => 'bi-journal-medical', 'url' => self::crud('equipment-logs'), 'perm' => 'equipment.view', 'module' => 'assets'],
            ]],
            ['label' => __('Online Store'), 'module' => 'store', 'paths' => ['store-orders'], 'items' => [
                ['label' => __('Online Orders'), 'icon' => 'bi-globe2', 'url' => self::crud('store-orders'), 'perm' => 'store_orders.view'],
                ['label' => __('Coupons'), 'icon' => 'bi-ticket-perforated', 'url' => self::crud('coupons'), 'perm' => 'coupons.view'],
                ['label' => __('Open Store'), 'icon' => 'bi-box-arrow-up-right', 'route' => 'store.home.self', 'perm' => 'store_orders.view'],
            ]],
            ['label' => __('Reports & Analytics'), 'module' => 'reports', 'paths' => ['reports', 'ai'], 'items' => [
                ['label' => __('Reports Center'), 'icon' => 'bi-bar-chart-line', 'route' => 'reports.index', 'perm' => 'reports.view'],
                ['label' => __('AI Assistant'), 'icon' => 'bi-stars', 'route' => 'ai.index', 'perm' => 'reports.view'],
            ]],
            ['label' => __('Administration'), 'paths' => ['settings', 'roles', 'users', 'integrations', 'backups', 'billing', 'api-tokens'], 'items' => [
                ['label' => __('Company Settings'), 'icon' => 'bi-gear', 'route' => 'settings.edit', 'perm' => 'settings.view'],
                ['label' => __('Branches'), 'icon' => 'bi-geo-alt', 'url' => self::crud('branches'), 'perm' => 'branches.view'],
                ['label' => __('Users'), 'icon' => 'bi-person-gear', 'url' => self::crud('users'), 'perm' => 'users.view'],
                ['label' => __('Roles & Permissions'), 'icon' => 'bi-shield-lock', 'route' => 'roles.index', 'perm' => 'roles.view'],
                ['label' => __('Working Hours'), 'icon' => 'bi-clock', 'url' => self::crud('working-hours'), 'perm' => 'working_hours.view'],
                ['label' => __('Holidays'), 'icon' => 'bi-calendar-heart', 'url' => self::crud('holidays'), 'perm' => 'holidays.view'],
                ['label' => __('Tax Rates'), 'icon' => 'bi-percent', 'url' => self::crud('tax-rates'), 'perm' => 'tax_rates.view'],
                ['label' => __('Document Numbering'), 'icon' => 'bi-123', 'url' => self::crud('document-sequences'), 'perm' => 'document_sequences.view'],
                ['label' => __('Notification Templates'), 'icon' => 'bi-chat-square-text', 'url' => self::crud('notification-templates'), 'perm' => 'notification_templates.view'],
                ['label' => __('Integrations'), 'icon' => 'bi-plug', 'route' => 'integrations.index', 'perm' => 'integrations.view', 'module' => 'integrations'],
                ['label' => __('Webhooks'), 'icon' => 'bi-broadcast', 'url' => self::crud('webhooks'), 'perm' => 'webhooks.view', 'module' => 'integrations'],
                ['label' => __('API Tokens'), 'icon' => 'bi-key', 'route' => 'api-tokens.index', 'perm' => 'integrations.view', 'module' => 'integrations'],
                ['label' => __('Audit Log'), 'icon' => 'bi-shield-exclamation', 'url' => self::crud('audit-logs'), 'perm' => 'audit_logs.view'],
                ['label' => __('Login History'), 'icon' => 'bi-door-closed', 'url' => self::crud('login-histories'), 'perm' => 'audit_logs.view'],
                ['label' => __('Backups'), 'icon' => 'bi-hdd-stack', 'route' => 'backups.index', 'perm' => 'backups.view',
                    // whole-database backups: platform admin, or the only company of an on-premise install
                    'when' => fn () => auth()->user()->is_super_admin || \App\Modules\Core\Models\Company::query()->count() === 1],
                ['label' => __('Subscription'), 'icon' => 'bi-stars', 'route' => 'billing.index', 'perm' => 'settings.view'],
            ]],
        ];
    }

    /**
     * The menu section of the current page (for the section bar under the main menu): the group whose item link
     * or declared path prefix matches the URL path best. Returns null on the dashboard and pages of the "Main" group.
     *
     * @return array{label: string, items: array, active: ?string}|null
     */
    public static function section(?string $path = null): ?array
    {
        $path = trim($path ?? request()->path(), '/');
        $best = null;
        $bestLen = 0;
        $activeHref = null;
        foreach (array_slice(self::visible(), 1) as $g) {
            $candidates = array_map(fn ($p) => [$p, null], $g['paths'] ?? []);
            foreach ($g['items'] as $i) {
                $candidates[] = [trim((string) parse_url($i['href'], PHP_URL_PATH), '/'), $i['href']];
            }
            foreach ($candidates as [$prefix, $href]) {
                if ($prefix === '' || ($path !== $prefix && ! str_starts_with($path, $prefix.'/'))) {
                    continue;
                }
                if (strlen($prefix) > $bestLen) {
                    [$best, $bestLen, $activeHref] = [$g, strlen($prefix), $href];
                }
            }
        }
        if (! $best || count($best['items']) < 2) {
            return null;
        }
        // detail pages (e.g. warranty/claims/5) highlight the list they belong to (m/warranty-claims)
        if (! $activeHref) {
            $seg = explode('/', $path);
            $aliases = ['inventory/transfers' => 'stock-transfers', 'inventory/counts' => 'stock-counts', 'inventory/adjustments' => 'stock-adjustments',
                'warranty/claims' => 'warranty-claims', 'hr/payroll' => 'payrolls', 'accounting/transfers' => 'fund-transfers', 'journal' => 'journal-entries',
                'vouchers/transfers' => 'fund-transfers', 'vouchers/expenses' => 'expenses', 'vouchers/payments' => 'payments',
                'quality/ncr' => 'nonconformities', 'quality/audits' => 'quality-audits'];
            $two = implode('/', array_slice($seg, 0, 2));
            $slugs = array_filter([$aliases[$two] ?? null, $aliases[$seg[0]] ?? null, str_replace('/', '-', $two), $seg[0]]);
            foreach ($slugs as $slug) {
                foreach ($best['items'] as $i) {
                    if (trim((string) parse_url($i['href'], PHP_URL_PATH), '/') === 'm/'.$slug) {
                        $activeHref = $i['href'];
                        break 2;
                    }
                }
            }
        }

        return ['label' => $best['label'], 'items' => $best['items'], 'active' => $activeHref];
    }

    /** Filtered by permission & module for the current user. */
    public static function visible(): array
    {
        $user = auth()->user();
        $company = tenant()->company();
        $out = [];
        foreach (self::groups() as $g) {
            if (! empty($g['module']) && $company && ! $company->moduleEnabled($g['module'])) {
                continue;
            }
            $items = array_values(array_filter($g['items'], function ($i) use ($user, $company) {
                if (! empty($i['module']) && $company && ! $company->moduleEnabled($i['module'])) {
                    return false;
                }

                if (isset($i['when']) && ! ($i['when'])()) {
                    return false;
                }

                return empty($i['perm']) || $user->can($i['perm']);
            }));
            if ($items) {
                $g['items'] = array_map(function ($i) {
                    $i['href'] = $i['url'] ?? (\Illuminate\Support\Facades\Route::has($i['route']) ? route($i['route']) : '#');

                    return $i;
                }, $items);
                $out[] = $g;
            }
        }

        return $out;
    }
}
