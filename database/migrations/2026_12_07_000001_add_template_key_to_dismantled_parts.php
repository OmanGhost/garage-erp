<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extracted parts remember which standard checklist part they are, to suggest OEM numbers for the same vehicle. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dismantled_parts', function (Blueprint $table) {
            $table->string('template_key', 40)->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('dismantled_parts', function (Blueprint $table) {
            $table->dropIndex(['template_key']);
            $table->dropColumn('template_key');
        });
    }
};
