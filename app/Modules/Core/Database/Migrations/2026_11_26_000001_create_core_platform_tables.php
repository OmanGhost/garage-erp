<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('billing_cycle', 10)->default('monthly'); // monthly|yearly
            $table->string('status', 20)->default('active'); // trial|active|past_due|cancelled|expired
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->date('trial_ends_at')->nullable();
            $table->money('amount');
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->money('amount');
            $table->string('status', 20)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('type', 50);
            $table->string('prefix', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->boolean('yearly_reset')->default(true);
            $table->unsignedSmallInteger('year')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'type']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->morphs('attachable');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('category', 50)->nullable();
            $table->string('caption')->nullable();
            $table->userRef('uploaded_by');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event', 60);
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->string('failure_reason', 100)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('key', 100);
            $table->string('route')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->unique(['user_id', 'key']);
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name');
            $table->rate('rate');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name');
            $table->date('date');
            $table->date('end_date')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('event', 60);
            $table->string('channel', 20); // mail|sms|whatsapp|database
            $table->string('subject_ar')->nullable();
            $table->string('subject_en')->nullable();
            $table->text('body_ar');
            $table->text('body_en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'event', 'channel']);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('provider', 40);
            $table->text('credentials')->nullable(); // encrypted
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'provider']);
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('url', 500);
            $table->json('events');
            $table->text('secret'); // encrypted
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->json('payload');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('channel', 20);
            $table->string('recipient');
            $table->string('event', 60)->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->nullableMorphs('related');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'notification_logs', 'webhook_deliveries', 'webhooks', 'integrations', 'notification_templates', 'holidays', 'working_hours', 'tax_rates', 'idempotency_keys', 'login_histories', 'audit_logs', 'attachments', 'document_sequences', 'subscription_invoices', 'subscriptions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
