<?php

namespace App\Services\Docker;

use App\Models\Project;
use App\Services\Docker\Contracts\FrameworkStrategyInterface;
use App\Services\Docker\Frameworks\LaravelFrameworkStrategy;
use App\Services\Docker\Frameworks\NodeFrameworkStrategy;

class FrameworkStrategyFactory
{
    public function __construct(
        private readonly LaravelFrameworkStrategy $laravelStrategy,
        private readonly NodeFrameworkStrategy $nodeStrategy
    ) {}

    /**
     * Resolve the framework strategy for the given project.
     */
    public function make(Project $project): FrameworkStrategyInterface
    {
        $framework = strtolower($project->framework ?? 'laravel');

        return match ($framework) {
            'node', 'nodejs' => $this->nodeStrategy,
            default => $this->laravelStrategy,
        };
    }
}
