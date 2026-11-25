<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public marketing website: platform-wide settings (SEO, contact) and trial requests.
 * These tables are NOT tenant-scoped; they belong to the platform (super admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('trial_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('company_name', 190);
            $table->string('email', 190)->index();
            $table->string('phone', 30);
            $table->string('city', 80)->nullable();
            $table->string('business_type', 30)->nullable(); // workshop|parts|both|dismantling|fleet
            $table->unsignedSmallInteger('branches')->nullable();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message')->nullable();
            $table->string('locale', 5)->default('ar');
            $table->string('status', 20)->default('new')->index(); // new|contacted|provisioned|rejected
            $table->text('admin_notes')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('source', 120)->nullable(); // utm_source / referrer
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_requests');
        Schema::dropIfExists('platform_settings');
    }
};
