<?php

test('local start script must opt into local image build without registry push', function () {
    $startScript = file_get_contents(base_path('scripts/start-local.sh'));
    $buildScript = file_get_contents(base_path('scripts/build-image.sh'));

    expect($startScript)->toContain('./scripts/build-image.sh --local');
    expect($buildScript)->toContain('--local');
    expect($buildScript)->toContain('--load');
});

test('docker installs restart the panel after generating its runtime application key', function () {
    $installer = file_get_contents(base_path('install.sh'));
    $localStartScript = file_get_contents(base_path('scripts/start-local.sh'));
    $dockerfile = file_get_contents(base_path('Dockerfile.panel'));

    expect($installer)->toContain("php artisan key:generate --force\n\n# Refresh the queue worker so it loads the runtime application key.\ndocker restart bitora-panel")
        ->and($installer)->toContain('# Livewire\'s endpoint prefix is derived from APP_KEY')
        ->and($installer)->toContain('php artisan route:clear');
    expect($localStartScript)->toContain("php artisan key:generate --force\ndocker restart bitora-panel\n")
        ->and($localStartScript)->toContain('php artisan route:clear');
    expect($dockerfile)->not->toContain('php artisan route:cache');
});

test('the panel version defaults match the Docker image and build version', function () {
    $bitoraConfig = file_get_contents(config_path('bitora.php'));
    $dockerfile = file_get_contents(base_path('Dockerfile.panel'));
    $buildScript = file_get_contents(base_path('scripts/build-image.sh'));

    expect(config('bitora.version'))->toBe('0.1.1');
    expect($bitoraConfig)->toContain("'version' => env('BITORA_VERSION', '0.1.1')");
    expect($dockerfile)->toContain('ARG BITORA_VERSION=0.1.1');
    expect(file_get_contents(base_path('.env.example')))->toContain('BITORA_VERSION=0.1.1');
    expect($buildScript)->toContain('BUILD_VERSION="$(awk -F= \'$1 == "ARG BITORA_VERSION" { print $2; exit }\' Dockerfile.panel)"')
        ->and($buildScript)->not->toContain('BUILD_VERSION="0.1.0"');
});

test('vite config should not download remote bunny fonts during the build', function () {
    $viteConfig = file_get_contents(base_path('vite.config.js'));
    $headTemplate = file_get_contents(resource_path('views/partials/head.blade.php'));

    expect($viteConfig)->not->toContain('bunny(');
    expect($viteConfig)->not->toContain('laravel-vite-plugin/fonts');
    expect($headTemplate)->not->toContain('@fonts');
});
