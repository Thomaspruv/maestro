<?php

namespace App\Http\Requests\AgentRuns;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        $run = $this->route('run');

        return $run && $this->user()->id === $run->task->project->user_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edited_output' => ['required', 'string', 'max:500000'],
        ];
    }
}
