# Entity Relationship Diagram

Generated from the live database schema (159 tables, 444 foreign keys). Each diagram shows one domain with its key columns and foreign keys; links to `companies`, `branches` and `users` are omitted for readability: **every business table carries `company_id`** (tenant isolation, indexed) and most carry `branch_id` and `created_by`.

Conventions: money columns are `DECIMAL(15,3)` (OMR), unit costs `DECIMAL(15,4)`, quantities `DECIMAL(14,3)`; document numbers are unique per company (`PREFIX-YYYY-000001`).

## Platform, tenancy & security

```mermaid
erDiagram
    plans {
        bigint id PK
        varchar code
        varchar name
        varchar name_ar
    }
    companies {
        bigint id PK
        varchar name
        varchar name_ar
        bigint plan_id FK
    }
    subscriptions {
        bigint id PK
        bigint plan_id FK
        varchar status
    }
    branches {
        bigint id PK
        varchar code
        varchar name_ar
        bigint cost_center_id FK
    }
    cost_centers {
        bigint id PK
        bigint parent_id FK
        varchar code
        varchar name_ar
    }
    users {
        bigint id PK
        varchar name
    }
    branch_user {
        bigint user_id PK
    }
    audit_logs {
        bigint id PK
    }
    login_histories {
        bigint id PK
    }
    attachments {
        bigint id PK
        bigint uploaded_by FK
    }
    integrations {
        bigint id PK
    }
    webhooks {
        bigint id PK
    }
    webhook_deliveries {
        bigint id PK
        bigint webhook_id FK
    }
    notification_templates {
        bigint id PK
    }
    document_sequences {
        bigint id PK
        varchar type
    }
    subscription_invoices {
        bigint id PK
        bigint subscription_id FK
        varchar number
        varchar status
    }
    working_hours {
        bigint id PK
    }
    holidays {
        bigint id PK
        varchar name
        date date
    }
    cost_centers ||--o{ branches : "cost_center_id"
    plans ||--o{ companies : "plan_id"
    plans ||--o{ subscriptions : "plan_id"
    subscriptions ||--o{ subscription_invoices : "subscription_id"
    webhooks ||--o{ webhook_deliveries : "webhook_id"
```

## Customers & vehicles

```mermaid
erDiagram
    customers {
        bigint id PK
        varchar code
        varchar type
        varchar name
        bigint customer_group_id FK
    }
    customer_groups {
        bigint id PK
        varchar name
    }
    customer_contacts {
        bigint id PK
        bigint customer_id FK
        varchar name
    }
    vehicles {
        bigint id PK
        bigint customer_id FK
        bigint fleet_id FK
        varchar plate_number
        varchar vin
        bigint vehicle_make_id FK
        bigint vehicle_model_id FK
        bigint insurance_company_id FK
    }
    vehicle_makes {
        bigint id PK
        varchar name
        varchar name_ar
    }
    vehicle_models {
        bigint id PK
        bigint vehicle_make_id FK
        varchar name
    }
    vehicle_ownerships {
        bigint id PK
        bigint vehicle_id FK
        bigint from_customer_id FK
        bigint to_customer_id FK
        bigint user_id FK
    }
    vehicle_accidents {
        bigint id PK
        bigint vehicle_id FK
    }
    complaints {
        bigint id PK
        bigint customer_id FK
        bigint job_card_id FK
        bigint vehicle_id FK
        varchar status
        bigint assigned_to FK
    }
    service_reminders {
        bigint id PK
        bigint customer_id FK
        bigint vehicle_id FK
        bigint service_package_id FK
        varchar status
    }
    fleets {
        bigint id PK
        bigint customer_id FK
        varchar name
        varchar status
    }
    insurance_companies {
        bigint id PK
        varchar name
        bigint customer_id FK
    }
    customers ||--o{ complaints : "customer_id"
    vehicles ||--o{ complaints : "vehicle_id"
    customer_groups ||--o{ customers : "customer_group_id"
    customers ||--o{ customer_contacts : "customer_id"
    customers ||--o{ fleets : "customer_id"
    customers ||--o{ insurance_companies : "customer_id"
    customers ||--o{ service_reminders : "customer_id"
    vehicles ||--o{ service_reminders : "vehicle_id"
    customers ||--o{ vehicles : "customer_id"
    fleets ||--o{ vehicles : "fleet_id"
    insurance_companies ||--o{ vehicles : "insurance_company_id"
    vehicle_makes ||--o{ vehicles : "vehicle_make_id"
    vehicle_models ||--o{ vehicles : "vehicle_model_id"
    vehicles ||--o{ vehicle_accidents : "vehicle_id"
    vehicle_makes ||--o{ vehicle_models : "vehicle_make_id"
    customers ||--o{ vehicle_ownerships : "from_customer_id"
    customers ||--o{ vehicle_ownerships : "to_customer_id"
    vehicles ||--o{ vehicle_ownerships : "vehicle_id"
```

Cross-domain links: `complaints.job_card_id` → `job_cards`, `service_reminders.service_package_id` → `service_packages`.

## Workshop

```mermaid
erDiagram
    departments {
        bigint id PK
        varchar code
        varchar name_ar
        bigint supervisor_id FK
        bigint cost_center_id FK
    }
    services {
        bigint id PK
        bigint department_id FK
        varchar code
        varchar name_ar
    }
    service_vehicle_prices {
        bigint id PK
        bigint service_id FK
    }
    job_cards {
        bigint id PK
        varchar number
        bigint customer_id FK
        bigint vehicle_id FK
        varchar status
        bigint service_advisor_id FK
        bigint department_id FK
        bigint bay_id FK
        bigint fleet_id FK
    }
    job_card_tasks {
        bigint id PK
        bigint job_card_id FK
        bigint department_id FK
        bigint service_id FK
        bigint technician_id FK
        bigint quotation_item_id FK
        varchar status
        decimal qty
    }
    job_card_parts {
        bigint id PK
        bigint job_card_id FK
        bigint part_id FK
        bigint warehouse_id FK
        bigint quotation_item_id FK
        decimal qty
        varchar status
        bigint requested_by FK
        bigint issued_by FK
    }
    job_card_status_histories {
        bigint id PK
        bigint job_card_id FK
        bigint user_id FK
    }
    job_card_time_logs {
        bigint id PK
        bigint job_card_task_id FK
        bigint technician_id FK
    }
    job_card_notes {
        bigint id PK
        bigint job_card_id FK
        bigint user_id FK
    }
    vehicle_check_ins {
        bigint id PK
        bigint job_card_id FK
        bigint vehicle_id FK
        bigint customer_id FK
        bigint received_by FK
    }
    inspection_templates {
        bigint id PK
        varchar name
    }
    inspection_results {
        bigint id PK
        bigint inspection_id FK
    }
    service_packages {
        bigint id PK
        varchar code
        varchar name_ar
    }
    service_package_items {
        bigint id PK
        bigint service_package_id FK
        bigint service_id FK
        bigint part_id FK
        decimal qty
    }
    inspections {
        bigint id PK
        bigint job_card_id FK
        bigint inspection_template_id FK
        bigint technician_id FK
        varchar status
    }
    inspection_findings {
        bigint id PK
        bigint inspection_id FK
        bigint service_id FK
        bigint part_id FK
        decimal qty
    }
    quotations {
        bigint id PK
        varchar number
        varchar type
        bigint parent_id FK
        bigint job_card_id FK
        bigint inspection_id FK
        bigint customer_id FK
        bigint vehicle_id FK
        varchar status
    }
    quotation_items {
        bigint id PK
        bigint quotation_id FK
        bigint service_id FK
        bigint part_id FK
        decimal qty
    }
    quality_checks {
        bigint id PK
        bigint job_card_id FK
        bigint inspector_id FK
    }
    workshop_bays {
        bigint id PK
        bigint department_id FK
        varchar code
        varchar name
        varchar type
    }
    vehicle_gate_logs {
        bigint id PK
        bigint vehicle_id FK
        bigint job_card_id FK
        bigint user_id FK
    }
    appointments {
        bigint id PK
        varchar number
        bigint customer_id FK
        bigint vehicle_id FK
        bigint department_id FK
        bigint service_id FK
        bigint technician_id FK
        bigint bay_id FK
        varchar status
    }
    workshop_bays ||--o{ appointments : "bay_id"
    departments ||--o{ appointments : "department_id"
    job_cards ||--o{ appointments : "job_card_id"
    services ||--o{ appointments : "service_id"
    inspection_templates ||--o{ inspections : "inspection_template_id"
    job_cards ||--o{ inspections : "job_card_id"
    inspections ||--o{ inspection_findings : "inspection_id"
    services ||--o{ inspection_findings : "service_id"
    inspections ||--o{ inspection_results : "inspection_id"
    workshop_bays ||--o{ job_cards : "bay_id"
    departments ||--o{ job_cards : "department_id"
    job_cards ||--o{ job_card_notes : "job_card_id"
    job_cards ||--o{ job_card_parts : "job_card_id"
    quotation_items ||--o{ job_card_parts : "quotation_item_id"
    job_cards ||--o{ job_card_status_histories : "job_card_id"
    departments ||--o{ job_card_tasks : "department_id"
    job_cards ||--o{ job_card_tasks : "job_card_id"
    quotation_items ||--o{ job_card_tasks : "quotation_item_id"
    services ||--o{ job_card_tasks : "service_id"
    job_card_tasks ||--o{ job_card_time_logs : "job_card_task_id"
    job_cards ||--o{ quality_checks : "job_card_id"
    inspections ||--o{ quotations : "inspection_id"
    job_cards ||--o{ quotations : "job_card_id"
    quotations ||--o{ quotation_items : "quotation_id"
    services ||--o{ quotation_items : "service_id"
    departments ||--o{ services : "department_id"
    services ||--o{ service_package_items : "service_id"
    service_packages ||--o{ service_package_items : "service_package_id"
    services ||--o{ service_vehicle_prices : "service_id"
    job_cards ||--o{ vehicle_check_ins : "job_card_id"
    job_cards ||--o{ vehicle_gate_logs : "job_card_id"
    departments ||--o{ workshop_bays : "department_id"
```

Cross-domain links: `appointments.customer_id` → `customers`, `appointments.vehicle_id` → `vehicles`, `departments.cost_center_id` → `cost_centers`, `inspection_findings.part_id` → `parts`, `job_cards.customer_id` → `customers`, `job_cards.fleet_id` → `fleets`, `job_cards.vehicle_id` → `vehicles`, `job_card_parts.part_id` → `parts`, `job_card_parts.warehouse_id` → `warehouses`, `quotations.customer_id` → `customers`, `quotations.vehicle_id` → `vehicles`, `quotation_items.part_id` → `parts`, `service_package_items.part_id` → `parts`, `vehicle_check_ins.customer_id` → `customers`, `vehicle_check_ins.vehicle_id` → `vehicles`, `vehicle_gate_logs.vehicle_id` → `vehicles`.

## Inventory & dismantling

```mermaid
erDiagram
    parts {
        bigint id PK
        varchar sku
        varchar name_ar
        bigint part_category_id FK
        bigint brand_id FK
        bigint default_supplier_id FK
    }
    part_categories {
        bigint id PK
        bigint parent_id FK
        varchar code
        varchar name_ar
    }
    brands {
        bigint id PK
        varchar name
    }
    part_alternatives {
        bigint id PK
        bigint part_id FK
        varchar number
        varchar type
    }
    part_compatibilities {
        bigint id PK
        bigint part_id FK
        bigint vehicle_make_id FK
        bigint vehicle_model_id FK
    }
    warehouses {
        bigint id PK
        varchar code
        varchar name
        varchar type
    }
    warehouse_locations {
        bigint id PK
        bigint warehouse_id FK
        varchar code
        varchar name
    }
    stock_levels {
        bigint id PK
        bigint part_id FK
        bigint warehouse_id FK
        bigint location_id FK
    }
    stock_batches {
        bigint id PK
        bigint part_id FK
        bigint warehouse_id FK
    }
    stock_movements {
        bigint id PK
        bigint warehouse_id FK
        bigint part_id FK
        varchar type
        decimal qty
        bigint user_id FK
    }
    stock_transfers {
        bigint id PK
        varchar number
        bigint from_warehouse_id FK
        bigint to_warehouse_id FK
        varchar status
        bigint received_by FK
    }
    stock_transfer_items {
        bigint id PK
        bigint stock_transfer_id FK
        bigint part_id FK
        decimal qty
    }
    stock_counts {
        bigint id PK
        varchar number
        bigint warehouse_id FK
        varchar type
        varchar status
        bigint posted_by FK
    }
    stock_adjustments {
        bigint id PK
        varchar number
        bigint warehouse_id FK
        date date
        varchar status
        bigint posted_by FK
    }
    stock_adjustment_items {
        bigint id PK
        bigint stock_adjustment_id FK
        bigint part_id FK
        decimal qty
    }
    stock_count_items {
        bigint id PK
        bigint stock_count_id FK
        bigint part_id FK
    }
    dismantled_vehicles {
        bigint id PK
        varchar number
        varchar vin
        bigint vehicle_make_id FK
        bigint vehicle_model_id FK
        bigint supplier_id FK
        bigint paid_from_bank_account_id FK
        bigint warehouse_id FK
        varchar status
    }
    dismantled_parts {
        bigint id PK
        bigint dismantled_vehicle_id FK
        bigint part_id FK
        varchar name
        bigint warehouse_id FK
        bigint location_id FK
        varchar status
    }
    dismantled_scraps {
        bigint id PK
        bigint dismantled_vehicle_id FK
        date date
        bigint bank_account_id FK
    }
    dismantled_vehicles ||--o{ dismantled_parts : "dismantled_vehicle_id"
    warehouse_locations ||--o{ dismantled_parts : "location_id"
    parts ||--o{ dismantled_parts : "part_id"
    warehouses ||--o{ dismantled_parts : "warehouse_id"
    dismantled_vehicles ||--o{ dismantled_scraps : "dismantled_vehicle_id"
    warehouses ||--o{ dismantled_vehicles : "warehouse_id"
    brands ||--o{ parts : "brand_id"
    part_categories ||--o{ parts : "part_category_id"
    parts ||--o{ part_alternatives : "part_id"
    parts ||--o{ part_compatibilities : "part_id"
    warehouses ||--o{ stock_adjustments : "warehouse_id"
    parts ||--o{ stock_adjustment_items : "part_id"
    stock_adjustments ||--o{ stock_adjustment_items : "stock_adjustment_id"
    parts ||--o{ stock_batches : "part_id"
    warehouses ||--o{ stock_batches : "warehouse_id"
    warehouses ||--o{ stock_counts : "warehouse_id"
    parts ||--o{ stock_count_items : "part_id"
    stock_counts ||--o{ stock_count_items : "stock_count_id"
    warehouse_locations ||--o{ stock_levels : "location_id"
    parts ||--o{ stock_levels : "part_id"
    warehouses ||--o{ stock_levels : "warehouse_id"
    parts ||--o{ stock_movements : "part_id"
    warehouses ||--o{ stock_movements : "warehouse_id"
    warehouses ||--o{ stock_transfers : "from_warehouse_id"
    warehouses ||--o{ stock_transfers : "to_warehouse_id"
    parts ||--o{ stock_transfer_items : "part_id"
    stock_transfers ||--o{ stock_transfer_items : "stock_transfer_id"
    warehouses ||--o{ warehouse_locations : "warehouse_id"
```

Cross-domain links: `dismantled_scraps.bank_account_id` → `bank_accounts`, `dismantled_vehicles.paid_from_bank_account_id` → `bank_accounts`, `dismantled_vehicles.supplier_id` → `suppliers`, `dismantled_vehicles.vehicle_make_id` → `vehicle_makes`, `dismantled_vehicles.vehicle_model_id` → `vehicle_models`, `parts.default_supplier_id` → `suppliers`, `part_compatibilities.vehicle_make_id` → `vehicle_makes`, `part_compatibilities.vehicle_model_id` → `vehicle_models`.

## Purchasing

```mermaid
erDiagram
    suppliers {
        bigint id PK
        varchar code
        varchar name
    }
    purchase_requests {
        bigint id PK
        varchar number
        bigint requested_by FK
        bigint job_card_id FK
        varchar status
        bigint approved_by FK
    }
    purchase_request_items {
        bigint id PK
        bigint purchase_request_id FK
        bigint part_id FK
        decimal qty
    }
    rfqs {
        bigint id PK
        varchar number
        bigint purchase_request_id FK
        varchar status
        bigint awarded_supplier_id FK
    }
    rfq_items {
        bigint id PK
        bigint rfq_id FK
        bigint part_id FK
        decimal qty
    }
    rfq_quotes {
        bigint id PK
        bigint rfq_supplier_id FK
        bigint rfq_item_id FK
    }
    part_supplier {
        bigint id PK
        bigint part_id FK
        bigint supplier_id FK
    }
    supplier_evaluations {
        bigint id PK
        bigint supplier_id FK
        bigint evaluated_by FK
    }
    purchase_orders {
        bigint id PK
        varchar number
        bigint supplier_id FK
        bigint warehouse_id FK
        bigint rfq_id FK
        bigint purchase_request_id FK
        varchar status
        decimal total
        bigint approved_by FK
    }
    purchase_order_items {
        bigint id PK
        bigint purchase_order_id FK
        bigint part_id FK
        decimal qty
    }
    goods_receipts {
        bigint id PK
        varchar number
        bigint purchase_order_id FK
        bigint supplier_id FK
        bigint warehouse_id FK
        varchar status
        decimal total
        bigint posted_by FK
    }
    goods_receipt_items {
        bigint id PK
        bigint goods_receipt_id FK
        bigint purchase_order_item_id FK
        bigint part_id FK
        decimal qty
        bigint location_id FK
    }
    supplier_invoices {
        bigint id PK
        varchar number
        bigint supplier_id FK
        bigint purchase_order_id FK
        bigint goods_receipt_id FK
        decimal total
        varchar status
        bigint posted_by FK
    }
    supplier_invoice_items {
        bigint id PK
        bigint supplier_invoice_id FK
        bigint part_id FK
        bigint account_id FK
        decimal qty
    }
    landed_costs {
        bigint id PK
        varchar number
        bigint goods_receipt_id FK
        date date
        varchar status
        decimal total
        bigint supplier_id FK
    }
    landed_cost_lines {
        bigint id PK
        bigint landed_cost_id FK
        varchar type
    }
    purchase_returns {
        bigint id PK
        varchar number
        bigint supplier_id FK
        bigint goods_receipt_id FK
        bigint warehouse_id FK
        date date
        varchar status
        decimal total
    }
    purchase_return_items {
        bigint id PK
        bigint purchase_return_id FK
        bigint part_id FK
        bigint goods_receipt_item_id FK
        decimal qty
    }
    purchase_orders ||--o{ goods_receipts : "purchase_order_id"
    suppliers ||--o{ goods_receipts : "supplier_id"
    goods_receipts ||--o{ goods_receipt_items : "goods_receipt_id"
    purchase_order_items ||--o{ goods_receipt_items : "purchase_order_item_id"
    goods_receipts ||--o{ landed_costs : "goods_receipt_id"
    suppliers ||--o{ landed_costs : "supplier_id"
    landed_costs ||--o{ landed_cost_lines : "landed_cost_id"
    suppliers ||--o{ part_supplier : "supplier_id"
    purchase_requests ||--o{ purchase_orders : "purchase_request_id"
    rfqs ||--o{ purchase_orders : "rfq_id"
    suppliers ||--o{ purchase_orders : "supplier_id"
    purchase_orders ||--o{ purchase_order_items : "purchase_order_id"
    purchase_requests ||--o{ purchase_request_items : "purchase_request_id"
    goods_receipts ||--o{ purchase_returns : "goods_receipt_id"
    suppliers ||--o{ purchase_returns : "supplier_id"
    goods_receipt_items ||--o{ purchase_return_items : "goods_receipt_item_id"
    purchase_returns ||--o{ purchase_return_items : "purchase_return_id"
    suppliers ||--o{ rfqs : "awarded_supplier_id"
    purchase_requests ||--o{ rfqs : "purchase_request_id"
    rfqs ||--o{ rfq_items : "rfq_id"
    rfq_items ||--o{ rfq_quotes : "rfq_item_id"
    suppliers ||--o{ supplier_evaluations : "supplier_id"
    goods_receipts ||--o{ supplier_invoices : "goods_receipt_id"
    purchase_orders ||--o{ supplier_invoices : "purchase_order_id"
    suppliers ||--o{ supplier_invoices : "supplier_id"
    supplier_invoices ||--o{ supplier_invoice_items : "supplier_invoice_id"
```

Cross-domain links: `goods_receipts.warehouse_id` → `warehouses`, `goods_receipt_items.location_id` → `warehouse_locations`, `goods_receipt_items.part_id` → `parts`, `part_supplier.part_id` → `parts`, `purchase_orders.warehouse_id` → `warehouses`, `purchase_order_items.part_id` → `parts`, `purchase_requests.job_card_id` → `job_cards`, `purchase_request_items.part_id` → `parts`, `purchase_returns.warehouse_id` → `warehouses`, `purchase_return_items.part_id` → `parts`, `rfq_items.part_id` → `parts`, `rfq_quotes.rfq_supplier_id` → `rfq_suppliers`, `supplier_invoice_items.account_id` → `accounts`, `supplier_invoice_items.part_id` → `parts`.

## Sales & POS

```mermaid
erDiagram
    invoices {
        bigint id PK
        varchar number
        varchar type
        bigint customer_id FK
        bigint job_card_id FK
        bigint pos_session_id FK
        bigint fleet_id FK
        bigint warehouse_id FK
        varchar status
    }
    invoice_items {
        bigint id PK
        bigint invoice_id FK
        bigint part_id FK
        bigint service_id FK
        bigint warehouse_id FK
        decimal qty
    }
    payments {
        bigint id PK
        varchar number
        bigint bank_account_id FK
        date date
        varchar status
        bigint pos_session_id FK
        bigint counter_account_id FK
    }
    payment_allocations {
        bigint id PK
        bigint payment_id FK
    }
    credit_notes {
        bigint id PK
        varchar number
        bigint invoice_id FK
        bigint customer_id FK
        date date
        bigint warehouse_id FK
        varchar status
        decimal total
        bigint posted_by FK
    }
    credit_note_items {
        bigint id PK
        bigint credit_note_id FK
        bigint invoice_item_id FK
        bigint part_id FK
        decimal qty
    }
    cash_registers {
        bigint id PK
        varchar name
        bigint bank_account_id FK
        bigint warehouse_id FK
    }
    pos_sessions {
        bigint id PK
        bigint cash_register_id FK
        bigint user_id FK
        varchar number
        varchar status
    }
    sales_orders {
        bigint id PK
        varchar number
        bigint customer_id FK
        bigint quotation_id FK
        bigint warehouse_id FK
        varchar status
        decimal total
    }
    warranties {
        bigint id PK
        bigint invoice_id FK
        bigint invoice_item_id FK
        bigint job_card_id FK
        bigint customer_id FK
        bigint vehicle_id FK
        bigint part_id FK
        bigint service_id FK
        varchar status
    }
    warranty_claims {
        bigint id PK
        varchar number
        bigint warranty_id FK
        varchar status
        bigint rework_job_card_id FK
    }
    insurance_claims {
        bigint id PK
        varchar number
        bigint insurance_company_id FK
        bigint customer_id FK
        bigint vehicle_id FK
        bigint job_card_id FK
        varchar status
    }
    store_orders {
        bigint id PK
        varchar number
        bigint customer_id FK
        bigint coupon_id FK
        decimal total
        varchar status
        bigint invoice_id FK
        bigint warehouse_id FK
    }
    store_order_items {
        bigint id PK
        bigint store_order_id FK
        bigint part_id FK
        decimal qty
    }
    online_payments {
        bigint id PK
        varchar status
    }
    coupons {
        bigint id PK
        varchar code
        varchar type
    }
    commissions {
        bigint id PK
        bigint user_id FK
        varchar type
        varchar status
    }
    sales_order_items {
        bigint id PK
        bigint sales_order_id FK
        bigint part_id FK
        bigint service_id FK
        decimal qty
    }
    fleet_approvals {
        bigint id PK
        bigint fleet_id FK
        bigint job_card_id FK
        bigint quotation_id FK
        varchar status
    }
    equipment {
        bigint id PK
        varchar code
        varchar name
        bigint custodian_employee_id FK
        varchar status
        bigint fixed_asset_id FK
    }
    equipment_logs {
        bigint id PK
        bigint equipment_id FK
        varchar type
        date date
    }
    invoices ||--o{ credit_notes : "invoice_id"
    credit_notes ||--o{ credit_note_items : "credit_note_id"
    invoice_items ||--o{ credit_note_items : "invoice_item_id"
    equipment ||--o{ equipment_logs : "equipment_id"
    pos_sessions ||--o{ invoices : "pos_session_id"
    invoices ||--o{ invoice_items : "invoice_id"
    pos_sessions ||--o{ payments : "pos_session_id"
    payments ||--o{ payment_allocations : "payment_id"
    cash_registers ||--o{ pos_sessions : "cash_register_id"
    sales_orders ||--o{ sales_order_items : "sales_order_id"
    coupons ||--o{ store_orders : "coupon_id"
    invoices ||--o{ store_orders : "invoice_id"
    store_orders ||--o{ store_order_items : "store_order_id"
    invoices ||--o{ warranties : "invoice_id"
    invoice_items ||--o{ warranties : "invoice_item_id"
    warranties ||--o{ warranty_claims : "warranty_id"
```

Cross-domain links: `cash_registers.bank_account_id` → `bank_accounts`, `cash_registers.warehouse_id` → `warehouses`, `credit_notes.customer_id` → `customers`, `credit_notes.warehouse_id` → `warehouses`, `credit_note_items.part_id` → `parts`, `equipment.custodian_employee_id` → `employees`, `equipment.fixed_asset_id` → `fixed_assets`, `fleet_approvals.fleet_id` → `fleets`, `fleet_approvals.job_card_id` → `job_cards`, `fleet_approvals.quotation_id` → `quotations`, `insurance_claims.customer_id` → `customers`, `insurance_claims.insurance_company_id` → `insurance_companies`, `insurance_claims.job_card_id` → `job_cards`, `insurance_claims.vehicle_id` → `vehicles`, `invoices.customer_id` → `customers`, `invoices.fleet_id` → `fleets`, `invoices.job_card_id` → `job_cards`, `invoices.warehouse_id` → `warehouses`, `invoice_items.part_id` → `parts`, `invoice_items.service_id` → `services`, `invoice_items.warehouse_id` → `warehouses`, `payments.bank_account_id` → `bank_accounts`, `payments.counter_account_id` → `accounts`, `sales_orders.customer_id` → `customers`, `sales_orders.quotation_id` → `quotations`, `sales_orders.warehouse_id` → `warehouses`, `sales_order_items.part_id` → `parts`, `sales_order_items.service_id` → `services`, `store_orders.customer_id` → `customers`, `store_orders.warehouse_id` → `warehouses`, `store_order_items.part_id` → `parts`, `warranties.customer_id` → `customers`, `warranties.job_card_id` → `job_cards`, `warranties.part_id` → `parts`, `warranties.service_id` → `services`, `warranties.supplier_id` → `suppliers`, `warranties.vehicle_id` → `vehicles`, `warranty_claims.rework_job_card_id` → `job_cards`.

## Accounting

```mermaid
erDiagram
    accounts {
        bigint id PK
        bigint parent_id FK
        varchar code
        varchar name_ar
        varchar type
    }
    fiscal_years {
        bigint id PK
        varchar name
        varchar status
        bigint closed_by FK
    }
    fiscal_periods {
        bigint id PK
        bigint fiscal_year_id FK
        varchar name
        varchar status
        bigint closed_by FK
    }
    journal_entries {
        bigint id PK
        varchar number
        date date
        varchar status
        bigint posted_by FK
    }
    journal_lines {
        bigint id PK
        bigint journal_entry_id FK
        bigint account_id FK
        bigint cost_center_id FK
    }
    bank_accounts {
        bigint id PK
        bigint account_id FK
        varchar type
        varchar name
    }
    bank_reconciliations {
        bigint id PK
        bigint bank_account_id FK
        varchar status
    }
    expenses {
        bigint id PK
        varchar number
        date date
        bigint account_id FK
        bigint bank_account_id FK
        bigint supplier_id FK
        bigint cost_center_id FK
        decimal total
        varchar status
    }
    fund_transfers {
        bigint id PK
        varchar number
        date date
        bigint from_bank_account_id FK
        bigint to_bank_account_id FK
        varchar status
    }
    fixed_assets {
        bigint id PK
        varchar code
        varchar name
        bigint asset_account_id FK
        bigint accumulated_account_id FK
        bigint expense_account_id FK
        bigint cost_center_id FK
        varchar status
    }
    asset_depreciations {
        bigint id PK
        bigint fixed_asset_id FK
    }
    budgets {
        bigint id PK
        bigint fiscal_year_id FK
        varchar name
        varchar status
    }
    budget_lines {
        bigint id PK
        bigint budget_id FK
        bigint account_id FK
        bigint cost_center_id FK
    }
    tax_rates {
        bigint id PK
        varchar name
    }
    fixed_assets ||--o{ asset_depreciations : "fixed_asset_id"
    accounts ||--o{ bank_accounts : "account_id"
    bank_accounts ||--o{ bank_reconciliations : "bank_account_id"
    fiscal_years ||--o{ budgets : "fiscal_year_id"
    accounts ||--o{ budget_lines : "account_id"
    budgets ||--o{ budget_lines : "budget_id"
    accounts ||--o{ expenses : "account_id"
    bank_accounts ||--o{ expenses : "bank_account_id"
    fiscal_years ||--o{ fiscal_periods : "fiscal_year_id"
    accounts ||--o{ fixed_assets : "accumulated_account_id"
    accounts ||--o{ fixed_assets : "asset_account_id"
    accounts ||--o{ fixed_assets : "expense_account_id"
    bank_accounts ||--o{ fund_transfers : "from_bank_account_id"
    bank_accounts ||--o{ fund_transfers : "to_bank_account_id"
    accounts ||--o{ journal_lines : "account_id"
    journal_entries ||--o{ journal_lines : "journal_entry_id"
```

Cross-domain links: `budget_lines.cost_center_id` → `cost_centers`, `expenses.cost_center_id` → `cost_centers`, `expenses.supplier_id` → `suppliers`, `fixed_assets.cost_center_id` → `cost_centers`, `journal_lines.cost_center_id` → `cost_centers`.

## HR & payroll

```mermaid
erDiagram
    employees {
        bigint id PK
        bigint user_id FK
        varchar code
        varchar name
        bigint hr_department_id FK
        bigint job_title_id FK
        varchar status
    }
    hr_departments {
        bigint id PK
        varchar name
        bigint manager_id FK
    }
    job_titles {
        bigint id PK
        varchar name
    }
    employee_contracts {
        bigint id PK
        bigint employee_id FK
        varchar type
        varchar status
    }
    attendances {
        bigint id PK
        bigint employee_id FK
        date date
        varchar status
    }
    leave_types {
        bigint id PK
        varchar name
    }
    leave_requests {
        bigint id PK
        bigint employee_id FK
        bigint leave_type_id FK
        varchar status
        bigint approved_by FK
    }
    employee_loans {
        bigint id PK
        bigint employee_id FK
        date date
        varchar status
    }
    employee_adjustments {
        bigint id PK
        bigint employee_id FK
        varchar type
        date date
    }
    payrolls {
        bigint id PK
        varchar number
        varchar status
        bigint approved_by FK
    }
    payroll_items {
        bigint id PK
        bigint payroll_id FK
        bigint employee_id FK
    }
    performance_reviews {
        bigint id PK
        bigint employee_id FK
        bigint reviewer_id FK
    }
    employees ||--o{ attendances : "employee_id"
    hr_departments ||--o{ employees : "hr_department_id"
    job_titles ||--o{ employees : "job_title_id"
    employees ||--o{ employee_adjustments : "employee_id"
    employees ||--o{ employee_contracts : "employee_id"
    employees ||--o{ employee_loans : "employee_id"
    employees ||--o{ leave_requests : "employee_id"
    leave_types ||--o{ leave_requests : "leave_type_id"
    employees ||--o{ payroll_items : "employee_id"
    payrolls ||--o{ payroll_items : "payroll_id"
    employees ||--o{ performance_reviews : "employee_id"
```

## Other tables

`department_user`, `idempotency_keys`, `inspection_template_items`, `model_has_permissions`, `model_has_roles`, `notifications`, `notification_logs`, `permissions`, `promotions`, `rfq_suppliers`, `roles`, `role_has_permissions`, `service_ratings`, `warehouse_user`.

Framework tables: `migrations`, `sessions`, `cache`, `jobs`, `failed_jobs`, `password_reset_tokens`, `personal_access_tokens` (Sanctum), and Spatie permission tables (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, teams keyed by `company_id`).
