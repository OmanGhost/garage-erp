<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Warranty claim activity log: opened, status changes, supplier claim updates and notes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_claim_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warranty_claim_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20); // opened | status | supplier | note
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
        DB::table('warranty_claims')->orderBy('id')->each(function ($c) {
            DB::table('warranty_claim_events')->insert(['company_id' => $c->company_id, 'warranty_claim_id' => $c->id, 'event' => 'opened',
                'to_status' => 'open', 'notes' => $c->description, 'user_id' => $c->created_by, 'created_at' => $c->created_at, 'updated_at' => $c->created_at]);
            if ($c->status !== 'open') {
                DB::table('warranty_claim_events')->insert(['company_id' => $c->company_id, 'warranty_claim_id' => $c->id, 'event' => 'status',
                    'from_status' => 'open', 'to_status' => $c->status, 'notes' => $c->inspection_result, 'created_at' => $c->updated_at, 'updated_at' => $c->updated_at]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claim_events');
    }
};
