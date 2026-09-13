<?php

namespace App\Services\Docker\Octane;

use App\Enums\OctaneServer;

class SwooleStrategy implements OctaneServerStrategy
{
    public function server(): OctaneServer
    {
        return OctaneServer::Swoole;
    }

    public function baseImage(string $phpVersion): string
    {
        return "php:{$phpVersion}-fpm-alpine";
    }

    public function phpSetup(): string
    {
        return <<<'DOCKER'
# Install Swoole for Laravel Octane
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install swoole \
    && docker-php-ext-enable swoole

DOCKER;
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
