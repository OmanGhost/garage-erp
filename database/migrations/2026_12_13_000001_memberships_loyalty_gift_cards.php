<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance memberships (yearly plans with included services, discounts, priority booking, roadside calls),
 * customer loyalty (points, tiers, referrals), gift cards; payments that settle an invoice from points or a gift card
 * have no bank account.
 */
return new class extends Migration
{
    /** [system_key, preferred code, name_ar, name_en, type, parent code] */
    protected array $accounts = [
        ['loyalty_liability', '2150', 'التزامات نقاط الولاء', 'Loyalty Points Liability', 'liability', '21'],
        ['gift_card_liability', '2160', 'بطاقات الهدايا المستحقة', 'Gift Cards Outstanding', 'liability', '21'],
        ['loyalty_expense', '5212', 'مصروف برنامج الولاء', 'Loyalty Program Expense', 'expense', '52'],
    ];

    public function up(): void
    {
        // accounts for companies that already exist (new companies get them from the chart template)
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($this->accounts as [$key, $code, $ar, $en, $type, $parentCode]) {
                if (DB::table('accounts')->where('company_id', $companyId)->where('system_key', $key)->exists()) {
                    continue;
                }
                $parent = DB::table('accounts')->where('company_id', $companyId)->where('code', $parentCode)->value('id');
                if (! DB::table('accounts')->where('company_id', $companyId)->where('code', $parentCode)->exists() && ! DB::table('accounts')->where('company_id', $companyId)->exists()) {
                    continue; // company without a chart of accounts yet
                }
                $c = $code;
                while (DB::table('accounts')->where('company_id', $companyId)->where('code', $c)->exists()) {
                    $c = (string) ((int) $c + 1);
                }
                DB::table('accounts')->insert(['company_id' => $companyId, 'code' => $c, 'name_ar' => $ar, 'name_en' => $en, 'type' => $type,
                    'normal_balance' => $type === 'expense' ? 'debit' : 'credit', 'parent_id' => $parent, 'system_key' => $key, 'is_group' => false,
                    'is_active' => true, 'cash_flow_category' => 'operating', 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_account_id')->nullable()->change();
        });

        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('code', 30);
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->decimal('price', 15, 3)->default(0);
            $table->unsignedSmallInteger('duration_months')->default(12);
            $table->decimal('parts_discount', 5, 2)->default(0);
            $table->decimal('labor_discount', 5, 2)->default(0);
            $table->boolean('priority_booking')->default(false);
            $table->unsignedSmallInteger('roadside_calls')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('membership_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('qty')->default(1);
            $table->timestamps();
        });
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('number', 40);
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('membership_plan_id')->constrained();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status', 12)->default('pending'); // pending|active|expired|cancelled
            $table->decimal('price', 15, 3)->default(0);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('roadside_used')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'customer_id', 'status']);
        });
        Schema::create('membership_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->unsignedSmallInteger('included')->default(0);
            $table->unsignedSmallInteger('used')->default(0);
            $table->timestamps();
            $table->unique(['membership_id', 'service_id']);
        });
        Schema::create('membership_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('invoice_item_id')->unique();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 15, 3)->default(0);
            $table->decimal('value', 15, 3)->default(0);
            $table->string('kind', 10)->default('included'); // included|discount
            $table->timestamps();
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('membership_id')->nullable()->after('core_part_id');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->unsignedBigInteger('membership_id')->nullable();
        });
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable(); // personal offer
            $table->string('campaign', 60)->nullable();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('loyalty_points', 15, 3)->default(0);
            $table->string('loyalty_tier', 12)->nullable();
            $table->string('referral_code', 20)->nullable();
            $table->unsignedBigInteger('referred_by_id')->nullable();
            $table->index(['company_id', 'referral_code']);
        });
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12); // earn|redeem|reverse|refund|referral|adjust|expire
            $table->decimal('points', 15, 3);
            $table->decimal('value', 15, 3)->default(0);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('credit_note_id')->nullable();
            $table->string('reference', 60)->nullable(); // idempotency key, e.g. earn:invoice:15
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'customer_id']);
        });
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('code', 30);
            $table->decimal('initial_amount', 15, 3);
            $table->decimal('balance', 15, 3);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 30)->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 10)->default('active'); // active|used|expired|void
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // issue|redeem|refund|void
            $table->decimal('amount', 15, 3);
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['gift_card_transactions', 'gift_cards', 'loyalty_transactions', 'membership_usages', 'membership_balances', 'memberships', 'membership_plan_items', 'membership_plans'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('customers', function (Blueprint $t) {
            $t->dropIndex(['company_id', 'referral_code']);
            $t->dropColumn(['loyalty_points', 'loyalty_tier', 'referral_code', 'referred_by_id']);
        });
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropColumn('membership_id'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['coupon_id', 'membership_id']));
        Schema::table('coupons', fn (Blueprint $t) => $t->dropColumn(['customer_id', 'campaign']));
    }
};
