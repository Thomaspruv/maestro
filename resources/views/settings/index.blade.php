@extends('layouts.maestro')

@section('title', 'Paramètres')

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        {{-- Profil --}}
        <div class="maestro-card p-5">
            <h2 class="mb-4 text-sm font-semibold text-text-primary">Profil</h2>
            <form method="POST" action="{{ route('settings.update') }}">
                @csrf
                @method('PUT')
                <x-maestro.input name="name" label="Nom" :value="old('name', $user->name)" :error="$errors->first('name')" required />
                <x-maestro.input name="email" label="Email" type="email" :value="old('email', $user->email)" :error="$errors->first('email')" required />
                <x-maestro.input name="password" label="Nouveau mot de passe" type="password" :error="$errors->first('password')" placeholder="Laisser vide pour ne pas changer" />
                <x-maestro.input name="password_confirmation" label="Confirmer le mot de passe" type="password" />
                <div class="mt-4 flex justify-end">
                    <x-maestro.button type="submit">Enregistrer le profil</x-maestro.button>
                </div>
            </form>
        </div>

        {{-- Clé API --}}
        <div class="maestro-card p-5">
            <h2 class="mb-4 text-sm font-semibold text-text-primary">Clé API Claude</h2>
            <p class="mb-3 text-[11px] text-text-muted">Requise pour exécuter les agents IA sur vos projets.</p>
            <form method="POST" action="{{ route('settings.api-key.update') }}">
                @csrf
                @method('PUT')
                <x-maestro.input
                    name="claude_api_key"
                    label="Clé API"
                    type="password"
                    :error="$errors->first('claude_api_key')"
                    placeholder="{{ $user->claude_api_key ? '••••••••••••' : 'sk-ant-...' }}"
                />
                <div class="mt-4 flex justify-end">
                    <x-maestro.button type="submit">Enregistrer la clé</x-maestro.button>
                </div>
            </form>
        </div>

        {{-- GitHub --}}
        <div class="maestro-card p-5">
            <h2 class="mb-1 text-sm font-semibold text-text-primary">GitHub</h2>
            <p class="mb-4 text-[11px] text-text-muted">
                Connectez votre compte pour que Maestro accède à vos dépôts (y compris privés).
            </p>

            <x-maestro.github-connect :redirect="route('settings.edit')" />
        </div>

        {{-- Budget --}}
        <div class="maestro-card p-5">
            <h2 class="mb-4 text-sm font-semibold text-text-primary">Budget mensuel</h2>
            <div class="mb-4 rounded-md bg-bg-surface px-3 py-2">
                <p class="text-[10px] text-text-muted">Coût du mois en cours</p>
                <p class="text-lg font-bold text-text-primary">${{ number_format($currentMonthCost, 2) }}</p>
            </div>
            <form method="POST" action="{{ route('settings.budget.update') }}">
                @csrf
                @method('PUT')
                <x-maestro.input
                    name="monthly_budget"
                    label="Budget mensuel ($)"
                    type="number"
                    step="0.01"
                    min="0"
                    :value="old('monthly_budget', $user->monthly_budget)"
                    :error="$errors->first('monthly_budget')"
                />
                <div class="mt-4 flex justify-end">
                    <x-maestro.button type="submit">Enregistrer le budget</x-maestro.button>
                </div>
            </form>
        </div>

        {{-- Accès MCP --}}
        @php
            $mcpUrl = rtrim(config('app.url'), '/').'/api/mcp';
        @endphp
        <div class="maestro-card p-5">
            <h2 class="mb-1 text-sm font-semibold text-text-primary">Accès MCP</h2>
            <p class="mb-4 text-[11px] text-text-muted">
                Connectez Hermes, Claude Code ou Claude Cowork à Maestro via l’API MCP.
            </p>

            <div class="mb-4 space-y-3 rounded-md border px-3 py-3 text-[11px]" style="border-color: var(--maestro-border);">
                <div>
                    <p class="mb-1 font-medium text-text-primary">URL du serveur MCP</p>
                    <code class="block break-all font-mono text-[10px] text-text-muted">{{ $mcpUrl }}</code>
                </div>

                <div>
                    <p class="mb-1 font-medium text-text-primary">Hermes (Bearer token)</p>
                    <pre class="overflow-x-auto rounded bg-bg-surface p-2 font-mono text-[10px] text-text-muted">mcp_servers:
  maestro:
    url: {{ $mcpUrl }}
    auth:
      type: bearer
      token: "&lt;token_généré_ci-dessous&gt;"</pre>
                </div>

                <div>
                    <p class="mb-1 font-medium text-text-primary">Claude Code (Bearer token)</p>
                    <pre class="overflow-x-auto rounded bg-bg-surface p-2 font-mono text-[10px] text-text-muted">claude mcp add --transport http maestro {{ $mcpUrl }} \
  --header "Authorization: Bearer &lt;token_généré_ci-dessous&gt;"</pre>
                </div>

                <div>
                    <p class="mb-1 font-medium text-text-primary">Claude Cowork (OAuth)</p>
                    <p class="text-text-muted">
                        Paramètres Cowork → Connecteurs → Ajouter un connecteur custom → collez l’URL ci-dessus.
                        Aucun token à saisir : l’authentification OAuth ouvre Maestro dans le navigateur.
                    </p>
                </div>
            </div>

            @if (session('mcp_token_plain'))
                <div class="mb-4 rounded-lg border px-3 py-2 text-[12px]" style="border-color: var(--maestro-warning-border); background: var(--maestro-warning-bg); color: var(--maestro-warning);">
                    <p class="mb-1 font-medium">Token généré — copiez-le maintenant</p>
                    <code class="block break-all font-mono text-[11px]">{{ session('mcp_token_plain') }}</code>
                </div>
            @endif

            @if ($mcpTokens->isNotEmpty())
                <ul class="mb-4 space-y-2">
                    @foreach ($mcpTokens as $token)
                        <li class="flex items-center justify-between rounded-md border px-3 py-2 text-[12px]">
                            <div>
                                <p class="font-medium text-text-primary">{{ $token->name }}</p>
                                <p class="text-[11px] text-text-muted">
                                    Créé {{ $token->created_at->diffForHumans() }}
                                    @if ($token->last_used_at)
                                        · Dernière utilisation {{ $token->last_used_at->diffForHumans() }}
                                    @else
                                        · Jamais utilisé
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('settings.mcp-tokens.destroy', $token) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="maestro-btn-danger text-[11px]">Révoquer</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mb-4 text-[12px] text-text-muted">Aucun token actif.</p>
            @endif

            <form method="POST" action="{{ route('settings.mcp-tokens.store') }}">
                @csrf
                <x-maestro.input name="name" label="Nom du token" :value="old('name', 'Hermes')" :error="$errors->first('name')" required />
                <div class="mt-4 flex justify-end">
                    <x-maestro.button type="submit">Générer un token</x-maestro.button>
                </div>
            </form>
        </div>
    </div>
@endsection
