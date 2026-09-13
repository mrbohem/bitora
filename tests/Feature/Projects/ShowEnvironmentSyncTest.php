<?php

use App\Livewire\Projects\Show;
use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\ContainerManagementService;
use App\Services\DeploymentService;
use App\Services\EnvironmentService;
use App\Services\GitService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
});

test('environment variables sync from .env file on page load', function () {
    $envService = Mockery::mock(EnvironmentService::class);
    $envService->shouldReceive('readLiveEnv')
        ->withArgs(fn ($project) => $project->id === $this->project->id)
        ->once()
        ->andReturn([
            'APP_NAME' => 'Lumina',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
        ]);
    $this->app->instance(EnvironmentService::class, $envService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project]);

    expect(EnvironmentVariable::where('project_id', $this->project->id)->count())->toBe(3);
    expect(EnvironmentVariable::where('project_id', $this->project->id)->where('key', 'APP_NAME')->value('value'))->toBe('Lumina');
});

test('existing database variables are updated from .env file', function () {
    EnvironmentVariable::create([
        'project_id' => $this->project->id,
        'key' => 'APP_NAME',
        'value' => 'OldName',
    ]);

    $envService = Mockery::mock(EnvironmentService::class);
    $envService->shouldReceive('readLiveEnv')
        ->withArgs(fn ($project) => $project->id === $this->project->id)
        ->once()
        ->andReturn([
            'APP_NAME' => 'NewName',
            'APP_DEBUG' => 'true',
        ]);
    $this->app->instance(EnvironmentService::class, $envService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project]);

    expect(EnvironmentVariable::where('project_id', $this->project->id)->count())->toBe(2);
    expect(EnvironmentVariable::where('project_id', $this->project->id)->where('key', 'APP_NAME')->value('value'))->toBe('NewName');
});

test('no environment variables configured message shows when .env is empty', function () {
    $envService = Mockery::mock(EnvironmentService::class);
    $envService->shouldReceive('readLiveEnv')
        ->withArgs(fn ($project) => $project->id === $this->project->id)
        ->once()
        ->andReturn([]);
    $this->app->instance(EnvironmentService::class, $envService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project])
        ->set('activeTab', 'env')
        ->assertSee('No environment variables configured');
});

test('user can edit environment variable', function () {
    $envService = Mockery::mock(EnvironmentService::class);
    $envService->shouldReceive('readLiveEnv')->andReturn([]);
    $envService->shouldReceive('setVariable')
        ->with(
            Mockery::on(fn ($p) => $p->id === $this->project->id),
            Mockery::type(User::class),
            'APP_DEBUG',
            'false'
        )
        ->once();
    $this->app->instance(EnvironmentService::class, $envService);

    $variable = EnvironmentVariable::create([
        'project_id' => $this->project->id,
        'key' => 'APP_DEBUG',
        'value' => 'true',
    ]);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project])
        ->call('editEnvVariable', $variable->id)
        ->assertSet('envKey', 'APP_DEBUG')
        ->assertSet('envValue', 'true')
        ->assertSet('editingEnvId', $variable->id)
        ->set('envValue', 'false')
        ->call('saveEnvVariable')
        ->assertDispatched('notify');
});

test('deleted environment variable is removed from .env file', function () {
    $variable = EnvironmentVariable::create([
        'project_id' => $this->project->id,
        'key' => 'TEMP_KEY',
        'value' => 'temp_value',
    ]);

    $envService = Mockery::mock(EnvironmentService::class);
    $envService->shouldReceive('readLiveEnv')->andReturn([]);
    $envService->shouldReceive('deleteVariable')
        ->with(
            Mockery::on(fn ($v) => $v->id === $variable->id),
            Mockery::type(User::class)
        )
        ->once();
    $this->app->instance(EnvironmentService::class, $envService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project])
        ->call('deleteEnvVariable', $variable->id)
        ->assertDispatched('notify');
});

test('refresh button syncs variables from .env file', function () {
    $envService = Mockery::mock(EnvironmentService::class);
    $envService->shouldReceive('readLiveEnv')
        ->withArgs(fn ($project) => $project->id === $this->project->id)
        ->twice()
        ->andReturn(['NEW_KEY' => 'new_value']);
    $this->app->instance(EnvironmentService::class, $envService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project])
        ->call('refreshEnvVariables')
        ->assertDispatched('notify');

    expect(EnvironmentVariable::where('project_id', $this->project->id)->where('key', 'NEW_KEY')->exists())->toBeTrue();
});

test('manual github sync pulls latest code and restarts the app', function () {
    $this->project->update([
        'git_repo' => 'https://github.com/test/repo.git',
        'git_branch' => 'main',
        'domain' => 'example.com',
        'status' => 'active',
    ]);

    $gitService = Mockery::mock(GitService::class);
    $gitService->shouldReceive('pullRepository')
        ->once()
        ->with($this->project, Mockery::type('string'));
    $this->app->instance(GitService::class, $gitService);

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('getContainerStatus')
        ->andReturn(['status' => 'running', 'running' => true]);
    $containerService->shouldReceive('getContainerStats')
        ->andReturn(['cpu_percent' => '10%', 'memory_usage' => '50MB', 'network_io' => '1KB']);
    $containerService->shouldReceive('restartContainer')
        ->once()
        ->with($this->project, Mockery::type('string'));
    $this->app->instance(ContainerManagementService::class, $containerService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project])
        ->call('syncFromGitHub')
        ->assertDispatched('notify');
});

test('github webhook auto syncs when enabled', function () {
    $this->project->update([
        'git_repo' => 'https://github.com/test/repo.git',
        'git_branch' => 'main',
        'domain' => 'example.com',
        'status' => 'active',
        'github_auto_update' => true,
    ]);

    $gitService = Mockery::mock(GitService::class);
    $gitService->shouldReceive('pullRepository')
        ->once()
        ->withArgs(function ($project, $path) {
            return $project->id === $this->project->id && is_string($path);
        });
    $this->app->instance(GitService::class, $gitService);

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('restartContainer')
        ->once()
        ->withArgs(function ($project, $path) {
            return $project->id === $this->project->id && is_string($path);
        });
    $this->app->instance(ContainerManagementService::class, $containerService);

    $this->actingAs($this->user)
        ->post(route('projects.github.webhook', $this->project), ['ref' => 'refs/heads/main'])
        ->assertNoContent();
});

test('reads env from container when container_id exists', function () {
    $this->project->update(['container_id' => 'test-container-123']);

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('execCommand')
        ->with($this->project, 'cat /var/www/html/.env')
        ->once()
        ->andReturn("APP_NAME=ContainerApp\nAPP_ENV=production");

    $envService = new EnvironmentService(
        app(ActivityLogService::class),
        app(DeploymentService::class),
        $containerService
    );

    $result = $envService->readLiveEnv($this->project);

    expect($result)->toBe([
        'APP_NAME' => 'ContainerApp',
        'APP_ENV' => 'production',
    ]);
});

test('writes env to container when container_id exists', function () {
    $this->project->update(['container_id' => 'test-container-123']);

    EnvironmentVariable::create([
        'project_id' => $this->project->id,
        'key' => 'APP_DEBUG',
        'value' => 'false',
    ]);

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('execCommand')
        ->with($this->project, 'cat /var/www/html/.env')
        ->once()
        ->andReturn('APP_NAME=OldApp');

    $containerService->shouldReceive('execCommand')
        ->with($this->project, Mockery::pattern('/sh -c \'cat > \/var\/www\/html\/\.env/'))
        ->once()
        ->andReturn('');

    $envService = new EnvironmentService(
        app(ActivityLogService::class),
        app(DeploymentService::class),
        $containerService
    );

    $envService->syncToEnvFile($this->project);

    // No exception means success
    expect(true)->toBeTrue();
});
