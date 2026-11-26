<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verification data for website trial requests: visitor country (from the IP) and a JSON bag with
 * technical details (browser data, IP intelligence, email domain checks) used by the super admin to
 * judge whether a request is genuine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_requests', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('city')->index();
            $table->json('meta')->nullable()->after('source');
            $table->timestamp('checked_at')->nullable()->after('meta');
        });
    }

    public function down(): void
    {
        Schema::table('trial_requests', function (Blueprint $table) {
            $table->dropIndex(['country_code']);
            $table->dropColumn(['country_code', 'meta', 'checked_at']);
        });
    }
};
