<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** POS cash sales: the cash handed over by the customer and the change given back, printed on the receipt. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('cash_tendered', 15, 3)->nullable();
            $table->decimal('change_given', 15, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['cash_tendered', 'change_given']));
    }
};
