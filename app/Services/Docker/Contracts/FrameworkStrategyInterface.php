<?php

namespace App\Services\Docker\Contracts;

use App\Models\Project;

interface FrameworkStrategyInterface
{
    /**
     * Generate Dockerfile content for the project.
     */
    public function generateDockerfile(Project $project): string;

    /**
     * Generate Nginx configuration files for the project.
     *
     * @return array{main: string, site: string}
     */
    public function generateNginxConfig(Project $project): array;

    /**
     * Generate Supervisor configuration content for the project.
     */
    public function generateSupervisorConfig(Project $project): string;
}
