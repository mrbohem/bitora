<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Process;

class ContainerManagementService
{
    /**
     * Get docker command prefix (with or without sudo based on environment)
     * In production Docker containers, www-data user needs sudo
     * In local development, current user has direct Docker access
     */
    private function dockerCmd(string $command): string
    {
        $prefix = config('app.docker_use_sudo', false) ? 'sudo ' : '';

        return $prefix.$command;
    }

    /**
     * Start container using docker-compose
     */
    public function startContainer(Project $project, string $projectPath): string
    {
        $result = Process::timeout(120)
            ->path($projectPath)
            ->run($this->dockerCmd('docker-compose up -d'));

        if ($result->failed()) {
            throw new \RuntimeException("Failed to start container: {$result->errorOutput()}");
        }

        // Get container ID
        $containerId = $this->getContainerId($project);

        return $containerId;
    }

    /**
     * Stop container
     */
    public function stopContainer(Project $project, string $projectPath): void
    {
        $result = Process::timeout(60)
            ->path($projectPath)
            ->run($this->dockerCmd('docker-compose stop'));

        if ($result->failed()) {
            throw new \RuntimeException("Failed to stop container: {$result->errorOutput()}");
        }
    }

    /**
     * Restart container
     */
    public function restartContainer(Project $project, string $projectPath): void
    {
        $result = Process::timeout(120)
            ->path($projectPath)
            ->run($this->dockerCmd('docker-compose restart'));

        if ($result->failed()) {
            throw new \RuntimeException("Failed to restart container: {$result->errorOutput()}");
        }
    }

    /**
     * Remove container and cleanup
     */
    public function removeContainer(Project $project, string $projectPath): void
    {
        if (! is_dir($projectPath)) {
            $containerId = $project->container_id ?? $this->getContainerId($project);

            if (! $containerId) {
                return;
            }

            $result = Process::timeout(60)
                ->run($this->dockerCmd("docker rm -fv {$containerId}"));

            if ($result->failed()) {
                throw new \RuntimeException("Failed to remove container: {$result->errorOutput()}");
            }

            return;
        }

        $result = Process::timeout(60)
            ->path($projectPath)
            ->run($this->dockerCmd('docker-compose down -v'));

        if ($result->failed()) {
            throw new \RuntimeException("Failed to remove container: {$result->errorOutput()}");
        }
    }

    /**
     * Get container ID
     */
    public function getContainerId(Project $project): ?string
    {
        $containerName = "bitora-{$project->slug}-app";

        $result = Process::run($this->dockerCmd("docker ps -aq -f name={$containerName}"));

        if ($result->failed()) {
            return null;
        }

        return trim($result->output()) ?: null;
    }

    /**
     * Get container status
     */
    public function getContainerStatus(Project $project): array
    {
        $containerId = $project->container_id ?? $this->getContainerId($project);

        if (! $containerId) {
            return [
                'status' => 'not_found',
                'running' => false,
            ];
        }

        $result = Process::run($this->dockerCmd("docker inspect {$containerId} --format='{{.State.Status}}'"));

        if ($result->failed()) {
            return [
                'status' => 'error',
                'running' => false,
            ];
        }

        $status = trim($result->output());

        return [
            'status' => $status,
            'running' => $status === 'running',
        ];
    }

    /**
     * Get container logs
     */
    public function getContainerLogs(Project $project, int $lines = 100): string
    {
        $containerId = $project->container_id;

        if (! $containerId) {
            return '';
        }

        $result = Process::run($this->dockerCmd("docker logs --tail {$lines} {$containerId}"));

        return $result->output();
    }

    /**
     * Execute command inside container
     */
    public function execCommand(Project $project, string $command): string
    {
        $containerId = $project->container_id;

        if (! $containerId) {
            throw new \RuntimeException('Container not found');
        }

        $result = Process::timeout(300)->run($this->dockerCmd("docker exec {$containerId} {$command}"));

        if ($result->failed()) {
            throw new \RuntimeException("Command execution failed: {$result->errorOutput()}");
        }

        return $result->output();
    }

    /**
     * Get container resource usage stats
     */
    public function getContainerStats(Project $project): array
    {
        $containerId = $project->container_id;

        if (! $containerId) {
            return [];
        }

        $result = Process::run($this->dockerCmd("docker stats {$containerId} --no-stream --format='{{.CPUPerc}}|{{.MemUsage}}|{{.NetIO}}|{{.BlockIO}}'"));

        if ($result->failed()) {
            return [];
        }

        $stats = explode('|', trim($result->output()));

        return [
            'cpu_percent' => $stats[0] ?? '0%',
            'memory_usage' => $stats[1] ?? '0B / 0B',
            'network_io' => $stats[2] ?? '0B / 0B',
            'block_io' => $stats[3] ?? '0B / 0B',
        ];
    }

    /**
     * Check if Docker is installed and running
     */
    public function isDockerAvailable(): bool
    {
        $result = Process::run($this->dockerCmd('docker --version'));

        if ($result->failed()) {
            return false;
        }

        // Check if Docker daemon is running
        $daemonCheck = Process::run($this->dockerCmd('docker ps'));

        return $daemonCheck->successful();
    }

    /**
     * Check if docker-compose is installed
     */
    public function isDockerComposeAvailable(): bool
    {
        $result = Process::run($this->dockerCmd('docker-compose --version'));

        return $result->successful();
    }

    /**
     * Get the external port mapped to container port 80
     */
    public function getExternalPort(Project $project): ?int
    {
        $containerId = $project->container_id ?? $this->getContainerId($project);

        if (! $containerId) {
            return null;
        }

        $result = Process::run($this->dockerCmd("docker port {$containerId} 80"));

        if ($result->failed() || empty(trim($result->output()))) {
            return null;
        }

        // Output format: 0.0.0.0:61527 or :::61527
        $output = trim($result->output());
        if (preg_match('/:(\d+)$/', $output, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
