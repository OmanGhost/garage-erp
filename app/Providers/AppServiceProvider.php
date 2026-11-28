<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Tenancy\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tenant::class);

        // Consistent column types for the whole schema.
        Blueprint::macro('company', function () {
            /** @var Blueprint $this */
            return $this->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
        });
        Blueprint::macro('money', function (string $column) {
            /** @var Blueprint $this */
            return $this->decimal($column, 15, 3)->default(0);
        });
        Blueprint::macro('cost', function (string $column) {
            /** @var Blueprint $this */
            return $this->decimal($column, 15, 4)->default(0);
        });
        Blueprint::macro('qty', function (string $column) {
            /** @var Blueprint $this */
            return $this->decimal($column, 14, 3)->default(0);
        });
        Blueprint::macro('rate', function (string $column) {
            /** @var Blueprint $this */
            return $this->decimal($column, 7, 3)->default(0);
        });
        Blueprint::macro('userRef', function (string $column) {
            /** @var Blueprint $this */
            return $this->foreignId($column)->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Model::preventLazyLoading(false);
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Short morph aliases (not enforced so every model can still be audited).
        Relation::morphMap([
            'user' => User::class,
            'customer' => \App\Modules\CRM\Models\Customer::class,
            'supplier' => \App\Modules\Purchasing\Models\Supplier::class,
            'employee' => \App\Modules\HR\Models\Employee::class,
            'vehicle' => \App\Modules\Vehicles\Models\Vehicle::class,
            'job_card' => \App\Modules\Workshop\Models\JobCard::class,
            'job_card_note' => \App\Modules\Workshop\Models\JobCardNote::class,
            'job_card_task' => \App\Modules\Workshop\Models\JobCardTask::class,
            'job_card_part' => \App\Modules\Workshop\Models\JobCardPart::class,
            'inspection' => \App\Modules\Workshop\Models\Inspection::class,
            'inspection_finding' => \App\Modules\Workshop\Models\InspectionFinding::class,
            'quality_sop' => \App\Modules\Quality\Models\QualitySop::class,
            'gift_card' => \App\Modules\Loyalty\Models\GiftCard::class,
            'membership' => \App\Modules\Loyalty\Models\Membership::class,
            'subcontract_order' => \App\Modules\Workshop\Models\SubcontractOrder::class,
            'roadside_request' => \App\Modules\Workshop\Models\RoadsideRequest::class,
            'fraud_alert' => \App\Modules\LossPrevention\Models\FraudAlert::class,
            'nonconformity' => \App\Modules\Quality\Models\Nonconformity::class,
            'quality_audit' => \App\Modules\Quality\Models\QualityAudit::class,
            'quotation' => \App\Modules\Workshop\Models\Quotation::class,
            'check_in' => \App\Modules\Workshop\Models\VehicleCheckIn::class,
            'part' => \App\Modules\Inventory\Models\Part::class,
            'stock_transfer' => \App\Modules\Inventory\Models\StockTransfer::class,
            'stock_count' => \App\Modules\Inventory\Models\StockCount::class,
            'stock_adjustment' => \App\Modules\Inventory\Models\StockAdjustment::class,
            'invoice' => \App\Modules\Sales\Models\Invoice::class,
            'credit_note' => \App\Modules\Sales\Models\CreditNote::class,
            'payment' => \App\Modules\Sales\Models\Payment::class,
            'pos_session' => \App\Modules\Sales\Models\PosSession::class,
            'purchase_order' => \App\Modules\Purchasing\Models\PurchaseOrder::class,
            'goods_receipt' => \App\Modules\Purchasing\Models\GoodsReceipt::class,
            'supplier_invoice' => \App\Modules\Purchasing\Models\SupplierInvoice::class,
            'landed_cost' => \App\Modules\Purchasing\Models\LandedCost::class,
            'purchase_return' => \App\Modules\Purchasing\Models\PurchaseReturn::class,
            'journal_entry' => \App\Modules\Accounting\Models\JournalEntry::class,
            'expense' => \App\Modules\Accounting\Models\Expense::class,
            'fund_transfer' => \App\Modules\Accounting\Models\FundTransfer::class,
            'fixed_asset' => \App\Modules\Accounting\Models\FixedAsset::class,
            'asset_depreciation' => \App\Modules\Accounting\Models\AssetDepreciation::class,
            'dismantled_vehicle' => \App\Modules\Dismantling\Models\DismantledVehicle::class,
            'dismantled_scrap' => \App\Modules\Dismantling\Models\DismantledScrap::class,
            'payroll' => \App\Modules\HR\Models\Payroll::class,
            'employee_loan' => \App\Modules\HR\Models\EmployeeLoan::class,
            'insurance_claim' => \App\Modules\Insurance\Models\InsuranceClaim::class,
            'insurance_company' => \App\Modules\Insurance\Models\InsuranceCompany::class,
            'warranty_claim' => \App\Modules\Warranty\Models\WarrantyClaim::class,
            'equipment' => \App\Modules\Assets\Models\Equipment::class,
            'appointment' => \App\Modules\Appointments\Models\Appointment::class,
            'store_order' => \App\Modules\Store\Models\StoreOrder::class,
            'company' => \App\Modules\Core\Models\Company::class,
            'branch' => \App\Modules\Core\Models\Branch::class,
            'complaint' => \App\Modules\CRM\Models\Complaint::class,
            'fleet' => \App\Modules\Fleet\Models\Fleet::class,
        ]);

        // platform SMTP saved by the super admin becomes the default mailer
        \App\Support\Mail\MailService::applyPlatformDefault();

        // Super admins and company admins bypass permission checks.
        Gate::before(function ($user, $ability) {
            if ($user->is_super_admin) {
                return true;
            }
            if ($user->company_id && tenant()->id() === $user->company_id && $user->hasRole('Company Admin')) {
                return true;
            }

            return null;
        });

        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\ErpEventSubscriber::class);

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        // public trial form: 5 submissions per hour per IP
        RateLimiter::for('trial', fn (Request $r) => Limit::perHour(5)->by($r->ip()));
        RateLimiter::for('portal', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
    }
}
