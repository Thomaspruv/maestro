@props([
    'variant' => 'primary',
    'type' => 'button',
    'tag' => 'button',
    'size' => 'sm',
])

@php
$uiVariant = match ($variant) {
    'ghost' => 'ghost',
    'danger' => 'danger',
    default => 'primary',
};
@endphp

<x-ui.button :variant="$uiVariant" :size="$size" :type="$type" :tag="$tag" {{ $attributes }}>
    {{ $slot }}
</x-ui.button>
