<?php

namespace App\Services\Docker\Octane;

use App\Enums\OctaneServer;

class OctaneServerStrategyFactory
{
    public function for(?string $server): OctaneServerStrategy
    {
        return match (OctaneServer::tryFrom($server ?? '') ?? OctaneServer::Swoole) {
            OctaneServer::Swoole => new SwooleStrategy,
            OctaneServer::RoadRunner => new RoadRunnerStrategy,
            OctaneServer::FrankenPHP => new FrankenPhpStrategy,
        };
    }
}
