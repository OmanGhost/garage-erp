<?php

namespace App\Modules\Inventory\Crud;

use App\Modules\Dismantling\Models\DismantledVehicle;
use App\Modules\Dismantling\Services\DismantlingService;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\Part;
use App\Modules\Inventory\Models\PartCategory;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PartResource extends CrudResource
{
    public static string $model = Part::class;
    public static string $slug = 'parts';
    public static string $permission = 'parts';
    public static ?string $module = 'inventory';
    public static string $icon = 'bi-gear-wide-connected';
    public static array $with = ['brand', 'category'];
    public static bool $hasAttachments = true;

    public function title(): string { return __('Spare Parts Catalog'); }
    public function singular(): string { return __('Spare part'); }

    public function query(): Builder
    {
        return parent::query()->withSum('stockLevels as on_hand', 'qty_on_hand')->withSum('stockLevels as reserved', 'qty_reserved');
    }

    public function fields(): array
    {
        return [
            Field::image('image_path')->label(__('Image'))->col(4)->section(__('Identification')),
            Field::text('sku')->label('SKU')->required()->index()->searchable()->unique('parts')->col(4)->help(__('Leave empty to generate automatically.')),
            Field::text('barcode')->label(__('Barcode'))->index()->searchable()->col(4)->attrs(['data-scan-target' => '1']),
            Field::text('oem_number')->label(__('OEM part number'))->index()->searchable()->col(4),
            Field::text('serial_number')->label(__('Serial number'))->col(4),
            Field::text('alternatives_text')->label(__('Alternative part numbers'))->col(4)->help(__('Comma separated'))->hideOnShow(),
            Field::text('name_ar')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(6),
            Field::text('name_en')->label(__('Name (English)'))->index()->searchable()->col(6),
            Field::select('part_category_id', fn () => PartCategory::query()->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all())->label(__('Category'))->relation('category', 'displayName')->index()->filterable()->col(4),
            Field::select('brand_id', fn () => Brand::query()->orderBy('name')->pluck('name', 'id')->all())->label(__('Manufacturer / brand'))->relation('brand')->index()->filterable()->col(4),
            Field::text('country_of_origin')->label(__('Country of origin'))->col(4)->rules(['size:2']),
            Field::select('part_type', Part::TYPES)->label(__('Part type'))->required()->default('aftermarket')->index()->filterable()->col(4),
            Field::select('condition_grade', ['A' => 'Grade A', 'B' => 'Grade B', 'C' => 'Grade C', 'damaged' => __('Damaged')])->label(__('Condition grade'))->col(4)->filterable(),
            Field::text('unit')->default('pcs')->col(4),
            Field::textarea('description'),
            Field::money('purchase_price')->label(__('Purchase price'))->section(__('Pricing'))->col(3),
            Field::money('sale_price')->label(__('Retail price'))->required()->index()->col(3),
            Field::money('wholesale_price')->label(__('Wholesale price'))->col(3),
            Field::money('workshop_price')->label(__('Workshop price'))->col(3),
            Field::money('min_sale_price')->label(__('Minimum sale price'))->col(3),
            Field::decimal('profit_margin')->label(__('Profit margin %'))->col(3)->help(__('Used to suggest the retail price when empty.')),
            Field::decimal('tax_rate')->label(__('VAT %'))->default(5)->col(3),
            Field::money('average_cost')->label(__('Average cost'))->hideOnForm(),
            Field::decimal('on_hand')->label(__('On hand'))->hideOnForm()->hideOnShow()->display(fn ($p) => '<span class="font-monospace '.(Money::cmp($p->on_hand ?? 0, $p->min_stock) <= 0 ? 'text-danger fw-bold' : '').'">'.qty($p->on_hand ?? 0).'</span>')->index(false),
            Field::decimal('reserved')->label(__('Reserved'))->hideOnForm()->hideOnShow()->display(fn ($p) => qty($p->reserved ?? 0))->index(false),
            Field::decimal('min_stock')->label(__('Minimum stock'))->section(__('Stock control'))->col(3),
            Field::decimal('reorder_qty')->label(__('Reorder quantity'))->col(3),
            Field::decimal('max_stock')->label(__('Maximum stock'))->col(3),
            Field::lookup('default_supplier_id', 'suppliers', 'supplier')->label(__('Default supplier'))->col(3),
            Field::number('warranty_days')->label(__('Warranty (days)'))->default(0)->col(3),
            Field::number('warranty_km')->label(__('Warranty (km)'))->default(0)->col(3),
            Field::checkbox('track_serial')->label(__('Track serial numbers'))->col(3),
            Field::checkbox('track_batch')->label(__('Track batches'))->col(3),
            Field::checkbox('track_expiry')->label(__('Track expiry dates'))->col(3),
            Field::checkbox('is_service_item')->label(__('Non-stock item'))->col(3),
            Field::checkbox('is_online')->label(__('Show in online store'))->col(3)->filterable(),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->col(3)->filterable(),
        ];
    }

    public function virtualFields(): array { return ['alternatives_text']; }

    public function prepare(array $data, ?Model $m): array
    {
        if (empty($data['sku']) && ! $m) {
            $data['sku'] = DocumentNumber::next('part', false);
        }
        if ((empty($data['sale_price']) || Money::isZero($data['sale_price'])) && ! empty($data['profit_margin']) && ! empty($data['purchase_price'])) {
            $data['sale_price'] = Money::add($data['purchase_price'], Money::percent($data['purchase_price'], $data['profit_margin']));
        }

        return $data;
    }

    public function rules(?Model $m): array
    {
        $r = parent::rules($m);
        $r['sku'] = array_values(array_diff($r['sku'], ['required']));
        array_unshift($r['sku'], 'nullable');

        return $r;
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        if (array_key_exists('alternatives_text', $data) && $data['alternatives_text'] !== null) {
            $m->alternatives()->delete();
            foreach (array_filter(array_map('trim', explode(',', $data['alternatives_text']))) as $n) {
                $m->alternatives()->create(['number' => $n]);
            }
        }
    }

    public function showUrl(Model $m): ?string { return route('parts.show', $m); }

    public function deleteBlocker(Model $m): ?string
    {
        return $m->movements()->exists() ? __('Parts with stock movements cannot be deleted. Deactivate instead.') : null;
    }

    public function headerActions(): array
    {
        return [
            ['label' => __('Advanced search'), 'url' => route('parts.search'), 'icon' => 'bi-binoculars'],
            ['label' => __('Import CSV'), 'url' => route('parts.import'), 'icon' => 'bi-upload'],
        ];
    }
}

class PartCategoryResource extends CrudResource
{
    public static string $model = PartCategory::class;
    public static string $slug = 'part-categories';
    public static string $permission = 'part_categories';
    public static ?string $module = 'inventory';
    public static string $icon = 'bi-folder2';
    public static array $with = ['parent'];

    public function title(): string { return __('Part Categories'); }

    public function fields(): array
    {
        return [
            Field::text('code')->index()->searchable()->col(3),
            Field::text('name_ar')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(5),
            Field::text('name_en')->label(__('Name (English)'))->index()->searchable()->col(4),
            Field::select('parent_id', fn () => PartCategory::query()->whereNull('parent_id')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all())->label(__('Parent category (sub-category of)'))->relation('parent', 'displayName')->index(),
        ];
    }
}

class BrandResource extends CrudResource
{
    public static string $model = Brand::class;
    public static string $slug = 'brands';
    public static string $permission = 'brands';
    public static ?string $module = 'inventory';
    public static string $icon = 'bi-award';
    public static string $defaultSort = 'name';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Manufacturers & Brands'); }

    public function fields(): array
    {
        return [
            Field::text('name')->required()->index()->searchable(),
            Field::text('country')->label(__('Country'))->index()->rules(['size:2']),
            Field::checkbox('is_oem')->label(__('OEM manufacturer'))->index(),
        ];
    }
}

class WarehouseResource extends CrudResource
{
    public static string $model = Warehouse::class;
    public static string $slug = 'warehouses';
    public static string $permission = 'warehouses';
    public static ?string $module = 'inventory';
    public static string $icon = 'bi-building';
    public static array $with = ['branch'];

    public function title(): string { return __('Warehouses'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->unique('warehouses')->col(3),
            Field::text('name')->label(__('Name (Arabic)'))->required()->index()->searchable()->col(5),
            Field::text('name_en')->label(__('Name (English)'))->index()->col(4),
            Field::select('branch_id', fn () => Options::branches())->label(__('Branch'))->relation('branch', 'displayName')->required()->index()->filterable(),
            Field::select('type', ['main', 'workshop', 'showroom', 'scrap', 'transit', 'online'])->default('main')->index(),
            Field::text('value')->label(__('Stock value'))->hideOnForm()->display(fn ($w) => e(money(app(\App\Modules\Inventory\Services\StockService::class)->valuation($w->id))))->index(false),
            Field::checkbox('is_active')->label(__('Active'))->default(true)->index(),
        ];
    }

    public function saving(Model $m, array $data, bool $creating): void
    {
        $max = tenant()->company()?->plan?->max_warehouses;
        if ($creating && $max && Warehouse::query()->count() >= $max) {
            throw new \App\Exceptions\BusinessException(__('Your subscription plan allows up to :n warehouses.', ['n' => $max]));
        }
    }
}

class WarehouseLocationResource extends CrudResource
{
    public static string $model = WarehouseLocation::class;
    public static string $slug = 'warehouse-locations';
    public static string $permission = 'warehouses';
    public static ?string $module = 'inventory';
    public static string $icon = 'bi-bookshelf';
    public static array $with = ['warehouse'];
    public static string $defaultSort = 'code';
    public static string $defaultDir = 'asc';

    public function title(): string { return __('Shelves & Locations'); }

    public function fields(): array
    {
        return [
            Field::select('warehouse_id', fn () => Options::warehouses())->label(__('Warehouse'))->relation('warehouse', 'displayName')->required()->index()->filterable(),
            Field::text('code')->required()->index()->searchable()->col(3)->help('A-01-03'),
            Field::text('name')->index()->col(3),
            Field::text('aisle')->label(__('Aisle'))->col(3),
            Field::text('rack')->label(__('Rack'))->col(3),
            Field::text('shelf')->label(__('Shelf'))->col(3),
            Field::text('bin')->label(__('Bin'))->col(3),
        ];
    }
}

class StockLevelResource extends CrudResource
{
    public static string $model = StockLevel::class;
    public static string $slug = 'stock-levels';
    public static string $permission = 'stock';
    public static ?string $module = 'inventory';
    public static string $icon = 'bi-boxes';
    public static bool $readonly = true;
    public static array $with = ['part', 'warehouse', 'location'];
    public static string $defaultSort = 'qty_on_hand';

    public function title(): string { return __('Stock Levels'); }

    public function query(): Builder
    {
        $q = parent::query()->whereHas('part');
        if (request('low') === '1') {
            $q->whereHas('part', fn ($p) => $p->whereColumn('stock_levels.qty_on_hand', '<=', 'parts.min_stock'));
        }

        return $q;
    }

    public function searchColumns(): array { return ['part.name_ar', 'part.sku', 'part.oem_number', 'part.barcode']; }

    public function fields(): array
    {
        return [
            Field::text('sku')->label('SKU')->display(fn ($s) => '<a href="'.route('parts.show', $s->part_id).'">'.e($s->part?->sku).'</a>')->index(false),
            Field::text('part_name')->label(__('Part'))->display(fn ($s) => e($s->part?->displayName()))->index(false),
            Field::select('warehouse_id', fn () => Options::warehouses())->label(__('Warehouse'))->relation('warehouse', 'displayName')->index()->filterable(),
            Field::text('loc')->label(__('Location'))->display(fn ($s) => e($s->location?->code))->index(false),
            Field::decimal('qty_on_hand')->label(__('On hand'))->index(),
            Field::decimal('qty_reserved')->label(__('Reserved'))->index(),
            Field::decimal('available')->label(__('Available'))->display(fn ($s) => '<b class="font-monospace">'.qty($s->available()).'</b>')->index(false),
            Field::money('avg_cost')->label(__('Avg. cost'))->index(),
            Field::text('value')->label(__('Value'))->display(fn ($s) => e(money(Money::mul($s->qty_on_hand, $s->avg_cost))))->index(false),
            Field::datetime('last_movement_at')->label(__('Last movement'))->index(),
        ];
    }

    public function headerActions(): array
    {
        return [['label' => __('Low stock only'), 'url' => route('crud.index', ['stock-levels', 'low' => 1]), 'icon' => 'bi-exclamation-triangle', 'class' => 'btn-outline-danger']];
    }
}

class StockMovementResource extends CrudResource
{
    public static string $model = StockMovement::class;
    public static string $slug = 'stock-movements';
    public static string $permission = 'stock';
    public static ?string $module = 'inventory';
    public static string $icon = 'bi-arrow-left-right';
    public static bool $readonly = true;
    public static array $with = ['part', 'warehouse', 'user', 'batch'];
    public static string $defaultSort = 'moved_at';
    public static ?string $dateColumn = 'moved_at';

    public function title(): string { return __('Stock Movements Log'); }

    public function searchColumns(): array { return ['reference_number', 'part.name_ar', 'part.sku']; }

    public function fields(): array
    {
        $types = array_merge(\App\Modules\Inventory\Services\StockService::IN_TYPES, \App\Modules\Inventory\Services\StockService::OUT_TYPES);

        return [
            Field::datetime('moved_at')->label(__('Date'))->index(),
            Field::select('type', $types)->index()->filterable(),
            Field::text('part_name')->label(__('Part'))->display(fn ($m) => e($m->part?->label()))->index(false),
            Field::select('warehouse_id', fn () => Options::warehouses())->label(__('Warehouse'))->relation('warehouse', 'displayName')->index()->filterable(),
            Field::decimal('qty')->label(__('Qty'))->display(fn ($m) => '<span class="font-monospace '.(Money::isPositive($m->qty) ? 'text-success' : 'text-danger').'">'.(Money::isPositive($m->qty) ? '+' : '').qty($m->qty).'</span>')->index(false),
            Field::money('unit_cost')->label(__('Unit cost'))->index(),
            Field::money('total_cost')->label(__('Value'))->index(),
            Field::decimal('balance_after')->label(__('Balance'))->index(),
            Field::text('serial')->label(__('Serial / batch'))->display(fn ($m) => e($m->batch?->serial_number ?? $m->batch?->batch_number))->index(false),
            Field::text('reference_number')->label(__('Reference'))->index(),
            Field::text('by')->label(__('By'))->display(fn ($m) => e($m->user?->name))->index(false),
        ];
    }
}

class StockTransferResource extends CrudResource
{
    public static string $model = StockTransfer::class;
    public static string $slug = 'stock-transfers';
    public static string $permission = 'stock_transfers';
    public static ?string $module = 'inventory';
    public static string $icon = 'bi-truck';
    public static array $with = ['fromWarehouse', 'toWarehouse', 'creator', 'receiver'];
    public static ?string $dateColumn = 'transfer_date';

    public function title(): string { return __('Stock Transfers'); }

    public function rowActions(Model $m): array
    {
        if (! auth()->user()?->can('stock_transfers.print')) {
            return [];
        }

        return $m->status === 'received'
            ? [['label' => __('Print completed transfer'), 'url' => route('inventory.transfers.print', [$m, 'completed']), 'icon' => 'bi-printer', 'blank' => true]]
            : [['label' => __('Print transfer request'), 'url' => route('inventory.transfers.print', [$m, 'request']), 'icon' => 'bi-printer', 'blank' => true]];
    }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('transfer_date')->label(__('Date'))->index(),
            Field::select('from_warehouse_id', fn () => Options::warehouses())->label(__('From'))->relation('fromWarehouse', 'displayName')->index()->filterable(),
            Field::select('to_warehouse_id', fn () => Options::warehouses())->label(__('To'))->relation('toWarehouse', 'displayName')->index()->filterable(),
            Field::select('status', ['draft', 'in_transit', 'received', 'cancelled'])->index()->filterable(),
            Field::text('requested')->label(__('Requested by'))->hideOnForm()->index(false)->display(fn ($t) => e($t->creator?->name ?? '—')),
            Field::text('received_by_name')->label(__('Received by'))->hideOnForm()->index(false)->display(fn ($t) => $t->receiver ? e($t->receiver->name).'<div class="small text-body-secondary">'.e($t->received_at?->format('Y-m-d H:i')).'</div>' : '<span class="text-body-tertiary">—</span>'),
        ];
    }

    public function createUrl(): ?string { return route('inventory.transfers.create'); }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('inventory.transfers.show', $m); }
    public function deleteBlocker(Model $m): ?string { return $m->status !== 'draft' ? __('Only draft documents can be deleted.') : null; }
}

class StockCountResource extends CrudResource
{
    public static string $model = StockCount::class;
    public static string $slug = 'stock-counts';
    public static string $permission = 'stock_counts';
    public static ?string $module = 'inventory';
    public static string $icon = 'bi-clipboard-data';
    public static array $with = ['warehouse'];
    public static ?string $dateColumn = 'count_date';

    public function title(): string { return __('Stock Counts (Stocktaking)'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('count_date')->label(__('Date'))->index(),
            Field::select('warehouse_id', fn () => Options::warehouses())->label(__('Warehouse'))->relation('warehouse', 'displayName')->index()->filterable(),
            Field::select('type', ['periodic', 'annual', 'cycle'])->index()->filterable(),
            Field::select('status', ['draft', 'in_progress', 'posted', 'cancelled'])->index()->filterable(),
        ];
    }

    public function createUrl(): ?string { return route('inventory.counts.create'); }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('inventory.counts.show', $m); }
    public function deleteBlocker(Model $m): ?string { return $m->status === 'posted' ? __('Posted documents cannot be deleted.') : null; }
}

class StockAdjustmentResource extends CrudResource
{
    public static string $model = StockAdjustment::class;
    public static string $slug = 'stock-adjustments';
    public static string $permission = 'stock_adjustments';
    public static ?string $module = 'inventory';
    public static string $icon = 'bi-sliders';
    public static array $with = ['warehouse'];
    public static ?string $dateColumn = 'date';

    public function title(): string { return __('Stock Adjustments'); }

    public function fields(): array
    {
        return [
            Field::text('number')->index()->searchable(),
            Field::date('date')->index(),
            Field::select('warehouse_id', fn () => Options::warehouses())->label(__('Warehouse'))->relation('warehouse', 'displayName')->index()->filterable(),
            Field::select('reason', ['damage', 'loss', 'found', 'correction', 'expired', 'opening'])->index()->filterable(),
            Field::select('status', ['draft', 'posted'])->index()->filterable(),
            Field::text('notes')->index(),
        ];
    }

    public function createUrl(): ?string { return route('inventory.adjustments.create'); }
    public function editUrl(Model $m): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('inventory.adjustments.show', $m); }
    public function deleteBlocker(Model $m): ?string { return $m->status === 'posted' ? __('Posted documents cannot be deleted.') : null; }
}

class DismantledVehicleResource extends CrudResource
{
    public static string $model = DismantledVehicle::class;
    public static string $slug = 'dismantled-vehicles';
    public static string $permission = 'dismantling';
    public static ?string $module = 'dismantling';
    public static string $icon = 'bi-car-front-fill';
    public static array $with = ['make', 'vehicleModel'];
    public static ?string $dateColumn = 'purchase_date';
    public static bool $hasAttachments = true;

    public function title(): string { return __('Purchased Vehicles for Dismantling'); }
    public function singular(): string { return __('Dismantling vehicle'); }

    public function fields(): array
    {
        return [
            Field::text('number')->hideOnForm()->index()->searchable(),
            Field::text('vin')->label(__('VIN (chassis)'))->index()->searchable()->col(4)->rules(['nullable', 'size:17'])->section(__('Vehicle')),
            Field::select('vehicle_make_id', fn () => VehicleMake::query()->orderBy('name')->pluck('name', 'id')->all())->label(__('Make'))->relation('make')->index()->col(4),
            Field::lookup('vehicle_model_id', 'vehicle_models', 'vehicleModel')->label(__('Model'))->col(4)->attrs(['params' => 'make_id=#f_vehicle_make_id']),
            Field::number('year')->col(3)->index(),
            Field::text('color')->col(3),
            Field::number('mileage')->label(__('Mileage (km)'))->col(3),
            Field::select('warehouse_id', fn () => Options::warehouses())->label(__('Target warehouse'))->relation('warehouse', 'displayName')->col(3),
            Field::select('source', ['auction', 'insurance', 'individual', 'dealer', 'other'])->label(__('Source'))->default('auction')->index()->filterable()->col(3)->section(__('Seller & purchase')),
            Field::lookup('supplier_id', 'suppliers', 'supplier')->label(__('Supplier (if on credit)'))->col(3),
            Field::text('seller_name')->label(__('Seller name'))->col(3),
            Field::tel('seller_phone')->label(__('Seller phone'))->col(3),
            Field::text('seller_id_number')->label(__('Seller ID'))->col(3),
            Field::date('purchase_date')->label(__('Purchase date'))->required()->default(now()->toDateString())->index()->col(3),
            Field::select('paid_from_bank_account_id', fn () => Options::bankAccounts())->label(__('Paid from (cash/bank)'))->col(3)->hideOnShow(),
            Field::money('purchase_cost')->label(__('Purchase cost'))->required()->index()->col(3),
            Field::money('transport_cost')->label(__('Transport cost'))->col(3),
            Field::money('dismantling_cost')->label(__('Dismantling cost'))->col(3),
            Field::money('other_costs')->label(__('Other costs'))->col(3),
            Field::money('allocated_cost')->label(__('Cost allocated to parts'))->hideOnForm(),
            Field::select('status', ['purchased', 'dismantling', 'completed', 'closed'])->hideOnForm()->index()->filterable(),
            Field::textarea('notes'),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['number'] = DocumentNumber::next('dismantled_vehicle');
            $data['status'] = 'purchased';
            $data['created_by'] = auth()->id();
        }
        if (! empty($data['vin'])) {
            $data['vin'] = strtoupper($data['vin']);
        }

        return $data;
    }

    public function rules(?Model $m): array
    {
        $r = parent::rules($m);
        $r['supplier_id'][] = 'required_without:paid_from_bank_account_id';
        $r += DismantlingService::checklistRules();

        return $r;
    }

    public function formExtras(?Model $m = null): ?string
    {
        return $m ? null : 'dismantling::_parts-checklist';
    }

    public function formFields(?Model $m = null): array
    {
        // costs are locked after purchase (use "add cost" to post extra costs)
        if ($m) {
            return array_values(array_filter(parent::formFields($m), fn ($f) => ! in_array($f->name, ['purchase_cost', 'transport_cost', 'dismantling_cost', 'other_costs', 'paid_from_bank_account_id', 'supplier_id', 'purchase_date'], true)));
        }

        return parent::formFields($m);
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        if ($created) {
            $service = app(DismantlingService::class);
            $service->registerPurchase($m);
            // parts ticked in the checklist of the new-vehicle form
            $parts = $service->addChecklist($m, (array) request()->input('checklist', []), $m->warehouse_id);
            if ($parts && request()->boolean('checklist_to_stock') && auth()->user()->can('dismantling.approve')) {
                $service->moveToStock($m);
            }
        }
    }

    public function showUrl(Model $m): ?string { return route('dismantling.show', $m); }
    public function deleteBlocker(Model $m): ?string { return __('Dismantling files are financial records and cannot be deleted.'); }
}
