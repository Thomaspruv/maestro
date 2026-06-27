@props(['status' => 'pending'])

@php
$config = match ($status) {
    'running' => ['bg' => 'var(--maestro-info-bg)', 'text' => 'var(--maestro-info)', 'dot' => 'var(--maestro-info)', 'label' => 'Running'],
    'completed', 'done' => ['bg' => 'var(--maestro-success-bg)', 'text' => 'var(--maestro-success)', 'dot' => 'var(--maestro-success)', 'label' => 'Completed'],
    'gate', 'waiting_gate' => ['bg' => 'var(--maestro-warning-bg)', 'text' => 'var(--maestro-warning)', 'dot' => 'var(--maestro-warning)', 'label' => 'Gate'],
    'blocked', 'failed', 'error' => ['bg' => 'var(--maestro-danger-bg)', 'text' => 'var(--maestro-danger)', 'dot' => 'var(--maestro-danger)', 'label' => ucfirst($status === 'failed' ? 'Failed' : ($status === 'error' ? 'Error' : 'Blocked'))],
    'skipped' => ['bg' => 'var(--maestro-neutral-bg)', 'text' => 'var(--maestro-neutral)', 'dot' => '#334155', 'label' => 'Skipped'],
    default => ['bg' => 'var(--maestro-neutral-bg)', 'text' => 'var(--maestro-neutral)', 'dot' => '#475569', 'label' => 'Pending'],
};
@endphp

<span style="background: {{ $config['bg'] }}; color: {{ $config['text'] }}; border: 0.5px solid {{ $config['bg'] }};"
      {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[12px] font-medium']) }}>
    <span class="inline-block h-1.5 w-1.5 rounded-full" style="background: {{ $config['dot'] }}"></span>
    {{ $slot->isEmpty() ? $config['label'] : $slot }}
</span>
