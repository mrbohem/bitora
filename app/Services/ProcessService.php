<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SupervisorWorker;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ProcessService
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    /**
     * Create a new supervisor worker
     */
    public function createWorker(Project $project, User $user, array $data): SupervisorWorker
    {
        $worker = SupervisorWorker::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'command' => $data['command'],
            'processes' => $data['processes'] ?? 1,
            'status' => 'stopped',
        ]);

        $this->generateSupervisorConfig($worker);
        $this->reloadSupervisor();

        $this->activityLogService->log($project, $user, 'worker.created', "Created worker: {$worker->name}");

        return $worker;
    }

    /**
     * Start a supervisor worker
     */
    public function startWorker(SupervisorWorker $worker, User $user): void
    {
        $result = Process::run("supervisorctl start {$worker->name}:*");

        if ($result->successful()) {
            $worker->update([
                'status' => 'running',
                'started_at' => now(),
                'error_message' => null,
            ]);

            $this->activityLogService->log($worker->project, $user, 'worker.started', "Started worker: {$worker->name}");
        } else {
            $worker->update([
                'status' => 'failed',
                'error_message' => $result->errorOutput(),
            ]);

            throw new \RuntimeException("Failed to start worker: {$result->errorOutput()}");
        }
    }

    /**
     * Stop a supervisor worker
     */
    public function stopWorker(SupervisorWorker $worker, User $user): void
    {
        $result = Process::run("supervisorctl stop {$worker->name}:*");

        if ($result->successful()) {
            $worker->update([
                'status' => 'stopped',
            ]);

            $this->activityLogService->log($worker->project, $user, 'worker.stopped', "Stopped worker: {$worker->name}");
        } else {
            throw new \RuntimeException("Failed to stop worker: {$result->errorOutput()}");
        }
    }

    /**
     * Restart a supervisor worker
     */
    public function restartWorker(SupervisorWorker $worker, User $user): void
    {
        $result = Process::run("supervisorctl restart {$worker->name}:*");

        if ($result->successful()) {
            $worker->update([
                'status' => 'running',
                'started_at' => now(),
                'error_message' => null,
            ]);

            $this->activityLogService->log($worker->project, $user, 'worker.restarted', "Restarted worker: {$worker->name}");
        } else {
            $worker->update([
                'status' => 'failed',
                'error_message' => $result->errorOutput(),
            ]);

            throw new \RuntimeException("Failed to restart worker: {$result->errorOutput()}");
        }
    }

    /**
     * Delete a supervisor worker
     */
    public function deleteWorker(SupervisorWorker $worker, User $user): void
    {
        $this->stopWorker($worker, $user);

        $configPath = "/etc/supervisor/conf.d/{$worker->name}.conf";
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        $this->reloadSupervisor();

        $this->activityLogService->log($worker->project, $user, 'worker.deleted', "Deleted worker: {$worker->name}");

        $worker->delete();
    }

    /**
     * Get worker status from supervisor
     */
    public function getWorkerStatus(SupervisorWorker $worker): array
    {
        $result = Process::run("supervisorctl status {$worker->name}:*");

        return [
            'output' => $result->output(),
            'running' => $result->successful(),
        ];
    }

    /**
     * Generate supervisor configuration file
     */
    private function generateSupervisorConfig(SupervisorWorker $worker): void
    {
        $projectPath = app(DeploymentService::class)->getProjectPath($worker->project);

        $config = <<<SUPERVISOR
[program:{$worker->name}]
process_name=%(program_name)s_%(process_num)02d
command={$worker->command}
directory={$projectPath}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs={$worker->processes}
redirect_stderr=true
stdout_logfile=/var/log/supervisor/{$worker->name}.log
stopwaitsecs=3600
SUPERVISOR;

        $configPath = "/etc/supervisor/conf.d/{$worker->name}.conf";
        File::put($configPath, $config);
    }

    /**
     * Reload supervisor to pick up config changes
     */
    private function reloadSupervisor(): void
    {
        Process::run('supervisorctl reread');
        Process::run('supervisorctl update');
    }

    /**
     * Sync cron jobs to system crontab
     */
    public function syncCronJobs(Project $project): void
    {
        $cronJobs = $project->cronJobs()->where('enabled', true)->get();

        $crontabContent = "# Lumina Forge - {$project->name}\n";

        foreach ($cronJobs as $job) {
            $crontabContent .= "{$job->schedule} {$job->command}\n";
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'cron');
        File::put($tempFile, $crontabContent);

        // Note: In production, this would need proper user context
        Process::run("crontab {$tempFile}");

        File::delete($tempFile);
    }
}
