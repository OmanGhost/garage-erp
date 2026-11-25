<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->userRef('supervisor_id');
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->default(5);
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('department_user', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['department_id', 'user_id']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->string('pricing_type', 10)->default('fixed'); // fixed|hourly
            $table->money('price');
            $table->money('hourly_rate');
            $table->money('estimated_cost');
            $table->unsignedInteger('estimated_minutes')->default(60);
            $table->rate('tax_rate')->default(5);
            $table->unsignedSmallInteger('warranty_days')->default(0);
            $table->unsignedInteger('warranty_km')->default(0);
            $table->string('commission_type', 10)->default('none'); // none|percent|fixed
            $table->money('commission_value');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('service_vehicle_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_type', 20);
            $table->money('price');
            $table->timestamps();
            $table->unique(['service_id', 'vehicle_type']);
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('code', 30);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->money('price');
            $table->unsignedInteger('interval_km')->nullable();
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->cascadeOnDelete();
            $table->qty('qty')->default(1);
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name');
            $table->string('code', 40)->nullable();
            $table->string('type', 10)->default('percent'); // percent|fixed
            $table->money('value');
            $table->string('applies_to', 10)->default('all'); // all|services|parts
            $table->money('min_amount');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workshop_bays', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('type', 20)->default('general'); // lift|pit|wash|paint_booth|alignment|general
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inspection_templates', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('vehicle_type', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inspection_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_template_id')->constrained()->cascadeOnDelete();
            $table->string('section', 100);
            $table->string('label');
            $table->string('label_en')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('job_cards', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending_inspection');
            $table->string('priority', 10)->default('normal'); // low|normal|high|urgent
            $table->string('job_type', 20)->default('repair'); // repair|maintenance|warranty|insurance|internal|fleet
            $table->userRef('service_advisor_id');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bay_id')->nullable()->constrained('workshop_bays')->nullOnDelete();
            $table->foreignId('fleet_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('insurance_claim_id')->nullable()->index();
            $table->unsignedBigInteger('warranty_claim_id')->nullable()->index();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->dateTime('received_at');
            $table->dateTime('promised_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedInteger('odometer_in')->nullable();
            $table->unsignedInteger('odometer_out')->nullable();
            $table->unsignedTinyInteger('fuel_level')->nullable(); // 0..100 %
            $table->text('customer_complaint')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('internal_notes')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->money('estimated_total');
            $table->money('approved_total');
            $table->money('labor_cost');
            $table->money('parts_cost');
            $table->money('total_cost');
            $table->money('revenue');
            $table->money('profit');
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->string('quality_status', 10)->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('vehicle_check_ins', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->dateTime('received_at');
            $table->userRef('received_by');
            $table->unsignedInteger('odometer')->nullable();
            $table->unsignedTinyInteger('fuel_level')->nullable();
            $table->text('reported_issues')->nullable();
            $table->json('existing_damages')->nullable();
            $table->json('personal_items')->nullable();
            $table->json('checklist')->nullable();
            $table->dateTime('expected_delivery_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('customer_signed_name')->nullable();
            $table->string('receipt_token', 64)->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_gate_logs', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 3); // in|out
            $table->dateTime('logged_at');
            $table->userRef('user_id');
            $table->unsignedInteger('odometer')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_template_id')->nullable()->constrained()->nullOnDelete();
            $table->userRef('technician_id');
            $table->string('status', 12)->default('in_progress');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('summary')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamps();
        });

        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('template_item_id')->nullable();
            $table->string('section', 100)->nullable();
            $table->string('label');
            $table->string('result', 12)->default('ok'); // ok|attention|urgent|na
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inspection_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('dtc_code', 20)->nullable()->index();
            $table->string('severity', 10)->default('medium'); // low|medium|high|critical
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->qty('qty')->default(1);
            $table->money('estimated_price');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('type', 10)->default('workshop'); // workshop|sales
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_supplementary')->default(false);
            $table->string('status', 20)->default('draft');
            $table->date('valid_until')->nullable();
            $table->money('subtotal');
            $table->money('discount_total');
            $table->money('tax_total');
            $table->money('total');
            $table->money('approved_total');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('response_by')->nullable();
            $table->string('response_channel', 20)->nullable();
            $table->text('response_notes')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('access_token', 64)->unique();
            $table->userRef('created_by');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'number', 'version']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('option_group', 20)->nullable();
            $table->string('item_type', 10)->default('service'); // service|part|labor|other
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('part_condition', 12)->nullable(); // new|used|refurbished
            $table->qty('qty')->default(1);
            $table->money('unit_price');
            $table->money('discount');
            $table->rate('tax_rate');
            $table->money('tax_amount');
            $table->money('line_total');
            $table->string('approval_status', 10)->default('pending');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('job_card_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->userRef('technician_id');
            $table->foreignId('quotation_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('status', 12)->default('pending'); // pending|in_progress|paused|done|cancelled
            $table->boolean('is_additional')->default(false);
            $table->string('approval_status', 10)->default('approved'); // approved|pending|rejected
            $table->qty('qty')->default(1);
            $table->money('unit_price');
            $table->money('discount');
            $table->rate('tax_rate');
            $table->money('line_total');
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->unsignedInteger('actual_minutes')->default(0);
            $table->money('labor_cost');
            $table->money('commission_amount');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('job_card_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_task_id')->constrained()->cascadeOnDelete();
            $table->userRef('technician_id');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->timestamps();
        });

        Schema::create('job_card_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->string('part_condition', 12)->nullable();
            $table->qty('qty');
            $table->qty('qty_reserved');
            $table->qty('qty_issued');
            $table->qty('qty_returned');
            $table->money('unit_price');
            $table->money('discount');
            $table->rate('tax_rate');
            $table->cost('unit_cost');
            $table->money('total_cost');
            $table->string('status', 20)->default('requested');
            $table->boolean('is_additional')->default(false);
            $table->string('approval_status', 10)->default('approved');
            $table->userRef('requested_by');
            $table->userRef('issued_by');
            $table->userRef('issued_to');
            $table->dateTime('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('job_card_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->userRef('user_id');
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('job_card_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->userRef('user_id');
            $table->text('note');
            $table->boolean('visible_to_customer')->default(false);
            $table->timestamps();
        });

        Schema::create('quality_checks', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->userRef('inspector_id');
            $table->string('result', 6); // pass|fail
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('checked_at');
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 12); // technician|sales
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->money('base_amount');
            $table->rate('rate');
            $table->money('amount');
            $table->string('status', 10)->default('pending'); // pending|paid|cancelled
            $table->unsignedBigInteger('payroll_id')->nullable();
            $table->date('earned_at');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 12)->default('open');
            $table->userRef('assigned_to');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('source', 10)->default('staff');
            $table->timestamps();
        });

        Schema::create('service_reminders', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('due_odometer')->nullable();
            $table->string('status', 12)->default('scheduled');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('last_job_card_id')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('service_ratings', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique('job_card_id');
        });
    }

    public function down(): void
    {
        foreach (['service_ratings', 'service_reminders', 'complaints', 'commissions', 'quality_checks', 'job_card_notes', 'job_card_status_histories', 'job_card_parts', 'job_card_time_logs', 'job_card_tasks', 'quotation_items', 'quotations', 'inspection_findings', 'inspection_results', 'inspections', 'vehicle_gate_logs', 'vehicle_check_ins', 'job_cards', 'inspection_template_items', 'inspection_templates', 'workshop_bays', 'promotions', 'service_package_items', 'service_packages', 'service_vehicle_prices', 'services', 'department_user', 'departments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
