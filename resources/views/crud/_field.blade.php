@php
    /** @var \App\Support\Crud\Field $f */
    $value = old($f->name, $f->value($model));
    $id = 'f_'.$f->name;
    $invalid = $errors->has($f->name) ? 'is-invalid' : '';
@endphp
<div class="col-12 col-md-{{ $f->col }} mb-3">
    @if($f->type === 'checkbox')
        <div class="form-check form-switch mt-md-4">
            <input type="hidden" name="{{ $f->name }}" value="0">
            <input class="form-check-input {{ $invalid }}" type="checkbox" role="switch" id="{{ $id }}" name="{{ $f->name }}" value="1" @checked($value)>
            <label class="form-check-label" for="{{ $id }}">{{ $f->getLabel() }}</label>
        </div>
    @else
        <label for="{{ $id }}" class="form-label {{ $f->required ? 'required' : '' }}">{{ $f->getLabel() }}</label>
        @switch($f->type)
            @case('textarea')
                <textarea id="{{ $id }}" name="{{ $f->name }}" rows="3" class="form-control {{ $invalid }}" @required($f->required)>{{ $value }}</textarea>
                @break
            @case('select')
                <select id="{{ $id }}" name="{{ $f->name }}" class="form-select {{ $invalid }}" @required($f->required)>
                    @unless($f->required)<option value="">—</option>@endunless
                    @foreach($f->getOptions() as $k => $v)
                        <option value="{{ $k }}" @selected((string) $value === (string) $k)>{{ $v }}</option>
                    @endforeach
                </select>
                @break
            @case('lookup')
                <x-lookup :name="$f->name" :type="$f->lookup" :value="$value" :text="$f->lookupText($model)" :required="$f->required" :params="$f->attrs['params'] ?? ''"/>
                @break
            @case('image')
                @if($value)<div class="mb-1"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($value) }}" class="rounded border" style="max-height:70px"></div>@endif
                <input type="file" id="{{ $id }}" name="{{ $f->name }}" accept="image/*" class="form-control {{ $invalid }}">
                @break
            @case('password')
                <input type="password" id="{{ $id }}" name="{{ $f->name }}" class="form-control {{ $invalid }}" autocomplete="new-password" @if($f->required && !$model) required @endif>
                @break
            @case('color')
                <input type="color" id="{{ $id }}" name="{{ $f->name }}" value="{{ $value ?: '#1f6feb' }}" class="form-control form-control-color {{ $invalid }}">
                @break
            @default
                @php
                    $htmlType = ['integer' => 'number', 'decimal' => 'number', 'datetime' => 'datetime-local'][$f->type] ?? $f->type;
                @endphp
                <input type="{{ $htmlType }}" id="{{ $id }}" name="{{ $f->name }}" value="{{ $value }}" class="form-control {{ $invalid }} {{ in_array($f->type, ['decimal', 'integer']) ? 'font-monospace' : '' }}"
                       @if($f->type === 'decimal') step="0.001" @endif @if($f->type === 'integer') step="1" min="0" @endif
                       @if($f->type === 'tel') placeholder="+968 9xxxxxxx" dir="ltr" @endif
                       @required($f->required) @foreach($f->attrs as $ak => $av) @if($ak !== 'params') {{ $ak }}="{{ $av }}" @endif @endforeach>
        @endswitch
    @endif
    @if($f->help)<div class="form-text">{{ $f->help }}</div>@endif
    @error($f->name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
