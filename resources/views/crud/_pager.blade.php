    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small text-body-secondary">{{ __('Showing :a-:b of :t', ['a' => $rows->firstItem() ?? 0, 'b' => $rows->lastItem() ?? 0, 't' => $rows->total()]) }}
            <select class="form-select form-select-sm d-inline-block w-auto ms-2" onchange="location.href=this.value">
                @foreach([10, 25, 50, 100] as $pp)
                    <option value="{{ request()->fullUrlWithQuery(['per_page' => $pp, 'page' => null]) }}" @selected($rows->perPage() === $pp)>{{ $pp }}</option>
                @endforeach
            </select>
        </div>
        {{ $rows->links() }}
    </div>
