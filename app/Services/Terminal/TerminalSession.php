<?php

namespace App\Services\Terminal;

use RuntimeException;

final readonly class TerminalSession
{
    public function __construct(
        public string $directory,
        public string $token,
    ) {}

    public function inputPath(): string
    {
        return $this->path('input');
    }

    public function outputPath(): string
    {
        return $this->path('output');
    }

    public function statusPath(): string
    {
        return $this->path('status');
    }

    public function pidPath(): string
    {
        return $this->path('pid');
    }

    public function runnerPath(): string
    {
        return $this->path('runner.php');
    }

    public function path(string $filename): string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
            throw new RuntimeException('Invalid terminal session file');
        }

        return $this->directory.'/'.$filename;
    }
}
