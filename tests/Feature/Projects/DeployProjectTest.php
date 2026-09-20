<?php

use App\Jobs\DeployProjectJob;
use App\Models\Project;
use App\Models\User;
use App\Services\ContainerManagementService;
use App\Services\DeploymentService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('user can access deploy page', function () {
    $response = $this->actingAs($this->user)->get(route('projects.deploy'));

    $response->assertStatus(200);
    $response->assertSeeLivewire('projects.deploy');
});

test('deployment service validates docker availability', function () {
    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('isDockerAvailable')->andReturn(false);
    $this->app->instance(ContainerManagementService::class, $containerService);

    $deploymentService = app(DeploymentService::class);

    expect(fn () => $deploymentService->deployProject($this->user, [
        'name' => 'Test Project',
    ]))->toThrow(RuntimeException::class, 'Docker is not installed or not running');
});

test('project creation stores correct docker fields', function () {
    Process::fake();

    $data = [
        'name' => 'Test Project',
        'git_repo' => 'https://github.com/test/repo.git',
        'git_branch' => 'develop',
        'git_auth_type' => 'token',
        'git_credentials' => 'ghp_test_token',
        'app_path' => 'apps/laravel',
        'domain' => 'test.example.com',
        'queue_enabled' => true,
        'queue_connection' => 'redis',
        'queue_workers' => 3,
    ];

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('isDockerAvailable')->andReturn(true);
    $containerService->shouldReceive('isDockerComposeAvailable')->andReturn(true);
    $containerService->shouldReceive('startContainer')->andReturn('container123');
    $this->app->instance(ContainerManagementService::class, $containerService);

    $deploymentService = app(DeploymentService::class);
    $project = $deploymentService->deployProject($this->user, $data);

    expect($project->name)->toBe('Test Project');
    expect($project->git_repo)->toBe('https://github.com/test/repo.git');
    expect($project->git_branch)->toBe('develop');
    expect($project->git_auth_type)->toBe('token');
    expect($project->app_path)->toBe('apps/laravel');
    expect($project->php_version)->toBe('8.4');
    expect($project->reverb_enabled)->toBeFalse();
    expect($project->octane_enabled)->toBeFalse();
    expect($project->octane_server)->toBeNull();
    expect($project->queue_enabled)->toBeTrue();
    expect($project->queue_connection)->toBe('redis');
    expect($project->queue_workers)->toBe(3);
});

test('project creation stores configured resource limits', function () {
    Process::fake();

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('isDockerAvailable')->andReturn(true);
    $containerService->shouldReceive('isDockerComposeAvailable')->andReturn(true);
    $containerService->shouldReceive('startContainer')->andReturn('container123');
    $this->app->instance(ContainerManagementService::class, $containerService);

    $project = app(DeploymentService::class)->deployProject($this->user, [
        'name' => 'Limited Project',
        'memory_limit_mb' => 512,
        'storage_limit_gb' => 10,
    ]);

    expect($project->memory_limit_mb)->toBe(512)
        ->and($project->storage_limit_gb)->toBe(10);
});

test('git credentials are encrypted in database', function () {
    Process::fake();

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('isDockerAvailable')->andReturn(true);
    $containerService->shouldReceive('isDockerComposeAvailable')->andReturn(true);
    $containerService->shouldReceive('startContainer')->andReturn('container123');
    $this->app->instance(ContainerManagementService::class, $containerService);

    $deploymentService = app(DeploymentService::class);
    $project = $deploymentService->deployProject($this->user, [
        'name' => 'Secure Project',
        'git_auth_type' => 'ssh',
        'git_credentials' => 'ssh-private-key-content',
    ]);

    // Raw DB value should be encrypted
    $rawValue = DB::table('projects')->where('id', $project->id)->value('git_credentials');
    expect($rawValue)->not->toBe('ssh-private-key-content');

    // Model accessor should decrypt
    expect($project->git_credentials)->toBe('ssh-private-key-content');
});

test('deployment rejects a Laravel path outside the repository', function () {
    Process::fake();

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('isDockerAvailable')->andReturn(true);
    $containerService->shouldReceive('isDockerComposeAvailable')->andReturn(true);
    $this->app->instance(ContainerManagementService::class, $containerService);

    expect(fn () => app(DeploymentService::class)->deployProject($this->user, [
        'name' => 'Unsafe Project',
        'app_path' => '../shared',
    ]))->toThrow(InvalidArgumentException::class);
});

test('deploy job rewrites APP_NAME to a slugged project identity during environment setup', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Demo Project',
        'slug' => 'demo-project',
    ]);

    $projectPath = storage_path('app/projects/deploy-env-test');
    File::ensureDirectoryExists($projectPath, 0755, true);
    File::put("{$projectPath}/.env.example", "APP_NAME=Laravel\nAPP_ENV=production\n");

    $job = new DeployProjectJob($project, []);
    $method = new ReflectionMethod($job, 'setupEnvironment');
    $method->setAccessible(true);
    $method->invoke($job, $projectPath);

    $envContent = File::get("{$projectPath}/.env");
    expect($envContent)->toContain('APP_NAME=demo-project');
    expect($envContent)->toContain('APP_ENV=production');

    File::deleteDirectory($projectPath);
});

test('deploy job enables Octane when the application requires it', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'octane_enabled' => false,
    ]);

    $projectPath = storage_path('app/projects/octane-detection-test');
    File::ensureDirectoryExists($projectPath, 0755, true);
    File::put("{$projectPath}/composer.json", json_encode([
        'require' => [
            'php' => '^8.3',
            'laravel/octane' => '^2.0',
            'laravel/reverb' => '^1.0',
        ],
    ]));
    File::put("{$projectPath}/.env.example", "APP_NAME=Laravel\n");

    $job = new DeployProjectJob($project, []);
    $method = new ReflectionMethod($job, 'detectApplicationFeatures');
    $method->setAccessible(true);
    $method->invoke($job, $projectPath);

    expect($project->fresh()->octane_enabled)->toBeTrue();
    expect($project->fresh()->octane_server)->toBe('swoole');
    expect($project->fresh()->reverb_enabled)->toBeTrue();
    expect($project->fresh()->php_version)->toBe('8.4');

    File::deleteDirectory($projectPath);
});

test('deploy job detects RoadRunner from the application environment example', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'octane_enabled' => false,
    ]);

    $projectPath = storage_path('app/projects/roadrunner-detection-test');
    File::ensureDirectoryExists($projectPath, 0755, true);
    File::put("{$projectPath}/composer.json", json_encode([
        'require' => ['laravel/octane' => '^2.0'],
    ]));
    File::put("{$projectPath}/.env.example", "OCTANE_SERVER=roadrunner\n");

    $job = new DeployProjectJob($project, []);
    $method = new ReflectionMethod($job, 'detectApplicationFeatures');
    $method->setAccessible(true);
    $method->invoke($job, $projectPath);

    expect($project->fresh()->octane_enabled)->toBeTrue();
    expect($project->fresh()->octane_server)->toBe('roadrunner');

    File::deleteDirectory($projectPath);
});

test('deploy job detects FrankenPHP from the application environment example', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'octane_enabled' => false,
    ]);

    $projectPath = storage_path('app/projects/frankenphp-detection-test');
    File::ensureDirectoryExists($projectPath, 0755, true);
    File::put("{$projectPath}/composer.json", json_encode([
        'require' => ['laravel/octane' => '^2.0'],
    ]));
    File::put("{$projectPath}/.env.example", "OCTANE_SERVER=frankenphp\n");

    $job = new DeployProjectJob($project, []);
    $method = new ReflectionMethod($job, 'detectApplicationFeatures');
    $method->setAccessible(true);
    $method->invoke($job, $projectPath);

    expect($project->fresh()->octane_enabled)->toBeTrue();
    expect($project->fresh()->octane_server)->toBe('frankenphp');

    File::deleteDirectory($projectPath);
});

test('deploy job leaves standard PHP-FPM enabled when Octane is absent', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'octane_enabled' => true,
        'octane_server' => 'roadrunner',
    ]);

    $projectPath = storage_path('app/projects/no-octane-detection-test');
    File::ensureDirectoryExists($projectPath, 0755, true);
    File::put("{$projectPath}/composer.json", json_encode([
        'require' => ['laravel/framework' => '^13.0'],
    ]));
    File::put("{$projectPath}/.env.example", "APP_NAME=Laravel\n");

    $job = new DeployProjectJob($project, []);
    $method = new ReflectionMethod($job, 'detectApplicationFeatures');
    $method->setAccessible(true);
    $method->invoke($job, $projectPath);

    expect($project->fresh()->octane_enabled)->toBeFalse();
    expect($project->fresh()->octane_server)->toBeNull();
    expect($project->fresh()->reverb_enabled)->toBeFalse();
    expect($project->fresh()->php_version)->toBe('8.4');

    File::deleteDirectory($projectPath);
});

test('deploy job selects a PHP version supported by the locked dependency graph', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $projectPath = storage_path('app/projects/php-version-detection-test');
    File::ensureDirectoryExists($projectPath, 0755, true);
    File::put("{$projectPath}/composer.json", json_encode([
        'require' => ['php' => '^7.4|^8.0'],
    ]));
    File::put("{$projectPath}/composer.lock", json_encode([
        'packages' => [[
            'name' => 'example/package',
            'require' => ['php' => '>=8.0.2 <8.2'],
        ]],
    ]));

    $job = new DeployProjectJob($project, []);
    $method = new ReflectionMethod($job, 'detectApplicationFeatures');
    $method->setAccessible(true);
    $method->invoke($job, $projectPath);

    expect($project->fresh()->php_version)->toBe('8.1');

    File::deleteDirectory($projectPath);
});

test('project status progresses through deployment stages', function () {
    Process::fake();

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('isDockerAvailable')->andReturn(true);
    $containerService->shouldReceive('isDockerComposeAvailable')->andReturn(true);
    $containerService->shouldReceive('startContainer')->andReturn('container123');
    $this->app->instance(ContainerManagementService::class, $containerService);

    $deploymentService = app(DeploymentService::class);
    $project = $deploymentService->deployProject($this->user, [
        'name' => 'Status Test',
    ]);

    expect($project->status)->toBe('active');
    expect($project->container_id)->toBe('container123');
    expect($project->deployed_at)->not->toBeNull();
});
