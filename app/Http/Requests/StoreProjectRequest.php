<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'git_repo' => ['nullable', 'url'],
            'git_branch' => ['nullable', 'string', 'max:255'],
            'git_auth_type' => ['required', 'in:none,ssh,token'],
            'git_credentials' => ['nullable', 'string'],
            'app_path' => ['required', 'string', 'max:255', 'regex:/^(?:\.|[A-Za-z0-9_][A-Za-z0-9._\/-]*)$/'],
            'php_version' => ['required', 'in:8.2,8.3,8.4'],
            'domain' => ['nullable', 'string', 'max:255'],
            'document_root' => ['nullable', 'string', 'max:500'],
            'queue_enabled' => ['boolean'],
            'queue_connection' => ['nullable', 'required_if:queue_enabled,true', 'string', 'max:50'],
            'queue_workers' => ['nullable', 'integer', 'min:1', 'max:10'],
            'env_variables' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'git_repo' => 'Git repository URL',
            'git_branch' => 'Git branch',
            'git_auth_type' => 'Git authentication type',
            'git_credentials' => 'Git credentials',
            'app_path' => 'Laravel folder path',
            'php_version' => 'PHP version',
            'document_root' => 'document root path',
            'queue_connection' => 'queue connection',
            'queue_workers' => 'number of queue workers',
            'env_variables' => 'environment variables',
        ];
    }
}
