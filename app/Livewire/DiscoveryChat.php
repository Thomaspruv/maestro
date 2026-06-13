<?php

namespace App\Livewire;

use App\Enums\TaskMode;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Models\Project;
use App\Services\CostEstimatorService;
use App\Services\DiscoveryChatService;
use Livewire\Component;

class DiscoveryChat extends Component
{
    public Project $project;

    public string $message = '';

    /** @var array<int, array<string, mixed>> */
    public array $messages = [];

    public ?string $error = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    public function send(DiscoveryChatService $chat): void
    {
        $this->authorize('view', $this->project);

        $this->validate([
            'message' => ['required', 'string', 'max:10000'],
        ], [
            'message.required' => 'Saisissez un message.',
        ]);

        $this->error = null;

        $userMessage = trim($this->message);
        $history = $this->conversationHistory();

        try {
            $result = $chat->send($this->project, auth()->user(), $history, $userMessage);

            $this->messages[] = [
                'role' => 'user',
                'content' => $userMessage,
            ];

            $this->messages[] = [
                'role' => 'assistant',
                'content' => $result['display_text'],
                'cost' => $result['cost'],
                'proposed_tasks' => array_map(
                    fn (array $task) => array_merge($task, ['status' => 'pending']),
                    $result['proposed_tasks'],
                ),
            ];

            $this->message = '';
            $this->dispatch('discovery-scroll-to-bottom');
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function launchDiscovery(DiscoveryChatService $chat): void
    {
        $this->authorize('view', $this->project);

        $this->error = null;

        try {
            $result = $chat->launch($this->project, auth()->user(), $this->conversationHistory());

            $this->messages[] = [
                'role' => 'user',
                'content' => 'Lancer la Discovery',
            ];

            $this->messages[] = [
                'role' => 'assistant',
                'content' => $result['display_text'],
                'cost' => $result['cost'],
                'proposed_tasks' => array_map(
                    fn (array $task) => array_merge($task, ['status' => 'pending']),
                    $result['proposed_tasks'],
                ),
            ];

            $this->dispatch('discovery-scroll-to-bottom');
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function addTask(int $messageIndex, int $taskIndex, CostEstimatorService $estimator): void
    {
        $this->authorize('view', $this->project);

        $taskData = $this->messages[$messageIndex]['proposed_tasks'][$taskIndex] ?? null;

        if (! $taskData || ($taskData['status'] ?? '') !== 'pending') {
            return;
        }

        $type = TaskType::tryFrom($taskData['type'] ?? '') ?? TaskType::Feature;
        $priority = TaskPriority::tryFrom($taskData['priority'] ?? '') ?? TaskPriority::Medium;
        $defaultModes = $this->project->default_modes ?? config('maestro.default_modes', []);
        $modeValue = $defaultModes[$type->value] ?? TaskMode::Manual->value;
        $mode = TaskMode::tryFrom($modeValue) ?? TaskMode::Manual;

        $maxSort = $this->project->tasks()->max('sort_order') ?? 0;

        $task = $this->project->tasks()->create([
            'title' => $taskData['title'],
            'description' => $taskData['description'] ?? '',
            'module' => $taskData['module'] ?? null,
            'type' => $type,
            'priority' => $priority,
            'mode' => $mode,
            'sort_order' => $maxSort + 1,
        ]);

        $estimate = $estimator->estimate($task);
        $task->update(['estimated_cost' => $estimate['total_mid']]);

        $this->messages[$messageIndex]['proposed_tasks'][$taskIndex]['status'] = 'added';
        $this->messages[$messageIndex]['proposed_tasks'][$taskIndex]['task_id'] = $task->id;

        session()->flash('success', "Tâche « {$task->title} » ajoutée au backlog.");
    }

    public function dismissTask(int $messageIndex, int $taskIndex): void
    {
        if (! isset($this->messages[$messageIndex]['proposed_tasks'][$taskIndex])) {
            return;
        }

        $this->messages[$messageIndex]['proposed_tasks'][$taskIndex]['status'] = 'dismissed';
    }

    public function clearHistory(): void
    {
        $this->messages = [];
        $this->error = null;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function conversationHistory(): array
    {
        return collect($this->messages)
            ->filter(fn (array $msg) => in_array($msg['role'], ['user', 'assistant'], true))
            ->map(fn (array $msg) => [
                'role' => $msg['role'],
                'content' => $msg['role'] === 'assistant'
                    ? ($msg['content'] ?? '')
                    : ($msg['content'] ?? ''),
            ])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.discovery-chat');
    }
}
