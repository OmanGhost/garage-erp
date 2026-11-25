<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->userRef('requested_by');
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->date('required_date')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 12)->default('draft'); // draft|submitted|approved|rejected|ordered|cancelled
            $table->text('notes')->nullable();
            $table->userRef('approved_by');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->qty('qty');
            $table->money('estimated_cost');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('number', 40);
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('draft'); // draft|sent|closed|awarded|cancelled
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('awarded_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->qty('qty');
            $table->timestamps();
        });

        Schema::create('rfq_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->string('status', 12)->default('invited'); // invited|quoted|declined|awarded
            $table->timestamp('quoted_at')->nullable();
            $table->money('total');
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rfq_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->constrained()->cascadeOnDelete();
            $table->cost('unit_price');
            $table->unsignedSmallInteger('lead_days')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('rfq_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->money('subtotal');
            $table->money('discount_total');
            $table->money('tax_total');
            $table->money('total');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->userRef('approved_by');
            $table->timestamp('approved_at')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->string('description')->nullable();
            $table->qty('qty');
            $table->qty('received_qty');
            $table->cost('unit_cost');
            $table->money('discount');
            $table->rate('tax_rate');
            $table->money('tax_amount');
            $table->money('line_total');
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->dateTime('received_at');
            $table->string('supplier_reference', 60)->nullable();
            $table->string('status', 12)->default('draft');
            $table->money('total');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->userRef('posted_by');
            $table->timestamp('posted_at')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->qty('qty');
            $table->qty('returned_qty');
            $table->cost('unit_cost');
            $table->cost('landed_cost_per_unit');
            $table->string('batch_number', 60)->nullable();
            $table->text('serial_numbers')->nullable();
            $table->date('expiry_date')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->text('batch_ids')->nullable();
            $table->money('line_total');
            $table->timestamps();
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('supplier_invoice_number', 60)->nullable();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->money('subtotal');
            $table->money('discount_total');
            $table->money('tax_total');
            $table->money('total');
            $table->money('paid_amount');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->userRef('posted_by');
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('description');
            $table->qty('qty');
            $table->cost('unit_cost');
            $table->rate('tax_rate');
            $table->money('tax_amount');
            $table->money('line_total');
            $table->timestamps();
        });

        Schema::create('landed_costs', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('number', 40);
            $table->foreignId('goods_receipt_id')->constrained();
            $table->date('date');
            $table->string('allocation_method', 10)->default('value'); // value|qty|weight
            $table->string('status', 12)->default('draft');
            $table->money('total');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('landed_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landed_cost_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // freight|customs|insurance|clearance|other
            $table->string('description')->nullable();
            $table->money('amount');
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->date('date');
            $table->string('status', 12)->default('draft');
            $table->money('subtotal');
            $table->money('tax_total');
            $table->money('total');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->qty('qty');
            $table->cost('unit_cost');
            $table->rate('tax_rate');
            $table->money('tax_amount');
            $table->money('line_total');
            $table->timestamps();
        });

        Schema::create('supplier_evaluations', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('period', 20);
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->unsignedTinyInteger('delivery_score')->default(0);
            $table->unsignedTinyInteger('price_score')->default(0);
            $table->unsignedTinyInteger('service_score')->default(0);
            $table->rate('overall');
            $table->text('notes')->nullable();
            $table->userRef('evaluated_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['supplier_evaluations', 'purchase_return_items', 'purchase_returns', 'landed_cost_lines', 'landed_costs', 'supplier_invoice_items', 'supplier_invoices', 'goods_receipt_items', 'goods_receipts', 'purchase_order_items', 'purchase_orders', 'rfq_quotes', 'rfq_suppliers', 'rfq_items', 'rfqs', 'purchase_request_items', 'purchase_requests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
