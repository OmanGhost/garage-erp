@props(['withCost' => false, 'withNotes' => false, 'signed' => false])
<div x-data="{ rows: [{uid: 1, part_id: '', qty: 1, unit_cost: '', notes: ''}], n: 1,
        add() { this.rows.push({uid: ++this.n, part_id: '', qty: 1, unit_cost: '', notes: ''}); this.$nextTick(() => erpInitLookups(this.$el)); } }">
    <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>{{ __('Part') }}</th><th style="width:130px">{{ __('Qty') }}{{ $signed ? ' (±)' : '' }}</th>
            @if($withCost)<th style="width:140px">{{ __('Unit cost') }}</th>@endif @if($withNotes)<th>{{ __('Notes') }}</th>@endif<th style="width:40px"></th></tr></thead>
        <tbody>
        <template x-for="(r, i) in rows" :key="r.uid">
            <tr>
                <td><div class="lookup" data-lookup="parts" @lookup-selected="r.part_id = $event.detail.id; if (!r.unit_cost) r.unit_cost = $event.detail.extra.purchase_price">
                    <input type="hidden" :name="`items[${i}][part_id]`" :value="r.part_id"><input type="text" class="form-control form-control-sm" autocomplete="off" placeholder="{{ __('Search part...') }}"><div class="lookup-menu"></div></div></td>
                <td><input type="number" step="0.001" @if(! $signed) min="0.001" @endif class="form-control form-control-sm" :name="`items[${i}][qty]`" x-model="r.qty"></td>
                @if($withCost)<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" :name="`items[${i}][unit_cost]`" x-model="r.unit_cost"></td>@endif
                @if($withNotes)<td><input class="form-control form-control-sm" :name="`items[${i}][notes]`" x-model="r.notes"></td>@endif
                <td><button type="button" class="btn btn-sm btn-link text-danger" @click="rows.splice(i, 1)"><i class="bi bi-x-circle"></i></button></td>
            </tr>
        </template>
        </tbody>
    </table>
    <button type="button" class="btn btn-sm btn-outline-primary" @click="add()"><i class="bi bi-plus"></i> {{ __('Add line') }}</button>
</div>
