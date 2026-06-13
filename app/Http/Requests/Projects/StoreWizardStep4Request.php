<?php

namespace App\Http\Requests\Projects;

use App\Enums\AgentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWizardStep4Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $agentTypes = array_column(AgentType::cases(), 'value');
        $models = array_keys(config('maestro.model_prices', []));

        return [
            'models' => ['required', 'array'],
            'models.*' => ['required', 'string', Rule::in($models)],
            'agents' => ['required', 'array'],
            'agents.*' => ['required', 'array'],
            'agents.*.is_active' => ['boolean'],
            'agents.*.model' => ['required', 'string', Rule::in($models)],
            'agents.*.system_prompt' => ['required', 'string', 'max:50000'],
            'agents.*.sort_order' => ['required', 'integer', 'min:0'],
        ] + collect($agentTypes)->mapWithKeys(fn (string $type) => [
            "agents.{$type}" => ['sometimes', 'array'],
        ])->all();
    }
}
