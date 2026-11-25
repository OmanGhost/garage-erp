<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit note lines keep their share of the invoice line discount, so a return is valued
 * at the price actually charged on the invoice (not the list price before discount).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->money('discount')->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
