<div>
    {{-- Navigation sections --}}
    <div class="mb-5 flex flex-nowrap gap-2 overflow-x-auto border-b border-bg-overlay pb-3">
        @foreach(['github' => 'Dépôt GitHub', 'context' => 'Contexte', 'roles' => 'Agents', 'pipeline' => 'Pipeline'] as $key => $label)
            <button
                @if($key === 'github')
                    wire:click="showGithubSection"
                @else
                    wire:click="$set('activeSection', '{{ $key }}')"
                @endif
                @class([
                    'inline-flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors',
                    'bg-primary-muted text-primary-light' => $activeSection === $key,
                    'text-text-secondary hover:text-text-primary' => $activeSection !== $key,
                ])
            >
                {{ $label }}
                @if($key === 'github')
                    @if($githubConnected)
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-success" title="GitHub connecté"></span>
                    @else
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-warning/20 px-1 text-[9px] font-bold text-warning" title="GitHub non connecté">!</span>
                    @endif
                @endif
            </button>
        @endforeach
    </div>

    {{-- Dépôt GitHub --}}
    @if($activeSection === 'github')
        <div class="maestro-card p-5">
            <h2 class="mb-1 text-sm font-semibold text-text-primary">Dépôt GitHub</h2>
            <p class="mb-4 text-[11px] leading-relaxed text-text-muted">
                Votre compte GitHub (token) est lié à votre profil Maestro.
                Ici, vous choisissez quel dépôt et quelle branche ce projet utilise.
            </p>

            @if($githubStatusMessage)
                <div class="mb-4 rounded-lg border border-success/30 bg-success-muted/20 px-4 py-2 text-xs text-success">
                    {{ $githubStatusMessage }}
                </div>
            @endif

            <x-maestro.github-project-config
                :connect-redirect="route('projects.settings.edit', $project)"
                :github-connected="$githubConnected"
                :github-username="$githubUsername"
                :saved-repo="$project->github_repo"
                :saved-branch="$project->github_branch"
            />

            <div class="mt-4 flex items-center justify-between gap-3">
                @unless($githubConnected)
                    <p class="text-[11px] text-text-muted">Connectez GitHub pour activer l'enregistrement.</p>
                @endunless
                <div class="ml-auto">
                    <x-maestro.button wire:click="saveGithub" :disabled="! $githubConnected">
                        Enregistrer le dépôt
                    </x-maestro.button>
                </div>
            </div>
        </div>
    @endif

    {{-- Contexte --}}
    @if($activeSection === 'context')
        <div class="maestro-card mb-4 p-5">
            <h2 class="mb-1 text-sm font-semibold text-text-primary">Vision produit</h2>
            <p class="mb-4 text-[11px] leading-relaxed text-text-muted">
                Décrivez la vision, le positionnement et les priorités produit. Ce texte est transmis aux agents
                Discovery, PM et UX pour aligner leurs propositions sur votre direction — pas seulement sur le code.
            </p>
            <x-maestro.textarea
                wire:model="vision"
                label="Vision"
                rows="5"
                placeholder="Ex. : Maestro aide les équipes solo à orchestrer des agents IA sur leur backlog. Priorité : simplicité, transparence des coûts, features orientées product owner…"
            />
        </div>

        <div class="maestro-card p-5">
            <h2 class="mb-1 text-sm font-semibold text-text-primary">Contexte technique</h2>
            <p class="mb-4 text-[11px] text-text-muted">Informations transmises à tous les agents du pipeline.</p>
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
    @if($activeSection === 'roles')
        <div class="mb-4">
            <p class="text-xs text-text-secondary">
                Overrides spécifiques à ce projet. Les rôles built-in sont hérités de votre compte Maestro.
            </p>
        </div>
        <div class="space-y-2">
            @foreach($agents as $index => $agent)
                @php
                    $type = $agent['role'];
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

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
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
