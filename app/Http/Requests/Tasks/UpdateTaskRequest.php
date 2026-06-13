<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskMode;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'module' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(TaskType::class)],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'mode' => ['sometimes', 'required', Rule::enum(TaskMode::class)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
