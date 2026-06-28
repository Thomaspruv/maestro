@extends('layouts.public-doc')

@section('title', 'Documentation API MCP')

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                @auth
                    <a href="{{ route('settings.mcp') }}" class="text-[11px] text-maestro-subtle hover:text-maestro-accent">
                        ← Intégrations MCP
                    </a>
                @endauth
                <h1 class="mt-1 text-lg font-semibold text-maestro-text">Documentation API MCP</h1>
                <p class="mt-1 text-[11px] text-maestro-subtle">Page publique — partageable sans compte Maestro.</p>
            </div>
            <button
                type="button"
                class="maestro-btn-secondary text-[11px]"
                x-data
                x-on:click="navigator.clipboard.writeText(@js($docsUrl)); $el.textContent = 'URL copiée !'; setTimeout(() => $el.textContent = 'Copier le lien', 2000)"
            >
                Copier le lien
            </button>
        </div>

        <div class="maestro-card p-4">
            <p class="mb-2 text-[11px] font-medium text-maestro-text">Endpoint MCP</p>
            <code class="block break-all rounded-md bg-maestro-surface-2 px-3 py-2 font-mono text-[11px] text-maestro-subtle">{{ $mcpUrl }}</code>
        </div>

        <article class="maestro-card mcp-docs-prose overflow-x-auto p-6 text-[13px] leading-relaxed text-maestro-text">
            {!! $html !!}
        </article>
    </div>
@endsection
