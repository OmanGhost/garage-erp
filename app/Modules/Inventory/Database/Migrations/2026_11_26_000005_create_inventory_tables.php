<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('type', 20)->default('main'); // main|workshop|showroom|scrap|transit|online
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('warehouse_user', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['warehouse_id', 'user_id']);
        });

        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name')->nullable();
            $table->string('aisle', 20)->nullable();
            $table->string('rack', 20)->nullable();
            $table->string('shelf', 20)->nullable();
            $table->string('bin', 20)->nullable();
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('part_categories', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('parent_id')->nullable()->constrained('part_categories')->nullOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('name');
            $table->string('country', 2)->nullable();
            $table->boolean('is_oem')->default(false);
            $table->timestamps();
        });

        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('sku', 60);
            $table->string('barcode', 80)->nullable();
            $table->string('oem_number', 80)->nullable();
            $table->string('serial_number', 80)->nullable();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->foreignId('part_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('country_of_origin', 2)->nullable();
            $table->string('part_type', 20)->default('aftermarket'); // genuine|oem|aftermarket|used|refurbished|damaged|scrap
            $table->string('condition_grade', 10)->nullable(); // A|B|C|damaged
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('pcs');
            $table->cost('purchase_price');
            $table->money('sale_price');
            $table->money('wholesale_price');
            $table->money('workshop_price');
            $table->money('min_sale_price');
            $table->rate('profit_margin');
            $table->rate('tax_rate')->default(5);
            $table->unsignedSmallInteger('warranty_days')->default(0);
            $table->unsignedInteger('warranty_km')->default(0);
            $table->qty('min_stock');
            $table->qty('reorder_qty');
            $table->decimal('max_stock', 14, 3)->nullable();
            $table->boolean('track_serial')->default(false);
            $table->boolean('track_batch')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->foreignId('default_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->cost('average_cost');
            $table->boolean('is_service_item')->default(false);
            $table->boolean('is_online')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('image_path')->nullable();
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->unsignedBigInteger('dismantled_vehicle_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'barcode']);
            $table->index(['company_id', 'oem_number']);
            $table->index(['company_id', 'name_ar']);
        });

        Schema::create('part_alternatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->string('number', 80)->index();
            $table->string('brand')->nullable();
            $table->string('type', 20)->default('alternative'); // alternative|superseded|cross_reference
            $table->timestamps();
        });

        Schema::create('part_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_make_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_model_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('engine', 60)->nullable();
            $table->string('vin_pattern', 17)->nullable(); // e.g. WMI+VDS prefix
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['vehicle_make_id', 'vehicle_model_id']);
        });

        Schema::create('part_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_part_number', 80)->nullable();
            $table->cost('last_price');
            $table->unsignedSmallInteger('lead_days')->default(0);
            $table->timestamps();
            $table->unique(['part_id', 'supplier_id']);
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->qty('qty_on_hand');
            $table->qty('qty_reserved');
            $table->cost('avg_cost');
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
            $table->unique(['part_id', 'warehouse_id']);
            $table->index(['company_id', 'warehouse_id']);
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 60)->nullable();
            $table->string('serial_number', 80)->nullable()->index();
            $table->date('expiry_date')->nullable();
            $table->qty('qty_received');
            $table->qty('qty_remaining');
            $table->cost('unit_cost');
            $table->dateTime('received_at');
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('dismantled_vehicle_id')->nullable()->index();
            $table->string('grade', 10)->nullable();
            $table->timestamps();
            $table->index(['part_id', 'warehouse_id', 'qty_remaining']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('part_id')->constrained();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('type', 30);
            $table->decimal('qty', 14, 3); // signed: + in / - out
            $table->cost('unit_cost');
            $table->money('total_cost');
            $table->decimal('balance_after', 14, 3)->default(0);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 60)->nullable();
            $table->userRef('user_id');
            $table->string('notes')->nullable();
            $table->dateTime('moved_at');
            $table->timestamps();
            $table->index(['company_id', 'part_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['company_id', 'moved_at']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('number', 40);
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->string('status', 12)->default('draft'); // draft|in_transit|received|cancelled
            $table->date('transfer_date');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->userRef('created_by');
            $table->userRef('received_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->qty('qty');
            $table->cost('unit_cost');
            $table->qty('received_qty');
            $table->timestamps();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('number', 40);
            $table->foreignId('warehouse_id')->constrained();
            $table->string('type', 12)->default('periodic'); // periodic|annual|cycle
            $table->string('status', 12)->default('draft'); // draft|in_progress|posted|cancelled
            $table->date('count_date');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->userRef('posted_by');
            $table->userRef('created_by');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->qty('system_qty');
            $table->decimal('counted_qty', 14, 3)->nullable();
            $table->qty('difference');
            $table->cost('unit_cost');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('number', 40);
            $table->foreignId('warehouse_id')->constrained();
            $table->date('date');
            $table->string('reason', 20); // damage|loss|found|correction|expired|opening
            $table->string('status', 12)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->userRef('posted_by');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->decimal('qty', 14, 3); // signed
            $table->cost('unit_cost');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['stock_adjustment_items', 'stock_adjustments', 'stock_count_items', 'stock_counts', 'stock_transfer_items', 'stock_transfers', 'stock_movements', 'stock_batches', 'stock_levels', 'part_supplier', 'part_compatibilities', 'part_alternatives', 'parts', 'brands', 'part_categories', 'warehouse_locations', 'warehouse_user', 'warehouses'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
