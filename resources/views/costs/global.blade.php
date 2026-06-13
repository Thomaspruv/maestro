@extends('layouts.maestro')

@section('title', 'Coûts global')

@section('content')
    <div class="mb-5 grid grid-cols-4 gap-3">
        <x-maestro.stat-card label="Coût du mois" :value="'$'.number_format($currentMonthCost, 2)" icon="💰" />
        @foreach($monthlyTotals->take(3) as $month)
            <x-maestro.stat-card
                label="{{ \Carbon\Carbon::parse($month->month)->translatedFormat('F Y') }}"
                :value="'$'.number_format($month->total_cost, 2)"
            />
        @endforeach
    </div>

    <div class="maestro-card overflow-hidden">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-bg-overlay bg-bg-surface">
                <tr>
                    <th class="px-4 py-2 font-semibold text-text-muted">Date</th>
                    <th class="px-4 py-2 font-semibold text-text-muted">Projet</th>
                    <th class="px-4 py-2 font-semibold text-text-muted">Tâche</th>
                    <th class="px-4 py-2 font-semibold text-text-muted">Agent</th>
                    <th class="px-4 py-2 font-semibold text-text-muted text-right">Coût</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="border-b border-bg-overlay/50 hover:bg-bg-surface/50">
                        <td class="px-4 py-2 text-text-muted">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">
                            @if($log->project)
                                <a href="{{ route('projects.show', $log->project) }}" class="text-text-primary hover:text-primary-light">
                                    {{ $log->project->name }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 text-text-muted">
                            @if($log->task && $log->project)
                                <a href="{{ route('projects.tasks.show', [$log->project, $log->task]) }}" class="hover:text-primary-light">
                                    {{ Str::limit($log->task->title, 35) }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 text-text-muted">
                            @if($log->agentRun)
                                {{ config('maestro.agent_labels.'.$log->agentRun->agent_type->value.'.emoji', '🤖') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right font-semibold text-text-primary">${{ number_format($log->cost, 4) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8">
                            <x-maestro.empty-state title="Aucun coût enregistré" icon="💸" class="border-0" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
@endsection
