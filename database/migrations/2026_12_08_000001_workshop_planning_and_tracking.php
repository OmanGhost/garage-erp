<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workshop resource planning (task scheduling per technician and bay, estimated completion) and
 * vehicle & key tracking (yard locations, key hooks / holders, test-drive permits, QR / RFID key tags).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_bays', function (Blueprint $table) {
            $table->boolean('has_lift')->default(false)->after('type');
            $table->unsignedInteger('lift_capacity_kg')->nullable()->after('has_lift');
            $table->unsignedSmallInteger('sort')->default(0)->after('lift_capacity_kg');
        });
        Schema::table('job_card_tasks', function (Blueprint $table) {
            $table->dateTime('scheduled_start')->nullable()->after('finished_at');
            $table->dateTime('scheduled_end')->nullable()->after('scheduled_start');
            $table->foreignId('bay_id')->nullable()->after('scheduled_end')->constrained('workshop_bays')->nullOnDelete();
            $table->index(['technician_id', 'scheduled_start']);
            $table->index(['bay_id', 'scheduled_start']);
        });

        Schema::create('yard_locations', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('zone', 60)->nullable();
            $table->string('type', 20)->default('parking'); // parking|bay|wash|waiting|road_test|outside
            $table->foreignId('bay_id')->nullable()->constrained('workshop_bays')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::table('job_cards', function (Blueprint $table) {
            $table->dateTime('estimated_completion_at')->nullable()->after('promised_at');
            $table->string('key_tag', 40)->nullable()->after('fuel_level');
            $table->string('key_hook', 30)->nullable()->after('key_tag');
            $table->unsignedBigInteger('key_holder_id')->nullable()->after('key_hook');
            $table->foreignId('yard_location_id')->nullable()->after('key_holder_id')->constrained('yard_locations')->nullOnDelete();
            $table->index(['company_id', 'key_tag']);
        });

        Schema::create('test_drives', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained();
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('authorized_by')->nullable();
            $table->string('purpose')->nullable();
            $table->string('route')->nullable();
            $table->unsignedSmallInteger('max_km')->nullable();
            $table->boolean('customer_consent')->default(false);
            $table->string('status', 12)->default('approved'); // approved|out|returned|cancelled
            $table->unsignedInteger('odometer_out')->nullable();
            $table->unsignedInteger('odometer_in')->nullable();
            $table->unsignedTinyInteger('fuel_out')->nullable();
            $table->unsignedTinyInteger('fuel_in')->nullable();
            $table->dateTime('out_at')->nullable();
            $table->dateTime('in_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('vehicle_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained();
            $table->string('type', 20); // moved|key_out|key_in|test_out|test_in|tagged
            $table->foreignId('yard_location_id')->nullable()->constrained('yard_locations')->nullOnDelete();
            $table->string('key_hook', 30)->nullable();
            $table->unsignedBigInteger('holder_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->string('via', 10)->default('web'); // web|qr|rfid
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['company_id', 'job_card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_tracking_events');
        Schema::dropIfExists('test_drives');
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('yard_location_id');
            $table->dropIndex(['company_id', 'key_tag']);
            $table->dropColumn(['estimated_completion_at', 'key_tag', 'key_hook', 'key_holder_id']);
        });
        Schema::dropIfExists('yard_locations');
        Schema::table('job_card_tasks', function (Blueprint $table) {
            $table->dropIndex(['technician_id', 'scheduled_start']);
            $table->dropIndex(['bay_id', 'scheduled_start']);
            $table->dropConstrainedForeignId('bay_id');
            $table->dropColumn(['scheduled_start', 'scheduled_end']);
        });
        Schema::table('workshop_bays', function (Blueprint $table) {
            $table->dropColumn(['has_lift', 'lift_capacity_kg', 'sort']);
        });
    }
};
