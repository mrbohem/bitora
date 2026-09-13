<?php

namespace App\Services\Docker\Generators\Laravel;

use App\Models\Project;
use App\Services\Docker\Octane\OctaneServerStrategyFactory;

class LaravelSupervisorGenerator
{
    public function __construct(
        private readonly OctaneServerStrategyFactory $octaneStrategies
    ) {}

    /**
     * Generate Supervisor configuration for Laravel.
     */
    public function generate(Project $project): string
    {
        // When Octane is enabled, php-fpm is not needed — Octane is its own HTTP server.
        $phpFpmBlock = $project->octane_enabled ? '' : <<<'SUPERVISOR'
[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

SUPERVISOR;

        $config = <<<'SUPERVISOR'
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[unix_http_server]
file=/var/run/supervisor.sock

[supervisorctl]
serverurl=unix:///var/run/supervisor.sock

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

SUPERVISOR;

        $config .= $phpFpmBlock;

        $config .= <<<'SUPERVISOR'
[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

SUPERVISOR;

        // Add Laravel Scheduler
        $config .= <<<'SUPERVISOR'
[program:laravel-scheduler]
command=php /var/www/html/artisan schedule:work
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

SUPERVISOR;

        // Add Queue Workers if enabled
        if ($project->queue_enabled) {
            $connection = $project->queue_connection ?? 'redis';
            $workers = $project->queue_workers ?? 1;

            $config .= <<<SUPERVISOR

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work {$connection} --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs={$workers}
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

SUPERVISOR;
        }

        // Add Reverb if enabled
        if ($project->reverb_enabled) {
            $config .= <<<'SUPERVISOR'

[program:laravel-reverb]
command=php /var/www/html/artisan reverb:start
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

SUPERVISOR;
        }

        // Add Octane if enabled
        if ($project->octane_enabled) {
            $octaneStrategy = $this->octaneStrategies->for($project->octane_server);
            $server = $octaneStrategy->server()->value;
            $serverOptions = $octaneStrategy->supervisorOptions();
            $config .= <<<SUPERVISOR

[program:laravel-octane]
command=php /var/www/html/artisan octane:start --server={$server} --host=127.0.0.1{$serverOptions} --port=8000 --workers=auto --max-requests=500
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

SUPERVISOR;
        }

        return $config;
    }
}
