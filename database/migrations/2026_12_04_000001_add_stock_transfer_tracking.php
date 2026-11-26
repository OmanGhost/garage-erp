<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock transfer tracking: who requested (created_by), dispatched, received or cancelled the transfer, and an
 * activity log (stock_transfer_events) with every step and note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('sent_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('received_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 250)->nullable()->after('cancelled_by');
        });
        Schema::create('stock_transfer_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20); // requested | dispatched | received | cancelled | note | printed
            $table->text('notes')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // history for existing transfers from the dates already stored
        DB::table('stock_transfers')->orderBy('id')->each(function ($t) {
            $rows = [['event' => 'requested', 'user_id' => $t->created_by, 'created_at' => $t->created_at]];
            if ($t->sent_at) {
                $rows[] = ['event' => 'dispatched', 'user_id' => null, 'created_at' => $t->sent_at];
            }
            if ($t->received_at) {
                $rows[] = ['event' => 'received', 'user_id' => $t->received_by, 'created_at' => $t->received_at];
            }
            foreach ($rows as $r) {
                DB::table('stock_transfer_events')->insert($r + ['company_id' => $t->company_id, 'stock_transfer_id' => $t->id, 'updated_at' => $r['created_at']]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_events');
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });
    }
};
