<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employee loans: internal financing with a profit rate, an installment schedule and a payment history.
 * Attendance: one-click self check-in / check-out (IP recorded).
 */
return new class extends Migration
{
    public function up(): void
    {
        // income from internal financing for every company that already exists
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            if (! DB::table('accounts')->where('company_id', $companyId)->exists()
                || DB::table('accounts')->where('company_id', $companyId)->where('system_key', 'financing_income')->exists()) {
                continue;
            }
            $code = '4108';
            while (DB::table('accounts')->where('company_id', $companyId)->where('code', $code)->exists()) {
                $code = (string) ((int) $code + 1);
            }
            DB::table('accounts')->insert(['company_id' => $companyId, 'code' => $code, 'name_ar' => 'إيرادات تمويل الموظفين', 'name_en' => 'Employee Financing Income',
                'type' => 'revenue', 'normal_balance' => 'credit', 'parent_id' => DB::table('accounts')->where('company_id', $companyId)->where('code', '4')->value('id'),
                'system_key' => 'financing_income', 'is_group' => false, 'is_active' => true, 'cash_flow_category' => 'operating', 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::table('employee_loans', function (Blueprint $table) {
            $table->string('loan_type', 12)->default('advance')->after('date'); // advance|financing
            $table->decimal('profit_rate', 7, 3)->default(0)->after('amount');
            $table->decimal('profit_amount', 15, 3)->default(0)->after('profit_rate');
            $table->decimal('total_payable', 15, 3)->default(0)->after('profit_amount');
            $table->unsignedSmallInteger('installments_count')->default(1)->after('installment');
            $table->date('first_month')->nullable()->after('installments_count');
            $table->decimal('profit_collected', 15, 3)->default(0)->after('remaining');
        });
        Schema::create('employee_loan_payments', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('employee_loan_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 3);
            $table->decimal('principal', 15, 3)->default(0);
            $table->decimal('profit', 15, 3)->default(0);
            $table->string('source', 12)->default('payroll'); // payroll|manual|settlement|opening
            $table->unsignedBigInteger('payroll_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['employee_loan_id', 'date']);
        });

        // existing loans: plain advances, first deduction in the month they were given, what was already repaid as history
        foreach (DB::table('employee_loans')->get() as $l) {
            $installments = (float) $l->installment > 0 ? (int) ceil((float) $l->amount / (float) $l->installment) : 1;
            DB::table('employee_loans')->where('id', $l->id)->update(['total_payable' => $l->amount, 'installments_count' => max(1, $installments),
                'first_month' => date('Y-m-01', strtotime((string) $l->date))]);
            $paid = round((float) $l->amount - (float) $l->remaining, 3);
            if ($paid > 0) {
                DB::table('employee_loan_payments')->insert(['company_id' => $l->company_id, 'employee_loan_id' => $l->id, 'date' => date('Y-m-d'),
                    'amount' => $paid, 'principal' => $paid, 'profit' => 0, 'source' => 'opening', 'notes' => 'Repaid before payment history was kept',
                    'created_at' => now(), 'updated_at' => now()]);
            }
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('check_in_ip', 45)->nullable()->after('check_out');
            $table->string('check_out_ip', 45)->nullable()->after('check_in_ip');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', fn (Blueprint $t) => $t->dropColumn(['check_in_ip', 'check_out_ip']));
        Schema::dropIfExists('employee_loan_payments');
        Schema::table('employee_loans', fn (Blueprint $t) => $t->dropColumn(['loan_type', 'profit_rate', 'profit_amount', 'total_payable', 'installments_count', 'first_month', 'profit_collected']));
    }
};
