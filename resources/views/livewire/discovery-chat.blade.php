<div
    class="flex h-[calc(100vh-12rem)] flex-col"
    x-data="{
        scrollToBottom() {
            const el = document.getElementById('discovery-messages');
            if (el) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
        }
    }"
    x-on:discovery-scroll-to-bottom.window="scrollToBottom()"
>
    @if (session('success'))
        <div class="mb-3 rounded-md border border-success/30 bg-success/10 px-3 py-2 text-xs text-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Fil de messages --}}
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-bg-overlay bg-bg-surface/50">
        <div
            wire:loading
            wire:target="send, launchDiscovery"
            class="discovery-progress-track"
            aria-hidden="true"
        >
            <div class="discovery-progress-bar"></div>
        </div>

        <div
            id="discovery-messages"
            class="flex-1 space-y-4 overflow-x-hidden overflow-y-auto p-4"
        >
        @forelse($messages as $index => $msg)
            @if($msg['role'] === 'user')
                <div class="flex justify-end">
                    <div class="max-w-[80%] rounded-lg bg-primary-muted px-4 py-2 text-xs text-text-primary">
                        <p class="whitespace-pre-wrap">{{ $msg['content'] }}</p>
                    </div>
                </div>
            @else
                <div class="flex justify-start">
                    <div class="max-w-[85%] space-y-3">
                        <div class="rounded-lg border border-bg-overlay bg-bg-elevated px-4 py-3 text-xs text-text-primary">
                            <div class="mb-1 flex items-center gap-2">
                                <span>🤖</span>
                                <span class="font-semibold text-text-secondary">Discovery IA</span>
                                @if(isset($msg['cost']))
                                    <span class="text-[10px] text-text-muted">{{ number_format($msg['cost'], 4) }} $</span>
                                @endif
                            </div>
                            <div class="whitespace-pre-wrap leading-relaxed">{{ $msg['content'] }}</div>
                        </div>

                        @if(!empty($msg['proposed_tasks']))
                            <div class="space-y-2 pl-2">
                                @foreach($msg['proposed_tasks'] as $taskIndex => $task)
                                    @php $status = $task['status'] ?? 'pending'; @endphp
                                    <div @class([
                                        'maestro-card p-3',
                                        'opacity-50' => in_array($status, ['added', 'dismissed']),
                                    ])>
                                        <div class="mb-2 flex items-start justify-between gap-2">
                                            <div>
                                                <p class="text-xs font-semibold text-text-primary">{{ $task['title'] }}</p>
                                                <p class="mt-0.5 text-[10px] text-text-muted">
                                                    {{ $task['type'] ?? 'feature' }}
                                                    · {{ $task['priority'] ?? 'medium' }}
                                                    @if(!empty($task['module']))
                                                        · {{ $task['module'] }}
                                                    @endif
                                                </p>
                                            </div>
                                            @if($status === 'added')
                                                <span class="rounded bg-success/20 px-2 py-0.5 text-[10px] text-success">Ajoutée</span>
                                            @elseif($status === 'dismissed')
                                                <span class="rounded bg-bg-overlay px-2 py-0.5 text-[10px] text-text-muted">Ignorée</span>
                                            @endif
                                        </div>
                                        @if(!empty($task['description']))
                                            <p class="mb-3 text-[10px] leading-relaxed text-text-secondary">{{ Str::limit($task['description'], 300) }}</p>
                                        @endif
                                        @if($status === 'pending')
                                            <div class="flex gap-2">
                                                <x-maestro.button wire:click="addTask({{ $index }}, {{ $taskIndex }})">
                                                    Ajouter au backlog
                                                </x-maestro.button>
                                                <x-maestro.button variant="ghost" wire:click="dismissTask({{ $index }}, {{ $taskIndex }})">
                                                    Ignorer
                                                </x-maestro.button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @empty
            <div
                class="flex h-full flex-col items-center justify-center px-6 text-center"
                wire:loading.remove
                wire:target="send, launchDiscovery"
            >
                <span class="discovery-btn-icon mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-muted text-3xl">
                    🤖
                </span>
                <p class="text-sm font-semibold text-text-primary">Discovery IA</p>
                <p class="mt-2 max-w-md text-xs leading-relaxed text-text-muted">
                    Analyse le code du repo, le backlog et la veille marché en une seule passe,
                    puis propose des features et améliorations fonctionnelles.
                </p>
                <button
                    type="button"
                    wire:click="launchDiscovery"
                    wire:loading.attr="disabled"
                    wire:target="send, launchDiscovery"
                    class="maestro-btn-discovery mt-5 px-5 py-2.5"
                >
                    <span class="discovery-btn-icon flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary/20 text-base">
                        🚀
                    </span>
                    <span class="text-xs font-semibold text-text-primary">Lancer la Discovery</span>
                </button>
            </div>
        @endforelse

        <div wire:loading wire:target="send, launchDiscovery" class="flex justify-start">
            <x-maestro.discovery-typing status="Analyse du code, veille marché et génération des propositions…" />
        </div>
        </div>
    </div>

    @if($error)
        <div class="mt-3 rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
            {{ $error }}
        </div>
    @endif

    {{-- Bandeau statut pendant le chargement --}}
    <div
        wire:loading
        wire:target="send, launchDiscovery"
        class="mt-3 flex items-center gap-2 rounded-md border border-primary/30 bg-primary-muted/30 px-3 py-2 text-[11px] text-primary-light"
        role="status"
        aria-live="polite"
    >
        <span class="discovery-btn-icon inline-flex h-6 w-6 items-center justify-center rounded-md bg-primary/20 text-sm">🤖</span>
        <span><strong>Discovery IA travaille</strong> — code, veille web et backlog en cours…</span>
    </div>

    {{-- Zone de saisie --}}
    <div
        class="mt-4 flex gap-2"
        wire:loading.class="discovery-input-loading"
        wire:target="send, launchDiscovery"
    >
        <div class="flex-1">
            <textarea
                wire:model="message"
                wire:keydown.enter.prevent="send"
                rows="2"
                placeholder="Affinez la Discovery ou posez une question complémentaire…"
                class="maestro-input w-full resize-none text-xs"
                wire:loading.attr="disabled"
                wire:target="send, launchDiscovery"
            ></textarea>
        </div>
        <div class="flex flex-col gap-2">
            <x-maestro.button
                wire:click="send"
                wire:loading.attr="disabled"
                wire:loading.class="discovery-send-loading"
                wire:target="send, launchDiscovery"
            >
                <span wire:loading.remove wire:target="send, launchDiscovery">Envoyer</span>
                <span wire:loading wire:target="send, launchDiscovery" class="inline-flex items-center gap-1.5">
                    <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Analyse…
                </span>
            </x-maestro.button>
            @if(!empty($messages))
                <x-maestro.button
                    variant="ghost"
                    wire:click="clearHistory"
                    wire:loading.attr="disabled"
                    wire:target="send, launchDiscovery"
                >
                    Effacer
                </x-maestro.button>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.hook('commit', ({ component, succeed }) => {
            succeed(() => {
                if (component.name === 'discovery-chat') {
                    window.dispatchEvent(new CustomEvent('discovery-scroll-to-bottom'));
                }
            });
        });
    });
</script>
