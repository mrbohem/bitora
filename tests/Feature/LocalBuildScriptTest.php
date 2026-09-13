<?php

test('local start script must opt into local image build without registry push', function () {
    $startScript = file_get_contents(base_path('scripts/start-local.sh'));
    $buildScript = file_get_contents(base_path('scripts/build-image.sh'));

    expect($startScript)->toContain('./scripts/build-image.sh --local');
    expect($buildScript)->toContain('--local');
    expect($buildScript)->toContain('--load');
});

test('vite config should not download remote bunny fonts during the build', function () {
    $viteConfig = file_get_contents(base_path('vite.config.js'));
    $headTemplate = file_get_contents(resource_path('views/partials/head.blade.php'));

    expect($viteConfig)->not->toContain('bunny(');
    expect($viteConfig)->not->toContain('laravel-vite-plugin/fonts');
    expect($headTemplate)->not->toContain('@fonts');
});
