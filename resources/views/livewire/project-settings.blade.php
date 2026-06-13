<div>
    {{-- Navigation sections --}}
    <div class="mb-5 flex gap-2 border-b border-bg-overlay pb-3">
        @foreach(['context' => 'Contexte', 'agents' => 'Agents', 'pipeline' => 'Pipeline'] as $key => $label)
            <button
                wire:click="$set('activeSection', '{{ $key }}')"
                @class([
                    'rounded-md px-3 py-1.5 text-xs font-semibold transition-colors',
                    'bg-primary-muted text-primary-light' => $activeSection === $key,
                    'text-text-secondary hover:text-text-primary' => $activeSection !== $key,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Contexte --}}
    @if($activeSection === 'context')
        <div class="maestro-card p-5">
            <x-maestro.textarea wire:model="stack" label="Stack technique" rows="4" />
            <x-maestro.textarea wire:model="conventions" label="Conventions de code" rows="4" />
            <x-maestro.textarea wire:model="modules" label="Modules / architecture" rows="4" />
            <x-maestro.textarea wire:model="design_system" label="Design system" rows="4" />
            <x-maestro.textarea wire:model="constraints" label="Contraintes" rows="4" />
            <div class="mt-4 flex justify-end">
                <x-maestro.button wire:click="saveContext">Enregistrer le contexte</x-maestro.button>
            </div>
        </div>
    @endif

    {{-- Agents --}}
    @if($activeSection === 'agents')
        <div class="space-y-2">
            @foreach($agents as $index => $agent)
                @php
                    $type = $agent['agent_type'];
                    $label = $agentLabels[$type] ?? ['emoji' => '🤖', 'name' => $type];
                @endphp
                <div class="maestro-card overflow-hidden">
                    <button
                        wire:click="toggleAgent({{ $index }})"
                        class="flex w-full items-center justify-between px-4 py-3 text-left"
                    >
                        <span class="text-xs font-semibold text-text-primary">
                            {{ $label['emoji'] }} {{ $label['name'] }}
                        </span>
                        <span class="text-text-muted">{{ $expandedAgentId === $index ? '▲' : '▼' }}</span>
                    </button>

                    @if($expandedAgentId === $index)
                        <div class="border-t border-bg-overlay px-4 pb-4 pt-2">
                            <label class="mb-3 flex items-center gap-2 text-xs">
                                <input type="checkbox" wire:model="agents.{{ $index }}.is_active" class="rounded border-bg-overlay">
                                Agent actif
                            </label>
                            <x-maestro.select wire:model="agents.{{ $index }}.model" label="Modèle">
                                @foreach($modelOptions as $model)
                                    <option value="{{ $model }}">{{ $model }}</option>
                                @endforeach
                            </x-maestro.select>
                            <x-maestro.textarea wire:model="agents.{{ $index }}.system_prompt" label="System prompt" rows="6" />

                            @if(!empty($agent['histories']))
                                <div class="mt-3">
                                    <p class="maestro-section-title mb-2">Historique des prompts</p>
                                    @foreach($agent['histories'] as $date => $prompt)
                                        <details class="mb-1 rounded border border-bg-overlay bg-bg-surface p-2">
                                            <summary class="cursor-pointer text-[10px] text-text-muted">{{ $date }}</summary>
                                            <pre class="mt-1 whitespace-pre-wrap text-[10px] text-text-secondary">{{ Str::limit($prompt, 500) }}</pre>
                                        </details>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="flex justify-end">
                <x-maestro.button wire:click="saveAgents">Enregistrer les agents</x-maestro.button>
            </div>
        </div>
    @endif

    {{-- Pipeline --}}
    @if($activeSection === 'pipeline')
        <div class="maestro-card p-5">
            @foreach($taskTypes as $type)
                <div class="mb-6 border-b border-bg-overlay pb-4 last:border-0">
                    <p class="mb-2 text-xs font-semibold text-text-primary">{{ ucfirst($type->value) }}</p>

                    <div class="mb-2 flex flex-wrap gap-1">
                        @foreach($pipeline[$type->value] ?? [] as $agent)
                            @php $al = $agentLabels[$agent] ?? ['emoji' => '🤖']; @endphp
                            <span class="rounded bg-bg-surface px-2 py-1 text-[10px]">{{ $al['emoji'] }} {{ $agent }}</span>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <label class="flex items-center gap-1 text-[10px]">
                            <input type="checkbox" wire:model="gates.{{ $type->value }}.gate_specs" class="rounded border-bg-overlay">
                            Gate specs
                        </label>
                        <label class="flex items-center gap-1 text-[10px]">
                            <input type="checkbox" wire:model="gates.{{ $type->value }}.gate_tech" class="rounded border-bg-overlay">
                            Gate tech
                        </label>
                        <label class="flex items-center gap-1 text-[10px]">
                            <input type="checkbox" wire:model="gates.{{ $type->value }}.gate_merge" class="rounded border-bg-overlay">
                            Gate merge
                        </label>
                    </div>

                    <x-maestro.select wire:model="modes.{{ $type->value }}" label="Mode par défaut" class="mt-2">
                        @foreach($taskModes as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->value }}</option>
                        @endforeach
                    </x-maestro.select>
                </div>
            @endforeach

            <div class="flex justify-end">
                <x-maestro.button wire:click="savePipeline">Enregistrer le pipeline</x-maestro.button>
            </div>
        </div>
    @endif
</div>
