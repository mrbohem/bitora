<?php

namespace App\Services;

use App\Models\Project;

class ProjectSyncService
{
    public function __construct(
        private readonly GitService $gitService,
        private readonly ContainerManagementService $containerService,
    ) {}

    public function sync(Project $project): void
    {
        $containerId = $this->containerService->getContainerId($project);

        if ($containerId === null) {
            throw new \RuntimeException('Container not found');
        }

        if ($containerId !== $project->container_id) {
            $project->update(['container_id' => $containerId]);
        }

        $this->gitService->pullRepositoryInContainer($project, $this->containerService);
        $containerId = $this->containerService->restartContainerInPlace($project);

        $project->update([
            'container_id' => $containerId,
            'status' => 'active',
            'deployment_error' => null,
        ]);
    }
}
