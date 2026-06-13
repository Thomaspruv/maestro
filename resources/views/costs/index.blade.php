@extends('layouts.maestro')

@section('title', 'Coûts — '.$project->name)

@section('content')
    <div class="mb-5 grid grid-cols-3 gap-3">
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
                    <th class="px-4 py-2 font-semibold text-text-muted">Tâche</th>
                    <th class="px-4 py-2 font-semibold text-text-muted">Agent</th>
                    <th class="px-4 py-2 font-semibold text-text-muted">Modèle</th>
                    <th class="px-4 py-2 font-semibold text-text-muted">Tokens</th>
                    <th class="px-4 py-2 font-semibold text-text-muted text-right">Coût</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="border-b border-bg-overlay/50 hover:bg-bg-surface/50">
                        <td class="px-4 py-2 text-text-muted">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">
                            @if($log->task)
                                <a href="{{ route('projects.tasks.show', [$project, $log->task]) }}" class="text-text-primary hover:text-primary-light">
                                    {{ Str::limit($log->task->title, 40) }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 text-text-muted">
                            @if($log->agentRun)
                                {{ config('maestro.agent_labels.'.$log->agentRun->agent_type->value.'.emoji', '🤖') }}
                                {{ config('maestro.agent_labels.'.$log->agentRun->agent_type->value.'.name', $log->agentRun->agent_type->value) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 text-text-muted">{{ $log->model ?? '—' }}</td>
                        <td class="px-4 py-2 text-text-muted">{{ number_format($log->input_tokens + $log->output_tokens) }}</td>
                        <td class="px-4 py-2 text-right font-semibold text-text-primary">${{ number_format($log->cost, 4) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8">
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
