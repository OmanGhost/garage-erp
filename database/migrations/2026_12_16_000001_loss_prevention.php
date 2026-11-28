<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal fraud & loss prevention: alerts raised by the monitoring rules, and a seal (HMAC) on every new audit log
 * row so that a row changed outside the application is detected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('rule', 30);
            $table->string('severity', 8)->default('medium'); // low|medium|high
            $table->unsignedBigInteger('user_id')->nullable();   // who performed the action
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('title');
            $table->decimal('amount', 15, 3)->nullable();
            $table->json('details')->nullable();
            $table->string('fingerprint', 120);
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('status', 14)->default('unreviewed'); // unreviewed|cleared|substantiated
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'fingerprint']);
            $table->index(['company_id', 'status', 'severity']);
            $table->index(['company_id', 'user_id', 'rule']);
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('seal', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', fn (Blueprint $t) => $t->dropColumn('seal'));
        Schema::dropIfExists('fraud_alerts');
    }
};
