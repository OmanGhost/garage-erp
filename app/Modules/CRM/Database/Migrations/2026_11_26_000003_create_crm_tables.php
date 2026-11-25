<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name');
            $table->rate('discount_percent');
            $table->string('price_level', 12)->default('retail');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('code', 30);
            $table->string('type', 12)->default('individual'); // individual|company|insurance|government
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('phone2', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('national_id', 40)->nullable();
            $table->string('cr_number', 50)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('classification', 20)->default('regular'); // regular|vip|corporate|blacklisted
            $table->money('credit_limit');
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->rate('discount_percent');
            $table->string('price_level', 12)->default('retail'); // retail|wholesale|workshop
            $table->string('source', 30)->nullable();
            $table->date('birth_date')->nullable();
            $table->boolean('marketing_opt_in')->default(true);
            $table->string('preferred_channel', 12)->default('sms');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'phone']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('position')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('code', 30);
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('cr_number', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 2)->default('OM');
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->money('credit_limit');
            $table->rate('rating');
            $table->text('bank_details')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('insurance_companies', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); // billing (AR) account
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('customer_id')->constrained();
            $table->string('name');
            $table->string('contract_number', 60)->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->decimal('monthly_spend_limit', 15, 3)->nullable();
            $table->decimal('per_job_limit', 15, 3)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->string('approver_name')->nullable();
            $table->string('approver_email')->nullable();
            $table->string('approver_phone', 30)->nullable();
            $table->rate('discount_percent');
            $table->unsignedInteger('maintenance_interval_km')->nullable();
            $table->unsignedSmallInteger('maintenance_interval_days')->nullable();
            $table->unsignedTinyInteger('billing_day')->default(1);
            $table->string('status', 12)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['fleets', 'insurance_companies', 'suppliers', 'customer_contacts', 'customers', 'customer_groups'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
