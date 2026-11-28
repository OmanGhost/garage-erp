# Changelog

All notable changes to Garage ERP. Versions follow [Semantic Versioning](https://semver.org):
**major** for breaking changes, **minor** for new features, **patch** for fixes.
The current version is stored in the `VERSION` file and shown in the footer of every page.
Create a new entry with `php artisan erp:release patch|minor|major "what changed"`.

## [1.25.3] - 2026-11-28
- Employee photo; card commission per terminal with a maximum per transaction
- Employee photo upload (images only), shown as an avatar in the employee list
- Card commission on each card terminal / bank account: percentage (e.g. 1.20 %) and maximum per transaction (e.g. 10.000 OMR); every card receipt books the net to the bank account (matching the bank deposit), the commission to Bank charges and the full amount to the customer; fee and net kept on the receipt, shown in the receipts list and the POS session-close summary; cancelling the receipt reverses the commission

## [1.25.2] - 2026-11-28
- Fixed editing users (role could not be changed); role change requests with approval and printable decision
- Fix: saving any edit of a user failed with a server error (the password rule object broke the "optional on edit" filter), so roles could not be changed; the edit form now shows the current role
- Role change requests: user, current and requested role, mandatory reason, effective date; one pending request per user; approvers notified, requester notified of the decision
- Decided by a branch manager or higher: never by the requester or the user concerned, a branch manager only for branches they manage, Company Admin / General Manager roles only by a company administrator; rejection needs a reason; approval applies the role at once (audit log)
- Printable A4 request with approved / rejected / pending stamp, reason, decision notes and signatures
- Only a company administrator can still change a role directly from the user form; others are asked to submit a request

## [1.25.1] - 2026-11-28
- POS cash payment popup with notes, change and printed cash received / change
- Paying in cash opens a popup: amount due, amount received with Omani note buttons (100 bz, ½, 1, 5, 10, 20, 50 rial; each click adds), exact amount and quick round amounts, live change to give back or amount still due; Enter confirms and prints, then a large "change to give back" screen; split and card payments keep the normal button
- Cash received and change are stored on the invoice and printed on the 80 mm receipt and the A4 invoice (with the balance due when not fully paid); only the bill is recorded in the drawer

## [1.25.0] - 2026-11-28
- Employee internal financing with installment schedule, and one-click attendance
- Loans: two types — salary advance, and internal financing with a profit rate on the principal (total payable split into equal monthly installments rounded up so the last one is never extra); first deduction month; live repayment plan in the form; terms recalculated on the server
- Loan page: installment schedule (due, received, left, balance after, paid / partly paid / overdue / upcoming), payments received (payroll, by hand, early settlement) split into principal and profit, outstanding principal and profit, record a payment or settle early (refused while an unposted payroll already holds the deduction); voucher prints the financing terms
- Accounting: disbursement Dr employee advances (principal only) / Cr bank; every collection is split cumulatively into principal (Cr employee advances) and profit (Cr new "Employee financing income" account 4108, added to every company); payroll posting allocates loan deductions first and records a payment row per loan; payroll deducts only from the first installment month
- Existing loans keep working: total payable = amount, first month = the month given, the part already repaid shown as an opening payment
- One-click attendance in the top bar for users linked to an active employee: the server decides clock-in or clock-out from the state, records time, IP and source, computes lateness from the branch opening time + grace (status "late"), worked and overtime minutes; refuses a second click within two minutes and a second clock-out the same day; night shifts close the open attendance of the previous day

## [1.24.1] - 2026-11-28
- Redesigned point of sale with a part inquiry panel
- POS search bar (F2) with a "Part inquiry" button, type filter (all / new / used) and "available here only"; results as detailed rows with image, numbers, brand, type, stock here (green / elsewhere / none), price, details and add buttons; parts already in the cart are marked
- Part inquiry panel: price for the level, available here / in all warehouses, sales per month and stock alerts (other warehouse, out of stock with expected supplier date, low stock); identification and warranty; all price levels and what this customer paid last time; cost, margin and last supplier for users who may see reports; stock by warehouse with location and reservations; quantities on order; interchangeable alternatives with stock and an add button; vehicle fitment; sales of 30 / 90 days, last sale and days of cover
- Cart redesign: retail / wholesale buttons, customer chip, warning below the minimum price, click a line to open its inquiry; the inquiry opens the genuine part first when several share an OEM number

## [1.24.0] - 2026-11-28
- Redesigned public website with features, solutions, customers, contact, legal and documentation pages
- Features catalogue (20 features in 6 groups) with a page per feature: what it does, why it matters, plans that include it and related features; mega menu in the header
- Solutions page by business type (independent garages, dealers, parts shops, fleets & insurers, body shops, roadside & memberships)
- Customers page managed from the platform site settings (real customers only; invites visitors to a trial while empty)
- Contact page: messages stored, super admins notified, copied to the sales email, honeypot and rate limit; new "Website messages" inbox in the platform admin
- Terms of use and privacy policy with built-in drafts in Arabic and English, replaceable from site settings (Markdown, HTML stripped)
- Documentation centre: 13 guides per language with search, sidebar, table of contents and previous / next
- New design layer (palette, page headers, footer with legal links), header collapses below 1200 px; sitemap and robots include all new pages; CSS cache follows the release version

## [1.23.0] - 2026-11-28
- Internal fraud & loss prevention: rules for unusual manual discounts (membership, coupon and standing customer / group discounts excluded; insurance and fleet invoices skipped), sales below the cost booked at posting, prices typed below the price list, selling-price cuts, cancelled receipts, returns, deleted held POS sales, stock write-offs without a document, repeated adjustments per user and per part, issued parts not invoiced at delivery, cash shortages / overages (repeat shortages escalate) and after-hours work by staff
- Alerts are checked after the transaction commits (a rolled-back action raises nothing, a failing rule never breaks the operation), grouped by fingerprint with an occurrence counter; medium / high alerts notify reviewers at once, low ones in the daily digest; thresholds editable on the dashboard
- Review with mandatory notes ("justified" / "confirmed misconduct"); nobody can review an alert about their own action; no delete
- Audit log sealed: every new row carries an HMAC of its content, the application refuses to update or delete audit rows, and "Verify audit log" reports rows changed directly in the database (older rows show as written before sealing; deleted rows and direct database access are outside what the application can detect or prevent; rotating APP_KEY invalidates the seals)
- New plan module "Fraud & loss prevention", switched on only for plans that already include every main module

## [1.22.1] - 2026-11-27
- Cash session closing: detailed breakdown and mandatory reason for differences
- The close-session dialog shows invoices and total sales, receipts / refunds / net per payment method (cash drawer vs. card, transfer, points, gift cards) and the expected cash (opening + cash received − cash refunded); the summary is refreshed each time the dialog opens
- The difference is shown live while counting (matches / shortage / overage); a session with a difference cannot be closed without a reason, enforced on the server; the reason is stored, shown on the session report and in the audit log

## [1.22.0] - 2026-11-27
- Subcontracted (outside) repairs: outside work orders linked to the job card (subcontractor, work type, item sent, agreed cost, customer price, expected return, outside warranty); the customer price becomes a job card task needing the usual approval; the customer's part leaves without a stock movement; on return the task is finished, the cost enters job costing and a draft supplier invoice is prepared on the new "Subcontracted work cost" account (5104); list of work out / overdue / under warranty and a printable order
- Outside work tasks are never auto-scheduled, cannot be started, finished or cancelled directly, and the job card cannot become ready / delivered / cancelled while the part is out (no override)
- Roadside assistance: calls (tow, battery, tyre, fuel, lockout, jump start) matched to the customer by phone and plate, driver dispatch with timestamps (assigned, en route, on site, completed), secret customer link to share the GPS location (Google Maps), pricing = base fee + km beyond the included distance + extras, a membership roadside call covers the base fee and is consumed once on completion, the rest billed on a draft service invoice, a tow into the workshop opens a linked job card
- New plan modules "Subcontracted repairs" and "Roadside assistance", switched on only for plans that already include every main module

## [1.21.0] - 2026-11-27
- Maintenance memberships: yearly plans with included services, parts / labour discounts, priority booking and roadside calls; sold with an invoice and active once posted; benefits applied on draft invoices and consumed at posting under lock; membership card (revenue recognised at sale, no deferral)
- Loyalty & rewards: points on posted invoices with silver / gold tiers, redemption as a payment (Dr loyalty liability / Cr receivables), points back when the receipt is cancelled and taken back on returns, inactivity expiry, referral bonus, gift cards (sold to a cash / bank account, spent as payment, breakage on expiry), invoice coupons and single-use personal offers to customers who have not come back
- New system accounts added to every existing company: loyalty points liability, gift cards outstanding, loyalty program expense; payments from points / gift cards have no bank account
- The walk-in / cash customer never earns, redeems or refers; insurance and fleet invoices earn no points; membership benefits are kept on the invoice header so editing a draft cannot skip their consumption
- Gift cards are issued to the main cash or a bank account (not to a POS drawer, whose session cash is counted from receipts)
- The two new modules are switched on automatically only for plans that already include every main module

## [1.20.0] - 2026-11-27
- Advanced stock operations: stock reservations with expiry, kits (assemble / disassemble at cost), consignment stock (booked against GRNI, settled per sale into a supplier invoice, returns to consignor), core charges (refundable deposit line, refund by credit note or forfeit), drop shipping (supplier delivers to the customer, received and invoiced in one step)
- Plans: the new features are modules (workshop planning, vehicle & key tracking, DVI, quality, inventory analytics, advanced stock operations) switched on per plan or per client; plans page redesigned with a feature comparison, descriptions and "new" badges; SaaS dashboard shows feature adoption. Existing plans keep the features they already had

## [1.19.0] - 2026-11-27
- Part cross reference: alternatives linked to catalog parts with brand and quality (OEM, OES, aftermarket, remanufactured, used), interchange groups by shared factory numbers (spaces / dashes ignored), cross reference search with stock and prices
- Inventory analytics: ABC classification by consumption value (saved on parts, refreshed monthly), stock aging buckets, dead stock with tied-up value, demand forecast with cover in months
- Automatic reordering into draft purchase orders per default supplier (nightly when enabled, or on demand), skipping parts already on order
- Supplier price history recorded on every goods receipt (back-filled from past receipts) with price change per supplier
- Cycle counting by ABC class (A 30 days, B 90, C 365) with last-counted date per part

## [1.18.0] - 2026-11-27
- Digital vehicle inspection (DVI): interactive vehicle diagram with colour-coded zones, fault photos per area, green / yellow / red priorities, measurements with OK limits, suggested services, technician and customer signatures, interactive report link where the customer approves or declines each repair, quotation from approved repairs, before / after repair comparison
- Quality management (QMS): dashboard (first-time fix, comebacks, QC pass rate, customer satisfaction, Pareto of causes, recurring warranty failures, supplier quality), nonconformities with root cause analysis, corrective / preventive actions with effectiveness checks, SOPs with review dates, internal audits that raise NCRs, QC checklist templates; a failed QC opens an NCR
- Fix: multi-line lists (checklist templates, vehicle models, audit questions) no longer break Arabic text

## [1.17.0] - 2026-11-27
- Workshop resource planning: day planner per bay / technician, automatic technician and bay scheduling within working hours, no double booking (tasks and appointments), estimated completion on the job card, bay occupancy, technician load, productivity and efficiency
- Live workshop screen for TVs: vehicles in progress, waiting, ready and late, today's appointments; real-time over Reverb with 30 s fallback and a secret TV link
- Vehicle & key tracking: yard locations and map, key hooks and key holders with full history, key tags with QR / barcode / RFID, scan page, test-drive permits with odometer before / after and distance limit

## [1.16.0] - 2026-11-27
- Dismantling: standard vehicle parts checklist in the new-vehicle form and the dismantling file, factory part numbers (OEM) suggested per make/model/year from earlier vehicles and the catalog, optional immediate move to stock, used stock grouped by OEM and grade
- Fleets: add / remove vehicles from the fleet page (with new-vehicle option) and choose the fleet in the vehicle form
- Sales invoice: per-line warranty with print button, print all warranty cards (A4 / PDF), redesigned invoice page
- Redesigned sales and supplier invoice lists (KPIs, status tabs, payment progress) and supplier invoice page
- Quick add: create customers, vehicles, parts, services and suppliers from any search field (job cards, invoices, purchase orders, POS) without leaving the form

## [1.15.0] - 2026-11-27
- SaaS billing: issue tax invoices to clients (subscription renewals and custom charges), discount and VAT, due dates and overdue tracking, record payments, cancel with reason, bilingual A4/PDF print with paid/cancelled/overdue stamp, billing header settings; clients view and print their own invoices from the Subscription page|POS on phones: stacked cart lines with qty stepper, price and discount fields, fixed bottom bar with total and jump to cart

## [1.14.1] - 2026-11-26
- Phones: search in the top bar opens as a full-width row from a search button instead of a squeezed box
- Phones: dashboard and summary cards show full numbers and labels (no more cut values), smaller icons
- Phones: tables scroll sideways instead of breaking every word onto its own line; tables outside a scroll area are wrapped automatically; invoice lines keep a readable width
- Phones: tabs and pill bars scroll in one row, tighter card spacing, page header buttons sized for touch
- SaaS admin: the top menu collapses behind a menu button on phones and tablets, with a badge for new requests

## [1.14.0] - 2026-11-26
- Warranty claims: activity log (opened, status changes with inspection result, supplier claim updates, notes), progress bar and a redesigned claim page
- Printing of warranty claims (A4 / PDF) with warranty details, inspection, resolution, costs, supplier recovery and tracking history
- Printable warranty card (A4 / PDF): covered item, serial, customer, vehicle, invoice, period, km limit, terms, QR to verify and signatures; from the warranty list and the claim page
- Section bar under the main menu: every page shows the links of its section (e.g. Warranty & Assets) with the current page highlighted, including detail pages

## [1.13.0] - 2026-11-26
- Company settings: e-mail (SMTP) setup with encrypted password and a test e-mail; customer messages are sent from the company's own address
- SaaS: platform SMTP (used for system e-mails and companies without their own server), new 'Domains & e-mail' page
- Custom domains: plans can include a custom domain (with a yearly domain fee); clients order a new domain or connect their own from the Subscription page; the platform admin handles requests, assigns domains, shows the DNS records and verifies them
- On a client's own domain the login shows the client's name and logo, and only that client's users can sign in
- Stock transfers: who requested, dispatched, received or cancelled each transfer, an activity log with notes, receiving notes, variance per item, and printing of the transfer request and of the completed transfer (A4 / PDF)

## [1.12.0] - 2026-11-26
- Vehicle page: new table of compatible parts in the system (make / model / year / engine / VIN pattern) with fitment, price, available stock and a quick filter
- Vehicle gate log redesigned: daily entries / exits, vehicles in the workshop, filters, manual movements (road test, towing…) and a printable movement slip (A5 / A4 / PDF) for each entry
- Vehicle handover authorizations: the owner authorises a person (name, ID, phone, relationship) to drop off / pick up the vehicle, approved by a code sent to the owner or a signed letter; printable authorization letter; revoke; history of use
- Delivery asks who collects the vehicle: the owner, or an authorized person whose ID must match an active pick-up authorization; recorded on the job card, the gate log and the gate pass
- Reception records who brought the vehicle and matches drop-off authorizations by ID number

## [1.11.0] - 2026-11-26
- Goods received note (GRN) printout (A4 / PDF) with supplier, PO, warehouse, items, locations, batches, serial numbers and signatures; print buttons on the receipt and the list
- Complaints: automatic reference number (CMP-YYYY-000001), new complaint page with tracking history (registration, status changes, assignment, notes, resolution), follow-up form (a resolution is required to close) and printout (A4 / PDF)
- Maintenance reminders: new board with status cards (overdue, due, sent, delivered, failed, customer confirmed, booked), search and date filters, call / WhatsApp / send buttons
- Reminder page: contact history with the message delivery status, recording calls / WhatsApp / visits and their result, follow-up date, customer confirmation and booking the visit directly
- Database prepared for vehicle handover authorizations (next release)

## [1.10.0] - 2026-11-26
- Vehicle gate pass (A5 / A4 / PDF): release and payment status, plate, vehicle, invoices, QR to verify and signatures; printed from the job card, the invoice, the invoice list and the gate log
- Delivery note for sales invoices: goods and delivered quantities without prices, returned quantities, receiver signature; printed from the invoice page and the invoice list

## [1.9.1] - 2026-11-26
- Vehicle reception: the customer signature is uploaded as a PNG file instead of a long base64 text field, which hosting firewalls (ModSecurity) were refusing with 403

## [1.9.0] - 2026-11-26
- New diagnostic command: php artisan erp:doctor checks PHP, extensions, upload limits, folders, database, drivers and permissions, with the fix for each problem
- php artisan erp:doctor --user=email replays a vehicle reception as that user and prints exactly why it is accepted or refused (rolled back, nothing saved)
- Every refused form submission (403 from a permission, module or account check) returns to the form with the reason shown and logged
- cPanel requirements and checks documented in docs/DEPLOYMENT.md

## [1.8.3] - 2026-11-26
- Forms refused with 403 (missing permission, module not in plan…) now return to the form with the data kept and the exact reason shown, instead of a bare 'Forbidden' page; the reason is also written to the log
- Vehicle reception: a missing permission names the permission (reception.create) and the user's roles

## [1.8.2] - 2026-11-26
- Vehicle reception: pressing the button lists every missing value at once, marks each field with its message and keeps the list above the button
- Vehicle reception: the reason a submission was refused is shown above the button (validation, open job card, server error with a reference number and the technical detail for administrators)
- An expired session no longer shows a 'Page expired' screen: the user returns to the form with the data kept and a clear message
- A failing SMS / WhatsApp confirmation no longer hides the created job card; it is reported as a warning

## [1.8.1] - 2026-11-26
- Vehicle reception: when the vehicle already has an open job card, the form stays filled and shows which job card is open, with a button to open it and an option to open an additional job card
- Vehicle reception: refusals from the server stay visible on the page instead of only a short notification

## [1.8.0] - 2026-11-26
- Credit notes: new page with summary, tracking timeline (created, posted, returned to stock, refunded or credited to the account), refund voucher link, original invoice balance and journal entry
- Credit notes: official bilingual printout (A4 and PDF) with original invoice, returned items, VAT, settlement and signatures; print buttons on the credit note, the list and the invoice
- Credit notes list: invoice link, reason and settlement status (awaiting posting, credited to account, partly refunded, refunded)
- Refunds are now linked to their credit note (existing refunds are linked automatically)
- Vehicle reception: a missing required value stays highlighted with its message next to the field and above the submit button until it is filled

## [1.7.2] - 2026-11-26
- Search lists (parts, services, customers, vehicles…) float above tables and cards instead of being cut off inside invoice lines; they open upward when there is no room below
- Search lists are wider, show long part names on two lines, and keep part numbers such as 04152-YZZA1 in the right direction on Arabic pages

## [1.7.1] - 2026-11-26
- Vehicle reception: when the form is refused, everything entered is kept (customer, vehicle, odometer, fuel, complaint, damages, belongings, checklist, job details and signature)
- Vehicle reception: the selected customer and vehicle are shown again instead of empty search boxes
- Vehicle reception: missing information is checked before sending, and each error is shown next to its field
- Vehicle reception: phone photos are reduced in the browser before upload so they stay within the hosting limits; clear messages for photos that are too large
- Uploads larger than the server limit return to the form with an explanation instead of an error page

## [1.7.0] - 2026-11-26
- Trial requests redesigned: summary cards, country filter and a clear table with risk level for each request
- New detail page for each trial request: contact details, request details, follow-up notes and trial company creation
- Request verification: email domain check (company, personal or temporary), phone country vs connection country, VPN/proxy/data-centre detection, device vs connection time zone, form fill time and duplicate requests
- Technical information: IP, country, region, internet provider, ASN, device, browser, operating system, screen, languages, referring site, first page visited and UTM tags
- Re-check button to run the network checks again (configurable with TRIAL_NETWORK_CHECKS / IP_LOOKUP_URL)

## [1.6.0] - 2026-11-26
- Arabic font changed to Cairo on the website and the whole system (self-hosted, no external requests)
- English pages keep their font; Arabic words inside them also use Cairo
- Dashboard charts redraw with Cairo once the font is loaded
- Stylesheets are versioned on every layout so browsers pick up design updates immediately

## [1.5.0] - 2026-11-26
- Unified popups: designed confirmation dialog replacing browser confirm, toast notifications with titles and progress, unified modal windows
- Notifications for every add, edit and delete naming the record and the list
- Every delete form asks for confirmation automatically; delete confirmations name the record
- Phone numbers, emails and links always display left-to-right in lists

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
