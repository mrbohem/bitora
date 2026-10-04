<?php

namespace App\Services;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;

class PanelUpdateService
{
    private const PANEL_CONTAINER = 'bitora-panel';

    private const UPDATE_CONTAINER = 'bitora-panel-updater';

    /**
     * @return array{version: string, tag: string}|null
     */
    public function latestRelease(): ?array
    {
        $tags = Http::connectTimeout(3)
            ->timeout(8)
            ->get(config('bitora.dockerhub_tags_url'))
            ->throw()
            ->json('results');

        if (! is_array($tags)) {
            throw new RuntimeException('Docker Hub returned an invalid tags response.');
        }

        $versions = collect($tags)
            ->pluck('name')
            ->filter(fn (mixed $tag): bool => is_string($tag) && preg_match('/^v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $tag) === 1)
            ->map(fn (string $tag): array => [
                'tag' => $tag,
                'version' => ltrim($tag, 'v'),
            ])
            ->sort(fn (array $left, array $right): int => version_compare($left['version'], $right['version']))
            ->values();

        return $versions->last();
    }

    public function currentVersion(): string
    {
        return (string) config('bitora.version', '0.0.0-dev');
    }

    /**
     * @param  array{version: string, tag: string}|null  $latestRelease
     */
    public function updateAvailable(?array $latestRelease): bool
    {
        return $latestRelease !== null
            && version_compare(ltrim($this->currentVersion(), 'v'), $latestRelease['version'], '<');
    }

    /**
     * Start an independent helper container so replacing the panel cannot interrupt the update.
     */
    public function startUpdate(): void
    {
        $latestRelease = $this->latestRelease();

        if ($latestRelease === null || ! $this->updateAvailable($latestRelease)) {
            throw new RuntimeException('No newer versioned Bitora image is currently available.');
        }

        $currentContainer = $this->inspectContainer(self::PANEL_CONTAINER);
        $storageMount = collect($currentContainer['Mounts'] ?? [])
            ->first(fn (array $mount): bool => $mount['Destination'] === '/var/www/html/storage' && $mount['Type'] === 'bind');

        if (! is_array($storageMount) || blank($storageMount['Source'] ?? null) || ! ($storageMount['RW'] ?? false)) {
            throw new RuntimeException('Panel update requires the persistent storage mount created by the Bitora installer.');
        }

        $existingUpdater = $this->inspectContainer(self::UPDATE_CONTAINER, throwOnFailure: false);

        if ($existingUpdater !== null && ($existingUpdater['State']['Running'] ?? false)) {
            throw new RuntimeException('A panel update is already running.');
        }

        if ($existingUpdater !== null) {
            $this->runDocker(['rm', '-f', self::UPDATE_CONTAINER]);
        }

        try {
            $this->writeStatus('starting', 'Starting the panel updater. Deployed project containers will not be changed.');
            $this->runDocker([
                'exec',
                self::PANEL_CONTAINER,
                'php',
                'artisan',
                'bitora:prepare-panel-update',
            ], timeout: 300);

            $imageId = $currentContainer['Image'] ?? null;

            if (! is_string($imageId) || $imageId === '') {
                throw new RuntimeException('The current panel image could not be identified.');
            }

            $targetImage = config('bitora.image_repository').':'.$latestRelease['tag'];
            $arguments = [
                'run',
                '--detach',
                '--name',
                self::UPDATE_CONTAINER,
                '--restart',
                'no',
                '--volume',
                '/var/run/docker.sock:/var/run/docker.sock',
                '--volume',
                $storageMount['Source'].':/var/www/html/storage',
                $imageId,
                '/bin/sh',
                '-lc',
                'sleep 5 && exec php artisan bitora:apply-panel-update --image='.escapeshellarg($targetImage),
            ];

            $result = Process::timeout(30)->run([...$this->dockerBinary(), ...$arguments]);

            if ($result->failed()) {
                throw new RuntimeException('Could not start the panel updater: '.trim($result->errorOutput()));
            }
        } catch (\Throwable $exception) {
            $this->writeStatus('failed', 'The panel updater could not start: '.$exception->getMessage());

            throw $exception;
        }
    }

    /**
     * Replace only the panel container, keeping the existing one available for rollback.
     */
    public function applyUpdate(string $targetImage): void
    {
        $expectedPrefix = config('bitora.image_repository').':';

        if (! Str::startsWith($targetImage, $expectedPrefix)
            || preg_match('/^v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', Str::after($targetImage, $expectedPrefix)) !== 1) {
            throw new RuntimeException('The requested panel image is not a valid versioned Bitora image.');
        }

        $rollbackContainer = 'bitora-panel-rollback-'.Str::lower(Str::random(8));
        $oldContainer = $this->inspectContainer(self::PANEL_CONTAINER);
        $metadataPath = storage_path('app/bitora/update-metadata.json');

        if (! File::exists($metadataPath)) {
            throw new RuntimeException('Persistent panel update metadata is missing; the existing installation was not changed.');
        }

        $metadata = json_decode(File::get($metadataPath), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($metadata) || ! isset($metadata['database_driver'])) {
            throw new RuntimeException('Persistent panel update metadata is invalid; the existing installation was not changed.');
        }

        if ($metadata['database_driver'] !== 'sqlite'
            || ($metadata['database_path'] ?? null) !== '/var/www/html/storage/app/bitora/panel.sqlite'
            || ($metadata['database_rollback_path'] ?? null) !== '/var/www/html/storage/app/bitora/panel.sqlite.rollback'
            || ! File::exists(storage_path('app/bitora/panel.sqlite.rollback'))) {
            throw new RuntimeException('A safe SQLite rollback snapshot is missing; the existing installation was not changed.');
        }

        $renamedOldContainer = false;
        $newContainerStarted = false;

        try {
            $this->writeStatus('pulling', "Downloading {$targetImage}...");
            $this->runDocker(['pull', $targetImage], timeout: 900);

            $this->writeStatus('restarting', 'Restarting the panel with the new version. Deployed projects keep running.');
            $this->runDocker(['rename', self::PANEL_CONTAINER, $rollbackContainer]);
            $renamedOldContainer = true;
            $this->runDocker(['stop', '--time', '30', $rollbackContainer], timeout: 60);

            $this->runDocker($this->newPanelArguments($oldContainer, $targetImage, $metadata), timeout: 120);
            $newContainerStarted = true;
            $this->connectAdditionalNetworks($oldContainer);
            $this->waitForHealthCheck();

            $this->writeStatus('completed', 'Bitora was updated successfully. Your deployed projects were left running.');
            $this->runDocker(['rm', '-f', $rollbackContainer], timeout: 60);
        } catch (\Throwable $exception) {
            try {
                $this->rollback($rollbackContainer, $renamedOldContainer, $newContainerStarted);
                $this->writeStatus('failed', 'Panel update failed; the previous panel version was restored. '.$exception->getMessage());
            } catch (\Throwable $rollbackException) {
                $message = 'Panel update failed, and automatic rollback was incomplete: '.$rollbackException->getMessage();
                $this->writeStatus('failed', $message);

                throw new RuntimeException($message, previous: $exception);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $oldContainer
     * @param  array<string, mixed>  $metadata
     * @return array<int, string>
     */
    private function newPanelArguments(array $oldContainer, string $targetImage, array $metadata): array
    {
        $arguments = ['run', '--detach', '--name', self::PANEL_CONTAINER];
        $restartPolicy = $oldContainer['HostConfig']['RestartPolicy']['Name'] ?? 'unless-stopped';

        if ($restartPolicy !== '' && $restartPolicy !== 'no') {
            array_push($arguments, '--restart', $restartPolicy);
        }

        $networks = $oldContainer['NetworkSettings']['Networks'] ?? [];
        $primaryNetwork = array_key_first($networks);

        if (! is_string($primaryNetwork)) {
            throw new RuntimeException('The existing panel Docker network could not be identified.');
        }

        array_push($arguments, '--network', $primaryNetwork);

        foreach ($this->publishedPorts($oldContainer) as $publishedPort) {
            array_push($arguments, '--publish', $publishedPort);
        }

        foreach ($oldContainer['Mounts'] ?? [] as $mount) {
            if (($mount['Destination'] ?? null) === '/var/www/html/.env') {
                continue;
            }

            $source = $mount['Source'] ?? $mount['Name'] ?? null;
            $destination = $mount['Destination'] ?? null;

            if (! is_string($source) || ! is_string($destination)) {
                continue;
            }

            $mode = ($mount['RW'] ?? true) ? 'rw' : 'ro';
            array_push($arguments, '--volume', "{$source}:{$destination}:{$mode}");
        }

        $storageMount = collect($oldContainer['Mounts'] ?? [])
            ->first(fn (array $mount): bool => ($mount['Destination'] ?? null) === '/var/www/html/storage' && ($mount['Type'] ?? null) === 'bind');

        if (! is_array($storageMount) || blank($storageMount['Source'] ?? null)) {
            throw new RuntimeException('The existing persistent panel storage mount could not be restored.');
        }

        array_push(
            $arguments,
            '--volume',
            $storageMount['Source'].'/app/bitora/panel.env:/var/www/html/.env:ro',
        );

        foreach ($oldContainer['Config']['Env'] ?? [] as $environmentVariable) {
            if (! is_string($environmentVariable) || ! str_contains($environmentVariable, '=')) {
                continue;
            }

            $name = strstr($environmentVariable, '=', before_needle: true);

            if ($name === 'BITORA_VERSION'
                || ($metadata['database_driver'] === 'sqlite' && in_array($name, ['DB_DATABASE', 'DB_URL'], true))) {
                continue;
            }

            array_push($arguments, '--env', $environmentVariable);
        }

        if ($metadata['database_driver'] === 'sqlite') {
            array_push(
                $arguments,
                '--env',
                'DB_URL=',
                '--env',
                'DB_DATABASE='.$metadata['database_path'],
            );
        }

        foreach ($oldContainer['Config']['Labels'] ?? [] as $label => $value) {
            array_push($arguments, '--label', "{$label}={$value}");
        }

        array_push(
            $arguments,
            '--entrypoint',
            '/bin/sh',
            $targetImage,
            '-lc',
            'php artisan optimize:clear && php artisan config:cache && php artisan migrate --force && exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf',
        );

        return $arguments;
    }

    /**
     * @param  array<string, mixed>  $container
     * @return array<int, string>
     */
    private function publishedPorts(array $container): array
    {
        $ports = [];

        foreach ($container['HostConfig']['PortBindings'] ?? [] as $containerPort => $bindings) {
            foreach ($bindings ?? [] as $binding) {
                $hostPort = $binding['HostPort'] ?? null;

                if (! is_string($hostPort) || $hostPort === '' || $hostPort === '0') {
                    continue;
                }

                $hostIp = $binding['HostIp'] ?? '';
                $host = str_contains($hostIp, ':') ? "[{$hostIp}]" : $hostIp;
                $ports[] = ($host !== '' ? $host.':' : '').$hostPort.':'.$containerPort;
            }
        }

        if ($ports === []) {
            throw new RuntimeException('No published panel port was found; the existing panel was not changed.');
        }

        return array_values(array_unique($ports));
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function connectAdditionalNetworks(array $container): void
    {
        $networks = $container['NetworkSettings']['Networks'] ?? [];
        $primaryNetwork = array_key_first($networks);

        foreach ($networks as $network => $settings) {
            if ($network === $primaryNetwork) {
                continue;
            }

            $arguments = ['network', 'connect'];

            foreach ($settings['Aliases'] ?? [] as $alias) {
                array_push($arguments, '--alias', $alias);
            }

            array_push($arguments, $network, self::PANEL_CONTAINER);
            $this->runDocker($arguments);
        }
    }

    private function waitForHealthCheck(): void
    {
        for ($attempt = 0; $attempt < 45; $attempt++) {
            $state = $this->runDocker(['inspect', '--format', '{{.State.Status}}', self::PANEL_CONTAINER], allowFailure: true);

            if ($state->failed() || trim($state->output()) !== 'running') {
                Sleep::for(2)->seconds();

                continue;
            }

            $health = $this->runDocker(
                ['exec', self::PANEL_CONTAINER, 'curl', '--fail', '--silent', '--show-error', 'http://127.0.0.1/up'],
                timeout: 10,
                allowFailure: true,
            );

            if ($health->successful()) {
                return;
            }

            Sleep::for(2)->seconds();
        }

        throw new RuntimeException('The updated panel did not pass its HTTP health check.');
    }

    private function rollback(string $rollbackContainer, bool $renamedOldContainer, bool $newContainerStarted): void
    {
        if ($renamedOldContainer || $newContainerStarted) {
            $this->runDocker(['rm', '-f', self::PANEL_CONTAINER], allowFailure: true);

            if ($this->inspectContainer(self::PANEL_CONTAINER, throwOnFailure: false) !== null) {
                throw new RuntimeException('The failed replacement container could not be removed.');
            }
        }

        if ($renamedOldContainer) {
            $rename = $this->runDocker(['rename', $rollbackContainer, self::PANEL_CONTAINER], allowFailure: true);

            if ($rename->failed()) {
                throw new RuntimeException('The previous panel container could not be restored: '.trim($rename->errorOutput()));
            }
        }

        $this->restoreDatabaseSnapshot();

        if ($renamedOldContainer) {
            $start = $this->runDocker(['start', self::PANEL_CONTAINER], timeout: 60, allowFailure: true);

            if ($start->failed()) {
                throw new RuntimeException('The previous panel container could not be restarted: '.trim($start->errorOutput()));
            }
        }
    }

    private function restoreDatabaseSnapshot(): void
    {
        $persistentDirectory = storage_path('app/bitora');
        $snapshotPath = "{$persistentDirectory}/panel.sqlite.rollback";
        $databasePath = "{$persistentDirectory}/panel.sqlite";
        $temporaryPath = "{$databasePath}.restore-".Str::lower(Str::random(8));

        if (! File::exists($snapshotPath) || ! copy($snapshotPath, $temporaryPath)) {
            throw new RuntimeException('The original SQLite database snapshot could not be restored.');
        }

        if (! rename($temporaryPath, $databasePath)) {
            File::delete($temporaryPath);

            throw new RuntimeException('The original SQLite database could not be restored.');
        }

        $owner = fileowner($persistentDirectory);
        $group = filegroup($persistentDirectory);
        $databaseOwner = fileowner($databasePath);
        $databaseGroup = filegroup($databasePath);

        if ($owner === false || $group === false || $databaseOwner === false || $databaseGroup === false
            || ($databaseOwner !== $owner && ! chown($databasePath, $owner))
            || ($databaseGroup !== $group && ! chgrp($databasePath, $group))
            || ! chmod($databasePath, 0660)) {
            throw new RuntimeException('The restored SQLite database permissions could not be set.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectContainer(string $name, bool $throwOnFailure = true): ?array
    {
        $result = $this->runDocker(['inspect', $name], allowFailure: ! $throwOnFailure);

        if ($result->failed()) {
            return null;
        }

        $containers = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

        return $containers[0] ?? null;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function runDocker(array $arguments, int $timeout = 300, bool $allowFailure = false): ProcessResult
    {
        $result = Process::timeout($timeout)->run([...$this->dockerBinary(), ...$arguments]);

        if ($result->failed() && ! $allowFailure) {
            throw new RuntimeException('Docker command failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function dockerBinary(): array
    {
        return config('app.docker_use_sudo', false)
            ? ['sudo', 'docker']
            : ['docker'];
    }

    public function status(): array
    {
        $path = config('bitora.update_status_path');

        if (! File::exists($path)) {
            return ['status' => 'idle', 'message' => null];
        }

        $status = json_decode(File::get($path), true);

        return is_array($status)
            ? $status
            : ['status' => 'failed', 'message' => 'The panel update status file is invalid.'];
    }

    public function writeStatus(string $status, ?string $message): void
    {
        $path = config('bitora.update_status_path');
        $directory = dirname($path);
        File::ensureDirectoryExists($directory);
        $temporaryPath = $path.'.tmp-'.Str::uuid();

        if (! File::put($temporaryPath, json_encode([
            'status' => $status,
            'message' => $message,
            'updated_at' => now()->toISOString(),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
            throw new RuntimeException('The panel update status could not be written.');
        }

        $owner = fileowner($directory);
        $group = filegroup($directory);
        $fileOwner = fileowner($temporaryPath);
        $fileGroup = filegroup($temporaryPath);

        if ($owner === false || $group === false || $fileOwner === false || $fileGroup === false
            || ($fileOwner !== $owner && ! chown($temporaryPath, $owner))
            || ($fileGroup !== $group && ! chgrp($temporaryPath, $group))
            || ! chmod($temporaryPath, 0664)
            || ! rename($temporaryPath, $path)) {
            File::delete($temporaryPath);

            throw new RuntimeException('The panel update status ownership or permissions could not be set.');
        }
    }
}
