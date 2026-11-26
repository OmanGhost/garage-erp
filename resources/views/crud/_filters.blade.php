@php $filters = $filters ?? $resource->filters(); @endphp
    <div class="card-body border-bottom">
        <form method="get" class="row g-2 align-items-end filters-bar">
            @foreach(array_filter($keep ?? [], fn ($v) => $v !== null && $v !== '') as $kk => $kv)<input type="hidden" name="{{ $kk }}" value="{{ $kv }}">@endforeach
            <div class="col-12 col-md-4 col-xl-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Search...') }}" data-scan-target>
                </div>
            </div>
            @foreach($filters as $f)
                <div class="col-6 col-md-auto">
                    @if($f->type === 'checkbox')
                        <select name="f_{{ $f->name }}" class="form-select" onchange="this.form.submit()">
                            <option value="">{{ $f->getLabel() }}: {{ __('All') }}</option>
                            <option value="1" @selected(request('f_'.$f->name) === '1')>{{ __('Yes') }}</option>
                            <option value="0" @selected(request('f_'.$f->name) === '0')>{{ __('No') }}</option>
                        </select>
                    @else
                        <select name="f_{{ $f->name }}" class="form-select" onchange="this.form.submit()">
                            <option value="">{{ $f->getLabel() }}: {{ __('All') }}</option>
                            @foreach($f->getOptions() as $k => $v)
                                <option value="{{ $k }}" @selected((string) request('f_'.$f->name) === (string) $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endforeach
            @if($resource::$dateColumn)
                <div class="col-6 col-md-auto"><input type="date" name="from" value="{{ request('from') }}" class="form-control" title="{{ __('From') }}"></div>
                <div class="col-6 col-md-auto"><input type="date" name="to" value="{{ request('to') }}" class="form-control" title="{{ __('To') }}"></div>
            @endif
            <div class="col-auto"><button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button></div>
            @if(request()->hasAny(['q', 'from', 'to']) || collect(request()->query())->keys()->contains(fn($k) => str_starts_with($k, 'f_')))
                <div class="col-auto"><a href="{{ url()->current() }}" class="btn btn-link">{{ __('Reset') }}</a></div>
            @endif
            <div class="col-auto ms-md-auto d-flex gap-1">
                @if($resource->can('export'))
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> {{ __('Export') }}</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"><i class="bi bi-file-earmark-excel text-success me-2"></i>Excel</a></li>
                            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>PDF</a></li>
                        </ul>
                    </div>
                @endif
                @if($resource->can('print') || $resource->can('export'))
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'print']) }}" target="_blank" class="btn btn-outline-secondary" title="{{ __('Print') }}"><i class="bi bi-printer"></i></a>
                @endif
            </div>
        </form>
    </div>
