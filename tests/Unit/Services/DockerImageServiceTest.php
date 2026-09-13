<?php

use App\Models\Project;
use App\Models\User;
use App\Services\DockerImageService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->service = app(DockerImageService::class);
    $this->user = User::factory()->create();
});

test('generates dockerfile with correct php version', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'php_version' => '8.3',
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $dockerfilePath = $this->service->generateDockerfile($project, $projectPath);

    expect(File::exists($dockerfilePath))->toBeTrue();

    $content = File::get($dockerfilePath);
    expect($content)->toContain('FROM php:8.3-fpm-alpine');

    File::deleteDirectory($projectPath);
});

test('includes swoole extension when octane with swoole is enabled', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'octane_enabled' => true,
        'octane_server' => 'swoole',
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $dockerfilePath = $this->service->generateDockerfile($project, $projectPath);
    $content = File::get($dockerfilePath);

    expect($content)->toContain('pecl install swoole');
    expect($content)->toContain('docker-php-ext-enable swoole');
    expect($content)->toContain('brotli-dev');
    expect($content)->toContain('openssl-dev');
    expect($content)->toContain('php artisan key:generate --force');
    expect($content)->toContain('touch database/database.sqlite');
    expect($content)->toContain('php artisan migrate --force');
    expect($content)->not->toContain('composer require laravel/octane');
    expect($content)->toContain('php artisan octane:install --server=swoole --no-interaction');

    File::deleteDirectory($projectPath);
});

test('configures reverb credentials when reverb is enabled', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'reverb_enabled' => true,
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $dockerfilePath = $this->service->generateDockerfile($project, $projectPath);
    $content = File::get($dockerfilePath);

    expect($content)->toContain('REVERB_APP_ID=local');
    expect($content)->toContain('BROADCAST_CONNECTION=reverb');
    expect($content)->toContain('REVERB_APP_KEY=local');
    expect($content)->toContain('REVERB_APP_SECRET=local');
    expect($content)->toContain('REVERB_HOST=127.0.0.1');
    expect($content)->toContain('REVERB_PORT=8080');
    expect($content)->toContain('VITE_REVERB_APP_KEY=local');
    expect($content)->toContain('VITE_REVERB_HOST=127.0.0.1');
    expect($content)->toContain('VITE_REVERB_PORT=8080');

    File::deleteDirectory($projectPath);
});

test('does not inject session cookie or session domain overrides into generated deployment images', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Demo App',
        'slug' => 'demo-app',
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $dockerfilePath = $this->service->generateDockerfile($project, $projectPath);
    $content = File::get($dockerfilePath);

    expect($content)->not->toContain('SESSION_COOKIE=');
    expect($content)->not->toContain('SESSION_DOMAIN=');

    File::deleteDirectory($projectPath);
});

test('includes redis extension when queue is enabled', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'queue_enabled' => true,
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $dockerfilePath = $this->service->generateDockerfile($project, $projectPath);
    $content = File::get($dockerfilePath);

    expect($content)->toContain('pecl install redis');
    expect($content)->toContain('docker-php-ext-enable redis');

    File::deleteDirectory($projectPath);
});

test('generates nginx config for standard php-fpm', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'octane_enabled' => false,
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $this->service->generateNginxConfig($project, $projectPath);

    $configPath = "{$projectPath}/docker/default.conf";
    expect(File::exists($configPath))->toBeTrue();

    $content = File::get($configPath);
    expect($content)->toContain('fastcgi_pass 127.0.0.1:9000');
    expect($content)->toContain('root /var/www/html/public');

    File::deleteDirectory($projectPath);
});

test('generates nginx config for octane proxy', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'octane_enabled' => true,
        'reverb_enabled' => true,
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $this->service->generateNginxConfig($project, $projectPath);

    $configPath = "{$projectPath}/docker/default.conf";
    $content = File::get($configPath);

    expect($content)->toContain('proxy_pass http://127.0.0.1:8000');
    expect($content)->toContain('proxy_pass http://127.0.0.1:8080');
    expect($content)->toContain('location ^~ /app/');
    expect($content)->not->toContain('fastcgi_pass');
    expect($content)->toContain('root /var/www/html/public');
    expect($content)->toContain('try_files $uri @octane;');
    expect($content)->toContain('location ~ \.php$ {');
    expect($content)->toContain('location @octane {');
    expect($content)->toContain('proxy_set_header Connection "";');
    expect($content)->toContain('proxy_set_header X-Real-IP');
    expect($content)->toContain('proxy_set_header X-Forwarded-For');

    File::deleteDirectory($projectPath);
});

test('supervisor config includes queue workers when enabled', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'queue_enabled' => true,
        'queue_connection' => 'redis',
        'queue_workers' => 3,
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $this->service->generateSupervisorConfig($project, $projectPath);

    $configPath = "{$projectPath}/docker/supervisord.conf";
    $content = File::get($configPath);

    expect($content)->toContain('[program:laravel-worker]');
    expect($content)->toContain('queue:work redis');
    expect($content)->toContain('numprocs=3');

    File::deleteDirectory($projectPath);
});

test('supervisor config includes reverb when enabled', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'reverb_enabled' => true,
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $this->service->generateSupervisorConfig($project, $projectPath);

    $configPath = "{$projectPath}/docker/supervisord.conf";
    $content = File::get($configPath);

    expect($content)->toContain('[program:laravel-reverb]');
    expect($content)->toContain('reverb:start');
    expect($content)->toContain('[unix_http_server]');
    expect($content)->toContain('serverurl=unix:///var/run/supervisor.sock');
    expect($content)->toContain('[rpcinterface:supervisor]');

    File::deleteDirectory($projectPath);
});

test('supervisor config includes octane when enabled', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'octane_enabled' => true,
        'octane_server' => 'swoole',
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $this->service->generateSupervisorConfig($project, $projectPath);

    $configPath = "{$projectPath}/docker/supervisord.conf";
    $content = File::get($configPath);

    expect($content)->toContain('[program:laravel-octane]');
    expect($content)->toContain('octane:start --server=swoole');
    // Octane binds on localhost only — Nginx proxies to it
    expect($content)->toContain('--host=127.0.0.1');
    // Worker recycling prevents Swoole memory leaks
    expect($content)->toContain('--max-requests=500');
    // php-fpm must NOT run alongside Octane
    expect($content)->not->toContain('[program:php-fpm]');

    File::deleteDirectory($projectPath);
});

test('generates RoadRunner installation and supervisor settings from project configuration', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'octane_enabled' => true,
        'octane_server' => 'roadrunner',
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $dockerfilePath = $this->service->generateDockerfile($project, $projectPath);
    $dockerfile = File::get($dockerfilePath);
    $this->service->generateSupervisorConfig($project, $projectPath);
    $supervisor = File::get("{$projectPath}/docker/supervisord.conf");

    expect($dockerfile)->toContain('php artisan octane:install --server=roadrunner --no-interaction');
    expect($dockerfile)->toContain('composer require spiral/roadrunner-cli spiral/roadrunner-http');
    expect($dockerfile)->toContain('vendor/bin/rr get-binary');
    expect($supervisor)->toContain('octane:start --server=roadrunner');
    expect($supervisor)->toContain('--rpc-port=6001');

    File::deleteDirectory($projectPath);
});

test('generates FrankenPHP image and supervisor settings from project configuration', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'php_version' => '8.4',
        'octane_enabled' => true,
        'octane_server' => 'frankenphp',
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $dockerfilePath = $this->service->generateDockerfile($project, $projectPath);
    $dockerfile = File::get($dockerfilePath);
    $this->service->generateSupervisorConfig($project, $projectPath);
    $supervisor = File::get("{$projectPath}/docker/supervisord.conf");

    expect($dockerfile)->toContain('FROM dunglas/frankenphp:php8.4-alpine');
    expect($dockerfile)->toContain('php artisan octane:install --server=frankenphp --no-interaction');
    expect($dockerfile)->not->toContain('pecl install swoole');
    expect($supervisor)->toContain('octane:start --server=frankenphp');
    expect($supervisor)->not->toContain('--rpc-port=6001');

    File::deleteDirectory($projectPath);
});
