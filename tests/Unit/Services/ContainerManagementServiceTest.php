<?php

use App\Models\Project;
use App\Services\ContainerManagementService;
use App\Services\ContainerResourceService;
use Illuminate\Support\Facades\Process;

test('container stats resolve the container when the project has no stored container id', function () {
    Process::fake([
        'docker ps -aq -f name=*' => Process::result(output: "resolved-container\n"),
        'docker stats resolved-container --no-stream --format=*' => Process::result(
            output: "12.50%|128MiB / 512MiB|1.2kB / 3.4kB|5.6kB / 7.8kB\n"
        ),
        "docker inspect resolved-container --format='{{.HostConfig.Memory}}'" => Process::result(
            output: "536870912\n"
        ),
        "docker inspect --size resolved-container --format='{{.SizeRw}}'" => Process::result(
            output: "2097152\n"
        ),
    ]);

    $project = Project::factory()->create([
        'slug' => 'real-stats',
        'container_id' => null,
        'memory_limit_mb' => 512,
    ]);

    $stats = app(ContainerManagementService::class)->getContainerStats($project);

    expect($stats)->toBe([
        'cpu_percent' => '12.50%',
        'memory_usage' => '128MiB / 512 MiB',
        'network_io' => '1.2kB / 3.4kB',
        'block_io' => '5.6kB / 7.8kB',
        'storage_usage' => '2 MiB',
        'storage_limit' => 'Unlimited',
    ]);

    Process::assertRan(fn ($process) => $process->command === 'docker ps -aq -f name=bitora-real-stats-app');
    Process::assertRan(fn ($process) => str_starts_with($process->command, 'docker stats resolved-container --no-stream'));
});

test('available resources include Docker memory and host storage', function () {
    Process::fake([
        "docker info --format='{{.MemTotal}}'" => Process::result(output: "2147483648\n"),
    ]);

    $resources = app(ContainerManagementService::class)->getAvailableResources();

    expect($resources['memory_mb'])->toBe(2048)
        ->and($resources['storage_gb'])->toBeGreaterThanOrEqual(0);
});

test('resource limits are rejected when they exceed detected capacity', function () {
    $errors = app(ContainerResourceService::class)->validateLimits(
        memoryLimitMb: 2048,
        storageLimitGb: 20,
        availableResources: ['memory_mb' => 1024, 'storage_gb' => 10],
    );

    expect($errors)->toBe([
        'memory_limit_mb' => 'The memory limit cannot exceed the available server memory.',
        'storage_limit_gb' => 'The storage limit cannot exceed the available server storage.',
    ]);
});
