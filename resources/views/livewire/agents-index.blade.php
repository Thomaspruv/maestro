<div>
    @if (session('success'))
        <div class="mb-4 rounded-md border border-success/30 bg-success/10 px-4 py-2 text-xs text-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-5 flex items-center justify-between">
        <div>
            <p class="text-sm text-text-secondary">
                Bibliothèque d'agents partagée entre tous vos projets. Les agents sont copiés à la création d'un projet.
            </p>
        </div>
        <x-maestro.button wire:click="openCreateModal">+ Ajouter un agent</x-maestro.button>
    </div>

    <div class="space-y-2">
        @forelse($agents as $index => $agent)
            <div class="maestro-card overflow-hidden">
                <button
                    wire:click="toggleAgent({{ $index }})"
                    class="flex w-full items-center justify-between px-4 py-3 text-left"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-lg">{{ $agent['emoji'] }}</span>
                        <div>
                            <p class="text-xs font-semibold text-text-primary">{{ $agent['name'] }}</p>
                            <p class="text-[10px] text-text-muted">
                                <code>{{ $agent['slug'] }}</code>
                                · {{ $agent['model'] }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($agent['is_builtin'])
                            <span class="rounded bg-bg-surface px-2 py-0.5 text-[10px] text-text-muted">Système</span>
                        @else
                            <span class="rounded bg-primary-muted px-2 py-0.5 text-[10px] text-primary-light">Custom</span>
                        @endif
                        <span class="text-text-muted">{{ $expandedAgentId === $index ? '▲' : '▼' }}</span>
                    </div>
                </button>

                @if($expandedAgentId === $index)
                    <div class="border-t border-bg-overlay px-4 pb-4 pt-3 space-y-3">
                        @if($agent['is_builtin'])
                            <p class="text-[10px] text-text-muted">
                                Slug système : <code>{{ $agent['slug'] }}</code> (non modifiable)
                            </p>
                        @endif

                        <div class="grid grid-cols-2 gap-3">
                            <x-maestro.input wire:model="agents.{{ $index }}.name" label="Nom" />
                            <x-maestro.input wire:model="agents.{{ $index }}.emoji" label="Emoji" />
                        </div>

                        <x-maestro.select wire:model="agents.{{ $index }}.model" label="Modèle">
                            @foreach($modelOptions as $model)
                                <option value="{{ $model }}">{{ $model }}</option>
                            @endforeach
                        </x-maestro.select>

                        <x-maestro.textarea
                            wire:model="agents.{{ $index }}.system_prompt"
                            label="System prompt"
                            rows="12"
                        />

                        @if($testOutput || $testError)
                            <div @class([
                                'rounded-md border px-3 py-2 text-xs',
                                'border-success/30 bg-success/10 text-success' => $testOutput,
                                'border-danger/30 bg-danger/10 text-danger' => $testError,
                            ])>
                                @if($testOutput)
                                    <p class="mb-1 font-semibold">Test réussi ({{ number_format($testCost ?? 0, 4) }} $)</p>
                                    <pre class="whitespace-pre-wrap text-[10px]">{{ $testOutput }}</pre>
                                @else
                                    <p>{{ $testError }}</p>
                                @endif
                            </div>
                        @endif

                        <div class="flex items-center justify-between">
                            <div class="flex gap-2">
                                <x-maestro.button variant="secondary" wire:click="testAgent({{ $index }})">
                                    Tester
                                </x-maestro.button>
                                @if(!$agent['is_builtin'])
                                    <x-maestro.button
                                        variant="danger"
                                        wire:click="confirmDelete({{ $agent['id'] }})"
                                    >
                                        Supprimer
                                    </x-maestro.button>
                                @endif
                            </div>
                            <x-maestro.button wire:click="saveAgent({{ $index }})">
                                Enregistrer
                            </x-maestro.button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <x-maestro.empty-state
                emoji="🤖"
                title="Aucun agent"
                description="Vos agents système seront créés automatiquement."
            />
        @endforelse
    </div>

    {{-- Modal création --}}
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="maestro-card w-full max-w-lg p-5">
                <h2 class="mb-4 text-sm font-semibold text-text-primary">Nouvel agent custom</h2>

                <div class="space-y-3">
                    <x-maestro.input
                        wire:model="newSlug"
                        label="Slug (identifiant unique)"
                        placeholder="ex. code_reviewer"
                    />
                    @error('newSlug') <p class="text-[10px] text-danger">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-3">
                        <x-maestro.input wire:model="newName" label="Nom" placeholder="Code Reviewer" />
                        <x-maestro.input wire:model="newEmoji" label="Emoji" />
                    </div>

                    <x-maestro.select wire:model="newModel" label="Modèle">
                        @foreach($modelOptions as $model)
                            <option value="{{ $model }}">{{ $model }}</option>
                        @endforeach
                    </x-maestro.select>

                    <x-maestro.textarea wire:model="newSystemPrompt" label="System prompt" rows="8" />
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <x-maestro.button variant="secondary" wire:click="$set('showCreateModal', false)">
                        Annuler
                    </x-maestro.button>
                    <x-maestro.button wire:click="createAgent">Créer</x-maestro.button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal suppression --}}
    @if($showDeleteConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="maestro-card w-full max-w-sm p-5">
                <h2 class="mb-2 text-sm font-semibold text-text-primary">Supprimer cet agent ?</h2>
                <p class="mb-4 text-xs text-text-secondary">
                    Cet agent ne sera plus copié dans les nouveaux projets. Les projets existants conservent leur snapshot.
                </p>
                <div class="flex justify-end gap-2">
                    <x-maestro.button variant="secondary" wire:click="$set('showDeleteConfirm', false)">
                        Annuler
                    </x-maestro.button>
                    <x-maestro.button variant="danger" wire:click="deleteAgent">Supprimer</x-maestro.button>
                </div>
            </div>
        </div>
    @endif
</div>
