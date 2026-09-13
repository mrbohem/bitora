<?php

namespace App\Services\Docker\Octane;

use App\Enums\OctaneServer;

class FrankenPhpStrategy implements OctaneServerStrategy
{
    public function server(): OctaneServer
    {
        return OctaneServer::FrankenPHP;
    }

    public function baseImage(string $phpVersion): string
    {
        return "dunglas/frankenphp:php{$phpVersion}-alpine";
    }

    public function phpSetup(): string
    {
        return '';
    }

    public function dependencySetup(): string
    {
        return '';
    }

    public function supervisorOptions(): string
    {
        return '';
    }
}
