<?php

namespace App\Actions;

use App\Models\Project;
use App\Services\ContainerManagementService;
use App\Services\DeploymentService;
use Illuminate\Support\Facades\File;

class DeleteProject
{
    public function __construct(
        private ContainerManagementService $containerService,
        private DeploymentService $deploymentService,
    ) {}

    public function handle(Project $project): void
    {
        $projectPath = $this->deploymentService->getProjectPath($project);

        $this->containerService->removeContainer($project, $projectPath);

        if (File::isDirectory($projectPath)) {
            File::deleteDirectory($projectPath);
        }

        File::delete(storage_path("app/ssh-keys/{$project->slug}.key"));

        $project->delete();
    }
}
