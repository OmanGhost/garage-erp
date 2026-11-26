<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform billing: subscription invoices become full tax invoices (lines, discount, VAT, issue / due dates,
 * payment details, cancellation) that the platform admin issues, views and prints.
 */
return new class extends Migration
{
    public function up(): void
    {
        // custom-charge invoices have no subscription / period
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable()->change();
            $table->date('period_start')->nullable()->change();
            $table->date('period_end')->nullable()->change();
        });
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->date('issue_date')->nullable()->after('number');
            $table->date('due_date')->nullable()->after('issue_date');
            $table->json('items')->nullable()->after('period_end');
            $table->decimal('subtotal', 15, 3)->default(0)->after('items');
            $table->decimal('discount', 15, 3)->default(0)->after('subtotal');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount');
            $table->decimal('tax_amount', 15, 3)->default(0)->after('tax_rate');
            $table->text('notes')->nullable()->after('paid_at');
            $table->string('payment_method', 30)->nullable()->after('notes');
            $table->string('payment_reference', 120)->nullable()->after('payment_method');
            $table->timestamp('cancelled_at')->nullable()->after('payment_reference');
            $table->string('cancel_reason', 250)->nullable()->after('cancelled_at');
            $table->foreignId('created_by')->nullable()->after('cancel_reason')->constrained('users')->nullOnDelete();
        });
        // existing invoices: one line, no VAT
        DB::table('subscription_invoices')->orderBy('id')->each(function ($i) {
            DB::table('subscription_invoices')->where('id', $i->id)->update([
                'issue_date' => substr((string) $i->created_at, 0, 10) ?: now()->toDateString(),
                'due_date' => $i->period_start ?: substr((string) $i->created_at, 0, 10),
                'items' => json_encode([['description' => 'Subscription '.$i->period_start.' → '.$i->period_end, 'qty' => 1, 'unit_price' => (string) $i->amount]]),
                'subtotal' => $i->amount,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['issue_date', 'due_date', 'items', 'subtotal', 'discount', 'tax_rate', 'tax_amount', 'notes',
                'payment_method', 'payment_reference', 'cancelled_at', 'cancel_reason']);
        });
    }
};
