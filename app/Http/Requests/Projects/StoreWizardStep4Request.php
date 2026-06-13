<?php

namespace App\Http\Requests\Projects;

use Database\Seeders\UserAgentSeeder;
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
        $user = $this->user();

        if ($user->agents()->count() === 0) {
            UserAgentSeeder::seedForUser($user);
        }

        $agentSlugs = $user->agents()->pluck('slug')->all();
        $models = array_keys(config('maestro.model_prices', []));

        $rules = [
            'models' => ['required', 'array'],
            'models.*' => ['required', 'string', Rule::in($models)],
            'agents' => ['required', 'array'],
        ];

        foreach ($agentSlugs as $slug) {
            $rules["agents.{$slug}"] = ['required', 'array'];
            $rules["agents.{$slug}.is_active"] = ['boolean'];
            $rules["agents.{$slug}.model"] = ['required', 'string', Rule::in($models)];
            $rules["agents.{$slug}.system_prompt"] = ['required', 'string', 'max:50000'];
            $rules["agents.{$slug}.sort_order"] = ['required', 'integer', 'min:0'];
        }

        return $rules;
    }
}
