<?php

namespace App\Services\Docker\Octane;

use App\Enums\OctaneServer;

interface OctaneServerStrategy
{
    public function server(): OctaneServer;

    public function baseImage(string $phpVersion): string;

    public function phpSetup(): string;

    public function dependencySetup(): string;

    public function supervisorOptions(): string;
}
