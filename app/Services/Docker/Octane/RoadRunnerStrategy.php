<?php

namespace App\Services\Docker\Octane;

use App\Enums\OctaneServer;

class RoadRunnerStrategy implements OctaneServerStrategy
{
    public function server(): OctaneServer
    {
        return OctaneServer::RoadRunner;
    }

    public function baseImage(string $phpVersion): string
    {
        return "php:{$phpVersion}-fpm-alpine";
    }

    public function phpSetup(): string
    {
        return '';
    }

    public function dependencySetup(): string
    {
        return <<<'DOCKER'
# Install RoadRunner dependencies and download the Linux binary in the image
RUN if [ ! -x vendor/bin/rr ]; then \
        composer require spiral/roadrunner-cli spiral/roadrunner-http --no-interaction; \
    fi \
    && vendor/bin/rr get-binary \
    && chmod +x rr

DOCKER;
    }

    public function supervisorOptions(): string
    {
        return ' --rpc-port=6001';
    }
}
