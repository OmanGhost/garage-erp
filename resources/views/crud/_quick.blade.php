{{-- body of the "add new" dialog opened from a search field (see QuickCreateController) --}}
<form method="post" action="{{ route('quick.store', $type) }}" data-quick-form novalidate>
    @csrf
    <div class="row">
        @foreach($fields as $f)
            @include('crud._field', ['f' => $f, 'model' => $model])
        @endforeach
    </div>
    <div class="small text-body-secondary"><i class="bi bi-info-circle"></i> {{ __('More details can be completed later from the full record.') }}</div>
</form>
