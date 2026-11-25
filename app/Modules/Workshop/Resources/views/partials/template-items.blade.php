<div class="card mb-3">
    <div class="card-header">{{ __('Checklist items') }}</div>
    <div class="card-body">
        @foreach($template->items->sortBy('sort')->groupBy('section') as $section => $items)
            <div class="fw-semibold mt-2">{{ $section }}</div>
            <ul class="mb-1">@foreach($items as $i)<li>{{ $i->label }} @if($i->label_en)<span class="text-body-secondary">/ {{ $i->label_en }}</span>@endif</li>@endforeach</ul>
        @endforeach
        <div class="form-text">{{ __('To change the list, edit the template and fill "Checklist items" (Section | Arabic | English per line).') }}</div>
    </div>
</div>
