<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking & handover features:
 *  - complaints get a reference number and a tracking history (complaint_updates)
 *  - service reminders get contact tracking (reminder_contacts) and acknowledgement
 *  - gate log entries record who brought / collected the vehicle
 *  - vehicle handover authorizations (owner authorises another person to drop off / pick up)
 *  - job cards / check-ins record who actually delivered and collected the vehicle
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('number', 30)->nullable()->after('company_id')->index();
        });
        Schema::create('complaint_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // created | status | assigned | note | resolution
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('service_reminders', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable()->after('sent_at');
            $table->timestamp('last_contact_at')->nullable()->after('acknowledged_at');
            $table->unsignedSmallInteger('contact_attempts')->default(0)->after('last_contact_at');
            $table->string('last_outcome', 30)->nullable()->after('contact_attempts');
            $table->date('follow_up_on')->nullable()->after('last_outcome');
            $table->foreignId('appointment_id')->nullable()->after('follow_up_on')->constrained()->nullOnDelete();
        });
        Schema::create('reminder_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_reminder_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);  // call | whatsapp | sms | email | visit | system
            $table->string('outcome', 30);  // message_sent | reached | acknowledged | booked | no_answer | declined | wrong_number | failed
            $table->text('notes')->nullable();
            $table->foreignId('notification_log_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('contacted_at');
            $table->timestamps();
        });

        Schema::create('handover_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->index();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10);                 // drop_off | pick_up | both
            $table->string('person_name', 150);
            $table->string('id_type', 20)->nullable();   // civil_id | resident_card | passport | driving_license
            $table->string('id_number', 40);
            $table->string('person_phone', 30)->nullable();
            $table->string('relationship', 30)->nullable(); // driver | employee | family | friend | company_rep | other
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->boolean('single_use')->default(true);
            $table->string('status', 12)->default('pending')->index(); // pending | active | used | revoked
            $table->string('approval_method', 12)->nullable(); // code | signature
            $table->string('approval_code_hash')->nullable();
            $table->timestamp('code_sent_at')->nullable();
            $table->unsignedTinyInteger('code_attempts')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->unsignedSmallInteger('uses_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 250)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['vehicle_id', 'status']);
        });

        Schema::table('vehicle_gate_logs', function (Blueprint $table) {
            $table->string('person_name', 150)->nullable()->after('odometer');
            $table->string('person_id_number', 40)->nullable()->after('person_name');
            $table->foreignId('handover_authorization_id')->nullable()->after('person_id_number')->constrained()->nullOnDelete();
        });
        Schema::table('job_cards', function (Blueprint $table) {
            $table->string('delivered_to_type', 12)->nullable()->after('delivered_at'); // owner | authorized
            $table->string('delivered_to_name', 150)->nullable()->after('delivered_to_type');
            $table->string('delivered_to_id_number', 40)->nullable()->after('delivered_to_name');
            $table->foreignId('handover_authorization_id')->nullable()->after('delivered_to_id_number')->constrained()->nullOnDelete();
        });
        Schema::table('vehicle_check_ins', function (Blueprint $table) {
            $table->string('brought_by_type', 12)->nullable()->after('customer_id'); // owner | authorized
            $table->string('brought_by_name', 150)->nullable()->after('brought_by_type');
            $table->string('brought_by_id_number', 40)->nullable()->after('brought_by_name');
            $table->foreignId('handover_authorization_id')->nullable()->after('brought_by_id_number')->constrained()->nullOnDelete();
        });

        // approval-code message template for existing companies
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach (['mail', 'sms', 'whatsapp', 'database'] as $channel) {
                DB::table('notification_templates')->updateOrInsert(
                    ['company_id' => $companyId, 'event' => 'handover.code', 'channel' => $channel],
                    ['subject_ar' => 'رمز اعتماد تفويض', 'subject_en' => 'Authorization code',
                        'body_ar' => 'عزيزي {{customer_name}}، رمز اعتماد تفويض {{person}} لتسليم/استلام مركبتكم {{plate}} هو {{code}} (التفويض {{number}}). لا تشارك الرمز إلا إذا كنت توافق.',
                        'body_en' => 'Dear {{customer_name}}, the code to approve {{person}} to hand over your vehicle {{plate}} is {{code}} (authorization {{number}}). Share it only if you agree.',
                        'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        // existing complaints get a reference number (CMP-YYYY-000001 per company, in creation order)
        $counters = [];
        DB::table('complaints')->orderBy('id')->each(function ($c) use (&$counters) {
            $year = substr((string) $c->created_at, 0, 4) ?: date('Y');
            $key = $c->company_id.'-'.$year;
            $counters[$key] = ($counters[$key] ?? 0) + 1;
            DB::table('complaints')->where('id', $c->id)->update(['number' => sprintf('CMP-%s-%06d', $year, $counters[$key])]);
        });
        foreach ($counters as $key => $n) {
            [$company, $year] = explode('-', $key);
            if ((int) $year === (int) date('Y')) {
                DB::table('document_sequences')->updateOrInsert(
                    ['company_id' => $company, 'type' => 'complaint'],
                    ['prefix' => 'CMP', 'next_number' => $n + 1, 'padding' => 6, 'yearly_reset' => true, 'year' => (int) $year, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('vehicle_check_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handover_authorization_id');
            $table->dropColumn(['brought_by_type', 'brought_by_name', 'brought_by_id_number']);
        });
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handover_authorization_id');
            $table->dropColumn(['delivered_to_type', 'delivered_to_name', 'delivered_to_id_number']);
        });
        Schema::table('vehicle_gate_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handover_authorization_id');
            $table->dropColumn(['person_name', 'person_id_number']);
        });
        Schema::dropIfExists('handover_authorizations');
        Schema::dropIfExists('reminder_contacts');
        Schema::table('service_reminders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_id');
            $table->dropColumn(['acknowledged_at', 'last_contact_at', 'contact_attempts', 'last_outcome', 'follow_up_on']);
        });
        Schema::dropIfExists('complaint_updates');
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['number']);
            $table->dropColumn('number');
        });
    }
};
