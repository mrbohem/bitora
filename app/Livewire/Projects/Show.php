<?php

namespace App\Livewire\Projects;

use App\Actions\DeleteProject;
use App\Models\CronJob;
use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Services\ContainerManagementService;
use App\Services\ContainerResourceService;
use App\Services\DeploymentService;
use App\Services\EnvironmentService;
use App\Services\ProjectContainerService;
use App\Services\ProjectSettingsService;
use App\Services\ProjectSyncService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Project $project;

    public $activeTab = 'overview';

    // Deployment status tracking
    public $deploymentStatus = '';

    public $deploymentMessage = '';

    public $deploymentProgress = 0;

    public ?string $syncMessage = null;

    public bool $syncFailed = false;

    // Cron tab
    public $cronSchedule = '';

    public $cronCommand = '';

    public $showCronModal = false;

    // Env tab
    public $envKey = '';

    public $envValue = '';

    public $showEnvModal = false;

    public bool $showDeleteModal = false;

    public bool $showSettingsModal = false;

    public string $settingsName = '';

    public string $settingsDomain = '';

    public string $settingsGitBranch = '';

    public bool $settingsQueueEnabled = false;

    public string $settingsQueueConnection = 'redis';

    public int $settingsQueueWorkers = 1;

    public ?int $settingsMemoryLimitMb = null;

    public ?int $settingsStorageLimitGb = null;

    public ?string $settingsCpuLimitCores = null;

    public array $availableResources = [
        'memory_mb' => null,
        'storage_gb' => null,
    ];

    public string $projectNameConfirmation = '';

    public $editingEnvId = null;

    // Logs tab
    public $logLines = 100;

    // Terminal tab
    public string $commandInput = '';

    public string $commandOutput = '';

    public string $commandError = '';

    public function mount(Project $project, EnvironmentService $environmentService)
    {
        abort_unless($project->user_id === auth()->id(), 403);

        $this->project = $project;
        $this->deploymentStatus = $project->status;
        $this->deploymentProgress = cache()->get("deployment.{$project->id}.progress", 0);
        $this->loadSettings();

        // Sync .env file to database on page load
        $this->syncEnvFromFile($environmentService);
    }

    private function syncEnvFromFile(EnvironmentService $environmentService): void
    {
        $liveEnv = $environmentService->readLiveEnv($this->project);

        foreach ($liveEnv as $key => $value) {
            EnvironmentVariable::updateOrCreate(
                ['project_id' => $this->project->id, 'key' => $key],
                ['value' => $value]
            );
        }
    }

    public function updateDeploymentStatus($event)
    {
        $this->deploymentStatus = $event['status'];
        $this->deploymentMessage = $event['message'] ?? '';
        $this->deploymentProgress = $event['progress'] ?? 0;

        // Refresh project model
        $this->project->refresh();
    }

    public function startProject(ProjectContainerService $projectContainerService)
    {
        try {
            $this->project = $projectContainerService->start($this->project);

            $this->dispatch('notify', message: 'Project started successfully!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Failed to start project: '.$e->getMessage(), type: 'error');
        }
    }

    public function stopProject(ContainerManagementService $containerService, DeploymentService $deploymentService)
    {
        try {
            $projectPath = $deploymentService->getProjectPath($this->project);
            $containerService->stopContainer($this->project, $projectPath);

            $this->project->update(['status' => 'stopped']);

            $this->dispatch('notify', message: 'Project stopped successfully!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Failed to stop project: '.$e->getMessage(), type: 'error');
        }
    }

    public function restartProject(
        ProjectContainerService $projectContainerService,
    ) {
        try {
            $this->project = $projectContainerService->restart($this->project);

            $this->dispatch('notify', message: 'Project restarted successfully!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Failed to restart project: '.$e->getMessage(), type: 'error');
        }
    }

    public function createCronJob()
    {
        $validated = $this->validate([
            'cronSchedule' => ['required', 'string', 'max:255', 'regex:/^(\*|[0-5]?[0-9]|\*\/[0-9]+)\s+(\*|[01]?[0-9]|2[0-3]|\*\/[0-9]+)\s+(\*|[0-2]?[0-9]|3[01]|\*\/[0-9]+)\s+(\*|[0-9]|1[0-2]|\*\/[0-9]+)\s+(\*|[0-6]|\*\/[0-9]+)$/'],
            'cronCommand' => ['required', 'string', 'max:1000'],
        ]);

        CronJob::create([
            'project_id' => $this->project->id,
            'schedule' => $validated['cronSchedule'],
            'command' => $validated['cronCommand'],
        ]);

        $this->reset(['cronSchedule', 'cronCommand', 'showCronModal']);
        $this->dispatch('notify', message: 'Cron job created successfully! Restart project to apply changes.', type: 'success');
    }

    public function deleteCronJob($id)
    {
        $cronJob = CronJob::findOrFail($id);
        $cronJob->delete();

        $this->dispatch('notify', message: 'Cron job deleted successfully! Restart project to apply changes.', type: 'success');
    }

    public function toggleCronJob($id)
    {
        $cronJob = CronJob::findOrFail($id);
        $cronJob->update(['enabled' => ! $cronJob->enabled]);

        $this->dispatch('notify', message: 'Cron job updated! Restart project to apply changes.', type: 'success');
    }

    public function saveEnvVariable(EnvironmentService $environmentService)
    {
        $validated = $this->validate([
            'envKey' => ['required', 'string', 'max:255'],
            'envValue' => ['required', 'string'],
        ]);

        $environmentService->setVariable($this->project, auth()->user(), $validated['envKey'], $validated['envValue']);

        $this->reset(['envKey', 'envValue', 'showEnvModal', 'editingEnvId']);
        $this->syncEnvFromFile($environmentService);
        $this->dispatch('notify', message: 'Environment variable saved successfully!', type: 'success');
    }

    public function editEnvVariable($id)
    {
        $variable = EnvironmentVariable::findOrFail($id);
        $this->editingEnvId = $variable->id;
        $this->envKey = $variable->key;
        $this->envValue = $variable->value;
        $this->showEnvModal = true;
    }

    public function deleteEnvVariable($id, EnvironmentService $environmentService)
    {
        $variable = EnvironmentVariable::findOrFail($id);
        $environmentService->deleteVariable($variable, auth()->user());

        $this->syncEnvFromFile($environmentService);
        $this->dispatch('notify', message: 'Environment variable deleted successfully!', type: 'success');
    }

    public function deleteProject(DeleteProject $deleteProject): void
    {
        $this->validate([
            'projectNameConfirmation' => ['required', 'string', Rule::in([$this->project->name])],
        ], [
            'projectNameConfirmation.in' => 'The project name does not match.',
        ]);

        $deleteProject->handle($this->project);

        $this->redirect(route('projects.index'), navigate: true);
    }

    public function refreshEnvVariables(EnvironmentService $environmentService)
    {
        $this->syncEnvFromFile($environmentService);
        $this->dispatch('notify', message: 'Environment variables refreshed from .env file!', type: 'success');
    }

    public function syncFromGitHub(ProjectSyncService $projectSyncService): void
    {
        $this->syncMessage = null;
        $this->syncFailed = false;

        if (blank($this->project->git_repo)) {
            $this->syncMessage = 'This project is not connected to a GitHub repository.';
            $this->syncFailed = true;

            return;
        }

        try {
            $projectSyncService->sync($this->project);
            $this->deploymentStatus = 'active';
            $this->syncMessage = 'GitHub code synced and project restarted successfully!';

            $this->dispatch('notify', message: $this->syncMessage, type: 'success');
        } catch (\Throwable $e) {
            $this->project->update(['status' => 'failed', 'deployment_error' => $e->getMessage()]);
            $this->deploymentStatus = 'failed';
            $this->syncMessage = 'GitHub sync failed: '.$e->getMessage();
            $this->syncFailed = true;

            $this->dispatch('notify', message: 'GitHub sync failed: '.$e->getMessage(), type: 'error');
        }
    }

    public function toggleGitHubAutoUpdate(): void
    {
        $this->project->update(['github_auto_update' => ! $this->project->github_auto_update]);
        $this->dispatch('notify', message: 'GitHub auto-update '.($this->project->github_auto_update ? 'enabled' : 'disabled').'.', type: 'success');
    }

    public function openSettings(): void
    {
        $this->loadAvailableResources();
        $this->loadSettings();
        $this->showSettingsModal = true;
    }

    public function saveSettings(ProjectSettingsService $settingsService, ContainerResourceService $resourceService): void
    {
        abort_unless($this->project->user_id === auth()->id(), 403);
        $this->loadAvailableResources();
        $this->settingsCpuLimitCores = blank($this->settingsCpuLimitCores) ? null : $this->settingsCpuLimitCores;

        $validated = $this->validate([
            'settingsName' => ['required', 'string', 'max:255'],
            'settingsDomain' => ['nullable', 'string', 'max:255'],
            'settingsGitBranch' => ['required', 'string', 'max:255'],
            'settingsQueueEnabled' => ['boolean'],
            'settingsQueueConnection' => ['nullable', 'required_if:settingsQueueEnabled,true', 'string', 'max:50'],
            'settingsQueueWorkers' => ['required', 'integer', 'min:1', 'max:10'],
            'settingsMemoryLimitMb' => ['nullable', 'integer', 'min:128'],
            'settingsStorageLimitGb' => ['nullable', 'integer', 'min:1'],
            'settingsCpuLimitCores' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01'],
        ]);

        $resourceErrors = $resourceService->validateLimits(
            $validated['settingsMemoryLimitMb'],
            $validated['settingsStorageLimitGb'],
            $this->availableResources,
        );

        if ($resourceErrors !== []) {
            throw ValidationException::withMessages($resourceErrors);
        }

        try {
            $this->project = $settingsService->update($this->project, auth()->user(), [
                'name' => $validated['settingsName'],
                'domain' => $validated['settingsDomain'] ?: null,
                'git_branch' => $validated['settingsGitBranch'],
                'queue_enabled' => $validated['settingsQueueEnabled'],
                'queue_connection' => $validated['settingsQueueConnection'] ?: 'redis',
                'queue_workers' => $validated['settingsQueueWorkers'],
                'memory_limit_mb' => $validated['settingsMemoryLimitMb'],
                'storage_limit_gb' => $validated['settingsStorageLimitGb'],
                'cpu_limit_cores' => $validated['settingsCpuLimitCores'],
            ]);

            $this->showSettingsModal = false;
            $this->dispatch('notify', message: 'Project settings saved successfully!', type: 'success');
        } catch (\Throwable $e) {
            $this->addError('settings', 'Settings could not be applied: '.$e->getMessage());
        }
    }

    private function loadSettings(): void
    {
        $this->settingsName = $this->project->name;
        $this->settingsDomain = $this->project->domain ?? '';
        $this->settingsGitBranch = $this->project->git_branch ?? 'main';
        $this->settingsQueueEnabled = (bool) $this->project->queue_enabled;
        $this->settingsQueueConnection = $this->project->queue_connection ?? 'redis';
        $this->settingsQueueWorkers = $this->project->queue_workers ?? 1;
        $this->settingsMemoryLimitMb = $this->project->memory_limit_mb;
        $this->settingsStorageLimitGb = $this->project->storage_limit_gb;
        $this->settingsCpuLimitCores = $this->project->cpu_limit_cores;
    }

    private function loadAvailableResources(): void
    {
        if ($this->availableResources['memory_mb'] === null && $this->availableResources['storage_gb'] === null) {
            $this->availableResources = app(ContainerManagementService::class)->getAvailableResources($this->project);
        }
    }

    #[Computed]
    public function containerStatus()
    {
        $containerService = app(ContainerManagementService::class);

        return $containerService->getContainerStatus($this->project);
    }

    #[Computed]
    public function containerStats()
    {
        $containerService = app(ContainerManagementService::class);

        return $containerService->getContainerStats($this->project);
    }

    #[Computed]
    public function logs()
    {
        $containerService = app(ContainerManagementService::class);

        return $containerService->getContainerLogs($this->project, $this->logLines);
    }

    public function refreshLogs()
    {
        unset($this->logs);
        $this->dispatch('notify', message: 'Logs refreshed!', type: 'success');
    }

    public function executeCommand(ContainerManagementService $containerService): void
    {
        $validated = $this->validate([
            'commandInput' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $this->commandOutput = $containerService->execCommand($this->project, $validated['commandInput']);
            $this->commandError = '';
            $this->dispatch('notify', message: 'Command executed successfully!', type: 'success');
        } catch (\RuntimeException $exception) {
            $this->commandOutput = '';
            $this->commandError = $exception->getMessage();
            $this->dispatch('notify', message: 'Command execution failed.', type: 'error');
        }
    }

    public function render()
    {
        return view('livewire.projects.show')->layout('layouts.app');
    }
}
