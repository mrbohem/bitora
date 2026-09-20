<?php

namespace App\Services\Terminal;

use App\Models\Project;
use Illuminate\Support\Str;
use RuntimeException;

final class TerminalSessionStore
{
    public function __construct(
        ?string $basePath = null,
    ) {
        $this->basePath = $basePath ?? storage_path('app/private/terminal');
    }

    private readonly string $basePath;

    public function create(Project $project): TerminalSession
    {
        $token = Str::random(64);
        $session = new TerminalSession($this->directory($project, $token), $token);

        if (! mkdir($session->directory, 0700, true) && ! is_dir($session->directory)) {
            throw new RuntimeException('Unable to create terminal session');
        }

        if (! touch($session->inputPath())
            || ! touch($session->outputPath())
            || ! chmod($session->inputPath(), 0600)
        ) {
            $this->delete($session);
            throw new RuntimeException('Unable to initialize terminal session');
        }

        return $session;
    }

    public function get(Project $project, string $token): TerminalSession
    {
        return new TerminalSession($this->directory($project, $token), $token);
    }

    public function appendInput(TerminalSession $session, string $input): void
    {
        if (! is_file($session->inputPath())) {
            throw new RuntimeException('Terminal session not found');
        }

        if (file_put_contents($session->inputPath(), $input, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Unable to write to terminal session');
        }
    }

    /**
     * @return array{data: string, offset: int, done: bool}
     */
    public function readOutput(TerminalSession $session, int $offset, bool $wait): array
    {
        if (! is_file($session->outputPath())) {
            throw new RuntimeException('Terminal session not found');
        }

        $size = $this->outputSize($session);

        if ($wait && ! is_file($session->statusPath())) {
            $deadline = microtime(true) + 1.5;

            while ($size <= $offset && microtime(true) < $deadline) {
                usleep(50000);
                $size = $this->outputSize($session);
            }
        }

        $offset = max(0, min($offset, $size));
        $handle = fopen($session->outputPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to read terminal output');
        }

        fseek($handle, $offset);
        $data = fread($handle, 65536) ?: '';
        fclose($handle);

        return [
            'data' => $data,
            'offset' => $offset + strlen($data),
            'done' => is_file($session->statusPath()),
        ];
    }

    public function delete(TerminalSession $session): void
    {
        if (! is_dir($session->directory)) {
            return;
        }

        foreach (glob($session->directory.'/*') ?: [] as $file) {
            if (is_file($file) || is_link($file)) {
                unlink($file);
            }
        }

        rmdir($session->directory);
    }

    private function outputSize(TerminalSession $session): int
    {
        clearstatcache(true, $session->outputPath());

        return filesize($session->outputPath()) ?: 0;
    }

    private function directory(Project $project, string $token): string
    {
        if (! preg_match('/^[A-Za-z0-9]+$/', $token)) {
            throw new RuntimeException('Invalid terminal session');
        }

        return $this->basePath.'/'.$project->user_id.'/'.$project->id.'/'.$token;
    }
}
