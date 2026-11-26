<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\VoucherController;
use App\Modules\Accounting\Http\Controllers\AccountingController;
use App\Modules\Appointments\Http\Controllers\AppointmentController;
use App\Modules\CRM\Http\Controllers\CustomerController;
use App\Modules\Dismantling\Http\Controllers\DismantlingController;
use App\Modules\HR\Http\Controllers\HrController;
use App\Modules\Insurance\Http\Controllers\AdvancedController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Portal\Http\Controllers\PortalController;
use App\Modules\Purchasing\Http\Controllers\PurchasingController;
use App\Modules\Reports\Http\Controllers\ReportController;
use App\Modules\Saas\Http\Controllers\AdminController;
use App\Modules\Saas\Http\Controllers\ClientController;
use App\Modules\Sales\Http\Controllers\SalesController;
use App\Modules\Store\Http\Controllers\StorefrontController;
use App\Modules\Workshop\Http\Controllers\PublicController;
use App\Modules\Workshop\Http\Controllers\WorkshopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
// public marketing website (Arabic at /, English at /en) + SEO endpoints
Route::get('/', [SiteController::class, 'root'])->name('site.home');
Route::get('/en', [SiteController::class, 'home'])->defaults('locale', 'en')->name('site.home.en');
Route::get('/trial', [SiteController::class, 'trial'])->defaults('locale', 'ar')->name('site.trial');
Route::get('/en/trial', [SiteController::class, 'trial'])->defaults('locale', 'en')->name('site.trial.en');
Route::post('/trial', [SiteController::class, 'storeTrial'])->middleware('throttle:trial')->name('site.trial.store');
Route::get('/trial/thanks', [SiteController::class, 'thanks'])->defaults('locale', 'ar')->name('site.thanks');
Route::get('/en/trial/thanks', [SiteController::class, 'thanks'])->defaults('locale', 'en')->name('site.thanks.en');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('site.sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('site.robots');
Route::post('/locale', [SystemController::class, 'switchLocale'])->name('locale.switch');
Route::get('/manifest.webmanifest', [SystemController::class, 'manifest'])->name('pwa.manifest');
Route::view('/offline', 'offline')->name('offline');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/two-factor', [AuthController::class, 'showTwoFactor'])->name('two-factor.challenge');
    Route::post('/two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:login');
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:login')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// customer-facing links (signed by random tokens)
Route::middleware('throttle:portal')->group(function () {
    Route::get('/q/{token}', [PublicController::class, 'quotation'])->name('public.quotation');
    Route::post('/q/{token}', [PublicController::class, 'respondQuotation'])->name('public.quotation.respond');
    Route::get('/fa/{token}', [PublicController::class, 'fleetApproval'])->name('public.fleet-approval');
    Route::post('/fa/{token}', [PublicController::class, 'fleetDecide'])->name('public.fleet-approval.decide');
    Route::get('/r/{token}', [PublicController::class, 'receipt'])->name('public.receipt');
    Route::get('/i/{uuid}', [PublicController::class, 'invoice'])->name('public.invoice');
    Route::get('/track/{token}', [PublicController::class, 'track'])->name('public.track');
});

// online store (per company)
Route::prefix('shop/{company:slug}')->middleware('throttle:portal')->name('store.')->group(function () {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');
    Route::get('/p/{part}', [StorefrontController::class, 'part'])->name('part');
    Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart');
    Route::post('/cart', [StorefrontController::class, 'addToCart'])->name('cart.add');
    Route::post('/cart/update', [StorefrontController::class, 'updateCart'])->name('cart.update');
    Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [StorefrontController::class, 'placeOrder'])->name('order.place');
    Route::get('/order/{uuid}', [StorefrontController::class, 'order'])->name('order');
});
Route::get('/pay/sandbox/{session}', [StorefrontController::class, 'sandbox'])->name('payments.sandbox');
Route::post('/pay/sandbox/{session}', [StorefrontController::class, 'sandboxComplete'])->name('payments.sandbox.complete');
Route::get('/pay/return/{payment}', [StorefrontController::class, 'paymentReturn'])->name('payments.return');

/*
|--------------------------------------------------------------------------
| Authenticated (all user types)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::post('/profile/theme', [ProfileController::class, 'theme'])->name('profile.theme');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('/profile/2fa', [ProfileController::class, 'enableTwoFactor'])->name('profile.2fa.enable');
    Route::post('/profile/2fa/confirm', [ProfileController::class, 'confirmTwoFactor'])->name('profile.2fa.confirm');
    Route::delete('/profile/2fa', [ProfileController::class, 'disableTwoFactor'])->name('profile.2fa.disable');
    Route::delete('/profile/sessions', [ProfileController::class, 'logoutOtherSessions'])->name('profile.sessions.destroy');
});

/*
|--------------------------------------------------------------------------
| Customer portal
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->middleware(['auth', 'usertype:customer', 'throttle:portal'])->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/vehicles', [PortalController::class, 'vehicles'])->name('vehicles');
    Route::post('/vehicles', [PortalController::class, 'storeVehicle'])->name('vehicles.store');
    Route::get('/jobs/{jobCard}', [PortalController::class, 'job'])->name('jobs.show');
    Route::post('/jobs/{jobCard}/rate', [PortalController::class, 'rate'])->name('jobs.rate');
    Route::get('/quotations/{quotation}', [PortalController::class, 'quotation'])->name('quotations.show');
    Route::post('/quotations/{quotation}', [PortalController::class, 'respondQuotation'])->name('quotations.respond');
    Route::get('/invoices', [PortalController::class, 'invoices'])->name('invoices');
    Route::get('/invoices/{invoice}/pdf', [PortalController::class, 'invoicePdf'])->name('invoices.pdf');
    Route::post('/invoices/{invoice}/pay', [PortalController::class, 'pay'])->name('invoices.pay');
    Route::get('/statement', [PortalController::class, 'statement'])->name('statement');
    Route::get('/warranties', [PortalController::class, 'warranties'])->name('warranties');
    Route::get('/appointments/new', [PortalController::class, 'createAppointment'])->name('appointments.create');
    Route::post('/appointments', [PortalController::class, 'storeAppointment'])->name('appointments.store');
    Route::get('/complaints', [PortalController::class, 'complaints'])->name('complaints');
    Route::post('/complaints', [PortalController::class, 'storeComplaint'])->name('complaints.store');
});

/*
|--------------------------------------------------------------------------
| SaaS administration (super admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth', 'usertype:super'])->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/companies/create', [AdminController::class, 'createCompany'])->name('companies.create');
    Route::post('/companies', [AdminController::class, 'storeCompany'])->name('companies.store');
    Route::get('/companies/{company}', fn ($company) => redirect()->route('admin.clients.show', $company))->name('companies.show');
    // clients (tenant companies)
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->whereNumber('client')->name('clients.show');
    Route::put('/clients/{company}/profile', [ClientController::class, 'updateProfile'])->name('clients.profile');
    Route::post('/clients/{company}/toggle', [ClientController::class, 'toggle'])->name('clients.toggle');
    Route::post('/clients/{company}/extend-trial', [ClientController::class, 'extendTrial'])->name('clients.extend-trial');
    Route::post('/clients/{company}/users/{user}/reset-password', [ClientController::class, 'resetPassword'])->name('clients.users.reset');
    Route::post('/clients/{company}/users/{user}/toggle', [ClientController::class, 'toggleUser'])->name('clients.users.toggle');
    Route::post('/clients/{company}/invoices/{invoice}/paid', [ClientController::class, 'markInvoicePaid'])->name('clients.invoices.paid');
    Route::delete('/clients/{company}', [ClientController::class, 'archive'])->name('clients.archive');
    Route::post('/clients/{client}/restore', [ClientController::class, 'restore'])->whereNumber('client')->name('clients.restore');
    Route::put('/companies/{company}', [AdminController::class, 'updateCompany'])->name('companies.update');
    Route::post('/companies/{company}/enter', [AdminController::class, 'enter'])->name('companies.enter');
    Route::post('/companies/{company}/renew', [AdminController::class, 'renew'])->name('companies.renew');
    Route::post('/leave', [AdminController::class, 'leave'])->name('leave');
    Route::get('/plans', [AdminController::class, 'plans'])->name('plans');
    Route::post('/plans', [AdminController::class, 'savePlan'])->name('plans.save');
    Route::get('/trials', [AdminController::class, 'trials'])->name('trials.index');
    Route::get('/trials/{trial}', [AdminController::class, 'showTrial'])->name('trials.show');
    Route::put('/trials/{trial}', [AdminController::class, 'updateTrial'])->name('trials.update');
    Route::post('/trials/{trial}/check', [AdminController::class, 'recheckTrial'])->name('trials.check');
    Route::post('/trials/{trial}/provision', [AdminController::class, 'provisionTrial'])->name('trials.provision');
    Route::get('/site', [AdminController::class, 'siteSettings'])->name('site.edit');
    Route::put('/site', [AdminController::class, 'updateSiteSettings'])->name('site.update');
});

/*
|--------------------------------------------------------------------------
| Staff application
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'usertype:staff', 'subscription'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/widgets', [DashboardController::class, 'saveWidgets'])->name('dashboard.widgets');
    Route::post('/branch', [SystemController::class, 'switchBranch'])->name('branch.switch');
    Route::get('/lookup/{type}', LookupController::class)->name('lookup');
    Route::post('/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // generic CRUD
    Route::prefix('m/{slug}')->name('crud.')->group(function () {
        Route::get('/', [CrudController::class, 'index'])->name('index');
        Route::get('/create', [CrudController::class, 'create'])->name('create');
        Route::post('/', [CrudController::class, 'store'])->name('store');
        Route::get('/{id}', [CrudController::class, 'show'])->name('show')->whereNumber('id');
        Route::get('/{id}/edit', [CrudController::class, 'edit'])->name('edit')->whereNumber('id');
        Route::put('/{id}', [CrudController::class, 'update'])->name('update')->whereNumber('id');
        Route::delete('/{id}', [CrudController::class, 'destroy'])->name('destroy')->whereNumber('id');
    });

    // official printable vouchers (receipt / payment / transfer)
    Route::prefix('vouchers')->name('vouchers.')->group(function () {
        Route::get('/payments/{payment}', [VoucherController::class, 'payment'])->name('payment');
        Route::get('/expenses/{expense}', [VoucherController::class, 'expense'])->name('expense');
        Route::get('/transfers/{transfer}', [VoucherController::class, 'transfer'])->name('transfer');
        Route::get('/loans/{loan}', [VoucherController::class, 'loan'])->name('loan');
        Route::get('/payroll/{payroll}', [VoucherController::class, 'payroll'])->name('payroll');
    });

    // administration
    Route::get('/settings', [SystemController::class, 'settings'])->name('settings.edit');
    Route::put('/settings', [SystemController::class, 'updateSettings'])->name('settings.update');
    Route::get('/roles', [SystemController::class, 'roles'])->name('roles.index');
    Route::get('/roles/{role}/edit', [SystemController::class, 'editRole'])->name('roles.edit');
    Route::post('/roles', [SystemController::class, 'storeRole'])->name('roles.store');
    Route::put('/roles/{role}', [SystemController::class, 'updateRole'])->name('roles.update');
    Route::get('/users/{user}/access', [SystemController::class, 'userAccess'])->name('users.access');
    Route::put('/users/{user}/access', [SystemController::class, 'saveUserAccess']);
    Route::get('/integrations', [SystemController::class, 'integrations'])->name('integrations.index')->middleware('module:integrations');
    Route::put('/integrations/{provider}', [SystemController::class, 'saveIntegration'])->name('integrations.save')->middleware('module:integrations');
    Route::get('/api-tokens', [SystemController::class, 'apiTokens'])->name('api-tokens.index')->middleware('module:integrations');
    Route::post('/api-tokens', [SystemController::class, 'createApiToken'])->name('api-tokens.store')->middleware('module:integrations');
    Route::delete('/api-tokens/{id}', [SystemController::class, 'revokeApiToken'])->name('api-tokens.destroy')->middleware('module:integrations');
    Route::get('/backups', [SystemController::class, 'backups'])->name('backups.index');
    Route::post('/backups', [SystemController::class, 'runBackup'])->name('backups.run');
    Route::get('/backups/{file}', [SystemController::class, 'downloadBackup'])->name('backups.download')->where('file', '[A-Za-z0-9_\-\.]+');
    Route::get('/billing', [SystemController::class, 'billing'])->name('billing.index');

    // CRM & vehicles
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
    Route::post('/customers/{customer}/portal', [CustomerController::class, 'portalAccess'])->name('customers.portal');
    Route::get('/vehicles/{vehicle}', [CustomerController::class, 'vehicle'])->name('vehicles.show');
    Route::post('/vehicles/{vehicle}/transfer', [CustomerController::class, 'transferVehicle'])->name('vehicles.transfer');
    Route::get('/vin-decode', [CustomerController::class, 'decodeVin'])->name('vehicles.vin');

    // workshop
    Route::middleware('module:workshop')->group(function () {
        Route::get('/workshop/board', [WorkshopController::class, 'board'])->name('workshop.board');
        Route::get('/reception', [WorkshopController::class, 'reception'])->name('reception.create');
        Route::post('/reception', [WorkshopController::class, 'checkIn'])->name('reception.store');
        Route::get('/reception/{jobCard}/receipt', [WorkshopController::class, 'receipt'])->name('reception.receipt');
        Route::get('/job-cards/{jobCard}', [WorkshopController::class, 'show'])->name('job-cards.show');
        Route::get('/job-cards/{jobCard}/print', [WorkshopController::class, 'print'])->name('job-cards.print');
        Route::get('/job-cards/{jobCard}/gate-pass/{format?}', [WorkshopController::class, 'gatePass'])->name('job-cards.gate-pass')->whereIn('format', ['a5', 'a4', 'pdf']);
        Route::post('/job-cards/{jobCard}/status', [WorkshopController::class, 'status'])->name('job-cards.status');
        Route::put('/job-cards/{jobCard}', [WorkshopController::class, 'update'])->name('job-cards.update');
        Route::post('/job-cards/{jobCard}/tasks', [WorkshopController::class, 'addTask'])->name('job-cards.tasks.store');
        Route::post('/job-cards/tasks/{task}/{action}', [WorkshopController::class, 'taskAction'])->name('job-cards.tasks.action')->whereIn('action', ['start', 'pause', 'finish', 'cancel', 'assign']);
        Route::post('/job-cards/{jobCard}/parts', [WorkshopController::class, 'addPart'])->name('job-cards.parts.store');
        Route::post('/job-cards/parts/{part}/{action}', [WorkshopController::class, 'partAction'])->name('job-cards.parts.action')->whereIn('action', ['issue', 'return', 'cancel']);
        Route::post('/job-cards/{jobCard}/notes', [WorkshopController::class, 'addNote'])->name('job-cards.notes.store');
        Route::post('/job-cards/{jobCard}/qc', [WorkshopController::class, 'qualityCheck'])->name('job-cards.qc');
        Route::post('/job-cards/{jobCard}/additional', [WorkshopController::class, 'decideAdditional'])->name('job-cards.additional');
        Route::post('/job-cards/{jobCard}/additional-quote', [WorkshopController::class, 'additionalQuote'])->name('job-cards.additional-quote');
        Route::post('/job-cards/{jobCard}/invoice', [WorkshopController::class, 'invoice'])->name('job-cards.invoice');
        Route::post('/job-cards/{jobCard}/purchase-request', [WorkshopController::class, 'purchaseRequest'])->name('job-cards.purchase-request');
        Route::post('/job-cards/{jobCard}/inspection', [WorkshopController::class, 'startInspection'])->name('inspections.start');
        Route::get('/inspections/{inspection}', [WorkshopController::class, 'inspection'])->name('inspections.show');
        Route::put('/inspections/{inspection}', [WorkshopController::class, 'saveInspection'])->name('inspections.update');
        Route::post('/inspections/{inspection}/findings', [WorkshopController::class, 'addFinding'])->name('inspections.findings.store');
        Route::delete('/inspections/findings/{finding}', [WorkshopController::class, 'deleteFinding'])->name('inspections.findings.destroy');
        Route::post('/inspections/{inspection}/complete', [WorkshopController::class, 'completeInspection'])->name('inspections.complete');
        Route::post('/inspections/{inspection}/quotation', [WorkshopController::class, 'quotationFromInspection'])->name('inspections.quotation');
        Route::get('/inspections/{inspection}/print', [WorkshopController::class, 'printInspection'])->name('inspections.print');
        Route::get('/departments/{department}/technicians', [WorkshopController::class, 'technicians'])->name('departments.technicians');
        Route::put('/departments/{department}/technicians', [WorkshopController::class, 'saveTechnicians']);
        Route::get('/ai', [ReportController::class, 'ai'])->name('ai.index');
    });

    // quotations (workshop & sales)
    Route::get('/quotations/new', [WorkshopController::class, 'createQuotation'])->name('quotations.sales.create');
    Route::post('/quotations', [WorkshopController::class, 'storeQuotation'])->name('quotations.store');
    Route::get('/quotations/{quotation}', [WorkshopController::class, 'quotation'])->name('quotations.show');
    Route::get('/quotations/{quotation}/edit', [WorkshopController::class, 'editQuotation'])->name('quotations.edit');
    Route::put('/quotations/{quotation}', [WorkshopController::class, 'updateQuotation'])->name('quotations.update');
    Route::post('/quotations/{quotation}/send', [WorkshopController::class, 'sendQuotation'])->name('quotations.send');
    Route::post('/quotations/{quotation}/respond', [WorkshopController::class, 'respondQuotation'])->name('quotations.respond');
    Route::post('/quotations/{quotation}/revise', [WorkshopController::class, 'reviseQuotation'])->name('quotations.revise');
    Route::post('/quotations/{quotation}/convert', [WorkshopController::class, 'convertQuotation'])->name('quotations.convert');
    Route::get('/quotations/{quotation}/print', [WorkshopController::class, 'printQuotation'])->name('quotations.print');

    // appointments
    Route::middleware('module:appointments')->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'calendar'])->name('appointments.calendar');
        Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::post('/appointments/{appointment}/status', [AppointmentController::class, 'status'])->name('appointments.status');
        Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    });

    // inventory
    Route::middleware('module:inventory')->group(function () {
        Route::get('/parts/search', [InventoryController::class, 'search'])->name('parts.search');
        Route::get('/parts/import', [InventoryController::class, 'importForm'])->name('parts.import');
        Route::post('/parts/import', [InventoryController::class, 'import']);
        Route::get('/parts/labels', [InventoryController::class, 'labels'])->name('parts.labels');
        Route::get('/parts/{part}', [InventoryController::class, 'part'])->name('parts.show');
        Route::post('/parts/{part}/compatibility', [InventoryController::class, 'addCompatibility'])->name('parts.compat.store');
        Route::delete('/parts/compatibility/{compat}', [InventoryController::class, 'deleteCompatibility'])->name('parts.compat.destroy');
        Route::get('/inventory/reorder', [InventoryController::class, 'reorder'])->name('inventory.reorder');
        Route::post('/inventory/reorder', [InventoryController::class, 'createOrdersFromSuggestions'])->name('inventory.reorder.order');
        Route::get('/inventory/transfers/create', [InventoryController::class, 'createTransfer'])->name('inventory.transfers.create');
        Route::post('/inventory/transfers', [InventoryController::class, 'storeTransfer'])->name('inventory.transfers.store');
        Route::get('/inventory/transfers/{transfer}', [InventoryController::class, 'transfer'])->name('inventory.transfers.show');
        Route::post('/inventory/transfers/{transfer}/{action}', [InventoryController::class, 'transferAction'])->name('inventory.transfers.action')->whereIn('action', ['send', 'receive', 'cancel']);
        Route::get('/inventory/counts/create', [InventoryController::class, 'createCount'])->name('inventory.counts.create');
        Route::post('/inventory/counts', [InventoryController::class, 'storeCount'])->name('inventory.counts.store');
        Route::get('/inventory/counts/{count}', [InventoryController::class, 'count'])->name('inventory.counts.show');
        Route::put('/inventory/counts/{count}', [InventoryController::class, 'saveCount'])->name('inventory.counts.update');
        Route::post('/inventory/counts/{count}/post', [InventoryController::class, 'postCount'])->name('inventory.counts.post');
        Route::get('/inventory/adjustments/create', [InventoryController::class, 'createAdjustment'])->name('inventory.adjustments.create');
        Route::post('/inventory/adjustments', [InventoryController::class, 'storeAdjustment'])->name('inventory.adjustments.store');
        Route::get('/inventory/adjustments/{adjustment}', [InventoryController::class, 'adjustment'])->name('inventory.adjustments.show');
        Route::post('/inventory/adjustments/{adjustment}/post', [InventoryController::class, 'postAdjustment'])->name('inventory.adjustments.post');
    });

    // dismantling
    Route::middleware('module:dismantling')->prefix('dismantling')->name('dismantling.')->group(function () {
        Route::get('/{vehicle}', [DismantlingController::class, 'show'])->name('show');
        Route::post('/{vehicle}/parts', [DismantlingController::class, 'addPart'])->name('parts.store');
        Route::delete('/parts/{part}', [DismantlingController::class, 'deletePart'])->name('parts.destroy');
        Route::post('/{vehicle}/stock', [DismantlingController::class, 'moveToStock'])->name('stock');
        Route::post('/{vehicle}/cost', [DismantlingController::class, 'addCost'])->name('cost');
        Route::post('/{vehicle}/scrap', [DismantlingController::class, 'scrap'])->name('scrap');
        Route::post('/{vehicle}/close', [DismantlingController::class, 'close'])->name('close');
        Route::get('/{vehicle}/labels', [DismantlingController::class, 'labels'])->name('labels');
    });

    // purchasing
    Route::middleware('module:purchasing')->group(function () {
        Route::get('/suppliers/{supplier}', [PurchasingController::class, 'supplier'])->name('suppliers.show');
        Route::get('/purchase-requests/create', [PurchasingController::class, 'createRequest'])->name('purchase-requests.create');
        Route::post('/purchase-requests', [PurchasingController::class, 'storeRequest'])->name('purchase-requests.store');
        Route::get('/purchase-requests/{pr}', [PurchasingController::class, 'request'])->name('purchase-requests.show');
        Route::post('/purchase-requests/{pr}/decide/{decision}', [PurchasingController::class, 'decideRequest'])->name('purchase-requests.decide')->whereIn('decision', ['approve', 'reject']);
        Route::get('/rfqs/create', [PurchasingController::class, 'createRfq'])->name('rfqs.create');
        Route::post('/rfqs', [PurchasingController::class, 'storeRfq'])->name('rfqs.store');
        Route::get('/rfqs/{rfq}', [PurchasingController::class, 'rfq'])->name('rfqs.show');
        Route::post('/rfqs/quotes/{rs}', [PurchasingController::class, 'saveQuote'])->name('rfqs.quote');
        Route::post('/rfqs/{rfq}/award/{rs}', [PurchasingController::class, 'award'])->name('rfqs.award');
        Route::get('/purchase-orders/create', [PurchasingController::class, 'createOrder'])->name('purchase-orders.create');
        Route::post('/purchase-orders', [PurchasingController::class, 'storeOrder'])->name('purchase-orders.store');
        Route::get('/purchase-orders/{po}', [PurchasingController::class, 'order'])->name('purchase-orders.show');
        Route::get('/purchase-orders/{po}/edit', [PurchasingController::class, 'editOrder'])->name('purchase-orders.edit');
        Route::put('/purchase-orders/{po}', [PurchasingController::class, 'updateOrder'])->name('purchase-orders.update');
        Route::post('/purchase-orders/{po}/{action}', [PurchasingController::class, 'orderAction'])->name('purchase-orders.action')->whereIn('action', ['submit', 'approve', 'cancel', 'close']);
        Route::get('/purchase-orders/{po}/receive', [PurchasingController::class, 'receiveForm'])->name('purchase-orders.receive');
        Route::post('/purchase-orders/{po}/receive', [PurchasingController::class, 'receive']);
        Route::get('/purchase-orders/{po}/print', [PurchasingController::class, 'printOrder'])->name('purchase-orders.print');
        Route::get('/goods-receipts/{grn}', [PurchasingController::class, 'receipt'])->name('goods-receipts.show');
        Route::post('/goods-receipts/{grn}/invoice', [PurchasingController::class, 'invoiceReceipt'])->name('goods-receipts.invoice');
        Route::get('/supplier-invoices/create', [PurchasingController::class, 'createSupplierInvoice'])->name('supplier-invoices.create');
        Route::post('/supplier-invoices', [PurchasingController::class, 'storeSupplierInvoice'])->name('supplier-invoices.store');
        Route::get('/supplier-invoices/{si}', [PurchasingController::class, 'supplierInvoice'])->name('supplier-invoices.show');
        Route::post('/supplier-invoices/{si}/post', [PurchasingController::class, 'postSupplierInvoice'])->name('supplier-invoices.post');
        Route::get('/landed-costs/create', [PurchasingController::class, 'createLandedCost'])->name('landed-costs.create');
        Route::post('/landed-costs', [PurchasingController::class, 'storeLandedCost'])->name('landed-costs.store');
        Route::get('/landed-costs/{lc}', [PurchasingController::class, 'landedCost'])->name('landed-costs.show');
        Route::post('/landed-costs/{lc}/post', [PurchasingController::class, 'postLandedCost'])->name('landed-costs.post');
        Route::get('/purchase-returns/create', [PurchasingController::class, 'createReturn'])->name('purchase-returns.create');
        Route::post('/purchase-returns', [PurchasingController::class, 'storeReturn'])->name('purchase-returns.store');
        Route::get('/purchase-returns/{ret}', [PurchasingController::class, 'purchaseReturn'])->name('purchase-returns.show');
        Route::post('/purchase-returns/{ret}/post', [PurchasingController::class, 'postReturn'])->name('purchase-returns.post');
    });

    // sales & POS
    Route::middleware('module:sales')->group(function () {
        Route::get('/pos', [SalesController::class, 'pos'])->name('pos.index');
        Route::post('/pos/open', [SalesController::class, 'openSession'])->name('pos.open');
        Route::post('/pos/close', [SalesController::class, 'closeSession'])->name('pos.close');
        Route::get('/pos/sessions/{session}', [SalesController::class, 'session'])->name('pos.sessions.show');
        Route::get('/invoices/create', [SalesController::class, 'createInvoice'])->name('invoices.create');
        Route::post('/invoices', [SalesController::class, 'storeInvoice'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [SalesController::class, 'invoice'])->name('invoices.show');
        Route::get('/invoices/{invoice}/edit', [SalesController::class, 'editInvoice'])->name('invoices.edit');
        Route::put('/invoices/{invoice}', [SalesController::class, 'updateInvoice'])->name('invoices.update');
        Route::post('/invoices/{invoice}/post', [SalesController::class, 'postInvoice'])->name('invoices.post');
        Route::get('/invoices/{invoice}/print/{format?}', [SalesController::class, 'printInvoice'])->name('invoices.print')->whereIn('format', ['a4', '80mm', 'pdf']);
        Route::get('/invoices/{invoice}/return', [SalesController::class, 'createCreditNote'])->name('credit-notes.create');
        Route::post('/invoices/{invoice}/return', [SalesController::class, 'storeCreditNote'])->name('credit-notes.store');
        Route::get('/invoices/{invoice}/delivery-note/{format?}', [SalesController::class, 'deliveryNote'])->name('invoices.delivery-note')->whereIn('format', ['a4', 'pdf']);
        Route::get('/credit-notes/{creditNote}', [SalesController::class, 'creditNote'])->name('credit-notes.show');
        Route::get('/credit-notes/{creditNote}/print/{format?}', [SalesController::class, 'printCreditNote'])->name('credit-notes.print')->whereIn('format', ['a4', 'pdf']);
        Route::post('/credit-notes/{creditNote}/post', [SalesController::class, 'postCreditNote'])->name('credit-notes.post');
        Route::get('/payments/receive', [SalesController::class, 'receiveForm'])->name('payments.receive');
        Route::post('/payments/receive', [SalesController::class, 'receive']);
        Route::get('/payments/pay', [SalesController::class, 'payForm'])->name('payments.pay');
        Route::post('/payments/pay', [SalesController::class, 'pay']);
        Route::get('/payments/{payment}', [SalesController::class, 'payment'])->name('payments.show');
        Route::post('/payments/{payment}/cancel', [SalesController::class, 'cancelPayment'])->name('payments.cancel');
        Route::get('/sales-orders/create', [SalesController::class, 'createOrder'])->name('sales-orders.create');
        Route::post('/sales-orders', [SalesController::class, 'storeOrder'])->name('sales-orders.store');
        Route::get('/sales-orders/{order}', [SalesController::class, 'order'])->name('sales-orders.show');
        Route::post('/sales-orders/{order}/{action}', [SalesController::class, 'orderAction'])->name('sales-orders.action')->whereIn('action', ['confirm', 'invoice', 'cancel']);
        Route::get('/store-orders/{order}', [SalesController::class, 'storeOrderView'])->name('store-orders.show');
        Route::post('/store-orders/{order}/{action}', [SalesController::class, 'storeOrderAction'])->name('store-orders.action')->whereIn('action', ['invoice', 'processing', 'shipped', 'delivered', 'cancelled', 'paid']);
        Route::get('/shop', fn () => redirect()->route('store.home', tenant()->company()->slug))->name('store.home.self');
    });

    // accounting
    Route::middleware('module:accounting')->prefix('accounting')->group(function () {
        Route::get('/statements', [AccountingController::class, 'statements'])->name('accounting.statements');
        Route::get('/ledger', [AccountingController::class, 'ledger'])->name('accounting.ledger');
        Route::get('/opening', [AccountingController::class, 'openingForm'])->name('accounting.opening');
        Route::post('/opening', [AccountingController::class, 'opening']);
        Route::get('/journal/create', [AccountingController::class, 'createJournal'])->name('journal.create');
        Route::post('/journal', [AccountingController::class, 'storeJournal'])->name('journal.store');
        Route::get('/journal/{entry}', [AccountingController::class, 'journal'])->name('journal.show');
        Route::post('/journal/{entry}/reverse', [AccountingController::class, 'reverse'])->name('journal.reverse');
        Route::post('/expenses/{expense}/post', [AccountingController::class, 'postExpense'])->name('accounting.expenses.post');
        Route::get('/transfers/create', [AccountingController::class, 'transferForm'])->name('accounting.transfers.create');
        Route::post('/transfers', [AccountingController::class, 'saveTransfer'])->name('accounting.transfers.store');
        Route::get('/transfers/{transfer}/edit', [AccountingController::class, 'transferForm'])->name('accounting.transfers.edit');
        Route::put('/transfers/{transfer}', [AccountingController::class, 'saveTransfer'])->name('accounting.transfers.update');
        Route::post('/transfers/{transfer}/post', [AccountingController::class, 'postTransfer'])->name('accounting.transfers.post');
        Route::get('/reconciliations/{rec}', [AccountingController::class, 'reconciliation'])->name('accounting.reconciliations.show');
        Route::put('/reconciliations/{rec}', [AccountingController::class, 'reconcile']);
        Route::get('/assets/depreciation', [AccountingController::class, 'depreciationForm'])->name('accounting.assets.depreciation');
        Route::post('/assets/depreciation', [AccountingController::class, 'runDepreciation']);
        Route::get('/assets/{asset}', [AccountingController::class, 'asset'])->name('accounting.assets.show');
        Route::post('/assets/{asset}/acquire', [AccountingController::class, 'acquireAsset'])->name('accounting.assets.acquire');
        Route::post('/assets/{asset}/dispose', [AccountingController::class, 'disposeAsset'])->name('accounting.assets.dispose');
        Route::get('/budgets/{budget}', [AccountingController::class, 'budget'])->name('accounting.budgets.show');
        Route::put('/budgets/{budget}', [AccountingController::class, 'saveBudget']);
        Route::get('/fiscal/{year}', [AccountingController::class, 'fiscalYear'])->name('accounting.fiscal.show');
        Route::post('/fiscal/periods/{period}/{action}', [AccountingController::class, 'periodAction'])->name('accounting.fiscal.period')->whereIn('action', ['close', 'reopen']);
        Route::post('/fiscal/{year}/close', [AccountingController::class, 'closeYear'])->name('accounting.fiscal.close');
    });

    // HR
    Route::middleware('module:hr')->prefix('hr')->name('hr.')->group(function () {
        Route::get('/attendance/daily', [HrController::class, 'daily'])->name('attendance.daily');
        Route::post('/attendance/daily', [HrController::class, 'saveDaily']);
        Route::post('/leaves/{leave}/{decision}', [HrController::class, 'decideLeave'])->name('leaves.decide')->whereIn('decision', ['approve', 'reject']);
        Route::get('/loans/create', [HrController::class, 'createLoan'])->name('loans.create');
        Route::post('/loans', [HrController::class, 'storeLoan'])->name('loans.store');
        Route::get('/payroll/create', [HrController::class, 'createPayroll'])->name('payroll.create');
        Route::post('/payroll', [HrController::class, 'generatePayroll'])->name('payroll.store');
        Route::get('/payroll/{payroll}', [HrController::class, 'payroll'])->name('payroll.show');
        Route::post('/payroll/{payroll}/{action}', [HrController::class, 'payrollAction'])->name('payroll.action')->whereIn('action', ['approve', 'post', 'pay']);
        Route::get('/payroll/{payroll}/payslip/{item}', [HrController::class, 'payslip'])->name('payroll.payslip');
    });

    // insurance, fleet, warranty
    Route::get('/insurance/claims/{claim}', [AdvancedController::class, 'claim'])->name('insurance.claims.show')->middleware('module:insurance');
    Route::post('/insurance/claims/{claim}/status', [AdvancedController::class, 'claimStatus'])->name('insurance.claims.status')->middleware('module:insurance');
    Route::post('/insurance/claims/{claim}/invoice', [AdvancedController::class, 'claimInvoice'])->name('insurance.claims.invoice')->middleware('module:insurance');
    Route::get('/fleets/{fleet}', [AdvancedController::class, 'fleet'])->name('fleets.show')->middleware('module:fleet');
    Route::post('/fleets/{fleet}/invoice', [AdvancedController::class, 'fleetInvoice'])->name('fleets.invoice')->middleware('module:fleet');
    Route::post('/fleets/approvals/{approval}/{decision}', [AdvancedController::class, 'decideApproval'])->name('fleets.approvals.decide')->whereIn('decision', ['approve', 'reject'])->middleware('module:fleet');
    Route::get('/warranty/claims/create', [AdvancedController::class, 'createWarrantyClaim'])->name('warranty.claims.create')->middleware('module:warranty');
    Route::post('/warranty/claims', [AdvancedController::class, 'storeWarrantyClaim'])->name('warranty.claims.store')->middleware('module:warranty');
    Route::get('/warranty/claims/{claim}', [AdvancedController::class, 'warrantyClaim'])->name('warranty.claims.show')->middleware('module:warranty');
    Route::post('/warranty/claims/{claim}/{action}', [AdvancedController::class, 'warrantyAction'])->name('warranty.claims.action')->whereIn('action', ['approve', 'reject', 'complete', 'supplier'])->middleware('module:warranty');

    // reports
    Route::middleware('module:reports')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{key}', [ReportController::class, 'show'])->name('reports.show');
        Route::post('/ai/diagnose', [ReportController::class, 'diagnose'])->name('ai.diagnose');
    });
});
