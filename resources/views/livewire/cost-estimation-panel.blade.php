<div class="maestro-card p-4">
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-xs font-semibold text-text-primary">Estimation des coûts</h3>
        @if($estimate)
            <span class="text-sm font-bold text-primary-light">${{ number_format($estimate['total_mid'], 4) }}</span>
        @endif
    </div>

    @if(!$task)
        <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-maestro.select wire:model.live="type" label="Type">
                <option value="feature">Fonctionnalité</option>
                <option value="bug">Bug</option>
                <option value="improvement">Amélioration</option>
                <option value="chore">Maintenance</option>
            </x-maestro.select>
            <x-maestro.select wire:model.live="mode" label="Mode">
                <option value="manual">Manuel</option>
                <option value="semi_auto">Semi-auto</option>
                <option value="full_auto">Auto</option>
            </x-maestro.select>
        </div>
    @endif

    @if($estimate)
        <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
            <div class="rounded bg-bg-surface px-2 py-1.5 text-center">
                <p class="text-[9px] text-text-muted">Bas</p>
                <p class="text-xs font-semibold text-text-primary">${{ number_format($estimate['total_low'], 4) }}</p>
            </div>
            <div class="rounded bg-primary-muted px-2 py-1.5 text-center">
                <p class="text-[9px] text-primary-light">Médian</p>
                <p class="text-xs font-semibold text-primary-light">${{ number_format($estimate['total_mid'], 4) }}</p>
            </div>
            <div class="rounded bg-bg-surface px-2 py-1.5 text-center">
                <p class="text-[9px] text-text-muted">Haut</p>
                <p class="text-xs font-semibold text-text-primary">${{ number_format($estimate['total_high'], 4) }}</p>
            </div>
        </div>

        <div class="max-h-48 space-y-1 overflow-y-auto">
            @foreach($estimate['roles'] ?? [] as $agent => $data)
                @php $label = config("maestro.role_labels.{$agent}", []); @endphp
                <div class="flex items-center justify-between rounded bg-bg-surface px-2 py-1 text-[10px]">
                    <span>{{ $label['emoji'] ?? '🤖' }} {{ $label['name'] ?? $agent }}</span>
                    <span class="text-text-muted">${{ number_format($data['estimated_cost'], 5) }}</span>
                </div>
            @endforeach
        </div>

        <p class="mt-2 text-[10px] text-text-muted">{{ $estimate['caching_note'] }}</p>
    @else
        <p class="text-xs text-text-muted">Calcul en cours...</p>
    @endif
</div>
