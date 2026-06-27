@props([
    'connectRedirect' => null,
    'showContextToggle' => false,
    'showTemplateOption' => false,
    'githubConnected' => false,
    'githubUsername' => null,
    'savedRepo' => null,
    'savedBranch' => null,
])

<div class="space-y-4">
    @if($githubConnected)
        <div class="rounded-lg border border-success/30 bg-success-muted/20 px-4 py-3">
            <p class="text-xs font-semibold text-success">
                Compte GitHub connecté
                @if($githubUsername)
                    · {{ $githubUsername }}
                @endif
            </p>
            <p class="mt-0.5 text-[10px] text-text-muted">
                Choisissez le dépôt de ce projet ci-dessous, puis cliquez « Enregistrer le dépôt ».
            </p>
        </div>
    @else
        <div class="rounded-lg border border-warning/30 bg-warning-muted/20 px-4 py-3">
            <p class="text-xs font-semibold text-warning">Compte GitHub non connecté</p>
            <p class="mt-1 text-[11px] leading-relaxed text-text-secondary">
                Connectez-vous ci-dessous ou dans
                <a href="{{ route('settings.edit') }}" class="text-primary-light hover:underline">Paramètres → GitHub</a>,
                puis revenez choisir le dépôt.
            </p>
        </div>
    @endif

    @if($savedRepo)
        <div class="rounded-lg border border-bg-overlay bg-bg-surface/40 px-4 py-3">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-text-muted">Dépôt enregistré pour ce projet</p>
            <p class="mt-1 font-mono text-xs text-text-primary">
                {{ $savedRepo }}
                <span class="text-text-muted">@</span>
                {{ $savedBranch ?: 'main' }}
            </p>
        </div>
    @endif

    <x-maestro.github-connect :redirect="$connectRedirect ?? url()->current()" :compact="true" />

    @if($showTemplateOption && $githubConnected)
        <div class="rounded-lg border border-bg-overlay bg-bg-surface/40 px-4 py-3">
            <label class="flex items-start gap-2 text-xs text-text-secondary">
                <input type="checkbox" wire:model.live="create_from_template" class="mt-0.5 rounded border-bg-overlay">
                <span>
                    <span class="font-semibold text-text-primary">Créer un nouveau dépôt depuis le template Maestro</span>
                    <span class="mt-0.5 block text-[10px] text-text-muted">
                        Génère un repo GitHub vierge à partir du template configuré ({{ config('maestro.github_template_repo') }}).
                    </span>
                </span>
            </label>

            @if($create_from_template ?? false)
                <div class="mt-3 space-y-3 border-t border-bg-overlay pt-3">
                    <x-maestro.input
                        wire:model="new_repo_name"
                        label="Nom du nouveau dépôt"
                        placeholder="mon-projet-maestro"
                        required
                        :error="$errors->first('new_repo_name')"
                    />
                    <x-maestro.select wire:model="new_repo_visibility" label="Visibilité">
                        <option value="private">Privé</option>
                        <option value="public">Public</option>
                    </x-maestro.select>
                </div>
            @endif
        </div>
    @endif

    @if($githubConnected && ! ($create_from_template ?? false))
        <div>
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="maestro-label mb-0">Choisir un dépôt</p>
                <button
                    type="button"
                    wire:click="loadGithubRepositories"
                    wire:loading.attr="disabled"
                    class="text-[10px] text-primary-light hover:underline"
                >
                    <span wire:loading.remove wire:target="loadGithubRepositories">Actualiser la liste</span>
                    <span wire:loading wire:target="loadGithubRepositories">Chargement…</span>
                </button>
            </div>

            @if($githubReposError ?? null)
                <p class="mb-2 text-[11px] text-danger">{{ $githubReposError }}</p>
            @endif

            @if($githubReposLoading ?? false)
                <p class="text-[11px] text-text-muted">Chargement de vos dépôts…</p>
            @elseif(! empty($githubRepos))
                <x-maestro.select wire:model.live="github_repo">
                    <option value="">— Sélectionner un dépôt —</option>
                    @foreach($githubRepos as $repo)
                        <option value="{{ $repo['full_name'] }}">
                            {{ $repo['private'] ? '🔒' : '📂' }} {{ $repo['full_name'] }}
                        </option>
                    @endforeach
                </x-maestro.select>
                <p class="mt-1 text-[10px] text-text-muted">
                    {{ count($githubRepos) }} dépôt(s). La branche par défaut est remplie automatiquement.
                </p>
            @else
                <p class="text-[11px] text-text-muted">
                    Cliquez sur « Actualiser la liste » pour voir vos dépôts GitHub.
                </p>
            @endif
        </div>

        <div class="border-t border-bg-overlay pt-4">
            <x-maestro.input
                wire:model.blur="github_repo"
                wire:blur="normalizeGithubRepoInput"
                label="Dépôt (owner/repo ou URL GitHub)"
                placeholder="mon-org/mon-projet"
                required
                :error="$errors->first('github_repo')"
            />
            <x-maestro.input
                wire:model="github_branch"
                label="Branche par défaut"
                placeholder="main"
                required
            />

            @if($showContextToggle)
                <label class="mt-2 flex items-center gap-2 text-xs text-text-secondary">
                    <input type="checkbox" wire:model="read_context_from_repo" class="rounded border-bg-overlay">
                    Lire le README / architecture depuis le repo
                </label>
            @endif
        </div>
    @endif
</div>
