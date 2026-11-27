<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock reservations, core charges (refundable deposit for the old part), kits (parts assembled from components),
 * consignment stock (supplier-owned until sold) and drop shipping (supplier delivers straight to the customer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('number', 40);
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 15, 3);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 12)->default('active'); // active|released|consumed|expired
            $table->unsignedBigInteger('reserved_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->decimal('core_charge', 15, 3)->default(0)->after('min_sale_price');
            $table->boolean('is_kit')->default(false)->after('is_service_item');
        });
        Schema::create('part_kit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('component_part_id')->constrained('parts')->cascadeOnDelete();
            $table->decimal('qty', 15, 3)->default(1);
            $table->timestamps();
            $table->unique(['kit_part_id', 'component_part_id']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('core_part_id')->nullable()->after('part_id');
        });
        Schema::create('core_returns', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('invoice_item_id')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('part_id')->constrained();
            $table->decimal('qty', 15, 3)->default(1);
            $table->decimal('amount', 15, 3);
            $table->date('due_date')->nullable();
            $table->string('status', 12)->default('pending'); // pending|returned|forfeited
            $table->string('condition', 12)->nullable(); // good|damaged
            $table->timestamp('returned_at')->nullable();
            $table->unsignedBigInteger('credit_note_id')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('is_consignment')->default(false);
            $table->foreignId('consignor_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->boolean('is_virtual')->default(false);
        });
        Schema::create('consignment_settlements', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->dateTime('period_from')->nullable();
            $table->dateTime('period_to');
            $table->decimal('qty', 15, 3)->default(0);
            $table->decimal('amount', 15, 3)->default(0);
            $table->unsignedBigInteger('supplier_invoice_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('consignment_settlement_id')->nullable()->constrained('consignment_settlements');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->boolean('drop_ship')->default(false);
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->text('ship_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('sales_order_id');
            $t->dropColumn(['drop_ship', 'ship_to']);
        });
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropConstrainedForeignId('consignment_settlement_id'));
        Schema::dropIfExists('consignment_settlements');
        Schema::table('warehouses', function (Blueprint $t) {
            $t->dropConstrainedForeignId('consignor_supplier_id');
            $t->dropColumn(['is_consignment', 'is_virtual']);
        });
        Schema::dropIfExists('core_returns');
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropColumn('core_part_id'));
        Schema::dropIfExists('part_kit_items');
        Schema::table('parts', fn (Blueprint $t) => $t->dropColumn(['core_charge', 'is_kit']));
        Schema::dropIfExists('stock_reservations');
    }
};
