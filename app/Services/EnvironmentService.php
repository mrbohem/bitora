<?php

namespace App\Services;

use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\File;

class EnvironmentService
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private DeploymentService $deploymentService,
        private ContainerManagementService $containerService
    ) {}

    /**
     * Read environment variables from live .env file
     */
    public function readLiveEnv(Project $project): array
    {
        if (! $project->container_id) {
            return $this->readLiveEnvFromHost($project);
        }

        try {
            $output = $this->containerService->execCommand($project, 'cat /var/www/html/.env');

            return $this->parseEnvContent($output);
        } catch (\Exception $e) {
            // Fallback to host file if container command fails
            return $this->readLiveEnvFromHost($project);
        }
    }

    /**
     * Read .env from host filesystem (fallback)
     */
    private function readLiveEnvFromHost(Project $project): array
    {
        $envPath = $this->getEnvPath($project);

        if (! File::exists($envPath)) {
            return [];
        }

        $content = File::get($envPath);

        return $this->parseEnvContent($content);
    }

    /**
     * Parse .env file content
     */
    private function parseEnvContent(string $content): array
    {
        $lines = explode("\n", $content);
        $variables = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $variables[trim($key)] = trim($value);
            }
        }

        return $variables;
    }

    /**
     * Update or create environment variable
     */
    public function setVariable(Project $project, User $user, string $key, string $value): EnvironmentVariable
    {
        $variable = EnvironmentVariable::updateOrCreate(
            ['project_id' => $project->id, 'key' => $key],
            ['value' => $value]
        );

        $this->syncToEnvFile($project);

        $this->activityLogService->log(
            $project,
            $user,
            'env.updated',
            "Updated environment variable: {$key}"
        );

        return $variable;
    }

    /**
     * Delete environment variable
     */
    public function deleteVariable(EnvironmentVariable $variable, User $user): void
    {
        $project = $variable->project;
        $key = $variable->key;

        $variable->delete();

        $this->syncToEnvFile($project);

        $this->activityLogService->log(
            $project,
            $user,
            'env.deleted',
            "Deleted environment variable: {$key}"
        );
    }

    /**
     * Sync database variables to .env file
     */
    public function syncToEnvFile(Project $project): void
    {
        if (! $project->container_id) {
            $this->syncToEnvFileOnHost($project);

            return;
        }

        try {
            // Read current .env from container
            $existingContent = $this->containerService->execCommand($project, 'cat /var/www/html/.env');

            $newContent = $this->buildEnvContent($project, $existingContent);

            // Write to container using heredoc
            $escapedContent = str_replace("'", "'\\''", $newContent);
            $this->containerService->execCommand($project, "sh -c 'cat > /var/www/html/.env << \"ENVEOF\"\n{$newContent}\nENVEOF'");
        } catch (\Exception $e) {
            // Fallback to host file
            $this->syncToEnvFileOnHost($project);
        }
    }

    /**
     * Sync to .env file on host (fallback)
     */
    private function syncToEnvFileOnHost(Project $project): void
    {
        $envPath = $this->getEnvPath($project);

        // Read current .env to preserve comments and structure
        $existingContent = File::exists($envPath) ? File::get($envPath) : '';

        $content = $this->buildEnvContent($project, $existingContent);

        // Backup before writing
        if (File::exists($envPath)) {
            File::copy($envPath, $envPath.'.backup');
        }

        File::put($envPath, $content);
    }

    /**
     * Build new .env content
     */
    private function buildEnvContent(Project $project, string $existingContent): string
    {
        $lines = explode("\n", $existingContent);

        $variables = $project->environmentVariables()->get()->pluck('value', 'key')->toArray();
        $processedKeys = [];
        $newLines = [];

        // Update existing variables or remove deleted ones
        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Keep comments and empty lines
            if (empty($trimmed) || str_starts_with($trimmed, '#')) {
                $newLines[] = $line;

                continue;
            }

            if (str_contains($trimmed, '=')) {
                [$key] = explode('=', $trimmed, 2);
                $key = trim($key);

                // Only keep if exists in database
                if (isset($variables[$key])) {
                    $newLines[] = "{$key}={$variables[$key]}";
                    $processedKeys[] = $key;
                }
                // Skip deleted variables
            } else {
                $newLines[] = $line;
            }
        }

        // Add new variables
        foreach ($variables as $key => $value) {
            if (! in_array($key, $processedKeys)) {
                $newLines[] = "{$key}={$value}";
            }
        }

        return implode("\n", $newLines);
    }

    /**
     * Get .env file path for project
     */
    private function getEnvPath(Project $project): string
    {
        $projectPath = $this->deploymentService->getProjectPath($project);
        $appPath = $project->app_path ?: '.';

        return ($appPath === '.' ? $projectPath : $projectPath.'/'.$appPath).'/.env';
    }
}
