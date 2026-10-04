<?php

use App\Services\PanelUpdateService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

it('selects the newest semantic version tag and ignores mutable image tags', function () {
    $url = 'https://hub.docker.com/v2/repositories/mrbohem7/bitora/tags?page_size=100';
    config(['bitora.dockerhub_tags_url' => $url]);

    Http::preventStrayRequests();
    Http::fake([
        $url => Http::response([
            'results' => [
                ['name' => 'latest'],
                ['name' => 'v0.2.0'],
                ['name' => 'v0.3.0-rc.1'],
                ['name' => 'v0.3.0'],
                ['name' => 'nightly'],
            ],
        ]),
    ]);

    $release = (new PanelUpdateService)->latestRelease();

    expect($release)->toBe([
        'tag' => 'v0.3.0',
        'version' => '0.3.0',
    ]);
});

it('offers an update only when a newer release exists', function () {
    config(['bitora.version' => '0.2.0']);

    $service = new PanelUpdateService;

    expect($service->updateAvailable(['tag' => 'v0.3.0', 'version' => '0.3.0']))->toBeTrue();
    expect($service->updateAvailable(['tag' => 'v0.2.0', 'version' => '0.2.0']))->toBeFalse();
    expect($service->updateAvailable(null))->toBeFalse();
});

it('replaces only the panel container and preserves the project deployment boundary', function () {
    $storagePath = sys_get_temp_dir().'/bitora-panel-update-'.Str::uuid();
    $persistentPath = "{$storagePath}/app/bitora";
    File::ensureDirectoryExists($persistentPath);

    $previousStoragePath = app()->storagePath();
    app()->useStoragePath($storagePath);
    config([
        'app.docker_use_sudo' => false,
        'bitora.image_repository' => 'mrbohem7/bitora',
        'bitora.update_status_path' => "{$persistentPath}/update-status.json",
    ]);
    File::put("{$persistentPath}/update-metadata.json", json_encode([
        'database_driver' => 'sqlite',
        'database_path' => '/var/www/html/storage/app/bitora/panel.sqlite',
        'database_rollback_path' => '/var/www/html/storage/app/bitora/panel.sqlite.rollback',
    ], JSON_THROW_ON_ERROR));
    File::put("{$persistentPath}/panel.sqlite.rollback", 'previous database snapshot');

    $container = [
        'Image' => 'sha256:old-panel-image',
        'Mounts' => [[
            'Type' => 'bind',
            'Source' => $storagePath,
            'Destination' => '/var/www/html/storage',
            'RW' => true,
        ]],
        'HostConfig' => [
            'RestartPolicy' => ['Name' => 'unless-stopped'],
            'PortBindings' => ['80/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '8080']]],
        ],
        'NetworkSettings' => ['Networks' => ['traefik-network' => ['Aliases' => ['bitora-panel']]]],
        'Config' => ['Env' => ['DOCKER_API_VERSION=1.40'], 'Labels' => []],
    ];

    Process::fake(function (PendingProcess $process) use ($container) {
        $command = $process->command;

        if ($command === ['docker', 'inspect', 'bitora-panel']) {
            return Process::result(output: json_encode([$container], JSON_THROW_ON_ERROR));
        }

        if (is_array($command) && array_slice($command, 0, 3) === ['docker', 'inspect', '--format']) {
            return 'running';
        }

        if (is_array($command) && array_slice($command, 0, 4) === ['docker', 'exec', 'bitora-panel', 'curl']) {
            return 'healthy';
        }

        return Process::result();
    });

    try {
        (new PanelUpdateService)->applyUpdate('mrbohem7/bitora:v0.3.0');

        Process::assertRan(function (PendingProcess $process): bool {
            $command = implode(' ', (array) $process->command);

            return str_contains($command, 'docker run --detach --name bitora-panel')
                && str_contains($command, 'mrbohem7/bitora:v0.3.0')
                && str_contains($command, 'DB_DATABASE=/var/www/html/storage/app/bitora/panel.sqlite');
        });
        Process::assertNotRan(function (PendingProcess $process): bool {
            $command = implode(' ', (array) $process->command);

            return preg_match('/\bdocker\s+(?:stop|rm|restart|kill)\b/', $command) === 1
                && ! str_contains($command, 'bitora-panel');
        });

        expect((new PanelUpdateService)->status()['status'])->toBe('completed');
    } finally {
        File::deleteDirectory($storagePath);
        app()->useStoragePath($previousStoragePath);
    }
});

it('restores the previous database and panel container when the replacement fails health checks', function () {
    $storagePath = sys_get_temp_dir().'/bitora-panel-rollback-'.Str::uuid();
    $persistentPath = "{$storagePath}/app/bitora";
    File::ensureDirectoryExists($persistentPath);

    $previousStoragePath = app()->storagePath();
    app()->useStoragePath($storagePath);
    config([
        'app.docker_use_sudo' => false,
        'bitora.image_repository' => 'mrbohem7/bitora',
        'bitora.update_status_path' => "{$persistentPath}/update-status.json",
    ]);
    File::put("{$persistentPath}/update-metadata.json", json_encode([
        'database_driver' => 'sqlite',
        'database_path' => '/var/www/html/storage/app/bitora/panel.sqlite',
        'database_rollback_path' => '/var/www/html/storage/app/bitora/panel.sqlite.rollback',
    ], JSON_THROW_ON_ERROR));
    File::put("{$persistentPath}/panel.sqlite", 'database after failed migration');
    File::put("{$persistentPath}/panel.sqlite.rollback", 'original database');

    $container = [
        'Image' => 'sha256:old-panel-image',
        'Mounts' => [[
            'Type' => 'bind',
            'Source' => $storagePath,
            'Destination' => '/var/www/html/storage',
            'RW' => true,
        ]],
        'HostConfig' => [
            'RestartPolicy' => ['Name' => 'unless-stopped'],
            'PortBindings' => ['80/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '8080']]],
        ],
        'NetworkSettings' => ['Networks' => ['traefik-network' => ['Aliases' => ['bitora-panel']]]],
        'Config' => ['Env' => ['DOCKER_API_VERSION=1.40'], 'Labels' => []],
    ];
    $panelInspectionCount = 0;

    Process::fake(function (PendingProcess $process) use ($container, &$panelInspectionCount) {
        $command = $process->command;

        if ($command === ['docker', 'inspect', 'bitora-panel']) {
            $panelInspectionCount++;

            return $panelInspectionCount === 1
                ? Process::result(output: json_encode([$container], JSON_THROW_ON_ERROR))
                : Process::result(errorOutput: 'No such container', exitCode: 1);
        }

        if (is_array($command) && array_slice($command, 0, 3) === ['docker', 'inspect', '--format']) {
            return 'running';
        }

        if (is_array($command) && array_slice($command, 0, 4) === ['docker', 'exec', 'bitora-panel', 'curl']) {
            return Process::result(errorOutput: 'Unhealthy panel', exitCode: 1);
        }

        return Process::result();
    });
    Sleep::fake();

    try {
        expect(fn () => (new PanelUpdateService)->applyUpdate('mrbohem7/bitora:v0.3.0'))
            ->toThrow(RuntimeException::class, 'health check');

        expect(File::get("{$persistentPath}/panel.sqlite"))->toBe('original database');
        expect((new PanelUpdateService)->status()['status'])->toBe('failed');
        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
            && $process->command[0] === 'docker'
            && $process->command[1] === 'rename'
            && Str::startsWith($process->command[2], 'bitora-panel-rollback-')
            && $process->command[3] === 'bitora-panel');
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'start', 'bitora-panel']);
        Sleep::assertSleptTimes(45);
    } finally {
        File::deleteDirectory($storagePath);
        app()->useStoragePath($previousStoragePath);
    }
});
