@props(['emoji', 'name', 'model', 'status' => 'pending'])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-lg border bg-maestro-surface px-3.5 py-2.5']) }}>
    <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md bg-maestro-accent-muted text-[13px]">
        {{ $emoji }}
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-[13px] font-medium text-maestro-text">{{ $name }}</p>
        <p class="text-[11px] text-maestro-subtle">{{ $model }}</p>
    </div>
    <x-ui.badge :status="$status" />
</div>
