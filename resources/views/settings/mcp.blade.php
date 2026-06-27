@extends('layouts.maestro')

@section('title', 'Intégrations MCP')

@section('content')
    <div class="mx-auto max-w-2xl space-y-4">
        <p class="text-[12px] text-text-muted">
            Connectez Hermes, Claude Code ou Claude Cowork à Maestro. Les tokens sont liés à votre compte
            (<span class="text-text-primary">{{ auth()->user()->email }}</span>).
        </p>

        <div class="maestro-card p-5">
            <h2 class="mb-1 text-sm font-semibold text-text-primary">Serveur MCP</h2>
            <p class="mb-3 text-[11px] text-text-muted">URL à configurer dans Hermes ou comme connecteur custom.</p>
            <p class="mb-3 text-[11px] text-text-muted">
                Guide complet : <code class="text-text-primary">docs/MCP.md</code> (génération token, Hermes, Claude Code, dépannage).
            </p>
            <code class="block break-all rounded-md bg-bg-surface px-3 py-2 font-mono text-[11px] text-text-muted">{{ $mcpUrl }}</code>
        </div>

        <div class="maestro-card p-5">
            <h2 class="mb-3 text-sm font-semibold text-text-primary">Configuration</h2>
            <div class="space-y-3 text-[11px]">
                <div>
                    <p class="mb-1 font-medium text-text-primary">Hermes</p>
                    <pre class="overflow-x-auto rounded bg-bg-surface p-2 font-mono text-[10px] text-text-muted">mcp_servers:
  maestro:
    url: {{ $mcpUrl }}
    auth:
      type: bearer
      token: "&lt;token_ci-dessous&gt;"</pre>
                </div>
                <div>
                    <p class="mb-1 font-medium text-text-primary">Claude Code</p>
                    <pre class="overflow-x-auto rounded bg-bg-surface p-2 font-mono text-[10px] text-text-muted">claude mcp add --transport http maestro {{ $mcpUrl }} \
  --header "Authorization: Bearer &lt;token_ci-dessous&gt;"</pre>
                </div>
                <div>
                    <p class="mb-1 font-medium text-text-primary">Claude Cowork</p>
                    <p class="text-text-muted">
                        Connecteur custom → collez l’URL ci-dessus → OAuth (pas de token à saisir).
                    </p>
                </div>
            </div>
        </div>

        <div class="maestro-card p-5">
            <h2 class="mb-1 text-sm font-semibold text-text-primary">Tokens d'accès</h2>
            <p class="mb-4 text-[11px] text-text-muted">
                Générez un token pour Hermes ou Claude Code. Il n’est affiché qu’une seule fois.
            </p>

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
