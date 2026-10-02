<?php

namespace App\Services;

use App\Models\Project;
use App\Services\Concerns\InteractsWithDocker;
use Illuminate\Support\Facades\Process;

class ProjectStorageService
{
    use InteractsWithDocker;

    public function usage(Project $project, string $containerId): ?int
    {
        $result = Process::run($this->dockerCommand("docker inspect --size {$containerId} --format='{{.SizeRw}}|{{.SizeRootFs}}'"));

        if ($result->failed()) {
            return null;
        }

        [$writableBytes, $rootFilesystemBytes] = array_pad(explode('|', trim($result->output()), 2), 2, null);
        $checkoutBytes = $this->checkoutSize($project);

        if (is_numeric($rootFilesystemBytes)) {
            return (int) $rootFilesystemBytes + $checkoutBytes;
        }

        return is_numeric($writableBytes)
            ? (int) $writableBytes + $checkoutBytes
            : null;
    }

    private function checkoutSize(Project $project): int
    {
        $projectPath = $project->document_root;

        if (! is_string($projectPath) || ! is_dir($projectPath)) {
            return 0;
        }

        $result = Process::run('du -sk '.escapeshellarg($projectPath));
        $kilobytes = trim(explode("\t", trim($result->output()), 2)[0] ?? '');

        return $result->successful() && is_numeric($kilobytes)
            ? (int) $kilobytes * 1024
            : 0;
    }
}
