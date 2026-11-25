{{-- Job card step-by-step guide: 8 phases + "what to do now" with requirements and next actions --}}
@php $g = $guide; @endphp
<div class="card mb-3 jc-guide">
    <div class="card-body pb-2">
        {{-- stepper --}}
        <ol class="jc-steps" aria-label="{{ __('guide.title') }}">
            @foreach($g['phases'] as $p)
                <li class="jc-step is-{{ $p['state'] }}" @if($p['state'] === 'current') aria-current="step" @endif>
                    <span class="jc-step-dot">
                        @if($p['state'] === 'done')<i class="bi bi-check-lg"></i>@else<i class="bi {{ $p['icon'] }}"></i>@endif
                    </span>
                    <span class="jc-step-label"><small>{{ $p['number'] }}</small> {{ $p['title'] }}</span>
                </li>
            @endforeach
        </ol>
        <div class="progress mt-2" style="height:5px" title="{{ $jc->progress }}%"><div class="progress-bar bg-success" style="width: {{ $jc->progress }}%"></div></div>
    </div>

    <div class="card-body border-top jc-guide-now">
        @if($g['cancelled'])
            <div class="text-danger fw-semibold"><i class="bi bi-x-octagon"></i> {{ __('guide.cancelled') }}</div>
        @elseif($g['finished'])
            <div class="text-success fw-semibold"><i class="bi bi-flag-fill"></i> {{ __('guide.finished') }}</div>
        @else
            @if($g['on_hold'])
                <div class="alert alert-secondary py-2 mb-3"><i class="bi bi-pause-circle"></i> {{ __('guide.on_hold') }}</div>
            @endif
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="small text-body-secondary mb-1">{{ __('guide.step_of', ['n' => $g['number'], 't' => $g['total']]) }} · {{ __('guide.now') }}</div>
                    <h5 class="mb-1"><i class="bi {{ \App\Modules\Workshop\Services\JobCardGuide::ICONS[$g['current']] }} text-brand"></i> {{ $g['title'] }}</h5>
                    <div class="small mb-2"><span class="text-body-secondary">{{ __('guide.responsible') }}:</span> <span class="fw-semibold">{{ $g['who'] }}</span></div>
                    <p class="mb-3">{{ $g['instruction'] }}</p>

                    {{-- actions --}}
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($g['actions'] as $a)
                            @continue(! auth()->user()->can($a['perm']))
                            @php $cls = 'btn btn-sm '.(! empty($a['primary']) ? 'btn-primary' : 'btn-outline-secondary'); @endphp
                            @switch($a['type'])
                                @case('tab')
                                    <button type="button" class="{{ $cls }}" data-guide-tab="{{ $a['target'] }}"><i class="bi {{ $a['icon'] }}"></i> {{ $a['label'] }}</button>
                                    @break
                                @case('anchor')
                                    <a class="{{ $cls }}" href="{{ $a['target'] }}"><i class="bi {{ $a['icon'] }}"></i> {{ $a['label'] }}</a>
                                    @break
                                @case('link')
                                    <a class="{{ $cls }}" href="{{ $a['url'] }}" @if(! empty($a['blank'])) target="_blank" @endif><i class="bi {{ $a['icon'] }}"></i> {{ $a['label'] }}</a>
                                    @break
                                @case('post')
                                    <form method="post" action="{{ $a['url'] }}" class="d-inline">@csrf <x-idem/><button class="{{ $cls }}"><i class="bi {{ $a['icon'] }}"></i> {{ $a['label'] }}</button></form>
                                    @break
                                @case('status')
                                    @if(! empty($a['disabled']))
                                        <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="{{ $a['reason'] }}">
                                            <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-lock"></i> {{ $a['label'] }}</button>
                                        </span>
                                    @else
                                        <form method="post" action="{{ route('job-cards.status', $jc) }}" class="d-inline" @if(! empty($a['confirm'])) data-confirm="{{ $a['confirm'] }}" @endif>@csrf <x-idem/>
                                            <input type="hidden" name="status" value="{{ $a['status'] }}">
                                            <button class="{{ ! empty($a['primary']) ? 'btn btn-sm btn-success' : $cls }}"><i class="bi {{ $a['icon'] }}"></i> {{ $a['label'] }}</button>
                                        </form>
                                    @endif
                                    @break
                            @endswitch
                        @endforeach
                    </div>
                    @foreach($g['actions'] as $a)
                        @if(! empty($a['disabled']) && auth()->user()->can($a['perm']))
                            <div class="small text-body-secondary mt-2"><i class="bi bi-info-circle"></i> {{ $a['label'] }} — {{ __('guide.why_disabled') }}: {{ $a['reason'] }}</div>
                        @endif
                    @endforeach
                </div>

                @if($g['checklist'])
                    <div class="col-lg-5">
                        <div class="jc-guide-checklist">
                            <div class="small fw-semibold mb-2">{{ __('guide.requirements') }}</div>
                            <ul class="list-unstyled mb-0">
                                @foreach($g['checklist'] as $c)
                                    <li class="{{ $c['ok'] ? 'is-ok' : '' }}">
                                        <i class="bi {{ $c['ok'] ? 'bi-check-circle-fill text-success' : ($c['optional'] ? 'bi-dash-circle text-body-tertiary' : 'bi-circle text-warning') }}"></i>
                                        <span>{{ $c['label'] }}</span>
                                        @if($c['optional'] && ! $c['ok'])<small class="text-body-tertiary">({{ __('guide.optional') }})</small>@endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
