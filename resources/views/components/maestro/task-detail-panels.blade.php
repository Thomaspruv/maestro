@props(['defaultTab' => 'pipeline'])

<div
    x-data="{ mobileTab: @js($defaultTab) }"
    {{ $attributes->merge(['class' => 'flex min-h-0 flex-1 flex-col']) }}
>
    <div class="mb-3 flex gap-1 rounded-lg border bg-maestro-surface p-1 lg:hidden">
        <button
            type="button"
            @click="mobileTab = 'pipeline'"
            :class="mobileTab === 'pipeline' ? 'bg-maestro-accent text-white' : 'text-maestro-muted'"
            class="flex-1 rounded-md px-3 py-2 text-[12px] font-medium transition-colors"
        >
            Pipeline
        </button>
        <button
            type="button"
            @click="mobileTab = 'output'"
            :class="mobileTab === 'output' ? 'bg-maestro-accent text-white' : 'text-maestro-muted'"
            class="flex-1 rounded-md px-3 py-2 text-[12px] font-medium transition-colors"
        >
            Output
        </button>
    </div>

    <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-[minmax(240px,280px)_1fr] lg:gap-4">
        <div
            class="min-h-0 overflow-y-auto"
            :class="{ 'hidden lg:block': mobileTab !== 'pipeline' }"
        >
            {{ $pipeline }}
        </div>
        <div
            class="flex min-h-0 flex-col overflow-hidden"
            :class="{ 'hidden lg:flex': mobileTab !== 'output' }"
        >
            {{ $output }}
        </div>
    </div>
</div>
