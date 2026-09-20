<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ContainerManagementService;
use App\Services\DeploymentService;
use App\Services\GitService;
use App\Services\ProjectSyncService;

test('sync pulls inside the container, restarts it, and marks the project active', function () {
    $project = Project::factory()->create([
        'user_id' => User::factory(),
        'status' => 'failed',
        'deployment_error' => 'Previous failure',
    ]);

    $gitService = Mockery::mock(GitService::class);
    $gitService->shouldReceive('pullRepositoryInContainer')
        ->once()
        ->with($project, Mockery::type(ContainerManagementService::class));

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('restartContainer')
        ->once()
        ->with($project, '/var/www/html/projects/'.$project->slug);

    $deploymentService = Mockery::mock(DeploymentService::class);
    $deploymentService->shouldReceive('getProjectPath')
        ->once()
        ->with($project)
        ->andReturn('/var/www/html/projects/'.$project->slug);

    $service = new ProjectSyncService($gitService, $containerService, $deploymentService);

    $service->sync($project);

    expect($project->fresh()->status)->toBe('active');
    expect($project->fresh()->deployment_error)->toBeNull();
});
