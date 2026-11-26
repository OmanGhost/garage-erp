<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Credit notes keep a link to the refund voucher paid to the customer (cash / bank), so the credit
 * note screen and its printout can show how it was settled. Existing refunds are linked by their notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreignId('refund_payment_id')->nullable()->after('refunded_amount')->constrained('payments')->nullOnDelete();
        });

        DB::table('credit_notes')->whereNotNull('number')->where('refunded_amount', '>', 0)->orderBy('id')
            ->each(function ($cn) {
                $payment = DB::table('payments')->where('company_id', $cn->company_id)->where('direction', 'out')
                    ->where('party_type', 'customer')->where('party_id', $cn->customer_id)
                    ->where('notes', 'like', '%'.$cn->number)->orderBy('id')->value('id');
                if ($payment) {
                    DB::table('credit_notes')->where('id', $cn->id)->update(['refund_payment_id' => $payment]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_payment_id');
        });
    }
};
