<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Messages sent from the public website contact form (platform level, not per company). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('company', 160)->nullable();
            $table->string('email', 160);
            $table->string('phone', 30)->nullable();
            $table->string('topic', 20)->default('other');
            $table->text('message');
            $table->string('locale', 2)->default('ar');
            $table->string('status', 12)->default('new'); // new|replied|archived
            $table->text('admin_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
