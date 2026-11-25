# Garage ERP: Automotive Workshop & Spare Parts ERP

نظام متكامل لإدارة ورش صيانة السيارات ومحلات قطع الغيار (الجديدة والمستعملة)، متعدد الشركات والفروع، عربي/إنجليزي، مهيأ لسلطنة عُمان (الريال العماني بثلاث خانات عشرية، ضريبة القيمة المضافة 5%، +968، توقيت مسقط، فواتير ضريبية ثنائية اللغة).

A multi-company, multi-branch ERP for automotive workshops and spare-parts businesses (new and used parts), built on Laravel 12 as a modular monolith with a REST API, an installable PWA, Arabic (RTL) and English (LTR), and dark/light themes.

## Modules

| Area | Highlights |
|---|---|
| Platform | SaaS tenants, plans and module limits, subscriptions, strict tenant isolation, branches and cost centres |
| CRM & vehicles | customers, groups, credit limits, fleets, insurance companies, vehicles with ownership history, accidents, VIN decoding, complaints, service reminders |
| Workshop | reception with photos, damage map and signature; inspections with DTC codes; versioned quotations with customer approval link; job cards with a 13-status workflow; Kanban board (real time); task time tracking; parts issue/return; QC; gate log; bays; departments; services with per-vehicle-type prices; packages; promotions |
| Inventory | new/used/refurbished parts, alternatives and vehicle compatibility, multi-warehouse, locations, batches, serials and expiry, FIFO or weighted average, negative-stock prevention, reservations, transfers, counts, adjustments, reorder suggestions with demand forecast, barcode labels |
| Dismantling | vehicles bought for parts, cost allocation to extracted parts, scrap sales, profitability |
| Purchasing | requests → RFQ (quote comparison) → PO (approval limits) → GRN → supplier invoice, landed costs, returns, supplier evaluation |
| Sales & POS | tax invoices (QR, bilingual), credit notes, receipts and payment vouchers, split payments, POS with sessions, hold/recall and cash over/short, sales orders, commissions, online store and coupons |
| Accounting | double-entry with automatic postings, reversals, fiscal periods and year-end close, cash/bank, reconciliation, expenses, fund transfers, fixed assets and depreciation, budgets, financial statements, aging, VAT report |
| HR & payroll | employees and documents, attendance (device API), leave, loans, adjustments, payroll, payslips, performance reviews |
| Warranty, insurance, fleets | automatic warranties, warranty claims and rework, insurance claims with deductible split, fleet approvals and monthly consolidated invoicing |
| Appointments | capacity-aware booking, holidays, reminders, no-shows, portal booking |
| Reports | 25 operational reports plus financial statements, all exportable to PDF / Excel / CSV / print; every list is searchable and exportable |
| Customer portal | vehicles, live repair status, quotation approval, invoices (PDF), statement, warranties, booking, complaints, ratings |
| Public website & SEO | bilingual landing page (features, live plans & prices, FAQ), free-trial requests with one-click tenant provisioning, editable SEO settings, canonical/hreflang, Open Graph, schema.org data, sitemap.xml, robots.txt, noindex on all application pages |
| Security | RBAC (16 roles + custom), 2FA (TOTP), audit trail, login history, idempotent forms and API, posted-document locking, rate limiting, security headers, optional SIEM syslog |

## Quick start

```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_* in .env (MySQL 8 / MariaDB 10.6+)
php artisan erp:install --admin=owner@example.om --password='Strong-Pass-123' --demo
php artisan serve
```

Demo logins (password `Demo@12345`): `admin@alnoor-auto.om`, plus one user per role (`reception@`, `tech1@`, `store@`, `cashier@`, `accountant@`… `@alnoor-auto.om`) and the portal customer `customer@alnoor-auto.om`. Platform super admin: `superadmin@garage-erp.test` / `ChangeMe@2026`. Change them before exposing the system to any network.

## Documentation

| Document | Audience |
|---|---|
| [docs/INSTALL.md](docs/INSTALL.md) | installation and first run |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Ubuntu (Nginx, PHP-FPM, Redis, Supervisor, Reverb, cron) and cPanel |
| [docs/BACKUP_RESTORE.md](docs/BACKUP_RESTORE.md) | backups, off-site copies, restore |
| [docs/API.md](docs/API.md) | REST API v1 and webhooks |
| [docs/ERD.md](docs/ERD.md) | database diagrams (generated from the schema) |
| [docs/USER_MANUAL.md](docs/USER_MANUAL.md) | دليل المستخدم (عربي) |
| [docs/ADMIN_MANUAL.md](docs/ADMIN_MANUAL.md) | دليل مدير النظام (عربي) |

## Architecture

```
app/
  Modules/<Module>/          Core, CRM, Vehicles, Workshop, Inventory, Dismantling, Purchasing,
    Models/ Services/         Sales, Accounting, HR, Insurance, Fleet, Warranty, Assets,
    Http/Controllers/         Appointments, Reports, Portal, Store, Saas
    Crud/Resources.php       declarative CRUD screens (list, search, filters, export, forms)
    Database/Migrations/
    Resources/views/
  Support/                   Money (bcmath), Totals, DocumentNumber, Tenancy, Audit, Crud engine, Export
  Http/Middleware/           tenant context, locale, module & subscription checks, idempotency, security headers
```

- **Business logic lives in services** (`InvoiceService`, `StockService`, `PostingService`, `JobCardService`…) that run in DB transactions with row locks; controllers, Livewire components, the API and the seeders all call the same services.
- **Money** is calculated with bcmath strings (3 decimals, half-up), never floats.
- **Tenancy**: global scopes on `company_id` (and allowed branches) on every business model; route model binding runs after the tenant is resolved; Spatie permission teams per company.
- **Accounting**: every financial document posts a balanced journal entry idempotently (one per source and purpose); posted documents are immutable.

## Tests

```bash
php artisan test                         # 51 feature and unit tests (SQLite in memory)
php tests/smoke.php admin@alnoor-auto.om # requests every page against the demo database
```

## Requirements

PHP 8.3+, MySQL 8 / MariaDB 10.6+, Composer 2. Optional: Redis (cache/queue), Supervisor, Laravel Reverb (bundled). No paid external services are required.

## Versions & releases

The current version is in [`VERSION`](VERSION) ([semantic versioning](https://semver.org)) and is shown, with the build (git commit), in the footer of every page. Changes are listed in [CHANGELOG.md](CHANGELOG.md).

```bash
php artisan erp:version                                   # Garage ERP 1.2.0 (a1b2c3d)
php artisan erp:release patch "Fixed ..."                 # fixes          1.2.0 → 1.2.1
php artisan erp:release minor "New feature ..."           # new features   1.2.0 → 1.3.0
php artisan erp:release major "Breaking change ..."       # breaking       1.2.0 → 2.0.0
git commit -am "Release v1.3.0" && git tag v1.3.0 && git push --follow-tags
```

On servers deployed without the `.git` folder, set `APP_BUILD` (for example the CI build number) to show a build identifier.
