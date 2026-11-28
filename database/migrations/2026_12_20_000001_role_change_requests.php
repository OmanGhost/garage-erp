<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Requests to change a user's role: reason, approval by a branch manager or higher, printable decision. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_change_requests', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('number', 40);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();       // whose role changes
            $table->string('current_role', 60)->nullable();
            $table->string('requested_role', 60);
            $table->text('reason');
            $table->date('effective_date')->nullable();
            $table->string('status', 12)->default('pending');                      // pending|approved|rejected|cancelled
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_change_requests');
    }
};
