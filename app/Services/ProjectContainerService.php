<?php

namespace App\Services;

use App\Models\Project;

class ProjectContainerService
{
    public function __construct(
        private readonly ContainerManagementService $containerService,
        private readonly DockerComposeService $composeService,
        private readonly DeploymentService $deploymentService,
    ) {}

    public function start(Project $project): Project
    {
        $containerId = $this->containerService->startContainer(
            $project,
            $this->deploymentService->getProjectPath($project),
        );

        $project->update([
            'container_id' => $containerId,
            'status' => 'active',
        ]);

        return $project->fresh();
    }

    public function restart(Project $project): Project
    {
        $projectPath = $this->deploymentService->getProjectPath($project);

        if ($this->ensurePort($project, $projectPath)) {
            $this->composeService->generateComposeFile($project, $projectPath);
        }

        $containerId = $this->containerService->restartContainer($project, $projectPath);

        $project->update([
            'container_id' => $containerId,
            'status' => 'active',
        ]);

        return $project->fresh();
    }

    public function rebuild(Project $project): Project
    {
        $projectPath = $this->deploymentService->getProjectPath($project);
        $this->ensurePort($project, $projectPath);
        $this->composeService->generateComposeFile($project, $projectPath);

        $containerId = $this->containerService->rebuildContainer($project, $projectPath);

        $project->update(['container_id' => $containerId]);

        return $project->fresh();
    }

    public function syncIdentity(Project $project): Project
    {
        $containerId = $this->containerService->getContainerId($project);

        if ($containerId !== null && $containerId !== $project->container_id) {
            $project->update(['container_id' => $containerId]);
        }

        return $project->fresh();
    }

    private function ensurePort(Project $project, string $projectPath): bool
    {
        if ($project->port !== null || ! is_dir($projectPath)) {
            return false;
        }

        $currentPort = $this->containerService->getExternalPort($project);

        if ($currentPort === null) {
            return false;
        }

        $project->update(['port' => $currentPort]);

        return true;
    }
}
