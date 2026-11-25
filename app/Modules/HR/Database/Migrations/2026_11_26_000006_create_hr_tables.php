<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->userRef('manager_id');
            $table->timestamps();
        });

        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->userRef('user_id');
            $table->string('code', 30);
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->foreignId('hr_department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nationality', 2)->nullable();
            $table->string('gender', 6)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('national_id', 40)->nullable();
            $table->date('id_expiry')->nullable();
            $table->string('passport_number', 40)->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('visa_number', 40)->nullable();
            $table->date('visa_expiry')->nullable();
            $table->date('driving_license_expiry')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('status', 12)->default('active');
            $table->money('basic_salary');
            $table->money('housing_allowance');
            $table->money('transport_allowance');
            $table->money('other_allowances');
            $table->money('hourly_cost');
            $table->rate('commission_rate');
            $table->string('bank_name')->nullable();
            $table->string('iban', 60)->nullable();
            $table->boolean('is_technician')->default(false);
            $table->text('skills')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number', 60)->nullable();
            $table->string('type', 20)->default('limited');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->money('basic_salary');
            $table->string('status', 12)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status', 12)->default('present');
            $table->string('source', 12)->default('manual');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->unsignedSmallInteger('days_per_year')->default(30);
            $table->boolean('is_paid')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 1)->default(1);
            $table->string('reason')->nullable();
            $table->string('status', 12)->default('pending');
            $table->userRef('approved_by');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->money('amount');
            $table->money('installment');
            $table->money('remaining');
            $table->string('status', 12)->default('active');
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_adjustments', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // allowance|bonus|incentive|deduction|overtime|penalty
            $table->money('amount');
            $table->date('date');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('payroll_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->date('period_month');
            $table->string('status', 12)->default('draft'); // draft|approved|posted|paid
            $table->money('total_gross');
            $table->money('total_deductions');
            $table->money('total_net');
            $table->userRef('approved_by');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->money('basic');
            $table->money('allowances');
            $table->money('overtime');
            $table->money('commissions');
            $table->money('bonuses');
            $table->money('deductions');
            $table->money('loan_deduction');
            $table->money('absence_deduction');
            $table->money('gross');
            $table->money('net');
            $table->decimal('worked_days', 5, 1)->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->userRef('reviewer_id');
            $table->string('period', 20);
            $table->rate('score');
            $table->json('criteria')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['performance_reviews', 'payroll_items', 'payrolls', 'employee_adjustments', 'employee_loans', 'leave_requests', 'leave_types', 'attendances', 'employee_contracts', 'employees', 'job_titles', 'hr_departments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
