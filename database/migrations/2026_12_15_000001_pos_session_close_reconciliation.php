<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Closing a cash session: the reason for any over / short is mandatory and kept apart from the notes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->string('difference_reason', 500)->nullable()->after('difference');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', fn (Blueprint $t) => $t->dropColumn('difference_reason'));
    }
};
