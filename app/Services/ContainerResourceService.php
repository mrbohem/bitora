<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class ContainerResourceService
{
    /**
     * @return array{memory_mb: int|null, storage_gb: int|null}
     */
    public function availableResources(): array
    {
        $memoryResult = Process::run($this->dockerCommand("docker info --format='{{.MemTotal}}'"));
        $memoryBytes = trim($memoryResult->output());
        $memoryMb = $memoryResult->successful() && is_numeric($memoryBytes)
            ? (int) floor((int) $memoryBytes / 1024 / 1024)
            : null;

        $storageBytes = is_dir(storage_path()) ? disk_free_space(storage_path()) : false;

        return [
            'memory_mb' => $memoryMb,
            'storage_gb' => $storageBytes !== false
                ? (int) floor($storageBytes / 1024 / 1024 / 1024)
                : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validateLimits(?int $memoryLimitMb, ?int $storageLimitGb, array $availableResources): array
    {
        $errors = [];

        if ($memoryLimitMb !== null
            && $availableResources['memory_mb'] !== null
            && $memoryLimitMb > $availableResources['memory_mb']) {
            $errors['memory_limit_mb'] = 'The memory limit cannot exceed the available server memory.';
        }

        if ($storageLimitGb !== null
            && $availableResources['storage_gb'] !== null
            && $storageLimitGb > $availableResources['storage_gb']) {
            $errors['storage_limit_gb'] = 'The storage limit cannot exceed the available server storage.';
        }

        return $errors;
    }

    private function dockerCommand(string $command): string
    {
        return (config('app.docker_use_sudo', false) ? 'sudo ' : '').$command;
    }
}
