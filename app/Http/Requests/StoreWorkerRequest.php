<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'command' => ['required', 'string', 'max:1000'],
            'processes' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * Get custom messages
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'The worker name may only contain letters, numbers, dashes, and underscores.',
        ];
    }
}
