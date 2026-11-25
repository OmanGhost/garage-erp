<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // global reference data shared by all tenants
        Schema::create('vehicle_makes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_ar')->nullable();
            $table->string('country', 2)->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_make_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('vehicle_type', 20)->default('car');
            $table->timestamps();
            $table->unique(['vehicle_make_id', 'name']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fleet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plate_number', 20)->nullable();
            $table->string('plate_code', 10)->nullable();
            $table->string('plate_country', 2)->default('OM');
            $table->string('vin', 17)->nullable();
            $table->foreignId('vehicle_make_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trim', 60)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('engine_number', 60)->nullable();
            $table->string('engine_type', 60)->nullable();
            $table->string('engine_capacity', 20)->nullable();
            $table->string('transmission', 20)->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->string('drive_type', 10)->nullable();
            $table->string('color', 40)->nullable();
            $table->unsignedInteger('odometer')->default(0);
            $table->string('odometer_unit', 2)->default('km');
            $table->string('vehicle_type', 20)->default('car');
            $table->foreignId('insurance_company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('insurance_policy_number', 60)->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('registration_expiry')->nullable();
            $table->date('next_service_date')->nullable();
            $table->unsignedInteger('next_service_odometer')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'plate_number']);
            $table->index(['company_id', 'vin']);
        });

        Schema::create('vehicle_ownerships', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('to_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('changed_at');
            $table->string('notes')->nullable();
            $table->userRef('user_id');
            $table->timestamps();
        });

        Schema::create('vehicle_accidents', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('accident_date');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->text('damage_description')->nullable();
            $table->string('police_report_number', 60)->nullable();
            $table->unsignedBigInteger('insurance_claim_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['vehicle_accidents', 'vehicle_ownerships', 'vehicles', 'vehicle_models', 'vehicle_makes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
