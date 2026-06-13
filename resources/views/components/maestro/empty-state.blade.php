@props(['title' => 'Aucun élément', 'description' => null, 'icon' => '📭'])

<div {{ $attributes->merge(['class' => 'maestro-empty-state']) }}>
    <div class="mb-2 text-3xl opacity-50">{{ $icon }}</div>
    <p class="text-sm font-medium text-text-secondary">{{ $title }}</p>
    @if($description)
        <p class="mt-1 max-w-xs text-[11px]">{{ $description }}</p>
    @endif
    @if($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
