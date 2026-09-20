<?php

namespace App\Services\Docker\Frameworks;

use App\Models\Project;
use App\Services\Docker\Contracts\FrameworkStrategyInterface;
use App\Services\Docker\Generators\Laravel\LaravelDockerfileGenerator;
use App\Services\Docker\Generators\Laravel\LaravelNginxGenerator;
use App\Services\Docker\Generators\Laravel\LaravelSupervisorGenerator;

class LaravelFrameworkStrategy implements FrameworkStrategyInterface
{
    public function __construct(
        private readonly LaravelDockerfileGenerator $dockerfileGenerator,
        private readonly LaravelNginxGenerator $nginxGenerator,
        private readonly LaravelSupervisorGenerator $supervisorGenerator
    ) {}

    public function generateDockerfile(Project $project, ?string $projectPath = null): string
    {
        return $this->dockerfileGenerator->generate($project, $projectPath);
    }

    public function generateNginxConfig(Project $project): array
    {
        return $this->nginxGenerator->generate($project);
    }

    public function generateSupervisorConfig(Project $project): string
    {
        return $this->supervisorGenerator->generate($project);
    }
}
