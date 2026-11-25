# Installation Guide

This guide installs Garage ERP on a developer machine or a fresh server.
For production hardening (Nginx, Supervisor, Redis, Reverb, SSL, cron) see [DEPLOYMENT.md](DEPLOYMENT.md).

## 1. Requirements

| Component | Version | Notes |
|---|---|---|
| PHP | 8.3 or newer | extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip` (`redis` optional) |
| MySQL / MariaDB | MySQL 8.0+ or MariaDB 10.6+ | `utf8mb4`, InnoDB |
| Composer | 2.x | |
| Redis | 6+ | optional; recommended for cache and queues in production |
| `mysqldump` / `mysql` | client binaries | used by backup / restore |

No Node.js build is needed: CSS/JS and vendor libraries (Bootstrap 5 + RTL, Bootstrap Icons, Chart.js, SortableJS, Laravel Echo) are already in `public/`.

No paid external service is required. SMS, WhatsApp, online payment and VIN decoding are optional integrations that stay disabled until configured.

## 2. Get the code and dependencies

```bash
cd /var/www
# copy or clone the project into garage-erp/
cd garage-erp
composer install --no-dev --optimize-autoloader     # use plain `composer install` for development
cp .env.example .env
php artisan key:generate
```

## 3. Create the database

```sql
CREATE DATABASE garage_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'garage_erp'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON garage_erp.* TO 'garage_erp'@'localhost';
FLUSH PRIVILEGES;
```

Put the credentials in `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

## 4. Configure `.env`

The important keys (all documented inside `.env.example`):

| Key | Example | Purpose |
|---|---|---|
| `APP_URL` | `https://erp.example.om` | Public URL (used in QR codes, approval links, emails) |
| `APP_LOCALE` | `ar` | Default language (`ar` or `en`) |
| `APP_TIMEZONE` | `Asia/Muscat` | Server timezone |
| `CACHE_STORE`, `QUEUE_CONNECTION` | `redis` or `database` | Use `database` when Redis is not available |
| `BROADCAST_CONNECTION` | `reverb` or `log` | Real-time Kanban / notifications. `log` disables live updates |
| `MAIL_*` | SMTP | Password resets, quotations, invoices |
| `MYSQLDUMP_PATH`, `MYSQL_PATH` | `mysqldump` | Full paths if not on PATH |
| `BACKUP_KEEP_DAYS` | `14` | Backup retention |
| `SIEM_*` | | Optional syslog forwarding of security events |

## 5. Install

```bash
php artisan erp:install --admin=owner@example.om --password='Choose-A-Strong-Pass1'
# add --demo to load the demo company (do NOT use --demo in production)
php artisan storage:link
```

`erp:install` runs migrations, syncs permissions and roles, loads reference data (subscription plans, ~38 vehicle makes with models), and creates the platform super admin.

Equivalent manual steps:

```bash
php artisan migrate --force
php artisan db:seed --force      # outside production this also creates the demo company
```

## 6. Run (development)

```bash
php artisan serve                 # http://127.0.0.1:8000
php artisan queue:work            # notifications, webhooks, SMS/WhatsApp
php artisan reverb:start          # only if BROADCAST_CONNECTION=reverb
php artisan schedule:work         # daily reminders, backups, depreciation
```

On Windows with XAMPP, point a virtual host to the `public/` folder, or use `php artisan serve`.

## 7. First login

| Who | Email | Password |
|---|---|---|
| Platform super admin | the email passed to `erp:install` | the password passed to `erp:install` |

With demo data (`--demo` or `db:seed` outside production):

| Role | Email | Password |
|---|---|---|
| Super admin (platform) | `superadmin@garage-erp.test` | `ChangeMe@2026` |
| Company admin | `admin@alnoor-auto.om` | `Demo@12345` |
| Other roles | `manager@`, `branch@`, `workshop@`, `reception@`, `tech1@`…`tech3@`, `qc@`, `store@`, `buyer@`, `sales@`, `cashier@`, `accountant@`, `hr@`, `auditor@` + `alnoor-auto.om` | `Demo@12345` |
| Customer portal | `customer@alnoor-auto.om` | `Demo@12345` |

Change every default password immediately in any environment reachable from a network.

## 8. Create the first real company

Log in as the super admin → **SaaS administration → New tenant**. Provisioning creates automatically:

- main branch, main and scrap warehouses
- full chart of accounts with system accounts (cash, AR, AP, inventory, VAT input/output, WIP…)
- current fiscal year with 12 monthly periods
- cash box, POS drawer, bank and card-clearing accounts, one POS register
- walk-in customer, 14 workshop departments, inspection checklist template
- bilingual notification templates
- the company admin user with the **Company Admin** role

## 9. Run the tests

```bash
php artisan test
```

Tests run on an in-memory SQLite database (see `phpunit.xml`) and need the `pdo_sqlite` extension. They cover money math, stock valuation (weighted average and FIFO), negative-stock prevention, reservations, transfers, invoice posting and GL entries, payments, credit notes, credit limits, closed periods, POS split payments and cash over/short, the job-card workflow and its guards, purchasing (PO → GRN → supplier invoice, landed costs), dismantling cost allocation, tenant isolation (web and API), role restrictions, portal isolation, backup access, duplicate-submission protection, login history, the REST API, the main web forms (reception check-in, invoices, payments, purchase orders, stock adjustments, generic CRUD with exports) and the Livewire POS terminal and Kanban board.

A page smoke test against the demo database is also available:

```bash
php tests/smoke.php admin@alnoor-auto.om     # requests every GET page and reports errors
```
