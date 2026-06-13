<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class StoreWizardStep2Request extends FormRequest
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
            'vision' => ['nullable', 'string', 'max:10000'],
            'stack' => ['required', 'string', 'max:10000'],
            'conventions' => ['required', 'string', 'max:10000'],
            'modules' => ['required', 'string', 'max:10000'],
            'design_system' => ['required', 'string', 'max:10000'],
            'constraints' => ['required', 'string', 'max:10000'],
        ];
    }
}
