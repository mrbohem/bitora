<?php

use App\Models\Project;
use App\Models\User;
use App\Services\DockerComposeService;
use App\Services\DockerImageService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->service = app(DockerComposeService::class);
    $this->user = User::factory()->create();
});

test('generated project network supports ipv4 and ipv6', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $composePath = $this->service->generateComposeFile($project, $projectPath);
    $content = File::get($composePath);

    expect($content)
        ->toContain("default:\n    driver: bridge\n    enable_ipv6: true")
        ->toContain('traefik-network:');

    File::deleteDirectory($projectPath);
});

test('dockerfile copies only the configured application path', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'app_path' => 'apps/laravel',
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $dockerfilePath = app(DockerImageService::class)->generateDockerfile($project, $projectPath);

    expect(File::get($dockerfilePath))->toContain('COPY apps/laravel/ .');

    File::deleteDirectory($projectPath);
});

test('dockerfile installs the sockets extension for composer dependencies', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $dockerfilePath = app(DockerImageService::class)->generateDockerfile($project, $projectPath);

    expect(File::get($dockerfilePath))
        ->toContain('linux-headers')
        ->toContain('docker-php-ext-install pdo pdo_mysql pdo_pgsql pdo_sqlite mbstring exif pcntl bcmath gd zip sockets');

    File::deleteDirectory($projectPath);
});

test('dockerfile builds frontend assets when a package build script exists', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $projectPath = storage_path('app/test-project');
    File::ensureDirectoryExists($projectPath);

    $dockerfilePath = app(DockerImageService::class)->generateDockerfile($project, $projectPath);

    expect(File::get($dockerfilePath))
        ->toContain('nodejs')
        ->toContain('npm')
        ->toContain('if [ -f package-lock.json ]; then npm ci; else npm install; fi')
        ->toContain('&& npm run build');

    File::deleteDirectory($projectPath);
});

test('generated docker compose includes required traefik network and routing labels', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'slug' => 'my-awesome-app',
        'domain' => 'https://example.com/',
    ]);

    $projectPath = storage_path('app/test-project-labels');
    File::ensureDirectoryExists($projectPath);

    $composePath = $this->service->generateComposeFile($project, $projectPath);
    $content = File::get($composePath);

    expect($content)
        ->toContain('traefik.docker.network: "traefik-network"')
        ->toContain('traefik.http.routers.bitora-my-awesome-app.service: "bitora-my-awesome-app"')
        ->toContain('traefik.http.services.bitora-my-awesome-app.loadbalancer.server.port: 80')
        ->toContain('traefik.http.routers.bitora-my-awesome-app.rule: "Host(`example.com`)"')
        ->toContain('traefik.http.routers.bitora-my-awesome-app.entrypoints: "web,websecure"');

    File::deleteDirectory($projectPath);
});

test('generated docker compose includes configured resource limits', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'memory_limit_mb' => 512,
        'storage_limit_gb' => 10,
    ]);

    $projectPath = storage_path('app/test-project-resource-limits');
    File::ensureDirectoryExists($projectPath);

    $composePath = $this->service->generateComposeFile($project, $projectPath);
    $content = File::get($composePath);

    expect($content)
        ->toContain('mem_limit: 512m')
        ->toContain('memswap_limit: 512m')
        ->toContain("storage_opt:\n      size: 10G");

    File::deleteDirectory($projectPath);
});

test('generated docker compose keeps resource limits unset by default', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $projectPath = storage_path('app/test-project-default-resources');
    File::ensureDirectoryExists($projectPath);

    $composePath = $this->service->generateComposeFile($project, $projectPath);
    $content = File::get($composePath);

    expect($content)
        ->not->toContain('mem_limit:')
        ->not->toContain('memswap_limit:')
        ->not->toContain('storage_opt:');

    File::deleteDirectory($projectPath);
});
