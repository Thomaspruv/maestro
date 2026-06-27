@props(['status' => 'waiting', 'label' => null])

@php
$uiStatus = match ($status) {
    'running' => 'running',
    'done', 'completed' => 'completed',
    'gate', 'waiting_gate' => 'gate',
    'error', 'failed' => 'failed',
    'skipped' => 'skipped',
    default => 'pending',
};
@endphp

<x-ui.badge :status="$uiStatus" {{ $attributes }}>
    {{ $label ?? $slot }}
</x-ui.badge>
