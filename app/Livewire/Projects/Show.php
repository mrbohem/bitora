<?php

namespace App\Livewire\Projects;

use App\Actions\DeleteProject;
use App\Models\CronJob;
use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Services\ContainerManagementService;
use App\Services\DeploymentService;
use App\Services\EnvironmentService;
use App\Services\GitService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    public Project $project;

    public $activeTab = 'overview';

    // Deployment status tracking
    public $deploymentStatus = '';

    public $deploymentMessage = '';

    public $deploymentProgress = 0;

    // Cron tab
    public $cronSchedule = '';

    public $cronCommand = '';

    public $showCronModal = false;

    // Env tab
    public $envKey = '';

    public $envValue = '';

    public $showEnvModal = false;

    public bool $showDeleteModal = false;

    public string $projectNameConfirmation = '';

    public $editingEnvId = null;

    // Logs tab
    public $logLines = 100;

    public function mount(Project $project, EnvironmentService $environmentService)
    {
        abort_unless($project->user_id === auth()->id(), 403);

        $this->project = $project;
        $this->deploymentStatus = $project->status;
        $this->deploymentProgress = cache()->get("deployment.{$project->id}.progress", 0);

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

    #[On('echo-private:project.{project.id},deployment.status')]
    public function updateDeploymentStatus($event)
    {
        $this->deploymentStatus = $event['status'];
        $this->deploymentMessage = $event['message'] ?? '';
        $this->deploymentProgress = $event['progress'] ?? 0;

        // Refresh project model
        $this->project->refresh();
    }

    public function startProject(ContainerManagementService $containerService, DeploymentService $deploymentService)
    {
        try {
            $projectPath = $deploymentService->getProjectPath($this->project);
            $containerService->startContainer($this->project, $projectPath);

            $this->project->update(['status' => 'active']);

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

    public function restartProject(ContainerManagementService $containerService, DeploymentService $deploymentService)
    {
        try {
            $projectPath = $deploymentService->getProjectPath($this->project);
            $containerService->restartContainer($this->project, $projectPath);

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

    public function syncFromGitHub(): void
    {
        if (blank($this->project->git_repo)) {
            $this->dispatch('notify', message: 'This project is not connected to a GitHub repository.', type: 'error');

            return;
        }

        try {
            $gitService = app(GitService::class);
            $containerService = app(ContainerManagementService::class);
            $projectPath = app(DeploymentService::class)->getProjectPath($this->project);

            $gitService->pullRepository($this->project, $projectPath);
            $containerService->restartContainer($this->project, $projectPath);
            $this->project->update(['status' => 'active']);

            $this->dispatch('notify', message: 'GitHub code synced successfully!', type: 'success');
        } catch (\Throwable $e) {
            $this->project->update(['status' => 'failed', 'deployment_error' => $e->getMessage()]);
            $this->dispatch('notify', message: 'GitHub sync failed: '.$e->getMessage(), type: 'error');
        }
    }

    public function toggleGitHubAutoUpdate(): void
    {
        $this->project->update(['github_auto_update' => ! $this->project->github_auto_update]);
        $this->dispatch('notify', message: 'GitHub auto-update '.($this->project->github_auto_update ? 'enabled' : 'disabled').'.', type: 'success');
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

    public function render()
    {
        return view('livewire.projects.show')->layout('layouts.app');
    }
}
