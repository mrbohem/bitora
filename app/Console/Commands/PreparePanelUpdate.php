<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

#[Signature('bitora:prepare-panel-update')]
#[Description('Persist panel secrets and SQLite data before replacing the panel container')]
class PreparePanelUpdate extends Command
{
    public function handle(): int
    {
        $environmentPath = base_path('.env');
        $persistentDirectory = storage_path('app/bitora');

        if (! File::exists($environmentPath) || blank(config('app.key'))) {
            $this->error('The panel environment file or application key is missing; refusing to update.');

            return self::FAILURE;
        }

        try {
            File::ensureDirectoryExists($persistentDirectory);
            $this->setPanelDirectoryPermissions($persistentDirectory, dirname($persistentDirectory));
            $databaseDriver = config('database.default');

            if ($databaseDriver !== 'sqlite') {
                throw new RuntimeException('One-click panel updates currently require the SQLite database used by the official installer.');
            }

            $this->persistSqliteDatabase($persistentDirectory);
            $this->persistEnvironmentFile($environmentPath, $persistentDirectory);
            File::put(
                "{$persistentDirectory}/update-metadata.json",
                json_encode([
                    'database_driver' => $databaseDriver,
                    'database_path' => '/var/www/html/storage/app/bitora/panel.sqlite',
                    'database_rollback_path' => '/var/www/html/storage/app/bitora/panel.sqlite.rollback',
                ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            );

            if (! chmod("{$persistentDirectory}/update-metadata.json", 0600)) {
                throw new RuntimeException('Persistent panel update metadata permissions could not be secured.');
            }
        } catch (\Throwable $exception) {
            $this->error('Panel state could not be persisted: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Panel environment and database are ready for a safe image update.');

        return self::SUCCESS;
    }

    private function persistSqliteDatabase(string $persistentDirectory): void
    {
        $sourcePath = config('database.connections.sqlite.database');
        $targetPath = "{$persistentDirectory}/panel.sqlite";
        $rollbackPath = "{$targetPath}.rollback";

        if (filled(config('database.connections.sqlite.url'))) {
            throw new RuntimeException('SQLite updates using DB_URL are not supported; the existing database was not changed.');
        }

        if (! is_string($sourcePath) || $sourcePath === '' || $sourcePath === ':memory:' || ! File::exists($sourcePath)) {
            throw new RuntimeException('The active SQLite database file could not be found.');
        }

        $sourceIsPersistent = realpath($sourcePath) === realpath($targetPath);
        $temporaryPath = "{$targetPath}.tmp-".Str::uuid();
        $temporaryRollbackPath = "{$rollbackPath}.tmp-".Str::uuid();
        $connection = DB::connection('sqlite');
        $connection->getPdo()->exec('PRAGMA busy_timeout = 30000');

        try {
            $connection->getPdo()->exec('VACUUM INTO '.$connection->getPdo()->quote($temporaryPath));
        } catch (\Throwable $exception) {
            File::delete([$temporaryPath, $temporaryRollbackPath]);

            throw $exception;
        }

        if (! copy($temporaryPath, $temporaryRollbackPath)) {
            File::delete($temporaryPath);

            throw new RuntimeException('The SQLite rollback snapshot could not be prepared in persistent storage.');
        }

        if (! $sourceIsPersistent && ! rename($temporaryPath, $targetPath)) {
            File::delete([$temporaryPath, $temporaryRollbackPath]);

            throw new RuntimeException('The SQLite database snapshot could not be moved into persistent storage.');
        }

        if ($sourceIsPersistent) {
            File::delete($temporaryPath);
        }

        if (! rename($temporaryRollbackPath, $rollbackPath)) {
            File::delete($temporaryRollbackPath);

            throw new RuntimeException('The SQLite rollback snapshot could not be saved in persistent storage.');
        }

        $this->setPanelFilePermissions($targetPath, $persistentDirectory, 0660);
        $this->setPanelFilePermissions($rollbackPath, $persistentDirectory, 0660);
    }

    private function persistEnvironmentFile(string $environmentPath, string $persistentDirectory): void
    {
        $targetPath = "{$persistentDirectory}/panel.env";
        $temporaryPath = "{$targetPath}.tmp-".Str::uuid();

        if (! copy($environmentPath, $temporaryPath)) {
            throw new RuntimeException('The panel environment file could not be copied to persistent storage.');
        }

        $this->setPanelFilePermissions($temporaryPath, $persistentDirectory, 0640);

        if (! rename($temporaryPath, $targetPath)) {
            File::delete($temporaryPath);

            throw new RuntimeException('The panel environment file could not be saved to persistent storage.');
        }
    }

    private function setPanelFilePermissions(string $path, string $persistentDirectory, int $mode): void
    {
        $owner = fileowner($persistentDirectory);
        $group = filegroup($persistentDirectory);
        $fileOwner = fileowner($path);
        $fileGroup = filegroup($path);

        if ($owner === false || $group === false || $fileOwner === false || $fileGroup === false
            || ($fileOwner !== $owner && ! chown($path, $owner))
            || ($fileGroup !== $group && ! chgrp($path, $group))
            || ! chmod($path, $mode)) {
            throw new RuntimeException('Persistent panel file ownership or permissions could not be set.');
        }
    }

    private function setPanelDirectoryPermissions(string $path, string $ownershipSource): void
    {
        $owner = fileowner($ownershipSource);
        $group = filegroup($ownershipSource);
        $directoryOwner = fileowner($path);
        $directoryGroup = filegroup($path);

        if ($owner === false || $group === false || $directoryOwner === false || $directoryGroup === false
            || ($directoryOwner !== $owner && ! chown($path, $owner))
            || ($directoryGroup !== $group && ! chgrp($path, $group))
            || ! chmod($path, 0775)) {
            throw new RuntimeException('Persistent panel update directory ownership or permissions could not be set.');
        }
    }
}
