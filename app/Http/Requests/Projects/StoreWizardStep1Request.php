<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class StoreWizardStep1Request extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'github_repo' => ['required', 'string', 'regex:/^[\w.\-]+\/[\w.\-]+$/'],
            'github_branch' => ['required', 'string', 'max:255'],
            'github_token' => ['nullable', 'string', 'min:10'],
            'read_context_from_repo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('github_token') && session()->has('github_oauth_token')) {
            $this->merge(['github_token' => session('github_oauth_token')]);
        }
    }
}
