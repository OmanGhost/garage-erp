<div class="card mb-3">
    <div class="card-header">{{ __('Kit components') }}</div>
    <div class="card-body">
        @can('parts.update')
        <form method="post" action="{{ route('inventory.kit.components', $p) }}">@csrf <x-idem/>
            @foreach($p->kitItems->concat([null])->take(40) as $index => $item)
                <div class="d-flex gap-2 mb-2">
                    <x-lookup name="components[{{ $index }}][component_part_id]" type="parts" :value="$item?->component_part_id" :text="$item?->component?->label() ?? ''" :placeholder="__('Component part')"/>
                    <label>{{ __('Qty') }}<input class="form-control" type="number" step=".001" min="0" name="components[{{ $index }}][qty]" value="{{ $item?->qty ?? 1 }}"></label>
                </div>
            @endforeach
            <button class="btn btn-outline-primary">{{ __('Save components') }}</button>
        </form>
        @else
            @foreach($p->kitItems as $item)<div>{{ $item->component?->label() }} ? {{ qty($item->qty) }}</div>@endforeach
        @endcan
        @if($p->is_kit) @can('stock.update')
        <form class="d-flex flex-wrap align-items-end gap-2 mt-3" method="post" action="{{ route('inventory.kit.build', [$p, 'assemble']) }}">@csrf <x-idem/>
            <label>{{ __('Warehouse') }}<select name="warehouse_id" class="form-select" required>@foreach($warehouses as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
            <label>{{ __('Qty') }}<input class="form-control" type="number" name="qty" min="1" max="10000" value="1" required></label>
            <button class="btn btn-primary">{{ __('Assemble kits') }}</button>
            <button class="btn btn-outline-secondary" formaction="{{ route('inventory.kit.build', [$p, 'disassemble']) }}">{{ __('Disassemble kits') }}</button>
        </form>
        @endcan @endif
    </div>
</div>
