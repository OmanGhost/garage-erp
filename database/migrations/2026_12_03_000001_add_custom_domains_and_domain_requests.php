<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom domains for clients:
 *  - plans say whether a custom domain is included and the yearly fee for registering a new domain
 *  - companies keep the domain verification state (pending DNS → active / disabled)
 *  - domain_requests: a client orders a new domain or asks to connect a domain it owns; the platform admin handles it
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('allow_custom_domain')->default(false)->after('modules');
            $table->decimal('domain_fee', 15, 3)->nullable()->after('allow_custom_domain'); // yearly fee to register a new domain
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->string('domain_status', 20)->nullable()->after('custom_domain'); // pending | active | disabled
            $table->string('domain_token', 64)->nullable()->after('domain_status');
            $table->timestamp('domain_verified_at')->nullable()->after('domain_token');
        });
        Schema::create('domain_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                 // register | connect
            $table->string('domain', 190);
            $table->string('alternatives', 255)->nullable();
            $table->unsignedTinyInteger('years')->default(1);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('new')->index(); // new | processing | completed | rejected | cancelled
            $table->decimal('price', 15, 3)->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        // existing custom domains (entered before) are treated as active
        \Illuminate\Support\Facades\DB::table('companies')->whereNotNull('custom_domain')->update(['domain_status' => 'active', 'domain_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_requests');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['domain_status', 'domain_token', 'domain_verified_at']);
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['allow_custom_domain', 'domain_fee']);
        });
    }
};
