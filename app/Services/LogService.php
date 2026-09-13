<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class LogService
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    /**
     * Get access logs for a project
     */
    public function getAccessLogs(Project $project, int $lines = 100): string
    {
        $logPath = $this->getAccessLogPath($project);

        if (! File::exists($logPath)) {
            return '';
        }

        return $this->tailFile($logPath, $lines);
    }

    /**
     * Get error logs for a project
     */
    public function getErrorLogs(Project $project, int $lines = 100): string
    {
        $logPath = $this->getErrorLogPath($project);

        if (! File::exists($logPath)) {
            return '';
        }

        return $this->tailFile($logPath, $lines);
    }

    /**
     * Download access log file
     */
    public function downloadAccessLog(Project $project): string
    {
        $logPath = $this->getAccessLogPath($project);

        if (! File::exists($logPath)) {
            throw new \RuntimeException('Access log file not found');
        }

        return $logPath;
    }

    /**
     * Download error log file
     */
    public function downloadErrorLog(Project $project): string
    {
        $logPath = $this->getErrorLogPath($project);

        if (! File::exists($logPath)) {
            throw new \RuntimeException('Error log file not found');
        }

        return $logPath;
    }

    /**
     * Clear access logs
     */
    public function clearAccessLogs(Project $project, User $user): void
    {
        $logPath = $this->getAccessLogPath($project);

        if (File::exists($logPath)) {
            File::put($logPath, '');
            $this->activityLogService->log($project, $user, 'logs.cleared', 'Cleared access logs');
        }
    }

    /**
     * Clear error logs
     */
    public function clearErrorLogs(Project $project, User $user): void
    {
        $logPath = $this->getErrorLogPath($project);

        if (File::exists($logPath)) {
            File::put($logPath, '');
            $this->activityLogService->log($project, $user, 'logs.cleared', 'Cleared error logs');
        }
    }

    /**
     * Get access log file path
     */
    private function getAccessLogPath(Project $project): string
    {
        return match ($project->web_server) {
            'nginx' => "/var/log/nginx/{$project->slug}-access.log",
            'apache' => "/var/log/apache2/{$project->slug}-access.log",
            default => throw new \InvalidArgumentException("Unsupported web server: {$project->web_server}"),
        };
    }

    /**
     * Get error log file path
     */
    private function getErrorLogPath(Project $project): string
    {
        return match ($project->web_server) {
            'nginx' => "/var/log/nginx/{$project->slug}-error.log",
            'apache' => "/var/log/apache2/{$project->slug}-error.log",
            default => throw new \InvalidArgumentException("Unsupported web server: {$project->web_server}"),
        };
    }

    /**
     * Tail last N lines from a file
     */
    private function tailFile(string $path, int $lines): string
    {
        $result = Process::run("tail -n {$lines} {$path}");

        return $result->successful() ? $result->output() : '';
    }
}
