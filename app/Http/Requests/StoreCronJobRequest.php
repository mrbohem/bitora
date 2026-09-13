<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCronJobRequest extends FormRequest
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
            'schedule' => ['required', 'string', 'max:255', 'regex:/^(\*|[0-5]?[0-9]|\*\/[0-9]+)\s+(\*|[01]?[0-9]|2[0-3]|\*\/[0-9]+)\s+(\*|[0-2]?[0-9]|3[01]|\*\/[0-9]+)\s+(\*|[0-9]|1[0-2]|\*\/[0-9]+)\s+(\*|[0-6]|\*\/[0-9]+)$/'],
            'command' => ['required', 'string', 'max:1000'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages
     */
    public function messages(): array
    {
        return [
            'schedule.regex' => 'The schedule must be a valid cron expression (e.g., "* * * * *").',
        ];
    }
}
