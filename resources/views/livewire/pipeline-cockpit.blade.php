<div class="space-y-6" @if($shouldPoll) wire:poll.5s="refreshSnapshot" @endif>
    <div class="flex items-start justify-between gap-4">
        <div>
            <x-ui.heading-2>Pipeline Cockpit</x-ui.heading-2>
            <p class="mt-1 text-[13px] text-maestro-muted">Tâche : {{ $task->title }}</p>
        </div>

        <x-ui.metric-card
            label="Coût total"
            :value="'$'.number_format($snapshot['total_cost'] ?? 0, 4)"
            :sub="($snapshot['is_active'] ?? false) ? 'Pipeline en cours…' : null"
            subColor="info"
            class="min-w-[180px] text-right"
        />
    </div>

    @if(($snapshot['steps'] ?? []) === [])
        <x-ui.card class="py-12 text-center">
            <div class="mb-4 text-maestro-subtle">
                <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="font-medium text-maestro-text">Pipeline non démarrée</p>
            <p class="mt-2 text-[13px] text-maestro-subtle">Démarrez la pipeline pour voir la progression en temps réel</p>
        </x-ui.card>
    @else
        <x-ui.card class="p-8">
            <div class="space-y-1">
                @foreach($snapshot['steps'] ?? [] as $index => $step)
                    @if($step['type'] === 'agent')
                        <x-maestro.cockpit-agent-step
                            :agent-type="$step['agent_type']"
                            :status="$step['status']"
                            :cost="$step['cost']"
                            :run-id="$step['run_id']"
                            :output-exists="$step['output_exists'] ?? false"
                            :attempt="$step['attempt'] ?? 1"
                            :error-message="$step['error_message'] ?? null"
                            :is-last="$index === count($snapshot['steps']) - 1"
                            @open-output="openAgentOutput"
                        />
                    @elseif($step['type'] === 'gate')
                        <x-maestro.cockpit-gate-step
                            :gate-id="$step['gate_id']"
                            :gate-type="$step['gate_type']"
                            :status="$step['status']"
                            :feedback="$step['feedback'] ?? null"
                            :is-last="$index === count($snapshot['steps']) - 1"
                            @approve-gate="approveGate"
                            @reject-gate="rejectGate"
                        />
                    @endif
                @endforeach
            </div>
        </x-ui.card>

        @if($snapshot['status'] === 'done')
            <div class="flex items-start gap-3 rounded-lg border p-4" style="border-color: var(--maestro-success-border); background: var(--maestro-success-bg);">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0" style="color: var(--maestro-success)" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <div>
                    <p class="font-medium" style="color: var(--maestro-success)">Pipeline terminée</p>
                    <p class="text-[13px] text-maestro-muted">Tous les agents ont été exécutés avec succès</p>
                </div>
            </div>
        @endif
    @endif
</div>

@script
<script>
    Livewire.on('open-agent-output', (data) => {
        window.dispatchEvent(new CustomEvent('open-agent-output', { detail: data }));
    });
</script>
@endscript
