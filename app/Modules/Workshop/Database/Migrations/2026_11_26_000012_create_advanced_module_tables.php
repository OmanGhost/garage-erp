<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2/3 modules: insurance, fleet approvals, warranty, equipment,
 * appointments, online store & online payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('insurance_company_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claim_number', 60)->nullable();
            $table->string('policy_number', 60)->nullable();
            $table->date('accident_date')->nullable();
            $table->string('accident_location')->nullable();
            $table->text('accident_description')->nullable();
            $table->string('police_report_number', 60)->nullable();
            $table->string('surveyor_name')->nullable();
            $table->money('estimated_cost');
            $table->money('approved_amount');
            $table->money('deductible_amount');
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('fleet_approvals', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('fleet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->money('amount');
            $table->string('status', 10)->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('approver_name')->nullable();
            $table->string('notes')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamps();
        });

        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('start_odometer')->nullable();
            $table->unsignedInteger('km_limit')->nullable();
            $table->text('terms')->nullable();
            $table->string('status', 10)->default('active'); // active|expired|claimed|void
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('supplier_warranty_end')->nullable();
            $table->string('serial_number', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('number', 40);
            $table->foreignId('warranty_id')->constrained();
            $table->date('claim_date');
            $table->unsignedInteger('odometer')->nullable();
            $table->text('description');
            $table->text('inspection_result')->nullable();
            $table->string('resolution', 10)->nullable(); // replace|repair|refund|reject
            $table->string('status', 12)->default('open'); // open|inspecting|approved|rejected|completed
            $table->foreignId('rework_job_card_id')->nullable()->constrained('job_cards')->nullOnDelete();
            $table->money('cost');
            $table->boolean('supplier_claim')->default(false);
            $table->string('supplier_claim_status', 12)->nullable();
            $table->money('supplier_recovered_amount');
            $table->text('notes')->nullable();
            $table->userRef('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('category', 20); // lift|diagnostic|maintenance|tool|computer|company_vehicle|other
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number', 80)->nullable();
            $table->date('purchase_date')->nullable();
            $table->money('purchase_cost');
            $table->date('warranty_until')->nullable();
            $table->foreignId('custodian_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('fixed_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('maintenance_interval_days')->nullable();
            $table->date('next_maintenance_at')->nullable();
            $table->unsignedSmallInteger('calibration_interval_days')->nullable();
            $table->date('next_calibration_at')->nullable();
            $table->money('operating_cost_per_hour');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('equipment_logs', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // maintenance|breakdown|calibration|inspection|custody
            $table->date('date');
            $table->text('description')->nullable();
            $table->money('cost');
            $table->string('performed_by')->nullable();
            $table->decimal('downtime_hours', 8, 2)->default(0);
            $table->date('next_due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->userRef('technician_id');
            $table->foreignId('bay_id')->nullable()->constrained('workshop_bays')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 12)->default('scheduled');
            $table->string('source', 12)->default('staff');
            $table->text('notes')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->unsignedTinyInteger('rescheduled_count')->default(0);
            $table->foreignId('job_card_id')->nullable()->constrained()->nullOnDelete();
            $table->userRef('created_by');
            $table->timestamps();
            $table->index(['company_id', 'starts_at']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('code', 40);
            $table->string('type', 10)->default('percent');
            $table->money('value');
            $table->money('min_order');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('number', 40);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->string('customer_email')->nullable();
            $table->string('shipping_method', 10)->default('pickup');
            $table->string('shipping_address')->nullable();
            $table->money('shipping_fee');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->money('subtotal');
            $table->money('discount_total');
            $table->money('tax_total');
            $table->money('total');
            $table->string('status', 12)->default('pending');
            $table->string('payment_method', 20)->default('cod');
            $table->string('payment_status', 10)->default('unpaid');
            $table->string('payment_reference', 120)->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->uuid('uuid')->unique();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('store_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->string('description');
            $table->qty('qty');
            $table->money('unit_price');
            $table->rate('tax_rate');
            $table->money('tax_amount');
            $table->money('line_total');
            $table->timestamps();
        });

        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->company();
            $table->string('payable_type', 50);
            $table->unsignedBigInteger('payable_id');
            $table->string('gateway', 30);
            $table->money('amount');
            $table->string('currency', 3)->default('OMR');
            $table->string('status', 12)->default('pending');
            $table->string('session_id', 191)->nullable()->index();
            $table->string('reference', 191)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->timestamps();
            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        foreach (['online_payments', 'store_order_items', 'store_orders', 'coupons', 'appointments', 'equipment_logs', 'equipment', 'warranty_claims', 'warranties', 'fleet_approvals', 'insurance_claims'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
