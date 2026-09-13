<?php

namespace App\Services;

use App\Models\Project;
use App\Services\Docker\FrameworkStrategyFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class DockerImageService
{
    public function __construct(
        private readonly FrameworkStrategyFactory $strategyFactory
    ) {}

    /**
     * Generate Dockerfile for the project.
     */
    public function generateDockerfile(Project $project, string $projectPath): string
    {
        $dockerfilePath = "{$projectPath}/Dockerfile";
        $strategy = $this->strategyFactory->make($project);

        $dockerfileContent = $strategy->generateDockerfile($project);
        File::put($dockerfilePath, $dockerfileContent);

        return $dockerfilePath;
    }

    /**
     * Generate Nginx configuration files for the project.
     */
    public function generateNginxConfig(Project $project, string $projectPath): void
    {
        $configDir = "{$projectPath}/docker";
        File::ensureDirectoryExists($configDir, 0755, true);

        $strategy = $this->strategyFactory->make($project);
        $configs = $strategy->generateNginxConfig($project);

        File::put("{$configDir}/nginx.conf", $configs['main']);
        File::put("{$configDir}/default.conf", $configs['site']);
    }

    /**
     * Generate Supervisor configuration for the project.
     */
    public function generateSupervisorConfig(Project $project, string $projectPath): void
    {
        $configDir = "{$projectPath}/docker";
        File::ensureDirectoryExists($configDir, 0755, true);

        $strategy = $this->strategyFactory->make($project);
        $supervisorConf = $strategy->generateSupervisorConfig($project);

        File::put("{$configDir}/supervisord.conf", $supervisorConf);
    }

    /**
     * Build Docker image.
     */
    public function buildImage(Project $project, string $projectPath): void
    {
        $imageName = "bitora-{$project->slug}";
        $imageTag = 'latest';

        $result = Process::forever()
            ->path($projectPath)
            ->run("docker build -t {$imageName}:{$imageTag} .");

        if ($result->failed()) {
            throw new \RuntimeException("Failed to build Docker image: {$result->errorOutput()}");
        }
    }
}
