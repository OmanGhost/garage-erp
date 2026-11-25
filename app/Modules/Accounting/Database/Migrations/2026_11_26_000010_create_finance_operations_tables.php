<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->date('date');
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->money('amount');
            $table->money('tax_amount');
            $table->money('total');
            $table->string('description');
            $table->string('reference', 100)->nullable();
            $table->string('status', 12)->default('draft');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('fund_transfers', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('number', 40);
            $table->date('date');
            $table->foreignId('from_bank_account_id')->constrained('bank_accounts');
            $table->foreignId('to_bank_account_id')->constrained('bank_accounts');
            $table->money('amount');
            $table->string('reference', 100)->nullable();
            $table->string('notes')->nullable();
            $table->string('status', 12)->default('draft');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('bank_account_id')->constrained();
            $table->date('statement_date');
            $table->money('statement_balance');
            $table->money('book_balance');
            $table->money('difference');
            $table->string('status', 12)->default('draft');
            $table->timestamp('completed_at')->nullable();
            $table->string('notes')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('category', 30)->nullable();
            $table->foreignId('asset_account_id')->constrained('accounts');
            $table->foreignId('accumulated_account_id')->constrained('accounts');
            $table->foreignId('expense_account_id')->constrained('accounts');
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->date('purchase_date');
            $table->date('depreciation_start');
            $table->money('cost');
            $table->money('salvage_value');
            $table->unsignedSmallInteger('useful_life_months');
            $table->string('method', 20)->default('straight_line');
            $table->money('accumulated_depreciation');
            $table->money('book_value');
            $table->string('status', 20)->default('active'); // active|disposed|fully_depreciated
            $table->date('disposed_at')->nullable();
            $table->money('disposal_amount');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->date('period_date');
            $table->money('amount');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
            $table->unique(['fixed_asset_id', 'period_date']);
        });
    }

    public function down(): void
    {
        foreach (['asset_depreciations', 'fixed_assets', 'bank_reconciliations', 'fund_transfers', 'expenses'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
