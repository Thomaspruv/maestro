<div class="maestro-card flex h-full flex-col" @if($shouldPoll) wire:poll.5s="refreshViewer" @endif>
    @if($run)
        <div class="flex items-center justify-between border-b border-bg-overlay px-4 py-3">
            @php $label = $agentLabels[$run->agent_type] ?? ['emoji' => '🤖', 'name' => $run->agent_type]; @endphp
            <div>
                <h2 class="text-sm font-semibold text-text-primary">
                    {{ $label['emoji'] }} {{ $label['name'] }}
                </h2>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <x-maestro.badge kind="agent_status" :value="$run->status" />
                    @if($run->model)
                        <span class="text-[10px] text-text-muted">{{ $run->model }}</span>
                    @endif
                    @if($duration)
                        <span class="text-[10px] text-text-muted">· {{ $duration }}</span>
                    @endif
                    @if($run->attempt > 1)
                        <span class="text-[10px] text-warning">· tentative {{ $run->attempt }}/3</span>
                    @endif
                </div>
            </div>
            <div class="flex gap-2">
                @if($run->status->value === 'completed')
                    <x-maestro.button variant="ghost" wire:click="toggleEdit">
                        {{ $editMode ? 'Annuler' : 'Modifier' }}
                    </x-maestro.button>
                @endif
            </div>
        </div>

        @if(in_array($run->status->value, ['running', 'pending'], true))
            <div class="border-b border-primary/20 bg-primary-muted/15 px-4 py-3">
                <div class="flex items-start gap-3">
                    <span class="pipeline-spinner shrink-0" aria-hidden="true"></span>
                    <div>
                        <p class="text-xs font-semibold text-primary-light">
                            {{ $run->status->value === 'pending' ? 'Agent en file d\'attente' : 'Agent en cours d\'exécution' }}
                        </p>
                        <p class="mt-1 text-[11px] text-text-secondary">{{ $activityMessage }}</p>
                        @if($run->started_at)
                            <p class="mt-2 text-[10px] text-text-muted">
                                Démarré à {{ $run->started_at->format('H:i:s') }}
                                ({{ $run->started_at->diffForHumans() }})
                            </p>
                        @elseif($run->status->value === 'pending')
                            <p class="mt-2 text-[10px] text-text-muted">
                                Job dispatché — démarrage imminent si Horizon est actif.
                            </p>
                        @endif
                        <p class="mt-2 text-[10px] text-text-muted">
                            L'output apparaîtra ici dès que l'agent aura terminé. Cette vue se met à jour automatiquement.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if($run->status->value === 'failed' && $run->error_message)
            <div class="border-b border-danger/30 bg-danger/10 px-4 py-3">
                <p class="text-xs font-semibold text-danger">Échec de l'agent</p>
                <p class="mt-1 whitespace-pre-wrap font-mono text-[11px] text-danger/90">{{ $run->error_message }}</p>
            </div>
        @endif

        <div class="flex-1 overflow-y-auto p-4">
            @if($editMode)
                <x-maestro.textarea wire:model="editedOutput" rows="20" class="font-mono text-[11px]" />
                <div class="mt-3 flex justify-end">
                    <x-maestro.button wire:click="saveOutput">Enregistrer</x-maestro.button>
                </div>
            @elseif($run->status->value === 'running' || $run->status->value === 'pending')
                <div class="flex h-full min-h-[200px] flex-col items-center justify-center rounded-lg border border-dashed border-bg-overlay bg-bg-surface/30 px-6 text-center">
                    <span class="pipeline-spinner mb-3 h-6 w-6" aria-hidden="true"></span>
                    <p class="text-xs text-text-muted">En attente de la réponse de l'agent…</p>
                </div>
            @else
                <div class="prose prose-invert max-w-none whitespace-pre-wrap font-mono text-[11px] text-text-secondary">
                    {{ $run->edited_output ?? $run->output ?? 'Aucun output disponible.' }}
                </div>
            @endif

            @if($run->input_tokens || $run->output_tokens)
                <div class="mt-4 flex flex-wrap gap-4 border-t border-bg-overlay pt-3 text-[10px] text-text-muted">
                    <span>Input: {{ number_format($run->input_tokens ?? 0) }} tokens</span>
                    <span>Output: {{ number_format($run->output_tokens ?? 0) }} tokens</span>
                    @if($run->cached_tokens)
                        <span>Cache: {{ number_format($run->cached_tokens) }} tokens</span>
                    @endif
                    @if($run->cost)
                        <span>Coût: ${{ number_format($run->cost, 4) }}</span>
                    @endif
                    @if($run->started_at && $run->completed_at)
                        <span>Terminé à {{ $run->completed_at->format('H:i:s') }}</span>
                    @endif
                </div>
            @endif
        </div>

        @if($pendingGate)
            <div class="border-t border-yellow-900/50 bg-warning-muted p-4">
                <p class="mb-3 text-xs font-semibold text-warning">🚧 Validation requise — {{ $pendingGate->gate_type->value }}</p>

                @if($editMode)
                    <p class="mb-2 text-[10px] text-text-muted">Modifications enregistrées avec la validation.</p>
                @endif

                <x-maestro.textarea wire:model="gateFeedback" label="Feedback (rejet)" rows="2" placeholder="Expliquez ce qui doit être corrigé..." />

                <div class="mt-3 flex gap-2">
                    <x-maestro.button wire:click="approveGate({{ $pendingGate->id }})">✓ Approuver</x-maestro.button>
                    <x-maestro.button variant="danger" wire:click="rejectGate({{ $pendingGate->id }})">✗ Rejeter</x-maestro.button>
                </div>
            </div>
        @endif
    @else
        <x-maestro.empty-state
            title="Sélectionnez un agent"
            description="Cliquez sur un agent dans la timeline pour voir son output et son statut."
            icon="👈"
            class="h-full border-0"
        />
    @endif
</div>
