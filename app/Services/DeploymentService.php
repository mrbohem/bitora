<?php

namespace App\Services;

use App\Jobs\DeployProjectJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

class DeploymentService
{
    public function __construct(
        private ContainerManagementService $containerService,
        private ActivityLogService $activityLogService
    ) {}

    /**
     * Deploy a new Laravel project using Docker (via Queue)
     */
    public function deployProject(User $user, array $data): Project
    {
        // Validate Docker is available
        if (! $this->containerService->isDockerAvailable()) {
            throw new \RuntimeException('Docker is not installed or not running on this server.');
        }

        if (! $this->containerService->isDockerComposeAvailable()) {
            throw new \RuntimeException('Docker Compose is not installed on this server.');
        }

        $project = Project::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'git_repo' => $data['git_repo'] ?? null,
            'git_branch' => $data['git_branch'] ?? 'main',
            'git_auth_type' => $data['git_auth_type'] ?? 'none',
            'git_credentials' => $data['git_credentials'] ?? null,
            'app_path' => $this->normalizeAppPath($data['app_path'] ?? '.'),
            'php_version' => $data['php_version'] ?? '8.4',
            'domain' => $data['domain'] ?? null,
            'document_root' => $this->determineDocumentRoot($data),
            // Reverb is detected from the deployed application's dependencies.
            'reverb_enabled' => false,
            // Octane is detected from the deployed application's dependencies.
            'octane_enabled' => false,
            'octane_server' => null,
            'queue_enabled' => $data['queue_enabled'] ?? false,
            'queue_connection' => $data['queue_connection'] ?? 'redis',
            'queue_workers' => $data['queue_workers'] ?? 1,
            'status' => 'pending',
        ]);

        $this->activityLogService->log($project, $user, 'project.created', "Created project: {$project->name}");

        // Dispatch deployment job
        DeployProjectJob::dispatch($project, $data);

        return $project->fresh();
    }

    /**
     * Determine document root path
     */
    private function determineDocumentRoot(array $data): string
    {
        if (isset($data['document_root']) && ! empty($data['document_root'])) {
            return $data['document_root'];
        }

        return storage_path('app/projects/'.Str::slug($data['name']));
    }

    private function normalizeAppPath(string $appPath): string
    {
        $appPath = trim(str_replace('\\', '/', $appPath));

        if ($appPath === '' || $appPath === '.') {
            return '.';
        }

        if (str_starts_with($appPath, '/') || str_contains($appPath, "\0") || preg_match('~(^|/)\.\.(?:/|$)~', $appPath)) {
            throw new \InvalidArgumentException('Laravel folder path must be a relative path inside the repository.');
        }

        return trim($appPath, '/');
    }

    /**
     * Get project filesystem path
     */
    public function getProjectPath(Project $project): string
    {
        return $project->document_root ?? storage_path('app/projects/'.$project->slug);
    }
}
