<?php

namespace App\Enums;

enum OctaneServer: string
{
    case Swoole = 'swoole';
    case RoadRunner = 'roadrunner';
    case FrankenPHP = 'frankenphp';

    public static function fromEnvironment(string $environment): self
    {
        if (preg_match('/^OCTANE_SERVER\s*=\s*["\']?([A-Za-z]+)["\']?\s*$/mi', $environment, $matches)) {
            return self::tryFrom(strtolower($matches[1])) ?? self::Swoole;
        }

        return self::Swoole;
    }
}
