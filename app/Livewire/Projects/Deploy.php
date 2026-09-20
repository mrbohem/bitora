<?php

namespace App\Livewire\Projects;

use App\Services\ContainerManagementService;
use App\Services\ContainerResourceService;
use App\Services\DeploymentService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Deploy extends Component
{
    public $step = 1;

    // Basic Info (Step 1)
    public $name = '';

    public $git_repo = '';

    public $git_branch = 'main';

    public $app_path = '.';

    public $git_auth_type = 'none';

    public $git_credentials = '';

    public $domain = '';

    // Features (Step 3)
    public $queue_enabled = false;

    public $queue_connection = 'redis';

    public $queue_workers = 1;

    // Resource limits
    public $memory_limit_mb;

    public $storage_limit_gb;

    public array $availableResources = [
        'memory_mb' => null,
        'storage_gb' => null,
    ];

    // Environment Variables (Step 4)
    public $env_variables = [];

    public $env_key = '';

    public $env_value = '';

    public function mount(ContainerManagementService $containerService): void
    {
        $this->availableResources = $containerService->getAvailableResources();
    }

    public function updatedGitAuthType($value)
    {
        // Clear credentials when auth type changes
        if ($value === 'none') {
            $this->git_credentials = '';
        }
    }

    public function nextStep(ContainerResourceService $resourceService): void
    {
        $this->validateCurrentStep($resourceService);
        $this->step++;
    }

    public function previousStep(): void
    {
        $this->step--;
    }

    public function addEnvVariable()
    {
        $this->validate([
            'env_key' => ['required', 'string', 'max:255'],
            'env_value' => ['required', 'string'],
        ]);

        $this->env_variables[$this->env_key] = $this->env_value;
        $this->env_key = '';
        $this->env_value = '';
    }

    public function removeEnvVariable($key)
    {
        unset($this->env_variables[$key]);
    }

    public function deploy(DeploymentService $deploymentService, ContainerResourceService $resourceService)
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'git_repo' => ['nullable', 'url'],
            'git_branch' => ['nullable', 'string', 'max:255'],
            'app_path' => ['required', 'string', 'max:255', 'regex:/^(?:\.|[A-Za-z0-9_][A-Za-z0-9._\/-]*)$/'],
            'git_auth_type' => ['required', 'in:none,ssh,token'],
            'git_credentials' => ['nullable', 'string'],
            'domain' => ['nullable', 'string', 'max:255'],
            'queue_enabled' => ['boolean'],
            'queue_connection' => ['nullable', 'required_if:queue_enabled,true', 'string', 'max:50'],
            'queue_workers' => ['nullable', 'integer', 'min:1', 'max:10'],
            'memory_limit_mb' => ['nullable', 'integer', 'min:128'],
            'storage_limit_gb' => ['nullable', 'integer', 'min:1'],
            'env_variables' => ['nullable', 'array'],
        ]);

        $this->validateResourceLimits($resourceService);

        // Clear credentials if auth type is 'none'
        if ($validated['git_auth_type'] === 'none') {
            $validated['git_credentials'] = null;
        }

        try {
            $project = $deploymentService->deployProject(auth()->user(), $validated);

            session()->flash('message', "Project '{$project->name}' is being deployed. You'll be redirected to monitor progress.");

            return $this->redirect(route('projects.show', $project), navigate: true);
        } catch (\Exception $e) {
            $this->addError('deployment', 'Deployment failed: '.$e->getMessage());
        }
    }

    public function validateCurrentStep(ContainerResourceService $resourceService): void
    {
        $rules = match ($this->step) {
            1 => [
                'name' => ['required', 'string', 'max:255'],
                'git_repo' => ['nullable', 'url'],
                'git_branch' => ['nullable', 'string', 'max:255'],
                'app_path' => ['required', 'string', 'max:255', 'regex:/^(?:\.|[A-Za-z0-9_][A-Za-z0-9._\/-]*)$/'],
                'git_auth_type' => ['required', 'in:none,ssh,token'],
            ],
            2 => [
                'domain' => ['nullable', 'string', 'max:255'],
            ],
            3 => [
                'queue_enabled' => ['boolean'],
                'queue_connection' => ['nullable', 'required_if:queue_enabled,true', 'string', 'max:50'],
                'queue_workers' => ['nullable', 'integer', 'min:1', 'max:10'],
                'memory_limit_mb' => ['nullable', 'integer', 'min:128'],
                'storage_limit_gb' => ['nullable', 'integer', 'min:1'],
            ],
            default => [],
        };

        $this->validate($rules);

        if ($this->step === 3) {
            $this->validateResourceLimits($resourceService);
        }
    }

    private function validateResourceLimits(ContainerResourceService $resourceService): void
    {
        $errors = $resourceService->validateLimits(
            $this->memory_limit_mb,
            $this->storage_limit_gb,
            $this->availableResources,
        );

        foreach ($errors as $field => $message) {
            $this->addError($field, $message);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function render()
    {
        return view('livewire.projects.deploy')->layout('layouts.app');
    }
}
