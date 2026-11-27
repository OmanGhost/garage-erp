<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Outside (subcontracted) repairs linked to the job card, and roadside assistance requests (tow, battery, tyre…).
 */
return new class extends Migration
{
    public function up(): void
    {
        // cost of outside work for every company that already exists
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            if (! DB::table('accounts')->where('company_id', $companyId)->exists()
                || DB::table('accounts')->where('company_id', $companyId)->where('system_key', 'subcontract_cost')->exists()) {
                continue;
            }
            $code = '5104';
            while (DB::table('accounts')->where('company_id', $companyId)->where('code', $code)->exists()) {
                $code = (string) ((int) $code + 1);
            }
            DB::table('accounts')->insert(['company_id' => $companyId, 'code' => $code, 'name_ar' => 'تكلفة الأعمال الخارجية', 'name_en' => 'Subcontracted Work Cost',
                'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => DB::table('accounts')->where('company_id', $companyId)->where('code', '51')->value('id'),
                'system_key' => 'subcontract_cost', 'is_group' => false, 'is_active' => true, 'cash_flow_category' => 'operating', 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::create('subcontract_orders', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->string('work_type', 20)->default('other'); // machining|transmission|ecu|radiator|upholstery|paint|other
            $table->string('description');
            $table->string('item_sent')->nullable(); // the customer's part / unit sent out (no stock movement)
            $table->decimal('quoted_cost', 15, 3)->default(0);
            $table->decimal('actual_cost', 15, 3)->nullable();
            $table->decimal('sell_price', 15, 3)->default(0);
            $table->date('expected_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedSmallInteger('warranty_days')->default(0);
            $table->date('warranty_until')->nullable();
            $table->string('status', 12)->default('draft'); // draft|sent_out|back_in|cancelled
            $table->unsignedBigInteger('job_card_task_id')->nullable();
            $table->unsignedBigInteger('supplier_invoice_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
        Schema::table('job_cards', function (Blueprint $table) {
            $table->decimal('subcontract_cost', 15, 3)->default(0)->after('parts_cost');
        });
        Schema::table('job_card_tasks', function (Blueprint $table) {
            $table->boolean('is_subcontract')->default(false)->after('is_additional');
        });

        Schema::create('roadside_requests', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('caller_name');
            $table->string('caller_phone', 30);
            $table->string('plate', 30)->nullable();
            $table->string('service_type', 12)->default('tow'); // tow|battery|tyre|fuel|lockout|jumpstart|other
            $table->string('location_text')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('location_at')->nullable();
            $table->string('destination_text')->nullable();
            $table->string('status', 12)->default('new'); // new|assigned|en_route|on_site|completed|cancelled
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('distance_km', 8, 1)->nullable();
            $table->decimal('base_fee', 15, 3)->default(0);
            $table->decimal('km_rate', 15, 3)->default(0);
            $table->decimal('extra_charges', 15, 3)->default(0);
            $table->decimal('total', 15, 3)->default(0);
            $table->unsignedBigInteger('membership_id')->nullable();
            $table->boolean('covered')->default(false);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('job_card_id')->nullable();
            $table->string('public_token', 64)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadside_requests');
        Schema::table('job_card_tasks', fn (Blueprint $t) => $t->dropColumn('is_subcontract'));
        Schema::table('job_cards', fn (Blueprint $t) => $t->dropColumn('subcontract_cost'));
        Schema::dropIfExists('subcontract_orders');
    }
};
