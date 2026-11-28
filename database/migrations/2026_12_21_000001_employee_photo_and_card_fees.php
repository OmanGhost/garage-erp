<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee photo; card processing fee per card terminal / bank account (percentage with a maximum per transaction),
 * and the fee and net amount kept on every receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
        });
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->decimal('card_fee_percent', 6, 3)->default(0);
            $table->decimal('card_fee_max', 15, 3)->nullable();     // null / 0 = no maximum
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('fee_amount', 15, 3)->default(0);
            $table->decimal('net_amount', 15, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['fee_amount', 'net_amount']));
        Schema::table('bank_accounts', fn (Blueprint $t) => $t->dropColumn(['card_fee_percent', 'card_fee_max']));
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('photo_path'));
    }
};
