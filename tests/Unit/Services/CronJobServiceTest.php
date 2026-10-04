<?php

use App\Models\CronJob;
use App\Models\Project;
use App\Services\CronJobService;
use Illuminate\Support\Facades\Process;

test('syncCronJobs writes enabled cron jobs and runs crontab command', function () {
    Process::fake([
        'crontab *' => Process::result(),
    ]);

    $project = Project::factory()->create([
        'name' => 'Demo Project',
    ]);

    CronJob::create([
        'project_id' => $project->id,
        'schedule' => '* * * * *',
        'command' => 'php artisan schedule:run',
        'enabled' => true,
    ]);

    CronJob::create([
        'project_id' => $project->id,
        'schedule' => '0 0 * * *',
        'command' => 'php artisan backup:clean',
        'enabled' => false,
    ]);

    $service = new CronJobService;
    $service->syncCronJobs($project);

    Process::assertRan(function ($process) {
        return str_starts_with($process->command, 'crontab ');
    });
});
