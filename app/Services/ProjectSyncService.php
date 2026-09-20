<?php

namespace App\Services;

use App\Models\Project;

class ProjectSyncService
{
    public function __construct(
        private readonly GitService $gitService,
        private readonly ContainerManagementService $containerService,
        private readonly DeploymentService $deploymentService,
    ) {}

    public function sync(Project $project): void
    {
        $this->gitService->pullRepositoryInContainer($project, $this->containerService);
        $this->containerService->restartContainer(
            $project,
            $this->deploymentService->getProjectPath($project),
        );

        $project->update([
            'status' => 'active',
            'deployment_error' => null,
        ]);
    }
}
