<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level client (tenant) information managed by the super admin:
 * the person to contact about the subscription, internal notes and lead source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('contact_name', 190)->nullable()->after('phone');
            $table->string('contact_phone', 30)->nullable()->after('contact_name');
            $table->text('admin_notes')->nullable()->after('is_active');
            $table->string('source', 40)->nullable()->after('admin_notes'); // trial_request|direct|referral|other
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_phone', 'admin_notes', 'source']);
        });
    }
};
