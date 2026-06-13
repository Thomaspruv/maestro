@props(['status' => 'waiting', 'label' => null])

@php
    $pillClass = match ($status) {
        'running' => 'maestro-pill-running',
        'done', 'completed' => 'maestro-pill-done',
        'gate', 'waiting_gate' => 'maestro-pill-gate',
        'error', 'failed' => 'maestro-pill-error',
        default => 'maestro-pill-waiting',
    };
@endphp

<span {{ $attributes->merge(['class' => "maestro-pipeline-pill {$pillClass}"]) }}>
    {{ $label ?? $slot }}
</span>
