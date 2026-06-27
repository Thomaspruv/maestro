@extends('layouts.maestro')

@section('title', 'Autoriser l’accès MCP')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="maestro-card p-5">
            <h2 class="mb-2 text-sm font-semibold text-text-primary">Autoriser {{ $clientName }}</h2>
            <p class="mb-4 text-[12px] text-text-muted">
                Cette application demande l’accès à vos projets et tâches Maestro via MCP
                (lecture et écriture).
            </p>

            <ul class="mb-6 list-inside list-disc text-[11px] text-text-muted">
                @foreach ($scopes as $scope)
                    <li>{{ $scope }}</li>
                @endforeach
            </ul>

            <form method="POST" action="{{ route('oauth.mcp.approve') }}" class="mb-2">
                @csrf
                <input type="hidden" name="client_id" value="{{ $authorize['client_id'] }}">
                <input type="hidden" name="redirect_uri" value="{{ $authorize['redirect_uri'] }}">
                <input type="hidden" name="response_type" value="{{ $authorize['response_type'] }}">
                <input type="hidden" name="code_challenge" value="{{ $authorize['code_challenge'] }}">
                <input type="hidden" name="code_challenge_method" value="{{ $authorize['code_challenge_method'] }}">
                <input type="hidden" name="state" value="{{ $authorize['state'] }}">
                @if ($authorize['scope'])
                    <input type="hidden" name="scope" value="{{ $authorize['scope'] }}">
                @endif
                <x-maestro.button type="submit" class="w-full">Autoriser</x-maestro.button>
            </form>

            <form method="POST" action="{{ route('oauth.mcp.deny') }}">
                @csrf
                <input type="hidden" name="redirect_uri" value="{{ $authorize['redirect_uri'] }}">
                <input type="hidden" name="state" value="{{ $authorize['state'] }}">
                <button type="submit" class="maestro-btn-secondary w-full text-[12px]">Refuser</button>
            </form>
        </div>
    </div>
@endsection
