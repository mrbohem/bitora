<?php

namespace App\Jobs;

use App\Enums\OctaneServer;
use App\Events\DeploymentStatusUpdated;
use App\Models\Project;
use App\Services\ActivityLogService;
use App\Services\ContainerManagementService;
use App\Services\DockerComposeService;
use App\Services\DockerImageService;
use App\Services\GitService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DeployProjectJob implements ShouldQueue
{
    use Batchable, Queueable;

    public $timeout = 600; // 10 minutes

    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Project $project,
        public array $deploymentData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        GitService $gitService,
        DockerImageService $dockerImageService,
        DockerComposeService $dockerComposeService,
        ContainerManagementService $containerService,
        ActivityLogService $activityLogService
    ): void {
        try {
            $projectPath = storage_path("app/projects/{$this->project->slug}");

            // Step 1: Clone Repository
            if (! empty($this->project->git_repo)) {
                $this->updateStatus('cloning', 'Cloning repository...');
                $gitService->cloneRepository($this->project, $projectPath);
                $this->updateProgress(20);

                // Install dependencies
                $this->updateStatus('installing', 'Installing dependencies...');
                $this->installDependencies($projectPath);
                $this->updateProgress(40);

                // Setup environment
                $applicationPath = $this->getApplicationPath($projectPath);
                $this->setupEnvironment($applicationPath);
                $this->detectApplicationFeatures($applicationPath);

                // Cleanup SSH key if used
                if ($this->project->git_auth_type === 'ssh') {
                    $gitService->cleanupSshKey($this->project);
                }
            } else {
                File::ensureDirectoryExists($projectPath, 0755, true);
                $this->detectApplicationFeatures($projectPath);
                $this->updateProgress(40);
            }

            // Step 2: Generate Docker Files
            $this->updateStatus('generating', 'Generating Docker configuration...');
            $dockerImageService->generateDockerfile($this->project, $projectPath);
            $dockerImageService->generateNginxConfig($this->project, $projectPath);
            $dockerImageService->generateSupervisorConfig($this->project, $projectPath);
            $dockerComposeService->generateComposeFile($this->project, $projectPath);
            $this->updateProgress(60);

            // Step 3: Build Docker Image
            $this->updateStatus('building', 'Building Docker image...');
            $dockerImageService->buildImage($this->project, $projectPath);
            $this->updateProgress(80);

            // Step 4: Start Container
            $this->updateStatus('starting', 'Starting container...');
            $containerId = $containerService->startContainer($this->project, $projectPath);
            $this->updateProgress(100);

            // Step 5: Mark as Active
            $this->project->update([
                'container_id' => $containerId,
                'docker_network' => 'traefik-network',
                'status' => 'active',
                'deployment_error' => null,
                'deployed_at' => now(),
            ]);

            $activityLogService->log(
                $this->project,
                $this->project->user,
                'project.deployed',
                "Successfully deployed project: {$this->project->name}"
            );

            $this->updateStatus('active', 'Project deployed successfully!');
        } catch (\Exception $e) {
            $this->project->update([
                'status' => 'failed',
                'deployment_error' => $e->getMessage(),
            ]);

            $activityLogService->log(
                $this->project,
                $this->project->user,
                'project.failed',
                "Failed to deploy project: {$e->getMessage()}"
            );

            throw $e;
        }
    }

    /**
     * Update deployment status
     */
    private function updateStatus(string $status, string $message): void
    {
        $this->project->update([
            'status' => $status,
            'deployment_error' => null,
        ]);

        // Broadcast event for real-time updates
        broadcast(new DeploymentStatusUpdated(
            $this->project->id,
            $status,
            $message,
            $this->project->deployment_progress ?? 0
        ));
    }

    /**
     * Update deployment progress
     */
    private function updateProgress(int $progress): void
    {
        // Store progress in cache for retrieval
        cache()->put(
            "deployment.{$this->project->id}.progress",
            $progress,
            now()->addHour()
        );

        broadcast(new DeploymentStatusUpdated(
            $this->project->id,
            $this->project->status,
            null,
            $progress
        ));
    }

    /**
     * Install project dependencies
     */
    private function installDependencies(string $projectPath): void
    {
        // Dependencies will be installed during Docker build
    }

    /**
     * Setup project environment
     */
    private function setupEnvironment(string $projectPath): void
    {
        $envPath = "{$projectPath}/.env";
        File::ensureDirectoryExists(dirname($envPath), 0755, true);

        if (File::exists("{$projectPath}/.env.example") && ! File::exists($envPath)) {
            File::copy("{$projectPath}/.env.example", $envPath);
        }

        $envContent = File::exists($envPath) ? File::get($envPath) : '';

        // Normalize the deployed app identity so the generated cookie namespace stays unique.
        // The panel's application name remains Bitora; deployed app sources inherit a project-specific APP_NAME.
        $projectAppName = Str::slug($this->project->name ?: $this->project->slug ?: 'project');
        $appNamePattern = '/^APP_NAME=.*/m';
        $appNameReplacement = "APP_NAME={$projectAppName}";

        if (preg_match($appNamePattern, $envContent)) {
            $envContent = preg_replace($appNamePattern, $appNameReplacement, $envContent);
        } else {
            $envContent .= "\n{$appNameReplacement}";
        }

        // Update with user-provided variables
        if (! empty($this->deploymentData['env_variables'])) {
            foreach ($this->deploymentData['env_variables'] as $key => $value) {
                $pattern = "/^{$key}=.*/m";
                $replacement = "{$key}={$value}";

                if (preg_match($pattern, $envContent)) {
                    $envContent = preg_replace($pattern, $replacement, $envContent);
                } else {
                    $envContent .= "\n{$replacement}";
                }
            }
        }

        File::put($envPath, $envContent);
    }

    /**
     * Detect Laravel Octane from the application's committed dependencies and environment example.
     */
    private function detectApplicationFeatures(string $projectPath): void
    {
        $composerPath = "{$projectPath}/composer.json";
        $hasOctane = false;
        $hasReverb = false;

        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true, 512, JSON_THROW_ON_ERROR);
            $dependencies = $composer['require'] ?? [];
            $hasOctane = array_key_exists('laravel/octane', $dependencies);
            $hasReverb = array_key_exists('laravel/reverb', $dependencies);
        }

        $envExamplePath = "{$projectPath}/.env.example";
        $server = File::exists($envExamplePath)
            ? OctaneServer::fromEnvironment(File::get($envExamplePath))->value
            : OctaneServer::Swoole->value;

        $this->project->update([
            'octane_enabled' => $hasOctane,
            'octane_server' => $hasOctane ? $server : null,
            'reverb_enabled' => $hasReverb,
        ]);
        $this->project->refresh();
    }

    private function getApplicationPath(string $projectPath): string
    {
        $appPath = $this->project->app_path ?: '.';

        return $appPath === '.' ? $projectPath : $projectPath.DIRECTORY_SEPARATOR.$appPath;
    }
}
