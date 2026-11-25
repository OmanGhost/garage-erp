# Changelog

All notable changes to Garage ERP. Versions follow [Semantic Versioning](https://semver.org):
**major** for breaking changes, **minor** for new features, **patch** for fixes.
The current version is stored in the `VERSION` file and shown in the footer of every page.
Create a new entry with `php artisan erp:release patch|minor|major "what changed"`.

## [1.4.1] - 2026-11-26
- Release command writes the VERSION file with Unix line endings

## [1.4.0] - 2026-11-26
- Fund transfers: new screen showing the current balance of both accounts as soon as they are selected, and each balance after the transfer
- Live checks: same account cannot be chosen twice and an amount above the available balance is flagged; swap button and quick amounts (full balance, 50%, 25%)
- Save as draft or save & post in one step, with a print voucher button right after posting
- Posting a transfer is refused if it exceeds the source account balance

## [1.3.0] - 2026-11-26
- Official government-style vouchers for every money document: receipts, payments, refunds, expenses, fund transfers, employee advances and salary payments
- Double frame, logo and company details in Arabic and English, red serial number, date boxes, amount in rial/baisa boxes and in words (Arabic and English), payment method checkboxes, signature boxes, stamp circle and QR code
- Paper: A5 landscape, or A4 with the original and a copy
- Print voucher buttons on expenses, fund transfers, employee advances and paid payrolls

## [1.2.0] - 2026-11-26
- Version number and build shown in the footer of the staff app, SaaS admin, customer portal and sign-in pages
- `php artisan erp:release` bumps the version and records the change in this changelog; `php artisan erp:version` shows it
- Git tags and GitHub releases for every version
- Fix: the application timezone now follows `APP_TIMEZONE` (Asia/Muscat) instead of UTC for scheduled jobs, commands, the SaaS admin and the public site

## [1.1.0] - 2026-11-26
- SaaS admin: Clients page with search, status filters, plan filter, sorting, KPIs and Excel/CSV export
- Client file: details and contact person, subscription and modules, users (reset password, enable/disable, login history), subscription invoices (mark paid), activity log
- Suspend / activate, extend trial, archive with typed confirmation and restore
- Website trial requests fill the client's source and contact details

## [1.0.0] - 2026-11-26
- First release: multi-company, multi-branch workshop and spare parts ERP for Oman (Laravel 12, Arabic/English, PWA)
- Workshop: reception, inspections, quotations with online customer approval, job cards with a step-by-step workflow guide, Kanban board, quality inspection
- Inventory: new and used parts, FIFO / weighted average, transfers, counts, reorder forecast; vehicle dismantling with cost allocation
- Purchasing, POS, VAT invoices, credit notes valued at the discounted price, payments
- Double-entry accounting with cost centers, financial statements, VAT report, fixed assets, budgets
- HR and payroll, insurance claims, fleets, warranty, appointments, customer portal, online store
- Public website with plans, trial requests and SEO; REST API; reports with PDF / Excel / CSV export
- Top navigation menu, automated tests and documentation
