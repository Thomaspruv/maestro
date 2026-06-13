<div>
    {{-- Étape 1 : Informations & GitHub --}}
    @if($step === 1)
        <div class="maestro-card p-5">
            <h2 class="mb-1 text-sm font-semibold text-text-primary">Informations du projet</h2>
            <p class="mb-4 text-[11px] text-text-muted">Les champs marqués <span class="text-danger">*</span> sont obligatoires.</p>

            <x-maestro.validation-summary />

            <x-maestro.input wire:model="name" label="Nom du projet" required />
            <x-maestro.textarea wire:model="description" label="Description" rows="2" />

            <div class="my-4 border-t border-bg-overlay pt-4">
                <p class="maestro-section-title mb-3">Dépôt GitHub</p>
                <x-maestro.github-project-config
                    :connect-redirect="route('projects.create')"
                    :show-context-toggle="true"
                    :github-connected="$githubConnected"
                    :github-username="$githubUsername"
                />
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <x-maestro.button wire:click="saveStep1">Continuer →</x-maestro.button>
            </div>
        </div>
    @endif

    {{-- Étape 2 : Contexte --}}
    @if($step === 2)
        <div class="maestro-card p-5">
            <h2 class="mb-1 text-sm font-semibold text-text-primary">Contexte projet</h2>
            <p class="mb-4 text-[11px] text-text-muted">Décrivez le contexte que les agents recevront en permanence. Tous les champs sont obligatoires.</p>

            <x-maestro.validation-summary />

            <x-maestro.textarea
                wire:model="vision"
                label="Vision produit"
                rows="4"
                placeholder="Où va le produit ? Quels utilisateurs, quelle promesse, quelles priorités ?"
            />
            <p class="mb-4 text-[10px] text-text-muted">Optionnel mais recommandé — utilisé par Discovery, PM et UX.</p>

            <x-maestro.textarea wire:model="stack" label="Stack technique" rows="3" required />
            <x-maestro.textarea wire:model="conventions" label="Conventions de code" rows="3" required />
            <x-maestro.textarea wire:model="modules" label="Modules / architecture" rows="3" required />
            <x-maestro.textarea wire:model="design_system" label="Design system" rows="3" required />
            <x-maestro.textarea wire:model="constraints" label="Contraintes" rows="3" required />

            <div class="mt-4 flex justify-between">
                <x-maestro.button variant="ghost" wire:click="goToStep(1)">← Retour</x-maestro.button>
                <x-maestro.button wire:click="saveStep2">Continuer →</x-maestro.button>
            </div>
        </div>
    @endif

    {{-- Étape 3 : Pipeline --}}
    @if($step === 3)
        <div class="maestro-card p-5">
            <h2 class="mb-4 text-sm font-semibold text-text-primary">Pipeline & modes</h2>

            <x-maestro.select wire:model.live="selectedTaskType" label="Type de tâche à configurer">
                @foreach($taskTypes as $type)
                    <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                @endforeach
            </x-maestro.select>

            <p class="maestro-label mt-4">Ordre des agents (glisser-déposer)</p>
            <ul
                wire:ignore
                id="pipeline-sortable-{{ $selectedTaskType }}"
                class="space-y-1 rounded-md border border-bg-overlay bg-bg-surface p-2"
                data-task-type="{{ $selectedTaskType }}"
            >
                @foreach($pipeline[$selectedTaskType] ?? [] as $agent)
                    @php $label = config("maestro.agent_labels.{$agent}", []); @endphp
                    <li class="flex cursor-grab items-center gap-2 rounded bg-bg-elevated px-3 py-2 text-xs" data-agent="{{ $agent }}">
                        <span>{{ $label['emoji'] ?? '🤖' }}</span>
                        <span>{{ $label['name'] ?? $agent }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4 grid grid-cols-3 gap-3">
                <label class="flex items-center gap-2 text-xs">
                    <input type="checkbox" wire:model="gates.{{ $selectedTaskType }}.gate_specs" class="rounded border-bg-overlay">
                    Gate specs
                </label>
                <label class="flex items-center gap-2 text-xs">
                    <input type="checkbox" wire:model="gates.{{ $selectedTaskType }}.gate_tech" class="rounded border-bg-overlay">
                    Gate tech
                </label>
                <label class="flex items-center gap-2 text-xs">
                    <input type="checkbox" wire:model="gates.{{ $selectedTaskType }}.gate_merge" class="rounded border-bg-overlay">
                    Gate merge
                </label>
            </div>

            <x-maestro.select wire:model="modes.{{ $selectedTaskType }}" label="Mode par défaut" class="mt-3">
                @foreach($taskModes as $mode)
                    <option value="{{ $mode->value }}">
                        @if($mode->value === 'manual') Manuel
                        @elseif($mode->value === 'semi_auto') Semi-auto
                        @else Auto
                        @endif
                    </option>
                @endforeach
            </x-maestro.select>

            @if($costEstimate)
                <div class="mt-4 rounded-md border border-bg-overlay bg-bg-surface p-3">
                    <p class="maestro-section-title mb-2">Estimation coût ({{ $selectedTaskType }})</p>
                    <p class="text-lg font-bold text-text-primary">${{ number_format($costEstimate['total_mid'], 4) }}</p>
                    <p class="text-[10px] text-text-muted">${{ number_format($costEstimate['total_low'], 4) }} — ${{ number_format($costEstimate['total_high'], 4) }}</p>
                </div>
            @endif

            <div class="mt-4 flex justify-between">
                <x-maestro.button variant="ghost" wire:click="goToStep(2)">← Retour</x-maestro.button>
                <x-maestro.button wire:click="saveStep3">Continuer →</x-maestro.button>
            </div>
        </div>
    @endif

    {{-- Étape 4 : Agents --}}
    @if($step === 4)
        <div class="space-y-4">
            <div class="maestro-card p-5">
                <h2 class="mb-4 text-sm font-semibold text-text-primary">Configuration des agents</h2>

                @foreach($agentTypes as $type)
                    @php
                        $value = $type->value;
                        $label = config("maestro.agent_labels.{$value}", []);
                    @endphp
                    <div class="mb-4 rounded-md border border-bg-overlay p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-semibold text-text-primary">
                                {{ $label['emoji'] ?? '🤖' }} {{ $label['name'] ?? $value }}
                            </span>
                            <label class="flex items-center gap-1 text-[10px]">
                                <input type="checkbox" wire:model="agents.{{ $value }}.is_active" class="rounded border-bg-overlay">
                                Actif
                            </label>
                        </div>
                        <x-maestro.select wire:model="agents.{{ $value }}.model" label="Modèle">
                            @foreach($modelOptions as $model)
                                <option value="{{ $model }}">{{ $model }}</option>
                            @endforeach
                        </x-maestro.select>
                        <x-maestro.textarea wire:model="agents.{{ $value }}.system_prompt" label="System prompt" rows="3" />
                    </div>
                @endforeach
            </div>

            @if($costEstimate)
                <div class="maestro-card p-5">
                    <h3 class="mb-3 text-xs font-semibold text-text-primary">Estimation finale</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <x-maestro.stat-card label="Estimation basse" :value="'$'.number_format($costEstimate['total_low'], 4)" />
                        <x-maestro.stat-card label="Estimation médiane" :value="'$'.number_format($costEstimate['total_mid'], 4)" />
                        <x-maestro.stat-card label="Estimation haute" :value="'$'.number_format($costEstimate['total_high'], 4)" />
                    </div>
                    <p class="mt-2 text-[10px] text-text-muted">{{ $costEstimate['caching_note'] }}</p>
                </div>
            @endif

            <div class="flex justify-between">
                <x-maestro.button variant="ghost" wire:click="goToStep(3)">← Retour</x-maestro.button>
                <x-maestro.button wire:click="finalize">Créer le projet ✓</x-maestro.button>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('livewire:navigated', initPipelineSortable);
document.addEventListener('DOMContentLoaded', initPipelineSortable);

function initPipelineSortable() {
    document.querySelectorAll('[id^="pipeline-sortable-"]').forEach(el => {
        if (el._sortable) return;
        const taskType = el.dataset.taskType;
        el._sortable = Sortable.create(el, {
            animation: 150,
            onEnd: () => {
                const order = [...el.querySelectorAll('[data-agent]')].map(li => li.dataset.agent);
                @this.call('updatePipelineOrder', taskType, order);
            },
        });
    });
}
</script>
@endpush
