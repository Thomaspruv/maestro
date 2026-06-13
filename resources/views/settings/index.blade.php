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
    </div>
@endsection
