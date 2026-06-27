<div class="space-y-3">
    @if(filled($successMessage ?? null))
        <div class="rounded-lg border border-success/30 bg-success-muted/20 px-4 py-2 text-xs text-success">
            {{ $successMessage }}
        </div>
    @endif

    @if($connected)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-success/30 bg-success-muted/20 px-4 py-3">
            <div>
                <p class="text-xs font-semibold text-success">
                    GitHub connecté
                    @if($username)
                        · {{ $username }}
                    @endif
                </p>
                @if(! $compact)
                    <p class="mt-0.5 text-[10px] text-text-muted">Vos dépôts privés sont accessibles à Maestro.</p>
                @endif
            </div>
            @if(! $compact)
                <button
                    type="button"
                    wire:click="disconnect"
                    wire:loading.attr="disabled"
                    class="text-[10px] text-text-muted hover:text-danger"
                >
                    Déconnecter
                </button>
            @endif
        </div>
    @elseif($authMode === 'oauth')
        <a
            href="{{ $connectUrl }}"
            class="inline-flex w-full items-center justify-center gap-2.5 rounded-lg border border-[#30363d] bg-[#24292f] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#32383f] {{ $compact ? 'py-2 text-xs' : '' }}"
        >
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-3.795-.735-.525-1.335-1.305-1.695-1.305-1.695-1.065-.735.075-.735.075 1.17 1.065 1.185 3.075 1.185 3.075 1.035 1.785 2.715 1.275 3.375.975.105-.75.405-1.275.735-1.575-2.55-.285-5.235-1.275-5.235-5.685 0-1.26.45-2.295 1.185-3.105-.12-.285-.525-1.425.12-2.97 0 0 .975-.315 3.195 1.185 1.005-.27 2.085-.405 3.165-.405s2.16.135 3.165.405c2.22-1.5 3.195-1.185 3.195-1.185.645 1.545.24 2.685.12 2.97.735.81 1.185 1.845 1.185 3.105 0 4.425-2.685 5.385-5.25 5.655.42.36.795 1.08.795 2.19 0 1.575-.015 2.85-.015 3.24 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/>
            </svg>
            Se connecter avec GitHub
        </a>
        @if(! $compact)
            <p class="text-center text-[10px] text-text-muted">
                Vous serez redirigé vers GitHub pour autoriser Maestro, puis renvoyé ici.
            </p>
        @endif
    @else
        <a
            href="{{ $tokenUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex w-full items-center justify-center gap-2.5 rounded-lg border border-[#30363d] bg-[#24292f] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#32383f] {{ $compact ? 'py-2 text-xs' : '' }}"
        >
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-3.795-.735-.525-1.335-1.305-1.695-1.305-1.695-1.065-.735.075-.735.075 1.17 1.065 1.185 3.075 1.185 3.075 1.035 1.785 2.715 1.275 3.375.975.105-.75.405-1.275.735-1.575-2.55-.285-5.235-1.275-5.235-5.685 0-1.26.45-2.295 1.185-3.105-.12-.285-.525-1.425.12-2.97 0 0 .975-.315 3.195 1.185 1.005-.27 2.085-.405 3.165-.405s2.16.135 3.165.405c2.22-1.5 3.195-1.185 3.195-1.185.645 1.545.24 2.685.12 2.97.735.81 1.185 1.845 1.185 3.105 0 4.425-2.685 5.385-5.25 5.655.42.36.795 1.08.795 2.19 0 1.575-.015 2.85-.015 3.24 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/>
            </svg>
            Se connecter avec GitHub
        </a>

        @if(! $compact)
            <p class="text-center text-[10px] text-text-muted">
                Étape 1 : GitHub s'ouvre dans un nouvel onglet. Générez un token avec accès aux dépôts, copiez-le, puis collez-le ci-dessous.
                @if(app()->environment('local'))
                    Mode développement — aucune URL de callback requise.
                @endif
            </p>
        @endif

        <div class="rounded-lg border border-bg-overlay bg-bg-surface/40 px-4 py-3 space-y-3">
            <x-maestro.input
                wire:model="github_token"
                label="{{ $compact ? 'Token GitHub' : 'Étape 2 — coller le token ici' }}"
                type="password"
                placeholder="ghp_..."
                :error="$errors->first('github_token')"
            />
            <div class="flex justify-end">
                <button
                    type="button"
                    wire:click="connect"
                    wire:loading.attr="disabled"
                    class="maestro-btn-ghost text-[11px] py-2 px-3"
                >
                    <span wire:loading.remove wire:target="connect">Valider la connexion</span>
                    <span wire:loading wire:target="connect">Vérification…</span>
                </button>
            </div>
        </div>
    @endif
</div>
