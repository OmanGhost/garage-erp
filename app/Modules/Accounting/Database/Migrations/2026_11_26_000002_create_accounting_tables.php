<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('type', 20); // asset|liability|equity|revenue|expense
            $table->string('normal_balance', 6); // debit|credit
            $table->boolean('is_group')->default(false);
            $table->string('system_key', 50)->nullable();
            $table->string('cash_flow_category', 20)->nullable(); // operating|investing|financing
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'system_key']);
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 10)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->userRef('closed_by');
            $table->timestamps();
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 10)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->userRef('closed_by');
            $table->timestamps();
            $table->index(['company_id', 'start_date', 'end_date']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->date('date');
            $table->string('description')->nullable();
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('purpose', 40)->default('main');
            $table->string('status', 12)->default('posted');
            $table->boolean('is_opening')->default(false);
            $table->money('total_debit');
            $table->money('total_credit');
            $table->timestamp('posted_at')->nullable();
            $table->userRef('posted_by');
            $table->unsignedBigInteger('reversal_of_id')->nullable()->index();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            // guarantees a business document is never posted twice for the same purpose
            $table->unique(['company_id', 'source_type', 'source_id', 'purpose'], 'je_source_unique');
            $table->index(['company_id', 'date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->index();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->money('debit');
            $table->money('credit');
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('party_type', 20)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedBigInteger('bank_reconciliation_id')->nullable()->index();
            $table->timestamps();
            $table->index(['party_type', 'party_id']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained('accounts');
            $table->string('type', 10)->default('cash'); // cash|bank|card|wallet
            $table->string('name');
            $table->string('bank_name')->nullable();
            $table->string('account_number', 60)->nullable();
            $table->string('iban', 60)->nullable();
            $table->string('currency', 3)->default('OMR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 12)->default('draft');
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('month');
            $table->money('amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['budget_lines', 'budgets', 'bank_accounts', 'journal_lines', 'journal_entries', 'fiscal_periods', 'fiscal_years', 'accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
