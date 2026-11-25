<div class="card">
    <div class="card-header"><i class="bi bi-clock-history me-1"></i>{{ __('Change history') }}</div>
    <div class="card-body small" style="max-height:600px;overflow:auto">
        <ul class="timeline">
            @foreach($audits as $a)
                <li>
                    <div class="fw-semibold">{{ status_label(str_replace('.', '_', $a->event)) }} <span class="text-body-secondary fw-normal">· {{ $a->user?->name ?? __('System') }}</span></div>
                    <div class="text-body-secondary">{{ $a->created_at?->format('Y-m-d H:i') }} @if($a->ip_address)· {{ $a->ip_address }}@endif</div>
                    @if($a->event === 'updated' && is_array($a->new_values))
                        <div class="mt-1">
                            @foreach($a->new_values as $k => $v)
                                <div><code>{{ $k }}</code>: <del class="text-danger">{{ \Illuminate\Support\Str::limit((string) ($a->old_values[$k] ?? ''), 40) }}</del> → <span class="text-success">{{ \Illuminate\Support\Str::limit((string) $v, 40) }}</span></div>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
