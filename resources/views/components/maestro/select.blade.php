@props(['label' => null, 'name' => null, 'error' => null, 'required' => false])

@php
    $wireField = $attributes->get('wire:model')
        ?? $attributes->get('wire:model.live')
        ?? $attributes->get('wire:model.blur')
        ?? $attributes->get('wire:model.defer');
    $fieldName = $name ?? $wireField;
    $fieldError = $error ?? ($fieldName ? $errors->first($fieldName) : null);
    $inputId = $fieldName ? 'field-'.str_replace(['.', '[', ']'], ['-', '-', ''], $fieldName) : null;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if($label)
        <label @if($inputId) for="{{ $inputId }}" @endif class="maestro-label">
            {{ $label }}@if($required)<span class="text-danger"> *</span>@endif
        </label>
    @endif
    <select
        @if($inputId) id="{{ $inputId }}" @endif
        @if($name) name="{{ $name }}" @endif
        @class([
            'maestro-input',
            'border-danger ring-1 ring-danger/30' => $fieldError,
        ])
        {{ $attributes->except('class') }}
    >
        {{ $slot }}
    </select>
    @if($fieldError)
        <p class="mt-1 text-[10px] text-danger">{{ $fieldError }}</p>
    @endif
</div>
