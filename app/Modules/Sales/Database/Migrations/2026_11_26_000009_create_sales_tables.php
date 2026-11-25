<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->foreignId('bank_account_id')->constrained(); // the cash box
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_register_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('number', 40);
            $table->dateTime('opened_at');
            $table->money('opening_cash');
            $table->dateTime('closed_at')->nullable();
            $table->money('expected_cash');
            $table->decimal('counted_cash', 15, 3)->nullable();
            $table->money('difference');
            $table->string('status', 10)->default('open');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->nullable();
            $table->string('type', 12)->default('sale'); // sale|pos|workshop|insurance|fleet|store
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pos_session_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('insurance_claim_id')->nullable()->index();
            $table->foreignId('fleet_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('store_order_id')->nullable()->index();
            $table->unsignedBigInteger('sales_order_id')->nullable()->index();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('price_level', 12)->default('retail');
            $table->string('payment_terms', 10)->default('cash'); // cash|credit
            $table->string('status', 20)->default('draft'); // draft|held|posted|partially_paid|paid|cancelled
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->money('subtotal');
            $table->money('discount_total');
            $table->money('tax_total');
            $table->money('total');
            $table->money('paid_amount');
            $table->money('cost_total');
            $table->userRef('salesperson_id');
            $table->string('held_label')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('uuid')->unique();
            $table->timestamp('posted_at')->nullable();
            $table->userRef('posted_by');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issue_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 10)->default('part'); // part|service|labor|other
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('job_card_task_id')->nullable();
            $table->unsignedBigInteger('job_card_part_id')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->qty('qty')->default(1);
            $table->money('unit_price');
            $table->money('discount');
            $table->rate('tax_rate');
            $table->money('tax_amount');
            $table->money('line_total');
            $table->cost('unit_cost');
            $table->money('cost_total');
            $table->qty('returned_qty');
            $table->unsignedSmallInteger('warranty_days')->default(0);
            $table->unsignedInteger('warranty_km')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->nullable();
            $table->foreignId('invoice_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->boolean('restock')->default(true);
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('draft');
            $table->money('subtotal');
            $table->money('tax_total');
            $table->money('total');
            $table->money('cost_total');
            $table->money('refunded_amount');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->userRef('posted_by');
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->qty('qty');
            $table->money('unit_price');
            $table->rate('tax_rate');
            $table->money('tax_amount');
            $table->money('line_total');
            $table->cost('unit_cost');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('direction', 3); // in|out
            $table->string('party_type', 20)->nullable(); // customer|supplier|employee|other
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('method', 20)->default('cash'); // cash|card|bank_transfer|cheque|online|wallet
            $table->foreignId('bank_account_id')->constrained();
            $table->money('amount');
            $table->date('date');
            $table->string('reference', 100)->nullable();
            $table->string('cheque_number', 40)->nullable();
            $table->date('cheque_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 12)->default('posted');
            $table->foreignId('pos_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('counter_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_reference', 120)->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['party_type', 'party_id']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('allocatable_type', 50);
            $table->unsignedBigInteger('allocatable_id');
            $table->money('amount');
            $table->timestamps();
            $table->index(['allocatable_type', 'allocatable_id']);
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('draft'); // draft|confirmed|invoiced|cancelled
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            $table->money('subtotal');
            $table->money('discount_total');
            $table->money('tax_total');
            $table->money('total');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->qty('qty')->default(1);
            $table->money('unit_price');
            $table->money('discount');
            $table->rate('tax_rate');
            $table->money('tax_amount');
            $table->money('line_total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['sales_order_items', 'sales_orders', 'payment_allocations', 'payments', 'credit_note_items', 'credit_notes', 'invoice_items', 'invoices', 'pos_sessions', 'cash_registers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
