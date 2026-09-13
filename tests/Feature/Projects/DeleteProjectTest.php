<?php

use App\Livewire\Projects\Show;
use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Models\User;
use App\Services\ContainerManagementService;
use App\Services\EnvironmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->projectPath = storage_path('app/testing-delete-project');
    $this->project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Lumina Demo',
        'slug' => 'lumina-demo',
        'document_root' => $this->projectPath,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->projectPath);
    File::delete(storage_path('app/ssh-keys/lumina-demo.key'));
});

test('project deletion requires the exact project name', function () {
    $environmentService = Mockery::mock(EnvironmentService::class);
    $environmentService->shouldReceive('readLiveEnv')->once()->andReturn([]);
    $this->app->instance(EnvironmentService::class, $environmentService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project])
        ->set('showDeleteModal', true)
        ->set('projectNameConfirmation', 'Lumina')
        ->call('deleteProject')
        ->assertHasErrors(['projectNameConfirmation']);

    expect(Project::find($this->project->id))->not->toBeNull();
});

test('project deletion removes docker resources, files, ssh key, and database records', function () {
    File::ensureDirectoryExists($this->projectPath);
    File::put($this->projectPath.'/docker-compose.yml', 'services: {}');
    File::ensureDirectoryExists(storage_path('app/ssh-keys'));
    File::put(storage_path('app/ssh-keys/lumina-demo.key'), 'private-key');
    $variable = EnvironmentVariable::create([
        'project_id' => $this->project->id,
        'key' => 'APP_ENV',
        'value' => 'production',
    ]);

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('getContainerStatus')
        ->andReturn(['status' => 'not_found', 'running' => false]);
    $containerService->shouldReceive('removeContainer')
        ->withArgs(fn ($project, $path) => $project->is($this->project) && $path === $this->projectPath)
        ->once();
    $this->app->instance(ContainerManagementService::class, $containerService);

    $environmentService = Mockery::mock(EnvironmentService::class);
    $environmentService->shouldReceive('readLiveEnv')->once()->andReturn([]);
    $this->app->instance(EnvironmentService::class, $environmentService);

    Livewire::actingAs($this->user)
        ->test(Show::class, ['project' => $this->project])
        ->set('projectNameConfirmation', 'Lumina Demo')
        ->call('deleteProject')
        ->assertRedirect(route('projects.index'));

    expect(Project::find($this->project->id))->toBeNull()
        ->and(EnvironmentVariable::find($variable->id))->toBeNull()
        ->and(File::isDirectory($this->projectPath))->toBeFalse()
        ->and(File::exists(storage_path('app/ssh-keys/lumina-demo.key')))->toBeFalse();
});

test('project model safely returns stored git credentials when encrypted payload cannot be decrypted', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Fallback Project',
        'slug' => 'fallback-project',
        'git_auth_type' => 'token',
        'git_credentials' => 'plain-token-or-legacy-ciphertext',
    ]);

    DB::table('projects')
        ->where('id', $project->id)
        ->update(['git_credentials' => 'not a valid encrypted payload']);

    $project->refresh();

    expect($project->git_credentials)->toBe('not a valid encrypted payload');
});

test('user cannot delete another users project', function () {
    $otherProject = Project::factory()->create();

    $this->actingAs($this->user)
        ->get(route('projects.show', $otherProject))
        ->assertForbidden();
});
