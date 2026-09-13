<?php

use App\Models\Project;
use App\Models\User;
use App\Services\Docker\Frameworks\LaravelFrameworkStrategy;
use App\Services\Docker\Frameworks\NodeFrameworkStrategy;
use App\Services\Docker\FrameworkStrategyFactory;

beforeEach(function () {
    $this->factory = app(FrameworkStrategyFactory::class);
    $this->user = User::factory()->create();
});

test('resolves laravel framework strategy by default', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $strategy = $this->factory->make($project);

    expect($strategy)->toBeInstanceOf(LaravelFrameworkStrategy::class);
});

test('resolves node framework strategy when framework is node or nodejs', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'framework' => 'nodejs',
    ]);

    $strategy = $this->factory->make($project);

    expect($strategy)->toBeInstanceOf(NodeFrameworkStrategy::class);
});
