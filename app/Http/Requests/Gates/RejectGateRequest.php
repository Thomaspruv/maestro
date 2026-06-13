<?php

namespace App\Http\Requests\Gates;

use Illuminate\Foundation\Http\FormRequest;

class RejectGateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('gate'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'feedback' => ['required', 'string', 'min:10', 'max:10000'],
        ];
    }
}
