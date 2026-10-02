<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;

class ProjectSettingsService
{
    public function __construct(
        private readonly DockerComposeService $composeService,
        private readonly DeploymentService $deploymentService,
        private readonly ProjectContainerService $projectContainerService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public function update(Project $project, User $user, array $settings): Project
    {
        $settings['queue_connection'] = $settings['queue_connection'] ?: 'redis';

        $changes = [];

        foreach ($settings as $key => $value) {
            $currentValue = $project->getAttribute($key);

            if ($key === 'queue_connection' && blank($currentValue)) {
                $currentValue = 'redis';
            }

            if ($currentValue !== $value) {
                $changes[$key] = $value;
            }
        }

        if ($changes === []) {
            if ($project->status === 'active') {
                return $this->projectContainerService->syncIdentity($project);
            }

            return $project->fresh();
        }

        $project->update($changes);

        $projectPath = $this->deploymentService->getProjectPath($project);

        if ($project->status === 'active' && is_dir($projectPath)) {
            $project = $this->projectContainerService->rebuild($project);
        } elseif (is_dir($projectPath)) {
            $this->composeService->generateComposeFile($project, $projectPath);
        }

        $this->activityLogService->log(
            $project,
            $user,
            'project.settings_updated',
            "Updated project settings: {$project->name}",
            ['settings' => array_keys($changes)],
        );

        return $project->fresh();
    }
}
