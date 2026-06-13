{{-- Indicateur visuel pendant que Discovery IA travaille --}}
<div {{ $attributes->merge(['class' => 'discovery-thinking']) }}>
    <div class="discovery-thinking-avatar" aria-hidden="true">
        <span class="discovery-thinking-ring discovery-thinking-ring-1"></span>
        <span class="discovery-thinking-ring discovery-thinking-ring-2"></span>
        <span class="discovery-thinking-emoji">🤖</span>
    </div>
    <div class="min-w-0 flex-1">
        <div class="mb-1 flex items-center gap-2">
            <span class="text-xs font-semibold text-primary-light">Discovery IA</span>
            <span class="discovery-thinking-badge">En cours</span>
        </div>
        <p class="discovery-thinking-status">{{ $status ?? 'Analyse en cours…' }}</p>
        <div class="discovery-typing-dots mt-2" aria-label="Discovery réfléchit">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</div>
