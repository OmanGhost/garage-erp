<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Part cross reference (alternatives linked to catalog parts, with their quality), ABC class, cycle-count dates
 * and the supplier price history (back-filled from posted goods receipts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_alternatives', function (Blueprint $table) {
            $table->foreignId('alternative_part_id')->nullable()->after('part_id')->constrained('parts')->nullOnDelete();
            $table->string('quality', 12)->nullable()->after('type'); // oem|oes|aftermarket|reman|used
            $table->string('notes')->nullable()->after('quality');
        });
        Schema::table('parts', function (Blueprint $table) {
            $table->char('abc_class', 1)->nullable()->after('part_type')->index();
            $table->timestamp('abc_calculated_at')->nullable()->after('abc_class');
        });
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->timestamp('last_counted_at')->nullable()->after('last_movement_at');
        });
        Schema::create('supplier_price_history', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('qty', 15, 3)->default(0);
            $table->string('source', 12)->default('grn'); // grn|rfq|manual
            $table->string('reference', 60)->nullable();
            $table->date('recorded_at');
            $table->timestamps();
            $table->index(['company_id', 'part_id', 'recorded_at']);
            $table->index(['company_id', 'supplier_id']);
        });

        // history of what was already received
        DB::table('goods_receipt_items as i')->join('goods_receipts as g', 'g.id', '=', 'i.goods_receipt_id')
            ->where('g.status', 'posted')->orderBy('i.id')
            ->select('g.company_id', 'i.part_id', 'g.supplier_id', 'i.unit_cost', 'i.qty', 'g.number', 'g.received_at')
            ->chunk(500, function ($rows) {
                DB::table('supplier_price_history')->insert($rows->map(fn ($r) => [
                    'company_id' => $r->company_id, 'part_id' => $r->part_id, 'supplier_id' => $r->supplier_id, 'unit_cost' => $r->unit_cost,
                    'qty' => $r->qty, 'source' => 'grn', 'reference' => $r->number, 'recorded_at' => substr((string) $r->received_at, 0, 10),
                    'created_at' => now(), 'updated_at' => now(),
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_price_history');
        Schema::table('stock_levels', fn (Blueprint $t) => $t->dropColumn('last_counted_at'));
        Schema::table('parts', function (Blueprint $t) {
            $t->dropIndex(['abc_class']);
            $t->dropColumn(['abc_class', 'abc_calculated_at']);
        });
        Schema::table('part_alternatives', function (Blueprint $t) {
            $t->dropConstrainedForeignId('alternative_part_id');
            $t->dropColumn(['quality', 'notes']);
        });
    }
};
