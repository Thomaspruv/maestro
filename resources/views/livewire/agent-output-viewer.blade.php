<div class="maestro-card flex h-full flex-col">
    @if($run)
        <div class="flex items-center justify-between border-b border-bg-overlay px-4 py-3">
            @php $label = $agentLabels[$run->agent_type->value] ?? ['emoji' => '🤖', 'name' => $run->agent_type->value]; @endphp
            <div>
                <h2 class="text-sm font-semibold text-text-primary">
                    {{ $label['emoji'] }} {{ $label['name'] }}
                </h2>
                <div class="mt-1 flex gap-2">
                    <x-maestro.badge kind="agent_status" :value="$run->status" />
                    @if($run->model)
                        <span class="text-[10px] text-text-muted">{{ $run->model }}</span>
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

        <div class="flex-1 overflow-y-auto p-4">
            @if($editMode)
                <x-maestro.textarea wire:model="editedOutput" rows="20" class="font-mono text-[11px]" />
                <div class="mt-3 flex justify-end">
                    <x-maestro.button wire:click="saveOutput">Enregistrer</x-maestro.button>
                </div>
            @else
                <div class="prose prose-invert max-w-none whitespace-pre-wrap font-mono text-[11px] text-text-secondary">
                    {{ $run->edited_output ?? $run->output ?? 'Aucun output disponible.' }}
                </div>
            @endif

            @if($run->input_tokens || $run->output_tokens)
                <div class="mt-4 flex gap-4 border-t border-bg-overlay pt-3 text-[10px] text-text-muted">
                    <span>Input: {{ number_format($run->input_tokens ?? 0) }} tokens</span>
                    <span>Output: {{ number_format($run->output_tokens ?? 0) }} tokens</span>
                    @if($run->cost)
                        <span>Coût: ${{ number_format($run->cost, 4) }}</span>
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
            description="Cliquez sur un agent dans la timeline pour voir son output."
            icon="👈"
            class="h-full border-0"
        />
    @endif
</div>
