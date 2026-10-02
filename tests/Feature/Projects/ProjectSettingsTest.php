<?php

use App\Livewire\Projects\Show;
use App\Models\Project;
use App\Models\User;
use App\Services\ContainerManagementService;
use App\Services\EnvironmentService;
use App\Services\ProjectSettingsService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);

    $environmentService = Mockery::mock(EnvironmentService::class);
    $environmentService->shouldReceive('readLiveEnv')->andReturn([]);
    $this->app->instance(EnvironmentService::class, $environmentService);

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('getAvailableResources')->with(Mockery::type(Project::class))->andReturn([
        'memory_mb' => 4096,
        'storage_gb' => 100,
    ]);
    $containerService->shouldReceive('getContainerStatus')->andReturn([
        'status' => 'not_found',
        'running' => false,
    ]);
    $this->app->instance(ContainerManagementService::class, $containerService);
});

test('user can save project settings', function () {
    $settingsService = Mockery::mock(ProjectSettingsService::class);
    $settingsService->shouldReceive('update')
        ->once()
        ->withArgs(function (Project $project, User $user, array $settings): bool {
            return $project->id === $this->project->id
                && $user->id === $this->user->id
                && $settings === [
                    'name' => 'Production API',
                    'domain' => 'api.example.com',
                    'git_branch' => 'production',
                    'queue_enabled' => true,
                    'queue_connection' => 'redis',
                    'queue_workers' => 3,
                    'memory_limit_mb' => 1024,
                    'storage_limit_gb' => 20,
                    'cpu_limit_cores' => '0.23',
                ];
        })
        ->andReturn($this->project);
    $this->app->instance(ProjectSettingsService::class, $settingsService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project])
        ->set('settingsName', 'Production API')
        ->set('settingsDomain', 'api.example.com')
        ->set('settingsGitBranch', 'production')
        ->set('settingsQueueEnabled', true)
        ->set('settingsQueueConnection', 'redis')
        ->set('settingsQueueWorkers', 3)
        ->set('settingsMemoryLimitMb', 1024)
        ->set('settingsStorageLimitGb', 20)
        ->set('settingsCpuLimitCores', '0.23')
        ->call('saveSettings')
        ->assertSet('showSettingsModal', false)
        ->assertDispatched('notify');
});

test('project settings reject limits above available resources', function () {
    $settingsService = Mockery::mock(ProjectSettingsService::class);
    $settingsService->shouldNotReceive('update');
    $this->app->instance(ProjectSettingsService::class, $settingsService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project])
        ->set('settingsMemoryLimitMb', 8192)
        ->call('saveSettings')
        ->assertHasErrors(['memory_limit_mb']);
});

test('project settings reject cpu limits with more than two decimal places', function () {
    $settingsService = Mockery::mock(ProjectSettingsService::class);
    $settingsService->shouldNotReceive('update');
    $this->app->instance(ProjectSettingsService::class, $settingsService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project])
        ->set('settingsCpuLimitCores', '0.234')
        ->call('saveSettings')
        ->assertHasErrors(['settingsCpuLimitCores']);
});
