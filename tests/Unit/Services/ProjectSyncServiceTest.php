<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ContainerManagementService;
use App\Services\GitService;
use App\Services\ProjectSyncService;

test('sync pulls inside the running container and restarts it without replacing its id', function () {
    $project = Project::factory()->create([
        'user_id' => User::factory(),
        'container_id' => 'stale-container-id',
        'status' => 'failed',
        'deployment_error' => 'Previous failure',
    ]);

    $gitService = Mockery::mock(GitService::class);
    $gitService->shouldReceive('pullRepositoryInContainer')
        ->once()
        ->with($project, Mockery::type(ContainerManagementService::class));

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('getContainerId')
        ->once()
        ->with($project)
        ->andReturn('current-container-id');
    $containerService->shouldReceive('restartContainerInPlace')
        ->once()
        ->with($project)
        ->andReturn('current-container-id');

    $service = new ProjectSyncService($gitService, $containerService);

    $service->sync($project);

    expect($project->fresh()->status)->toBe('active');
    expect($project->fresh()->deployment_error)->toBeNull();
    expect($project->fresh()->container_id)->toBe('current-container-id');
});

test('sync fails before pulling when no project container exists', function () {
    $project = Project::factory()->create([
        'user_id' => User::factory(),
        'container_id' => 'stale-container-id',
    ]);

    $containerService = Mockery::mock(ContainerManagementService::class);
    $containerService->shouldReceive('getContainerId')
        ->once()
        ->with($project)
        ->andReturnNull();

    $service = new ProjectSyncService(
        Mockery::mock(GitService::class),
        $containerService,
    );

    expect(fn () => $service->sync($project))->toThrow(RuntimeException::class, 'Container not found');
});
