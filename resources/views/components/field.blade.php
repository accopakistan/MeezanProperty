@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'options' => null,
    'placeholder' => null,
    'rows' => 5,
])
{{--
    Generic form field. type: text|email|password|tel|number|textarea|select.
    For select, pass $options as [value => label]; a slot overrides options.
--}}
@php
    $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name));
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($key);
    $current = $type === 'password' ? null : old($key, $value);
    $classes = 'input'.($error ? ' input-error' : '');
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }} @if ($required)<span class="text-red-500" aria-hidden="true">*</span>@endif</label>
    @endif

    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" class="{{ $classes }}" placeholder="{{ $placeholder }}" @required($required)
                  @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif {{ $attributes->except(['class', 'id']) }}>{{ $current }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="{{ $classes }}" @required($required)
                @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif {{ $attributes->except(['class', 'id']) }}>
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @else
                @if ($placeholder !== null)
                    <option value="">{{ $placeholder }}</option>
                @endif
                @foreach ($options ?? [] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            @endif
        </select>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current }}" class="{{ $classes }}" placeholder="{{ $placeholder }}" @required($required) @if ($type === 'tel' && ! $attributes->has('inputmode')) inputmode="tel" @endif
               @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif {{ $attributes->except(['class', 'id']) }}>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="field-error">{{ $error }}</p>
    @elseif ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
</div>
