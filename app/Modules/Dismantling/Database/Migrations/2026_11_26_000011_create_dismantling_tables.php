<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dismantled_vehicles', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('vin', 17)->nullable()->index();
            $table->foreignId('vehicle_make_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_model_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 40)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->string('source', 20)->default('auction'); // auction|insurance|individual|dealer|other
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('seller_name')->nullable();
            $table->string('seller_phone', 30)->nullable();
            $table->string('seller_id_number', 40)->nullable();
            $table->date('purchase_date');
            $table->money('purchase_cost');
            $table->money('transport_cost');
            $table->money('dismantling_cost');
            $table->money('other_costs');
            $table->money('allocated_cost');
            $table->money('scrap_revenue');
            $table->foreignId('paid_from_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('purchased'); // purchased|dismantling|completed|closed
            $table->text('notes')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('dismantled_parts', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('dismantled_vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('oem_number', 80)->nullable();
            $table->string('grade', 10)->default('A'); // A|B|C|damaged
            $table->money('estimated_value');
            $table->money('sale_price');
            $table->money('allocated_cost');
            $table->string('serial_number', 80)->nullable()->index();
            $table->string('barcode', 80)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('status', 12)->default('pending'); // pending|in_stock|sold|scrapped
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('dismantled_scraps', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('dismantled_vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->money('amount');
            $table->date('date');
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['dismantled_scraps', 'dismantled_parts', 'dismantled_vehicles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
