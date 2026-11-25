<?php

namespace App\Modules\Purchasing\Crud;

use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\LandedCost;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\Rfq;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Models\SupplierEvaluation;
use App\Modules\Purchasing\Models\SupplierInvoice;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;

class SupplierResource extends CrudResource
{
    public static string $model = Supplier::class;
    public static string $slug = 'suppliers';
    public static string $permission = 'suppliers';
    public static ?string $module = 'purchasing';
    public static string $icon = 'bi-shop';
    public static bool $hasAttachments = true;

    public function title(): string { return __('Suppliers'); }
    public function singular(): string { return __('Supplier'); }

    public function fields(): array
    {
        return [
            Field::text('code')->hideOnForm()->index()->searchable(),
            Field::text('name')->required()->index()->searchable()->col(6),
            Field::text('name_en')->label(__('Name (English)'))->searchable()->col(6),
            Field::text('contact_person')->label(__('Contact person'))->col(4),
            Field::tel('phone')->index()->searchable()->col(4),
            Field::email('email')->col(4),
            Field::text('vat_number')->label(__('VAT number'))->col(4),
            Field::text('cr_number')->label(__('C.R. number'))->col(4),
            Field::text('country')->default('OM')->col(4)->rules(['size:2']),
            Field::text('city')->col(4),
            Field::text('address')->col(8),
            Field::number('payment_terms_days')->label(__('Payment terms (days)'))->default(30)->col(4),
            Field::money('credit_limit')->label(__('Credit limit'))->col(4),
            Field::decimal('rating')->label(__('Rating (0-5)'))->col(4)->rules(['min:0', 'max:5']),
            Field::text('balance')->label(__('Payable balance'))->hideOnForm()->display(fn ($s) => '<span class="font-monospace">'.e(money($s->balance())).'</span>')->index(false),
            Field::textarea('bank_details')->label(__('Bank details')),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index()->filterable(),
            Field::textarea('notes'),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['code'] = DocumentNumber::next('supplier', false);
        }

        return $data;
    }

    public function showUrl(Model $m): ?string { return route('suppliers.show', $m); }

    public function deleteBlocker(Model $m): ?string
    {
        return $m->purchaseOrders()->exists() || $m->invoices()->exists() ? __('Suppliers with transactions cannot be deleted. Deactivate instead.') : null;
    }
}

abstract class PurchasingDocResource extends CrudResource
{
    public static ?string $module = 'purchasing';
    public static array $with = ['supplier'];

    public function editUrl(Model $m): ?string { return null; }

    public function deleteBlocker(Model $m): ?string
    {
        return ! in_array($m->status, ['draft', 'submitted', 'pending_approval'], true) ? __('Only draft documents can be deleted.') : null;
    }
}

class PurchaseRequestResource extends PurchasingDocResource
{
    public static string $model = PurchaseRequest::class;
    public static string $slug = 'purchase-requests';
    public static string $permission = 'purchase_requests';
    public static string $icon = 'bi-journal-plus';
    public static array $with = ['requester', 'jobCard'];
    public static ?string $dateColumn = 'created_at';

    public function title(): string { return __('Internal Purchase Requests'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::datetime('created_at')->label(__('Date'))->index(),
            Field::text('req')->label(__('Requested by'))->display(fn ($p) => e($p->requester?->name))->index(false),
            Field::text('job')->label(__('Job card'))->display(fn ($p) => e($p->jobCard?->number ?? '—'))->index(false),
            Field::date('required_date')->label(__('Required by'))->index(),
            Field::select('priority', ['low', 'normal', 'high', 'urgent'])->index()->filterable(),
            Field::select('status', ['draft', 'submitted', 'approved', 'rejected', 'ordered', 'cancelled'])->index()->filterable(),
        ];
    }

    public function createUrl(): ?string { return route('purchase-requests.create'); }
    public function showUrl(Model $m): ?string { return route('purchase-requests.show', $m); }
}

class RfqResource extends PurchasingDocResource
{
    public static string $model = Rfq::class;
    public static string $slug = 'rfqs';
    public static string $permission = 'rfqs';
    public static string $icon = 'bi-envelope-paper';
    public static array $with = ['awardedSupplier'];
    public static ?string $dateColumn = 'created_at';

    public function title(): string { return __('Requests for Quotation (RFQ)'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::datetime('created_at')->label(__('Date'))->index(),
            Field::date('due_date')->label(__('Due date'))->index(),
            Field::select('status', ['draft', 'sent', 'closed', 'awarded', 'cancelled'])->index()->filterable(),
            Field::text('awarded')->label(__('Awarded to'))->display(fn ($r) => e($r->awardedSupplier?->name ?? '—'))->index(false),
        ];
    }

    public function createUrl(): ?string { return route('rfqs.create'); }
    public function showUrl(Model $m): ?string { return route('rfqs.show', $m); }
    public function deleteBlocker(Model $m): ?string { return $m->status === 'awarded' ? __('Awarded RFQs cannot be deleted.') : null; }
}

class PurchaseOrderResource extends PurchasingDocResource
{
    public static string $model = PurchaseOrder::class;
    public static string $slug = 'purchase-orders';
    public static string $permission = 'purchase_orders';
    public static string $icon = 'bi-cart-check';
    public static array $with = ['supplier', 'warehouse'];
    public static ?string $dateColumn = 'order_date';

    public function title(): string { return __('Purchase Orders'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('order_date')->label(__('Date'))->index(),
            Field::text('sup')->label(__('Supplier'))->display(fn ($p) => e($p->supplier?->name))->index(false),
            Field::text('wh')->label(__('Warehouse'))->display(fn ($p) => e($p->warehouse?->displayName()))->index(false),
            Field::date('expected_date')->label(__('Expected'))->index(),
            Field::select('status', ['draft', 'pending_approval', 'approved', 'partially_received', 'received', 'closed', 'cancelled'])->index()->filterable(),
            Field::money('total')->index(),
        ];
    }

    public function createUrl(): ?string { return route('purchase-orders.create'); }
    public function editUrl(Model $m): ?string { return in_array($m->status, ['draft', 'pending_approval'], true) ? route('purchase-orders.edit', $m) : null; }
    public function canEditRecord(Model $m): bool { return in_array($m->status, ['draft', 'pending_approval'], true); }
    public function showUrl(Model $m): ?string { return route('purchase-orders.show', $m); }
}

class GoodsReceiptResource extends PurchasingDocResource
{
    public static string $model = GoodsReceipt::class;
    public static string $slug = 'goods-receipts';
    public static string $permission = 'goods_receipts';
    public static string $icon = 'bi-box-seam';
    public static array $with = ['supplier', 'order', 'warehouse'];
    public static ?string $dateColumn = 'received_at';

    public function title(): string { return __('Goods Receipts (GRN)'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::datetime('received_at')->label(__('Received'))->index(),
            Field::text('sup')->label(__('Supplier'))->display(fn ($g) => e($g->supplier?->name))->index(false),
            Field::text('po')->label(__('PO'))->display(fn ($g) => e($g->order?->number))->index(false),
            Field::text('supplier_reference')->label(__('Supplier ref.'))->index()->searchable(),
            Field::select('status', ['draft', 'posted', 'cancelled'])->index()->filterable(),
            Field::money('total')->index(),
        ];
    }

    public function createUrl(): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('goods-receipts.show', $m); }
}

class SupplierInvoiceResource extends PurchasingDocResource
{
    public static string $model = SupplierInvoice::class;
    public static string $slug = 'supplier-invoices';
    public static string $permission = 'supplier_invoices';
    public static string $icon = 'bi-receipt';
    public static ?string $dateColumn = 'invoice_date';

    public function title(): string { return __('Supplier Invoices'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::text('supplier_invoice_number')->label(__('Supplier invoice no.'))->index()->searchable(),
            Field::date('invoice_date')->label(__('Date'))->index(),
            Field::text('sup')->label(__('Supplier'))->display(fn ($i) => e($i->supplier?->name))->index(false),
            Field::date('due_date')->label(__('Due'))->index(),
            Field::select('status', ['draft', 'posted', 'partially_paid', 'paid', 'cancelled'])->index()->filterable(),
            Field::money('total')->index(),
            Field::money('paid_amount')->label(__('Paid'))->index(),
        ];
    }

    public function createUrl(): ?string { return route('supplier-invoices.create'); }
    public function showUrl(Model $m): ?string { return route('supplier-invoices.show', $m); }
}

class LandedCostResource extends PurchasingDocResource
{
    public static string $model = LandedCost::class;
    public static string $slug = 'landed-costs';
    public static string $permission = 'landed_costs';
    public static string $icon = 'bi-airplane';
    public static array $with = ['receipt', 'supplier'];
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Landed Costs (Freight & Customs)'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('date')->index(),
            Field::text('grn')->label(__('Goods receipt'))->display(fn ($l) => e($l->receipt?->number))->index(false),
            Field::select('allocation_method', ['value' => __('By value'), 'qty' => __('By quantity')])->label(__('Allocation'))->index(),
            Field::select('status', ['draft', 'posted'])->index()->filterable(),
            Field::money('total')->index(),
        ];
    }

    public function createUrl(): ?string { return route('landed-costs.create'); }
    public function showUrl(Model $m): ?string { return route('landed-costs.show', $m); }
}

class PurchaseReturnResource extends PurchasingDocResource
{
    public static string $model = PurchaseReturn::class;
    public static string $slug = 'purchase-returns';
    public static string $permission = 'purchase_returns';
    public static string $icon = 'bi-arrow-return-left';
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Purchase Returns'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('date')->index(),
            Field::text('sup')->label(__('Supplier'))->display(fn ($r) => e($r->supplier?->name))->index(false),
            Field::text('reason')->index(),
            Field::select('status', ['draft', 'posted'])->index()->filterable(),
            Field::money('total')->index(),
        ];
    }

    public function createUrl(): ?string { return route('purchase-returns.create'); }
    public function showUrl(Model $m): ?string { return route('purchase-returns.show', $m); }
}

class SupplierEvaluationResource extends CrudResource
{
    public static string $model = SupplierEvaluation::class;
    public static string $slug = 'supplier-evaluations';
    public static string $permission = 'supplier_evaluations';
    public static ?string $module = 'purchasing';
    public static string $icon = 'bi-star-half';
    public static array $with = ['supplier', 'evaluator'];

    public function title(): string { return __('Supplier Evaluations'); }

    public function fields(): array
    {
        $score = fn ($n) => Field::number($n)->required()->col(3)->rules(['min:0', 'max:10'])->index();

        return [
            Field::lookup('supplier_id', 'suppliers', 'supplier')->label(__('Supplier'))->required()->index(),
            Field::text('period')->required()->index()->help('2026-Q1'),
            $score('quality_score')->label(__('Quality (0-10)')),
            $score('delivery_score')->label(__('Delivery (0-10)')),
            $score('price_score')->label(__('Price (0-10)')),
            $score('service_score')->label(__('Service (0-10)')),
            Field::decimal('overall')->label(__('Overall'))->hideOnForm()->index(),
            Field::textarea('notes'),
        ];
    }

    public function saving(Model $m, array $data, bool $creating): void
    {
        $m->overall = round(($m->quality_score * .35 + $m->delivery_score * .3 + $m->price_score * .2 + $m->service_score * .15), 2);
        $m->evaluated_by = auth()->id();
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        $avg = SupplierEvaluation::query()->where('supplier_id', $m->supplier_id)->avg('overall');
        Supplier::query()->whereKey($m->supplier_id)->update(['rating' => round($avg / 2, 2)]);
    }
}
