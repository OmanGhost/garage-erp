<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digital vehicle inspection (zones on the vehicle diagram, measurements, before / after, customer report link,
 * signatures, customer decisions on findings) and the quality management system (SOPs, nonconformities,
 * corrective / preventive actions, internal audits).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_templates', function (Blueprint $table) {
            $table->string('kind', 12)->default('inspection')->after('vehicle_type'); // inspection|qc
        });
        Schema::table('inspection_template_items', function (Blueprint $table) {
            $table->string('unit', 20)->nullable()->after('label_en');
            $table->decimal('min_ok', 12, 3)->nullable()->after('unit');
            $table->decimal('max_ok', 12, 3)->nullable()->after('min_ok');
            $table->foreignId('service_id')->nullable()->after('max_ok')->constrained()->nullOnDelete();
        });
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('stage', 10)->default('before')->after('status'); // before|after
            $table->string('public_token', 64)->nullable()->unique()->after('stage');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('customer_viewed_at')->nullable();
            $table->string('technician_signature')->nullable();
            $table->timestamp('technician_signed_at')->nullable();
            $table->string('customer_signature')->nullable();
            $table->string('customer_signed_name')->nullable();
            $table->timestamp('customer_signed_at')->nullable();
        });
        Schema::table('inspection_results', function (Blueprint $table) {
            $table->decimal('value', 12, 3)->nullable()->after('result');
            $table->string('unit', 20)->nullable()->after('value');
        });
        Schema::table('inspection_findings', function (Blueprint $table) {
            $table->string('zone', 30)->nullable()->after('description');
            $table->unsignedBigInteger('result_id')->nullable()->after('zone');
            $table->string('customer_decision', 10)->nullable(); // approved|declined
            $table->timestamp('decided_at')->nullable();
        });

        Schema::create('quality_sops', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('code', 30);
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('version', 20)->default('1.0');
            $table->string('status', 12)->default('draft'); // draft|active|obsolete
            $table->date('effective_date')->nullable();
            $table->date('review_date')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code', 'version']);
        });

        Schema::create('quality_audits', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('title');
            $table->string('area', 60)->nullable();
            $table->unsignedBigInteger('auditor_id')->nullable();
            $table->date('planned_date');
            $table->timestamp('performed_at')->nullable();
            $table->string('status', 12)->default('planned'); // planned|in_progress|completed
            $table->json('checklist')->nullable();
            $table->decimal('score', 5, 1)->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('nonconformities', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('source', 20)->default('internal'); // job_card|comeback|supplier|part|internal_audit|customer_complaint|warranty|internal
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quality_audit_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('complaint_id')->nullable();
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 20)->default('workmanship'); // workmanship|part_defect|procedure|equipment|communication|supplier|other
            $table->string('severity', 10)->default('minor'); // minor|major|critical
            $table->date('detected_at');
            $table->unsignedBigInteger('detected_by')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->text('root_cause')->nullable();
            $table->string('analysis_method', 12)->nullable(); // 5why|fishbone|other
            $table->decimal('cost', 15, 3)->default(0);
            $table->string('status', 14)->default('open'); // open|investigating|action|verification|closed
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('capa_actions', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('nonconformity_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12)->default('corrective'); // corrective|preventive
            $table->string('description');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 12)->default('open'); // open|in_progress|done|verified|cancelled
            $table->timestamp('completed_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('effective')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['capa_actions', 'nonconformities', 'quality_audits', 'quality_sops'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('inspection_findings', fn (Blueprint $t) => $t->dropColumn(['zone', 'result_id', 'customer_decision', 'decided_at']));
        Schema::table('inspection_results', fn (Blueprint $t) => $t->dropColumn(['value', 'unit']));
        Schema::table('inspections', function (Blueprint $t) {
            $t->dropUnique(['public_token']);
            $t->dropColumn(['stage', 'public_token', 'sent_at', 'customer_viewed_at', 'technician_signature', 'technician_signed_at', 'customer_signature', 'customer_signed_name', 'customer_signed_at']);
        });
        Schema::table('inspection_template_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('service_id');
            $t->dropColumn(['unit', 'min_ok', 'max_ok']);
        });
        Schema::table('inspection_templates', fn (Blueprint $t) => $t->dropColumn('kind'));
    }
};
