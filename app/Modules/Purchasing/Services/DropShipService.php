<?php

namespace App\Modules\Purchasing\Services;

use App\Exceptions\BusinessException;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\InvoiceService;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Drop shipping: the supplier delivers a sales order straight to the customer. A purchase order (flagged drop-ship,
 * shipping address = the customer) is raised for the order's parts; when the supplier confirms delivery the goods are
 * received into a virtual "drop-ship" warehouse and invoiced to the customer from it in the same step, so stock
 * never sits in the workshop and both purchase (GRNI) and sale (COGS) are booked normally.
 */
class DropShipService
{
    public function __construct(protected PurchasingService $purchasing, protected InvoiceService $invoices) {}

    public function warehouse(): Warehouse
    {
        return Warehouse::query()->where('is_virtual', true)->where('code', 'DROPSHIP')->first()
            ?? Warehouse::create(['code' => 'DROPSHIP', 'name' => __('Drop shipping (virtual)'), 'is_active' => true, 'is_virtual' => true, 'branch_id' => tenant()->branchId()]);
    }

    public function createPo(SalesOrder $so, int $supplierId): PurchaseOrder
    {
        return DB::transaction(function () use ($so, $supplierId) {
            $so = SalesOrder::query()->with('items.part', 'customer')->lockForUpdate()->findOrFail($so->id);
            if (! in_array($so->status, ['draft', 'confirmed'], true)) {
                throw new BusinessException(__('Only open sales orders can be drop shipped.'));
            }
            if (PurchaseOrder::query()->where('sales_order_id', $so->id)->where('drop_ship', true)->whereNotIn('status', ['cancelled', 'rejected'])->exists()) {
                throw new BusinessException(__('This sales order already has a drop-ship purchase order.'));
            }
            $lines = $so->items->whereNotNull('part_id')->map(fn ($i) => ['part_id' => $i->part_id, 'qty' => $i->qty, 'unit_cost' => $i->part?->purchase_price ?? 0])->values()->all();
            if (! $lines) {
                throw new BusinessException(__('The sales order has no parts to order.'));
            }
            $c = $so->customer;
            $po = $this->purchasing->createOrder(['supplier_id' => $supplierId, 'warehouse_id' => $this->warehouse()->id,
                'notes' => __('Drop ship for sales order :n', ['n' => $so->number])], $lines);
            $po->forceFill(['drop_ship' => true, 'sales_order_id' => $so->id,
                'ship_to' => trim($c->name."\n".($c->address ?? '').' '.($c->city ?? '')."\n".($c->phone ?? ''))])->save();
            if ($so->status === 'draft') {
                $so->update(['status' => 'confirmed']);
            }
            AuditLogger::log('dropship.ordered', $po, ['sales_order' => $so->number]);

            return $po;
        });
    }

    /** Supplier delivered to the customer: receive into the virtual warehouse and invoice the customer. */
    public function delivered(PurchaseOrder $po): Invoice
    {
        if (! $po->drop_ship || ! $po->sales_order_id) {
            throw new BusinessException(__('This is not a drop-ship purchase order.'));
        }

        return DB::transaction(function () use ($po) {
            $so = SalesOrder::query()->with('items')->lockForUpdate()->findOrFail($po->sales_order_id);
            $po = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($po->id);
            if ($po->status !== 'approved' || $so->status !== 'confirmed' || $so->invoice_id) {
                throw new BusinessException(__('Confirm delivery only for an approved, undelivered order.'));
            }
            $this->purchasing->receive($po, $po->items->mapWithKeys(fn ($item) => [$item->id => ['qty' => $item->qty]])->all(),
                ['notes' => __('Delivered directly to the customer')]);
            $inv = $this->invoices->createDraft(['customer_id' => $so->customer_id, 'warehouse_id' => $po->warehouse_id, 'sales_order_id' => $so->id,
                'payment_terms' => 'credit', 'notes' => __('Drop shipped by the supplier — sales order :n', ['n' => $so->number])],
                $so->items->map(fn ($i) => ['item_type' => $i->part_id ? 'part' : 'service', 'part_id' => $i->part_id, 'service_id' => $i->service_id,
                    'description' => $i->description, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'discount' => $i->discount, 'tax_rate' => $i->tax_rate,
                    'warehouse_id' => $po->warehouse_id])->all());
            $so->update(['status' => 'invoiced', 'invoice_id' => $inv->id]);
            AuditLogger::log('dropship.delivered', $po, ['invoice' => $inv->id]);

            return $inv;
        });
    }
}
