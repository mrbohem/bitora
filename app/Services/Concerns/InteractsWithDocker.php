<?php

namespace App\Services\Concerns;

trait InteractsWithDocker
{
    protected function dockerCommand(string $command): string
    {
        return (config('app.docker_use_sudo', false) ? 'sudo ' : '').$command;
    }
}
