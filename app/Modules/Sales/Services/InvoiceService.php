<?php

namespace App\Modules\Sales\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Events\InvoicePosted;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Warranty\Services\WarrantyService;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\JobCardPart;
use App\Modules\Workshop\Models\JobCardTask;
use App\Support\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Settings;
use App\Support\Totals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(
        protected StockService $stock,
        protected PostingService $posting,
        protected CommissionService $commissions,
        protected WarrantyService $warranties,
    ) {}

    /**
     * @param  array  $header  customer_id, type, issue_date, due_date, payment_terms, price_level, warehouse_id, notes, salesperson_id ...
     * @param  array  $items  [item_type, part_id, service_id, description, qty, unit_price, discount, tax_rate, warehouse_id, job_card_task_id, job_card_part_id, unit_cost]
     */
    public function createDraft(array $header, array $items): Invoice
    {
        return DB::transaction(function () use ($header, $items) {
            $customer = Customer::query()->findOrFail($header['customer_id']);
            $invoice = Invoice::create(array_merge([
                'type' => 'sale',
                'status' => 'draft',
                'issue_date' => now()->toDateString(),
                'payment_terms' => 'cash',
                'price_level' => $customer->price_level ?: 'retail',
                'uuid' => (string) Str::uuid(),
                'created_by' => auth()->id(),
                'salesperson_id' => auth()->id(),
            ], array_filter($header, fn ($v) => $v !== null)));
            $this->syncItems($invoice, $items);

            return $invoice->fresh('items');
        });
    }

    public function updateDraft(Invoice $invoice, array $header, array $items): Invoice
    {
        if (! in_array($invoice->status, ['draft', 'held'], true)) {
            throw new BusinessException(__('Only draft invoices can be edited.'));
        }

        return DB::transaction(function () use ($invoice, $header, $items) {
            $invoice->fill(array_intersect_key($header, array_flip(['customer_id', 'issue_date', 'due_date', 'payment_terms', 'price_level', 'warehouse_id', 'notes', 'salesperson_id', 'held_label'])))->save();
            $invoice->items()->delete();
            $this->syncItems($invoice, $items);

            return $invoice->fresh('items');
        });
    }

    protected function syncItems(Invoice $invoice, array $items): void
    {
        $vat = tenant()->company()?->vat_rate ?? config('erp.vat_rate');
        $sort = 0;
        // core deposit lines are rebuilt from the parts every time the lines are saved
        $items = array_values(array_filter($items, fn ($i) => ($i['item_type'] ?? null) !== 'core'));
        $items = array_merge($items, CoreChargeService::depositLines($items));
        foreach ($items as $i) {
            $qty = Money::round($i['qty'] ?? 1);
            if (! Money::isPositive($qty)) {
                continue;
            }
            $part = ! empty($i['part_id']) ? Part::query()->find($i['part_id']) : null;
            $type = $i['item_type'] ?? ($part ? 'part' : 'service');
            $price = $i['unit_price'] ?? ($part ? $part->priceFor($invoice->price_level) : 0);
            $taxRate = $i['tax_rate'] ?? ($part?->tax_rate ?? $vat);
            $c = Totals::line($qty, $price, $i['discount'] ?? 0, $taxRate);
            $invoice->items()->create([
                'item_type' => $type,
                'part_id' => $part?->id,
                'core_part_id' => $i['core_part_id'] ?? null,
                'service_id' => $i['service_id'] ?? null,
                'job_card_task_id' => $i['job_card_task_id'] ?? null,
                'job_card_part_id' => $i['job_card_part_id'] ?? null,
                'warehouse_id' => $i['warehouse_id'] ?? $invoice->warehouse_id,
                'description' => $i['description'] ?? ($part ? $part->displayName() : '-'),
                'qty' => $qty,
                'unit_price' => Money::round($price),
                'discount' => $c['discount'],
                'tax_rate' => $taxRate,
                'tax_amount' => $c['tax'],
                'line_total' => $c['net'],
                'unit_cost' => $i['unit_cost'] ?? 0,
                'warranty_days' => $i['warranty_days'] ?? ($part?->warranty_days ?? 0),
                'warranty_km' => $i['warranty_km'] ?? ($part?->warranty_km ?? 0),
                'sort' => $sort++,
            ]);
        }
        $this->recalc($invoice);
    }

    public function recalc(Invoice $invoice): void
    {
        $items = $invoice->items()->get();
        $t = Totals::document($items->map(fn ($i) => ['qty' => $i->qty, 'unit_price' => $i->unit_price, 'discount' => $i->discount, 'tax_rate' => $i->tax_rate]));
        $invoice->forceFill(['subtotal' => $t['subtotal'], 'discount_total' => $t['discount_total'], 'tax_total' => $t['tax_total'], 'total' => $t['total']])->save();
    }

    /** Build a draft invoice from the approved / executed work of a job card. */
    public function fromJobCard(JobCard $jc, array $opt = []): Invoice
    {
        $existing = $jc->invoices()->whereIn('status', ['draft', 'held'])->where('customer_id', $opt['customer_id'] ?? $jc->customer_id)->first();
        if ($existing && empty($opt['force_new'])) {
            return $existing;
        }
        if ($jc->invoices()->whereIn('status', ['posted', 'partially_paid', 'paid'])->exists() && empty($opt['allow_additional'])) {
            throw new BusinessException(__('This job card has already been invoiced.'));
        }

        $items = [];
        $tasks = $jc->tasks()->with('service')->where('status', '!=', 'cancelled')->where('approval_status', 'approved')->get();
        foreach ($tasks as $t) {
            $items[] = [
                'item_type' => 'service', 'service_id' => $t->service_id, 'job_card_task_id' => $t->id,
                'description' => $t->description, 'qty' => $t->qty, 'unit_price' => $t->unit_price, 'discount' => $t->discount,
                'tax_rate' => $t->tax_rate, 'warranty_days' => $t->service?->warranty_days ?? 0, 'warranty_km' => $t->service?->warranty_km ?? 0,
            ];
        }
        $parts = $jc->parts()->with('part')->where('approval_status', 'approved')->get();
        foreach ($parts as $p) {
            $net = $p->netQty();
            if (! Money::isPositive($net)) {
                continue;
            }
            $items[] = [
                'item_type' => 'part', 'part_id' => $p->part_id, 'job_card_part_id' => $p->id, 'warehouse_id' => $p->warehouse_id,
                'description' => $p->description ?: $p->part->displayName(), 'qty' => $net, 'unit_price' => $p->unit_price,
                'discount' => Money::isZero($p->qty) ? 0 : Money::mul($p->discount, Money::div($net, $p->qty, 6)),
                'tax_rate' => $p->tax_rate, 'unit_cost' => $p->unit_cost,
            ];
        }
        foreach ($opt['extra_items'] ?? [] as $x) {
            $items[] = $x;
        }
        if (! $items) {
            throw new BusinessException(__('There is nothing to invoice on this job card.'));
        }
        $type = $jc->insurance_claim_id ? 'insurance' : ($jc->fleet_id ? 'fleet' : 'workshop');

        return $this->createDraft([
            'type' => $opt['type'] ?? $type,
            'customer_id' => $opt['customer_id'] ?? $jc->customer_id,
            'job_card_id' => $jc->id,
            'branch_id' => $jc->branch_id,
            'fleet_id' => $jc->fleet_id,
            'insurance_claim_id' => $jc->insurance_claim_id,
            'price_level' => 'workshop',
            'payment_terms' => $opt['payment_terms'] ?? 'credit',
            'due_date' => $opt['due_date'] ?? now()->addDays($jc->customer->payment_terms_days ?: 0)->toDateString(),
        ], $items);
    }

    /**
     * Post (approve) an invoice: number, stock issue, COGS, AR/revenue/VAT journal,
     * warranties and commissions — atomically and only once.
     */
    public function post(Invoice $invoice, bool $skipCreditCheck = false): Invoice
    {
        return DB::transaction(function () use ($invoice, $skipCreditCheck) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->isPosted()) {
                return $invoice; // idempotent
            }
            if (! in_array($invoice->status, ['draft', 'held'], true)) {
                throw new BusinessException(__('This invoice cannot be posted.'));
            }
            $invoice->load('items', 'customer', 'jobCard.department');
            if ($invoice->items->isEmpty()) {
                throw new BusinessException(__('The invoice has no items.'));
            }
            $this->recalc($invoice);
            $invoice->refresh()->load('items');

            if (! $skipCreditCheck && $invoice->payment_terms === 'credit') {
                $this->assertCredit($invoice->customer, $invoice->total);
            }

            $defaultWarehouse = $invoice->warehouse_id ?: Warehouse::query()->where('branch_id', $invoice->branch_id)->value('id') ?: Warehouse::query()->value('id');
            $directCost = $wipCost = '0';
            $otherRevenue = $coreDeposits = '0';
            $partsRevenue = $serviceRevenue = $directCostByCc = []; // keyed by cost center id (0 = none)
            $jcCostCenter = $invoice->jobCard?->department?->cost_center_id;

            foreach ($invoice->items as $item) {
                if ($item->item_type === 'part' && $item->part_id) {
                    $pcc = $this->costCenterFor($invoice, $item) ?? 0;
                    $partsRevenue[$pcc] = Money::add($partsRevenue[$pcc] ?? '0', $item->line_total);
                    if ($item->job_card_part_id) {
                        $jcp = JobCardPart::query()->find($item->job_card_part_id);
                        $unit = $jcp ? (string) $jcp->unit_cost : (string) $item->unit_cost;
                        $cost = Money::mul($item->qty, $unit);
                        $wipCost = Money::add($wipCost, $cost);
                        $item->update(['unit_cost' => $unit, 'cost_total' => $cost]);
                    } elseif (! $item->part?->is_service_item) {
                        $wh = $item->warehouse_id ?: $defaultWarehouse;
                        if (! $wh) {
                            throw new BusinessException(__('No warehouse selected for stock items.'));
                        }
                        $r = $this->stock->issue($item->part_id, $wh, $item->qty, ['type' => 'sale', 'reference' => $invoice, 'reference_number' => $invoice->number]);
                        $directCost = Money::add($directCost, $r['total_cost']);
                        $directCostByCc[$pcc] = Money::add($directCostByCc[$pcc] ?? '0', $r['total_cost']);
                        $item->update(['unit_cost' => $r['unit_cost'], 'cost_total' => $r['total_cost'], 'warehouse_id' => $wh]);
                    }
                } elseif (in_array($item->item_type, ['service', 'labor'], true)) {
                    $cc = $this->costCenterFor($invoice, $item);
                    $serviceRevenue[$cc ?? 0] = Money::add($serviceRevenue[$cc ?? 0] ?? '0', $item->line_total);
                } elseif ($item->item_type === 'core') {
                    $coreDeposits = Money::add($coreDeposits, $item->line_total);
                } else {
                    $otherRevenue = Money::add($otherRevenue, $item->line_total);
                }
            }

            $invoice->number = DocumentNumber::next($invoice->type === 'pos' ? 'pos' : 'invoice');
            $costTotal = Money::add($directCost, $wipCost);

            $lines = [['account' => 'ar', 'debit' => $invoice->total, 'party' => $invoice->customer, 'description' => $invoice->number]];
            foreach ($partsRevenue as $cc => $amount) {
                $lines[] = ['account' => 'sales_parts', 'credit' => $amount, 'cost_center_id' => $cc ?: null];
            }
            foreach ($serviceRevenue as $cc => $amount) {
                $lines[] = ['account' => 'sales_services', 'credit' => $amount, 'cost_center_id' => $cc ?: null];
            }
            $lines[] = ['account' => 'customer_advances', 'credit' => $coreDeposits, 'party' => $invoice->customer];
            $lines[] = ['account' => 'other_revenue', 'credit' => $otherRevenue];
            $lines[] = ['account' => 'vat_output', 'credit' => $invoice->tax_total];
            // cost of sales: parts issued to the job (WIP) on the job's department, direct stock sales per parts cost center
            $lines[] = ['account' => 'cogs', 'debit' => $wipCost, 'cost_center_id' => $jcCostCenter];
            foreach ($directCostByCc as $cc => $amount) {
                $lines[] = ['account' => 'cogs', 'debit' => $amount, 'cost_center_id' => $cc ?: null];
            }
            $lines[] = ['account' => 'inventory', 'credit' => $directCost];
            $lines[] = ['account' => 'wip', 'credit' => $wipCost];

            $je = $this->posting->post([
                'date' => $invoice->issue_date->toDateString(),
                'description' => __('Sales invoice').' '.$invoice->number.' - '.$invoice->customer->name,
                'source' => $invoice,
                'branch_id' => $invoice->branch_id,
                'lines' => $lines,
            ]);

            $invoice->forceFill([
                'status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id(),
                'journal_entry_id' => $je->id, 'cost_total' => $costTotal,
            ])->save();

            if ($invoice->job_card_id) {
                $jc = JobCard::query()->find($invoice->job_card_id);
                if ($jc && ! $jc->invoice_id) {
                    $jc->forceFill(['invoice_id' => $invoice->id])->save();
                }
                $jc && app(\App\Modules\Workshop\Services\JobCostingService::class)->recalculate($jc);
            }

            $this->warranties->createFromInvoice($invoice);
            app(CoreChargeService::class)->fromInvoice($invoice);
            $this->commissions->fromInvoice($invoice);
            AuditLogger::log('invoice.posted', $invoice, ['number' => $invoice->number, 'total' => (string) $invoice->total]);
            DB::afterCommit(fn () => event(new InvoicePosted($invoice)));

            return $invoice;
        });
    }

    /**
     * Cost center of an invoice line (also used to reverse it on a credit note):
     * workshop lines follow the task / job card department; direct parts sales go to the
     * "new parts" or "used parts" cost center configured in settings.
     */
    public function costCenterFor(Invoice $invoice, $item): ?int
    {
        $jcCc = $invoice->jobCard?->department?->cost_center_id;
        if (in_array($item->item_type, ['service', 'labor'], true)) {
            if ($item->job_card_task_id) {
                $taskCc = JobCardTask::query()->with('department')->find($item->job_card_task_id)?->department?->cost_center_id;
                if ($taskCc) {
                    return $taskCc;
                }
            }

            return $jcCc;
        }
        if ($item->item_type === 'part') {
            if ($jcCc) {
                return $jcCc;
            }
            $used = in_array($item->part?->part_type, ['used', 'refurbished', 'damaged', 'scrap'], true);
            $id = Settings::get($used ? 'accounting.cost_center_parts_used' : 'accounting.cost_center_parts_new');

            return $id ? (int) $id : null;
        }

        return null;
    }

    public function assertCredit(Customer $customer, mixed $amount): void
    {
        if (Settings::bool('sales.allow_credit_without_limit') || auth()->user()?->can('invoices.credit_override')) {
            return;
        }
        if ($customer->classification === 'blacklisted') {
            throw new BusinessException(__('Credit sales are blocked for this customer.'));
        }
        $newBalance = Money::add($customer->balance(), $amount);
        if (Money::cmp($newBalance, $customer->credit_limit) > 0) {
            throw new BusinessException(__('Credit limit exceeded for :c. Limit :l, balance after invoice :b.', [
                'c' => $customer->name, 'l' => money($customer->credit_limit), 'b' => money($newBalance),
            ]));
        }
    }

    public function deleteDraft(Invoice $invoice): void
    {
        if (! in_array($invoice->status, ['draft', 'held'], true)) {
            throw new BusinessException(__('Posted invoices cannot be deleted. Use a credit note.'));
        }
        $invoice->items()->delete();
        $invoice->delete();
    }
}
