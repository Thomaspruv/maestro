@props(['kind', 'value'])

@php
    $labels = [
        'task_type' => [
            'feature' => 'Fonctionnalité',
            'bug' => 'Bug',
            'improvement' => 'Amélioration',
            'chore' => 'Maintenance',
        ],
        'mode' => [
            'manual' => 'Manuel',
            'semi_auto' => 'Semi-auto',
            'full_auto' => 'Auto',
        ],
        'priority' => [
            'critical' => 'Critique',
            'high' => 'Haute',
            'medium' => 'Moyenne',
            'low' => 'Basse',
        ],
        'agent_status' => [
            'pending' => 'En attente',
            'running' => 'En cours',
            'completed' => 'Terminé',
            'failed' => 'Échoué',
            'waiting_gate' => 'Gate',
            'skipped' => 'Ignoré',
        ],
        'task_status' => [
            'backlog' => 'Backlog',
            'in_progress' => 'En cours',
            'waiting_hermes' => 'Hermes',
            'in_review' => 'En revue',
            'done' => 'Terminé',
            'failed' => 'Échoué',
        ],
    ];

    $colors = [
        'task_type' => [
            'feature' => 'bg-primary-muted text-primary-light',
            'bug' => 'bg-danger-muted text-danger',
            'improvement' => 'bg-[#1e2a3f] text-blue-300',
            'chore' => 'bg-bg-overlay text-text-muted',
        ],
        'mode' => [
            'manual' => 'bg-bg-overlay text-text-secondary',
            'semi_auto' => 'bg-warning-muted text-warning',
            'full_auto' => 'bg-success-muted text-success',
        ],
        'priority' => [
            'critical' => 'bg-danger-muted text-danger',
            'high' => 'bg-warning-muted text-warning',
            'medium' => 'bg-primary-muted text-primary-light',
            'low' => 'bg-bg-overlay text-text-muted',
        ],
        'agent_status' => [
            'pending' => 'bg-bg-overlay text-text-muted',
            'running' => 'bg-primary-muted text-primary-light',
            'completed' => 'bg-success-muted text-success',
            'failed' => 'bg-danger-muted text-danger',
            'waiting_gate' => 'bg-warning-muted text-warning',
            'skipped' => 'bg-bg-overlay text-text-faint',
        ],
        'task_status' => [
            'backlog' => 'bg-bg-overlay text-text-muted',
            'in_progress' => 'bg-primary-muted text-primary-light',
            'waiting_hermes' => 'bg-primary-muted text-primary-light',
            'in_review' => 'bg-warning-muted text-warning',
            'done' => 'bg-success-muted text-success',
            'failed' => 'bg-danger-muted text-danger',
        ],
    ];

    $rawValue = $value instanceof \BackedEnum ? $value->value : (string) $value;
    $label = $labels[$kind][$rawValue] ?? $rawValue;
    $colorClass = $colors[$kind][$rawValue] ?? 'bg-bg-overlay text-text-secondary';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-[12px] font-medium uppercase tracking-wide {$colorClass}"]) }}>
    {{ $label }}
</span>
