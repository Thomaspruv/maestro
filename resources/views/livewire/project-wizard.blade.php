<div>
    {{-- Étape 1 : Informations & GitHub --}}
    @if($step === 1)
        <x-ui.card>
            <x-ui.heading-3 class="mb-1">Informations du projet</x-ui.heading-3>
            <p class="mb-4 text-[12px] text-maestro-subtle">Les champs marqués <span style="color: var(--maestro-danger)">*</span> sont obligatoires.</p>

            <x-maestro.validation-summary />

            <x-maestro.input wire:model="name" label="Nom du projet" required />
            <x-maestro.textarea wire:model="description" label="Description" rows="2" />

            <div class="my-4 border-t pt-4">
                <x-ui.label class="mb-3 block">Dépôt GitHub</x-ui.label>
                <x-maestro.github-project-config
                    :connect-redirect="route('projects.create')"
                    :show-context-toggle="true"
                    :show-template-option="$githubTemplateEnabled"
                    :github-connected="$githubConnected"
                    :github-username="$githubUsername"
                />
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <x-ui.button variant="primary" wire:click="saveStep1">Continuer →</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    {{-- Étape 2 : Contexte --}}
    @if($step === 2)
        <x-ui.card>
            <x-ui.heading-3 class="mb-1">Contexte projet</x-ui.heading-3>
            <p class="mb-4 text-[12px] text-maestro-subtle">Décrivez le contexte que les agents recevront en permanence. Tous les champs sont obligatoires.</p>

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
                <x-ui.button variant="secondary" wire:click="goToStep(1)">← Retour</x-ui.button>
                <x-ui.button variant="primary" wire:click="saveStep2">Continuer →</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    {{-- Étape 3 : Pipeline --}}
    @if($step === 3)
        <x-ui.card>
            <x-ui.heading-3 class="mb-4">Pipeline & modes</x-ui.heading-3>

            <x-maestro.select wire:model.live="selectedTaskType" label="Type de tâche à configurer">
                @foreach($taskTypes as $type)
                    <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                @endforeach
            </x-maestro.select>

            <p class="maestro-label mt-4">Ordre des agents (glisser-déposer)</p>
            <ul
                wire:ignore
                id="pipeline-sortable-{{ $selectedTaskType }}"
                class="space-y-1 rounded-lg border bg-maestro-surface p-2"
                data-task-type="{{ $selectedTaskType }}"
            >
                @foreach($pipeline[$selectedTaskType] ?? [] as $agent)
                    @php $label = config("maestro.role_labels.{$agent}", []); @endphp
                    <li class="flex cursor-grab items-center gap-2 rounded-lg bg-maestro-surface-2 px-3 py-2 text-[13px]" data-agent="{{ $agent }}">
                        <span>{{ $label['emoji'] ?? '🤖' }}</span>
                        <span>{{ $label['name'] ?? $agent }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
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
                <x-ui.card class="mt-4">
                    <x-ui.label class="mb-2 block">Estimation coût ({{ $selectedTaskType }})</x-ui.label>
                    <p class="text-[24px] font-medium text-maestro-text">${{ number_format($costEstimate['total_mid'], 4) }}</p>
                    <p class="text-[12px] text-maestro-subtle">${{ number_format($costEstimate['total_low'], 4) }} — ${{ number_format($costEstimate['total_high'], 4) }}</p>
                </x-ui.card>
            @endif

            <div class="mt-4 flex justify-between">
                <x-ui.button variant="secondary" wire:click="goToStep(2)">← Retour</x-ui.button>
                <x-ui.button variant="primary" wire:click="saveStep3">Continuer →</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    {{-- Étape 4 : Rôles pipeline --}}
    @if($step === 4)
        <div class="space-y-4">
            <x-ui.card>
                <x-ui.heading-3 class="mb-4">Rôles du pipeline</x-ui.heading-3>

                @foreach($agentTypes as $type)
                    @php
                        $value = $type->value;
                        $label = config("maestro.role_labels.{$value}", []);
                    @endphp
                    <div class="mb-4 rounded-lg border p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-[13px] font-medium text-maestro-text">
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
            </x-ui.card>

            @if($costEstimate)
                <x-ui.card>
                    <x-ui.heading-3 class="mb-3">Estimation finale</x-ui.heading-3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <x-ui.metric-card label="Estimation basse" :value="'$'.number_format($costEstimate['total_low'], 4)" />
                        <x-ui.metric-card label="Estimation médiane" :value="'$'.number_format($costEstimate['total_mid'], 4)" />
                        <x-ui.metric-card label="Estimation haute" :value="'$'.number_format($costEstimate['total_high'], 4)" />
                    </div>
                    <p class="mt-2 text-[12px] text-maestro-subtle">{{ $costEstimate['caching_note'] }}</p>
                </x-ui.card>
            @endif

            <div class="flex justify-between">
                <x-ui.button variant="secondary" wire:click="goToStep(3)">← Retour</x-ui.button>
                <x-ui.button variant="primary" wire:click="finalize">Créer le projet ✓</x-ui.button>
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
