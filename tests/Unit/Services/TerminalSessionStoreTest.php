<?php

use App\Models\Project;
use App\Models\User;
use App\Services\Terminal\TerminalSessionStore;

it('creates and deletes an isolated terminal session', function () {
    $basePath = storage_path('framework/testing/terminal');
    $store = new TerminalSessionStore($basePath);
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $session = null;

    try {
        $session = $store->create($project);

        expect($session->token)->toHaveLength(64)
            ->and(is_dir($session->directory))->toBeTrue()
            ->and(is_file($session->inputPath()))->toBeTrue()
            ->and(is_file($session->outputPath()))->toBeTrue();
    } finally {
        if ($session) {
            $store->delete($session);
        }
    }

    expect(is_dir($session->directory))->toBeFalse();
});
