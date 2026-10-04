<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Manages cron job scheduling for deployed projects.
 *
 * Responsibility: sync project cron jobs to the system crontab.
 * Supervisor worker management lives in ProcessService.
 */
class CronJobService
{
    /**
     * Sync all enabled cron jobs for a project to the system crontab.
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
