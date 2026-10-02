<?php

namespace App\Services;

use App\Models\Project;
use App\Services\Concerns\InteractsWithDocker;
use Illuminate\Support\Facades\Process;

class ContainerResourceService
{
    use InteractsWithDocker;

    /**
     * @return array{memory_mb: int|null, storage_gb: int|null}
     */
    public function availableResources(?Project $exceptProject = null): array
    {
        $memoryResult = Process::run($this->dockerCommand("docker info --format='{{.MemTotal}}'"));
        $memoryBytes = trim($memoryResult->output());
        $memoryMb = $memoryResult->successful() && is_numeric($memoryBytes)
            ? (int) floor((int) $memoryBytes / 1024 / 1024)
            : null;

        $storageBytes = is_dir(storage_path()) ? disk_total_space(storage_path()) : false;
        $totalStorageGb = $storageBytes !== false
            ? (int) floor($storageBytes / 1024 / 1024 / 1024)
            : null;
        $allocatedStorageGb = Project::query()
            ->when($exceptProject !== null, fn ($query) => $query->where($exceptProject->getKeyName(), '!=', $exceptProject->getKey()))
            ->sum('storage_limit_gb');

        return [
            'memory_mb' => $memoryMb,
            'storage_gb' => $totalStorageGb !== null
                ? max($totalStorageGb - (int) $allocatedStorageGb, 0)
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
}
