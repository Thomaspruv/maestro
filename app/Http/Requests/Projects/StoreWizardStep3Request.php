<?php

namespace App\Http\Requests\Projects;

use App\Enums\TaskMode;
use App\Enums\TaskType;
use Database\Seeders\UserAgentSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWizardStep3Request extends FormRequest
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
        $taskTypes = array_column(TaskType::cases(), 'value');
        $user = $this->user();

        if ($user->agents()->count() === 0) {
            UserAgentSeeder::seedForUser($user);
        }

        $agentSlugs = $user->agents()->pluck('slug')->all();

        $rules = [
            'pipeline' => ['required', 'array'],
            'gates' => ['required', 'array'],
            'modes' => ['required', 'array'],
        ];

        foreach ($taskTypes as $type) {
            $rules["pipeline.{$type}"] = ['required', 'array'];
            $rules["pipeline.{$type}.*"] = ['string', Rule::in($agentSlugs)];
            $rules["gates.{$type}"] = ['required', 'array'];
            $rules["gates.{$type}.gate_specs"] = ['boolean'];
            $rules["gates.{$type}.gate_tech"] = ['boolean'];
            $rules["gates.{$type}.gate_merge"] = ['boolean'];
            $rules["modes.{$type}"] = ['required', Rule::enum(TaskMode::class)];
        }

        return $rules;
    }
}
