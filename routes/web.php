<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitePagesController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\VoucherController;
use App\Modules\Accounting\Http\Controllers\AccountingController;
use App\Modules\Appointments\Http\Controllers\AppointmentController;
use App\Modules\CRM\Http\Controllers\CustomerController;
use App\Modules\Dismantling\Http\Controllers\DismantlingController;
use App\Modules\HR\Http\Controllers\HrController;
use App\Modules\Insurance\Http\Controllers\AdvancedController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\StockOpsController;
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
// website pages: Arabic at /x, English at /en/x
foreach (['ar' => '', 'en' => '/en'] as $siteLocale => $prefix) {
    $sfx = $siteLocale === 'en' ? '.en' : '';
    Route::get($prefix.'/features', [SitePagesController::class, 'features'])->defaults('locale', $siteLocale)->name('site.features'.$sfx);
    Route::get($prefix.'/features/{slug}', [SitePagesController::class, 'feature'])->defaults('locale', $siteLocale)->where('slug', '[a-z0-9-]+')->name('site.feature'.$sfx);
    Route::get($prefix.'/solutions', [SitePagesController::class, 'solutions'])->defaults('locale', $siteLocale)->name('site.solutions'.$sfx);
    Route::get($prefix.'/customers', [SitePagesController::class, 'customers'])->defaults('locale', $siteLocale)->name('site.customers'.$sfx);
    Route::get($prefix.'/contact', [SitePagesController::class, 'contact'])->defaults('locale', $siteLocale)->name('site.contact'.$sfx);
    Route::get($prefix.'/terms', [SitePagesController::class, 'legal'])->defaults('doc', 'terms')->defaults('locale', $siteLocale)->name('site.terms'.$sfx);
    Route::get($prefix.'/privacy', [SitePagesController::class, 'legal'])->defaults('doc', 'privacy')->defaults('locale', $siteLocale)->name('site.privacy'.$sfx);
    Route::get($prefix.'/docs', [SitePagesController::class, 'docs'])->defaults('locale', $siteLocale)->name('site.docs'.$sfx);
    Route::get($prefix.'/docs/{slug}', [SitePagesController::class, 'doc'])->defaults('locale', $siteLocale)->where('slug', '[a-z0-9-]+')->name('site.doc'.$sfx);
}
Route::post('/contact', [SitePagesController::class, 'storeContact'])->middleware('throttle:trial')->name('site.contact.store');
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
    Route::get('/dvi/{token}', [PublicController::class, 'dvi'])->name('public.dvi');
    Route::post('/dvi/{token}/findings/{finding}', [PublicController::class, 'dviDecide'])->name('public.dvi.decide');
    Route::post('/dvi/{token}/sign', [PublicController::class, 'dviSign'])->name('public.dvi.sign');
    Route::get('/dvi/{token}/photo/{attachment}', [PublicController::class, 'dviPhoto'])->name('public.dvi.photo');
    Route::get('/tv/{token}', [\App\Modules\Workshop\Http\Controllers\LiveController::class, 'tv'])->name('workshop.tv');
    Route::get('/rsa/{token}', [\App\Modules\Workshop\Http\Controllers\RoadsideController::class, 'publicShow'])->name('roadside.public');
    Route::post('/rsa/{token}/location', [\App\Modules\Workshop\Http\Controllers\RoadsideController::class, 'publicLocation'])->middleware('throttle:20,1')->name('roadside.public.location');
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
    Route::get('/billing', [\App\Modules\Saas\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/create', [\App\Modules\Saas\Http\Controllers\BillingController::class, 'create'])->name('billing.create');
    Route::post('/billing', [\App\Modules\Saas\Http\Controllers\BillingController::class, 'store'])->name('billing.store');
    Route::post('/billing/settings', [\App\Modules\Saas\Http\Controllers\BillingController::class, 'settings'])->name('billing.settings');
    Route::get('/billing/{invoice}', [\App\Modules\Saas\Http\Controllers\BillingController::class, 'show'])->name('billing.show');
    Route::post('/billing/{invoice}/pay', [\App\Modules\Saas\Http\Controllers\BillingController::class, 'pay'])->name('billing.pay');
    Route::post('/billing/{invoice}/cancel', [\App\Modules\Saas\Http\Controllers\BillingController::class, 'cancel'])->name('billing.cancel');
    Route::get('/billing/{invoice}/print/{format?}', [\App\Modules\Saas\Http\Controllers\BillingController::class, 'print'])->name('billing.print')->whereIn('format', ['a4', 'pdf']);
    Route::get('/domains', [\App\Modules\Saas\Http\Controllers\DomainController::class, 'index'])->name('domains.index');
    Route::post('/domains/assign', [\App\Modules\Saas\Http\Controllers\DomainController::class, 'assign'])->name('domains.assign');
    Route::post('/domains/{company}/verify', [\App\Modules\Saas\Http\Controllers\DomainController::class, 'verify'])->name('domains.verify');
    Route::post('/domains/{company}/status', [\App\Modules\Saas\Http\Controllers\DomainController::class, 'status'])->name('domains.status');
    Route::post('/domains/requests/{domainRequest}', [\App\Modules\Saas\Http\Controllers\DomainController::class, 'handle'])->name('domains.requests.handle');
    Route::post('/domains/smtp', [\App\Modules\Saas\Http\Controllers\DomainController::class, 'saveSmtp'])->name('domains.smtp');
    Route::post('/domains/smtp/test', [\App\Modules\Saas\Http\Controllers\DomainController::class, 'testSmtp'])->name('domains.smtp.test');
    Route::post('/plans', [AdminController::class, 'savePlan'])->name('plans.save');
    Route::get('/messages', [AdminController::class, 'messages'])->name('messages.index');
    Route::get('/messages/{message}', [AdminController::class, 'showMessage'])->name('messages.show');
    Route::put('/messages/{message}', [AdminController::class, 'updateMessage'])->name('messages.update');
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
    Route::get('/quick/{type}', [\App\Http\Controllers\QuickCreateController::class, 'form'])->name('quick.form');
    Route::post('/quick/{type}', [\App\Http\Controllers\QuickCreateController::class, 'store'])->name('quick.store');
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
    Route::post('/settings/smtp', [\App\Http\Controllers\MailSettingsController::class, 'save'])->name('settings.smtp');
    Route::post('/settings/smtp/test', [\App\Http\Controllers\MailSettingsController::class, 'test'])->name('settings.smtp.test');
    Route::get('/roles', [SystemController::class, 'roles'])->name('roles.index');
    Route::get('/roles/{role}/edit', [SystemController::class, 'editRole'])->name('roles.edit');
    Route::post('/roles', [SystemController::class, 'storeRole'])->name('roles.store');
    Route::put('/roles/{role}', [SystemController::class, 'updateRole'])->name('roles.update');
    Route::get('/users/{user}/access', [SystemController::class, 'userAccess'])->name('users.access');
    // role change requests
    Route::get('/role-requests', [\App\Http\Controllers\RoleRequestController::class, 'index'])->name('role-requests.index');
    Route::get('/role-requests/create', [\App\Http\Controllers\RoleRequestController::class, 'create'])->name('role-requests.create');
    Route::post('/role-requests', [\App\Http\Controllers\RoleRequestController::class, 'store'])->name('role-requests.store');
    Route::get('/role-requests/{roleRequest}', [\App\Http\Controllers\RoleRequestController::class, 'show'])->name('role-requests.show');
    Route::post('/role-requests/{roleRequest}/decide', [\App\Http\Controllers\RoleRequestController::class, 'decide'])->name('role-requests.decide');
    Route::post('/role-requests/{roleRequest}/cancel', [\App\Http\Controllers\RoleRequestController::class, 'cancel'])->name('role-requests.cancel');
    Route::get('/role-requests/{roleRequest}/print', [\App\Http\Controllers\RoleRequestController::class, 'print'])->name('role-requests.print');
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
    Route::get('/billing/invoices/{invoice}/{format?}', [SystemController::class, 'billingInvoice'])->name('billing.invoice')->whereIn('format', ['a4', 'pdf']);
    Route::post('/domain-requests', [\App\Http\Controllers\DomainRequestController::class, 'store'])->name('domain-requests.store');
    Route::post('/domain-requests/{domainRequest}/cancel', [\App\Http\Controllers\DomainRequestController::class, 'cancel'])->name('domain-requests.cancel');

    // CRM & vehicles
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/reminders', [\App\Modules\CRM\Http\Controllers\ReminderController::class, 'index'])->name('reminders.index');
    Route::get('/reminders/{reminder}', [\App\Modules\CRM\Http\Controllers\ReminderController::class, 'show'])->name('reminders.show');
    Route::post('/reminders/{reminder}/send', [\App\Modules\CRM\Http\Controllers\ReminderController::class, 'send'])->name('reminders.send');
    Route::post('/reminders/{reminder}/contact', [\App\Modules\CRM\Http\Controllers\ReminderController::class, 'contact'])->name('reminders.contact');
    Route::post('/reminders/{reminder}/book', [\App\Modules\CRM\Http\Controllers\ReminderController::class, 'book'])->name('reminders.book');
    Route::post('/reminders/{reminder}/complete', [\App\Modules\CRM\Http\Controllers\ReminderController::class, 'complete'])->name('reminders.complete');
    Route::get('/complaints/{complaint}', [\App\Modules\CRM\Http\Controllers\ComplaintController::class, 'show'])->name('complaints.show');
    Route::post('/complaints/{complaint}/follow-up', [\App\Modules\CRM\Http\Controllers\ComplaintController::class, 'followUp'])->name('complaints.follow-up');
    Route::get('/complaints/{complaint}/print/{format?}', [\App\Modules\CRM\Http\Controllers\ComplaintController::class, 'print'])->name('complaints.print')->whereIn('format', ['a4', 'pdf']);
    Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
    Route::post('/customers/{customer}/portal', [CustomerController::class, 'portalAccess'])->name('customers.portal');
    Route::get('/vehicles/{vehicle}', [CustomerController::class, 'vehicle'])->name('vehicles.show');
    Route::post('/vehicles/{vehicle}/transfer', [CustomerController::class, 'transferVehicle'])->name('vehicles.transfer');
    Route::get('/vin-decode', [CustomerController::class, 'decodeVin'])->name('vehicles.vin');

    // workshop
    Route::middleware('module:workshop')->group(function () {
        Route::get('/workshop/board', [WorkshopController::class, 'board'])->name('workshop.board');
        // resource planning
        Route::get('/workshop/planner', [\App\Modules\Workshop\Http\Controllers\PlanningController::class, 'index'])->middleware('module:planning')->name('workshop.planner');
        Route::post('/workshop/planner/auto', [\App\Modules\Workshop\Http\Controllers\PlanningController::class, 'autoScheduleAll'])->middleware('module:planning')->name('workshop.planner.auto-all');
        Route::post('/workshop/planner/{jobCard}/auto', [\App\Modules\Workshop\Http\Controllers\PlanningController::class, 'autoSchedule'])->middleware('module:planning')->name('workshop.planner.auto');
        Route::post('/workshop/planner/tasks/{task}', [\App\Modules\Workshop\Http\Controllers\PlanningController::class, 'schedule'])->middleware('module:planning')->name('workshop.planner.schedule');
        Route::delete('/workshop/planner/tasks/{task}', [\App\Modules\Workshop\Http\Controllers\PlanningController::class, 'unschedule'])->middleware('module:planning')->name('workshop.planner.unschedule');
        // live screen
        Route::get('/workshop/live', [\App\Modules\Workshop\Http\Controllers\LiveController::class, 'index'])->middleware('module:planning')->name('workshop.live');
        Route::post('/workshop/live/token', [\App\Modules\Workshop\Http\Controllers\LiveController::class, 'rotateToken'])->middleware('module:planning')->name('workshop.live.token');
        // memberships & loyalty
        Route::get('/loyalty', [\App\Modules\Loyalty\Http\Controllers\LoyaltyController::class, 'dashboard'])->middleware('module:loyalty')->name('loyalty.dashboard');
        Route::post('/loyalty/settings', [\App\Modules\Loyalty\Http\Controllers\LoyaltyController::class, 'saveSettings'])->middleware('module:loyalty')->name('loyalty.settings');
        Route::post('/loyalty/offers', [\App\Modules\Loyalty\Http\Controllers\LoyaltyController::class, 'sendOffer'])->middleware('module:loyalty')->name('loyalty.offers');
        Route::post('/loyalty/gift-cards', [\App\Modules\Loyalty\Http\Controllers\LoyaltyController::class, 'issueGiftCard'])->middleware('module:loyalty')->name('gift-cards.store');
        Route::get('/gift-cards/{card}/print', [\App\Modules\Loyalty\Http\Controllers\LoyaltyController::class, 'printGiftCard'])->middleware('module:loyalty')->name('gift-cards.print');
        Route::post('/customers/{customer}/points', [\App\Modules\Loyalty\Http\Controllers\LoyaltyController::class, 'adjustPoints'])->middleware('module:loyalty')->name('loyalty.adjust');
        Route::post('/customers/{customer}/memberships', [\App\Modules\Loyalty\Http\Controllers\LoyaltyController::class, 'sellMembership'])->middleware('module:memberships')->name('memberships.sell');
        Route::get('/memberships/{membership}', [\App\Modules\Loyalty\Http\Controllers\LoyaltyController::class, 'membership'])->middleware('module:memberships')->name('memberships.show');
        Route::get('/memberships/{membership}/card', [\App\Modules\Loyalty\Http\Controllers\LoyaltyController::class, 'printMembership'])->middleware('module:memberships')->name('memberships.card');
        Route::post('/invoices/{invoice}/loyalty/{action}', [\App\Modules\Loyalty\Http\Controllers\LoyaltyController::class, 'invoiceAction'])->name('invoices.loyalty')->whereIn('action', ['membership', 'coupon', 'points', 'gift-card']);
        // quality management
        Route::get('/quality', [\App\Modules\Quality\Http\Controllers\QualityController::class, 'dashboard'])->middleware('module:quality')->name('quality.dashboard');
        Route::post('/quality/job-cards/{jobCard}/ncr', [\App\Modules\Quality\Http\Controllers\QualityController::class, 'ncrFromJob'])->middleware('module:quality')->name('quality.ncr.from-job');
        Route::get('/quality/ncr/{ncr}', [\App\Modules\Quality\Http\Controllers\QualityController::class, 'ncr'])->middleware('module:quality')->name('quality.ncr.show');
        Route::put('/quality/ncr/{ncr}', [\App\Modules\Quality\Http\Controllers\QualityController::class, 'updateNcr'])->middleware('module:quality')->name('quality.ncr.update');
        Route::post('/quality/ncr/{ncr}/actions', [\App\Modules\Quality\Http\Controllers\QualityController::class, 'addAction'])->middleware('module:quality')->name('quality.ncr.actions');
        Route::post('/quality/ncr/{ncr}/close', [\App\Modules\Quality\Http\Controllers\QualityController::class, 'closeNcr'])->middleware('module:quality')->name('quality.ncr.close');
        Route::post('/quality/actions/{action}/{step}', [\App\Modules\Quality\Http\Controllers\QualityController::class, 'actionStep'])->middleware('module:quality')->name('quality.actions.step')->whereIn('step', ['complete', 'verify']);
        Route::get('/quality/audits/{audit}', [\App\Modules\Quality\Http\Controllers\QualityController::class, 'audit'])->middleware('module:quality')->name('quality.audits.show');
        Route::post('/quality/audits/{audit}', [\App\Modules\Quality\Http\Controllers\QualityController::class, 'recordAudit'])->middleware('module:quality')->name('quality.audits.record');
        // one-click attendance (any user linked to an active employee)
        Route::post('/attendance/punch', \App\Modules\HR\Http\Controllers\AttendancePunchController::class)->middleware('throttle:20,1')->name('attendance.punch');
        // fraud & loss prevention
        Route::get('/loss-prevention', [\App\Modules\LossPrevention\Http\Controllers\LossPreventionController::class, 'index'])->middleware('module:loss_prevention')->name('loss-prevention.index');
        Route::post('/loss-prevention/settings', [\App\Modules\LossPrevention\Http\Controllers\LossPreventionController::class, 'saveSettings'])->middleware('module:loss_prevention')->name('loss-prevention.settings');
        Route::post('/loss-prevention/verify-audit', [\App\Modules\LossPrevention\Http\Controllers\LossPreventionController::class, 'verifyAudit'])->middleware(['module:loss_prevention', 'throttle:6,1'])->name('loss-prevention.verify');
        Route::get('/loss-prevention/{alert}', [\App\Modules\LossPrevention\Http\Controllers\LossPreventionController::class, 'show'])->middleware('module:loss_prevention')->name('loss-prevention.show');
        Route::post('/loss-prevention/{alert}/review', [\App\Modules\LossPrevention\Http\Controllers\LossPreventionController::class, 'review'])->middleware('module:loss_prevention')->name('loss-prevention.review');
        // subcontracted (outside) repairs
        Route::get('/workshop/subcontracts', [\App\Modules\Workshop\Http\Controllers\SubcontractController::class, 'index'])->middleware('module:subcontracting')->name('subcontracts.index');
        Route::post('/job-cards/{jobCard}/subcontracts', [\App\Modules\Workshop\Http\Controllers\SubcontractController::class, 'store'])->middleware('module:subcontracting')->name('subcontracts.store');
        Route::post('/subcontracts/{order}/{action}', [\App\Modules\Workshop\Http\Controllers\SubcontractController::class, 'action'])->middleware('module:subcontracting')->name('subcontracts.action')->whereIn('action', ['send', 'receive', 'cancel']);
        Route::get('/subcontracts/{order}/print', [\App\Modules\Workshop\Http\Controllers\SubcontractController::class, 'print'])->middleware('module:subcontracting')->name('subcontracts.print');
        // roadside assistance
        Route::get('/roadside', [\App\Modules\Workshop\Http\Controllers\RoadsideController::class, 'index'])->middleware('module:roadside')->name('roadside.index');
        Route::post('/roadside', [\App\Modules\Workshop\Http\Controllers\RoadsideController::class, 'store'])->middleware('module:roadside')->name('roadside.store');
        Route::post('/roadside/settings', [\App\Modules\Workshop\Http\Controllers\RoadsideController::class, 'saveSettings'])->middleware('module:roadside')->name('roadside.settings');
        Route::get('/roadside/{roadside}', [\App\Modules\Workshop\Http\Controllers\RoadsideController::class, 'show'])->middleware('module:roadside')->name('roadside.show');
        Route::post('/roadside/{roadside}/{action}', [\App\Modules\Workshop\Http\Controllers\RoadsideController::class, 'action'])->middleware('module:roadside')->name('roadside.action')->whereIn('action', ['assign', 'en_route', 'on_site', 'complete', 'cancel']);
        // vehicle & key tracking
        Route::get('/workshop/tracking', [\App\Modules\Workshop\Http\Controllers\TrackingController::class, 'index'])->middleware('module:vehicle_tracking')->name('tracking.index');
        Route::post('/workshop/tracking/scan', [\App\Modules\Workshop\Http\Controllers\TrackingController::class, 'scan'])->middleware('module:vehicle_tracking')->name('tracking.scan');
        Route::get('/workshop/tracking/{jobCard}', [\App\Modules\Workshop\Http\Controllers\TrackingController::class, 'card'])->middleware('module:vehicle_tracking')->name('tracking.card');
        Route::post('/workshop/tracking/{jobCard}/{action}', [\App\Modules\Workshop\Http\Controllers\TrackingController::class, 'action'])->middleware('module:vehicle_tracking')->name('tracking.action')->whereIn('action', ['move', 'key-out', 'key-in', 'tag']);
        Route::get('/job-cards/{jobCard}/key-tag', [\App\Modules\Workshop\Http\Controllers\TrackingController::class, 'keyTag'])->middleware('module:vehicle_tracking')->name('tracking.key-tag');
        Route::post('/job-cards/{jobCard}/test-drives', [\App\Modules\Workshop\Http\Controllers\TrackingController::class, 'storeDrive'])->middleware('module:vehicle_tracking')->name('test-drives.store');
        Route::post('/test-drives/{drive}/{action}', [\App\Modules\Workshop\Http\Controllers\TrackingController::class, 'driveAction'])->middleware('module:vehicle_tracking')->name('test-drives.action')->whereIn('action', ['start', 'finish', 'cancel']);
        Route::get('/test-drives/{drive}/print', [\App\Modules\Workshop\Http\Controllers\TrackingController::class, 'printDrive'])->middleware('module:vehicle_tracking')->name('test-drives.print');
        Route::get('/reception', [WorkshopController::class, 'reception'])->name('reception.create');
        Route::post('/reception', [WorkshopController::class, 'checkIn'])->name('reception.store');
        Route::get('/reception/{jobCard}/receipt', [WorkshopController::class, 'receipt'])->name('reception.receipt');
        Route::get('/job-cards/{jobCard}', [WorkshopController::class, 'show'])->name('job-cards.show');
        Route::get('/job-cards/{jobCard}/print', [WorkshopController::class, 'print'])->name('job-cards.print');
        Route::get('/handover-authorizations', [\App\Modules\Workshop\Http\Controllers\HandoverAuthorizationController::class, 'index'])->name('handover-authorizations.index');
        Route::get('/handover-authorizations/new', [\App\Modules\Workshop\Http\Controllers\HandoverAuthorizationController::class, 'create'])->name('handover-authorizations.create');
        Route::post('/handover-authorizations', [\App\Modules\Workshop\Http\Controllers\HandoverAuthorizationController::class, 'store'])->name('handover-authorizations.store');
        Route::get('/handover-authorizations/{authorization}', [\App\Modules\Workshop\Http\Controllers\HandoverAuthorizationController::class, 'show'])->name('handover-authorizations.show');
        Route::post('/handover-authorizations/{authorization}/send-code', [\App\Modules\Workshop\Http\Controllers\HandoverAuthorizationController::class, 'sendCode'])->name('handover-authorizations.send-code');
        Route::post('/handover-authorizations/{authorization}/approve', [\App\Modules\Workshop\Http\Controllers\HandoverAuthorizationController::class, 'approve'])->name('handover-authorizations.approve');
        Route::post('/handover-authorizations/{authorization}/revoke', [\App\Modules\Workshop\Http\Controllers\HandoverAuthorizationController::class, 'revoke'])->name('handover-authorizations.revoke');
        Route::get('/handover-authorizations/{authorization}/print/{format?}', [\App\Modules\Workshop\Http\Controllers\HandoverAuthorizationController::class, 'print'])->name('handover-authorizations.print')->whereIn('format', ['a4', 'pdf']);
        Route::get('/gate-log', [\App\Modules\Workshop\Http\Controllers\GateLogController::class, 'index'])->name('gate-logs.index');
        Route::post('/gate-log', [\App\Modules\Workshop\Http\Controllers\GateLogController::class, 'store'])->name('gate-logs.store');
        Route::get('/gate-log/{gateLog}/print/{format?}', [\App\Modules\Workshop\Http\Controllers\GateLogController::class, 'print'])->name('gate-logs.print')->whereIn('format', ['a5', 'a4', 'pdf']);
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
        Route::post('/inspections/{inspection}/suggest', [WorkshopController::class, 'suggestServices'])->middleware('module:dvi')->name('inspections.suggest');
        Route::post('/inspections/{inspection}/send', [WorkshopController::class, 'sendInspection'])->middleware('module:dvi')->name('inspections.send');
        Route::post('/inspections/{inspection}/sign', [WorkshopController::class, 'signInspection'])->middleware('module:dvi')->name('inspections.sign');
        Route::post('/inspections/findings/{finding}/decision', [WorkshopController::class, 'decideFinding'])->name('inspections.findings.decide');
        Route::get('/job-cards/{jobCard}/inspections/compare', [WorkshopController::class, 'compareInspections'])->middleware('module:dvi')->name('inspections.compare');
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
        Route::post('/parts/{part}/alternatives', [InventoryController::class, 'addAlternative'])->name('parts.alternatives.store');
        Route::delete('/parts/alternatives/{alternative}', [InventoryController::class, 'deleteAlternative'])->name('parts.alternatives.destroy');
        Route::get('/inventory/reservations', [StockOpsController::class, 'reservations'])->middleware('module:inventory_ops')->name('inventory.reservations');
        Route::post('/inventory/reservations', [StockOpsController::class, 'storeReservation'])->middleware('module:inventory_ops')->name('inventory.reservations.store');
        Route::post('/inventory/reservations/{reservation}/release', [StockOpsController::class, 'releaseReservation'])->middleware('module:inventory_ops')->name('inventory.reservations.release');
        Route::post('/parts/{part}/kit', [StockOpsController::class, 'kitComponents'])->middleware('module:inventory_ops')->name('inventory.kit.components');
        Route::post('/parts/{part}/kit/{action}', [StockOpsController::class, 'kitBuild'])->middleware('module:inventory_ops')->name('inventory.kit.build')->whereIn('action', ['assemble', 'disassemble']);
        Route::get('/inventory/consignment', [StockOpsController::class, 'consignment'])->middleware('module:inventory_ops')->name('inventory.consignment');
        Route::get('/inventory/consignment/{warehouse}', [StockOpsController::class, 'consignmentWarehouse'])->middleware('module:inventory_ops')->name('inventory.consignment.show');
        Route::post('/inventory/consignment/{warehouse}/{action}', [StockOpsController::class, 'consignmentAction'])->middleware('module:inventory_ops')->name('inventory.consignment.action')->whereIn('action', ['receive', 'return', 'settle']);
        Route::get('/inventory/cores', [StockOpsController::class, 'cores'])->middleware('module:inventory_ops')->name('inventory.cores');
        Route::post('/inventory/cores/{core}/{action}', [StockOpsController::class, 'coreAction'])->middleware('module:inventory_ops')->name('inventory.cores.action')->whereIn('action', ['receive', 'forfeit']);
        Route::get('/inventory/cross-reference', [InventoryController::class, 'crossReference'])->name('inventory.cross-reference');
        Route::get('/inventory/analytics', [InventoryController::class, 'analytics'])->middleware('module:inventory_analytics')->name('inventory.analytics');
        Route::post('/inventory/analytics/{action}', [InventoryController::class, 'analyticsAction'])->middleware('module:inventory_analytics')->name('inventory.analytics.action')->whereIn('action', ['abc', 'reorder', 'cycle', 'settings']);
        Route::post('/parts/{part}/compatibility', [InventoryController::class, 'addCompatibility'])->name('parts.compat.store');
        Route::delete('/parts/compatibility/{compat}', [InventoryController::class, 'deleteCompatibility'])->name('parts.compat.destroy');
        Route::get('/inventory/reorder', [InventoryController::class, 'reorder'])->name('inventory.reorder');
        Route::post('/inventory/reorder', [InventoryController::class, 'createOrdersFromSuggestions'])->name('inventory.reorder.order');
        Route::get('/inventory/transfers/create', [InventoryController::class, 'createTransfer'])->name('inventory.transfers.create');
        Route::post('/inventory/transfers', [InventoryController::class, 'storeTransfer'])->name('inventory.transfers.store');
        Route::get('/inventory/transfers/{transfer}', [InventoryController::class, 'transfer'])->name('inventory.transfers.show');
        Route::post('/inventory/transfers/{transfer}/note', [InventoryController::class, 'transferNote'])->name('inventory.transfers.note');
        Route::get('/inventory/transfers/{transfer}/print/{type}/{format?}', [InventoryController::class, 'printTransfer'])->name('inventory.transfers.print')->whereIn('type', ['request', 'completed'])->whereIn('format', ['a4', 'pdf']);
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
        Route::get('/oem-suggest', [DismantlingController::class, 'suggest'])->name('suggest');
        Route::get('/{vehicle}', [DismantlingController::class, 'show'])->name('show');
        Route::post('/{vehicle}/checklist', [DismantlingController::class, 'addChecklist'])->name('parts.checklist');
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
        Route::post('/purchase-orders/{po}/delivered', [StockOpsController::class, 'dropShipDelivered'])->middleware('module:sales')->middleware('module:inventory_ops')->name('purchase-orders.delivered');
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
        Route::get('/goods-receipts/{grn}/print/{format?}', [PurchasingController::class, 'printReceipt'])->name('goods-receipts.print')->whereIn('format', ['a4', 'pdf']);
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
        Route::get('/pos/sessions/{session}/close-summary', [SalesController::class, 'closeSummary'])->name('pos.sessions.summary');
        Route::get('/invoices/create', [SalesController::class, 'createInvoice'])->name('invoices.create');
        Route::post('/invoices', [SalesController::class, 'storeInvoice'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [SalesController::class, 'invoice'])->name('invoices.show');
        Route::get('/invoices/{invoice}/edit', [SalesController::class, 'editInvoice'])->name('invoices.edit');
        Route::put('/invoices/{invoice}', [SalesController::class, 'updateInvoice'])->name('invoices.update');
        Route::post('/invoices/{invoice}/post', [SalesController::class, 'postInvoice'])->name('invoices.post');
        Route::get('/invoices/{invoice}/print/{format?}', [SalesController::class, 'printInvoice'])->name('invoices.print')->whereIn('format', ['a4', '80mm', 'pdf']);
        Route::get('/invoices/{invoice}/return', [SalesController::class, 'createCreditNote'])->name('credit-notes.create');
        Route::post('/invoices/{invoice}/return', [SalesController::class, 'storeCreditNote'])->name('credit-notes.store');
        Route::get('/invoices/{invoice}/warranties/{format?}', [SalesController::class, 'printWarranties'])->name('invoices.warranties')->whereIn('format', ['a4', 'pdf']);
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
        Route::post('/sales-orders/{order}/drop-ship', [StockOpsController::class, 'dropShip'])->middleware('module:purchasing')->middleware('module:inventory_ops')->name('sales-orders.drop-ship');
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
        Route::get('/loans/{loan}', [HrController::class, 'showLoan'])->name('loans.show');
        Route::post('/loans/{loan}/repay', [HrController::class, 'repayLoan'])->name('loans.repay');
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
    Route::post('/fleets/{fleet}/vehicles', [AdvancedController::class, 'fleetAddVehicle'])->name('fleets.vehicles.add')->middleware('module:fleet');
    Route::delete('/fleets/{fleet}/vehicles/{vehicle}', [AdvancedController::class, 'fleetRemoveVehicle'])->name('fleets.vehicles.remove')->middleware('module:fleet');
    Route::post('/fleets/{fleet}/invoice', [AdvancedController::class, 'fleetInvoice'])->name('fleets.invoice')->middleware('module:fleet');
    Route::post('/fleets/approvals/{approval}/{decision}', [AdvancedController::class, 'decideApproval'])->name('fleets.approvals.decide')->whereIn('decision', ['approve', 'reject'])->middleware('module:fleet');
    Route::get('/warranty/claims/create', [AdvancedController::class, 'createWarrantyClaim'])->name('warranty.claims.create')->middleware('module:warranty');
    Route::post('/warranty/claims', [AdvancedController::class, 'storeWarrantyClaim'])->name('warranty.claims.store')->middleware('module:warranty');
    Route::get('/warranty/claims/{claim}', [AdvancedController::class, 'warrantyClaim'])->name('warranty.claims.show')->middleware('module:warranty');
    Route::post('/warranty/claims/{claim}/note', [AdvancedController::class, 'warrantyNote'])->name('warranty.claims.note')->middleware('module:warranty');
    Route::get('/warranty/claims/{claim}/print/{format?}', [AdvancedController::class, 'printWarrantyClaim'])->name('warranty.claims.print')->whereIn('format', ['a4', 'pdf'])->middleware('module:warranty');
    Route::get('/warranties/{warranty}/print/{format?}', [AdvancedController::class, 'printWarranty'])->name('warranties.print')->whereIn('format', ['a4', 'pdf'])->middleware('module:warranty');
    Route::post('/warranty/claims/{claim}/{action}', [AdvancedController::class, 'warrantyAction'])->name('warranty.claims.action')->whereIn('action', ['approve', 'reject', 'complete', 'supplier'])->middleware('module:warranty');

    // reports
    Route::middleware('module:reports')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{key}', [ReportController::class, 'show'])->name('reports.show');
        Route::post('/ai/diagnose', [ReportController::class, 'diagnose'])->name('ai.diagnose');
    });
});
