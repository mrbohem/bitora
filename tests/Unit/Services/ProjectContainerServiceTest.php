<?php

use App\Models\Project;
use App\Services\ContainerManagementService;
use App\Services\DeploymentService;
use App\Services\DockerComposeService;
use App\Services\ProjectContainerService;

it('persists the container identity after restarting a project', function () {
    $project = Project::factory()->create([
        'status' => 'active',
        'container_id' => 'old-container',
        'port' => 61001,
    ]);

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('restartContainer')
        ->once()
        ->with($project, '/tmp/project')
        ->andReturn('new-container');

    $deploymentService = Mockery::mock(DeploymentService::class);
    $deploymentService->shouldReceive('getProjectPath')
        ->once()
        ->with($project)
        ->andReturn('/tmp/project');

    $composeService = Mockery::mock(DockerComposeService::class);
    $composeService->shouldNotReceive('generateComposeFile');

    $service = new ProjectContainerService($containerService, $composeService, $deploymentService);

    $updatedProject = $service->restart($project);

    expect($updatedProject->container_id)->toBe('new-container')
        ->and($updatedProject->status)->toBe('active');
});
