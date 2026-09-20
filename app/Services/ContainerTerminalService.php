<?php

namespace App\Services;

use App\Models\Project;
use App\Services\Terminal\TerminalProcessManager;
use App\Services\Terminal\TerminalSessionStore;
use RuntimeException;

class ContainerTerminalService
{
    public function __construct(
        private readonly TerminalSessionStore $sessions,
        private readonly TerminalProcessManager $processes,
    ) {}

    /**
     * @return array{token: string, output_url: string}
     */
    public function start(Project $project): array
    {
        if (! $project->container_id) {
            throw new RuntimeException('Container not found');
        }

        $session = $this->sessions->create($project);

        try {
            $this->processes->start($session, $project->container_id);
        } catch (\Throwable $exception) {
            $this->sessions->delete($session);
            throw $exception;
        }

        return [
            'token' => $session->token,
            'output_url' => route('projects.terminal.output', [$project, $session->token]),
        ];
    }

    public function write(Project $project, string $token, string $input): void
    {
        $session = $this->sessions->get($project, $token);
        $this->sessions->appendInput($session, $input);

        if (str_contains($input, "\x03")) {
            $this->processes->interrupt($session);
        }
    }

    public function interrupt(Project $project, string $token): void
    {
        $this->processes->interrupt($this->sessions->get($project, $token));
    }

    /**
     * @return array{data: string, offset: int, done: bool}
     */
    public function output(Project $project, string $token, int $offset = 0, bool $wait = false): array
    {
        return $this->sessions->readOutput($this->sessions->get($project, $token), $offset, $wait);
    }

    public function close(Project $project, string $token): void
    {
        $session = $this->sessions->get($project, $token);
        $this->processes->stop($session);
        $this->sessions->delete($session);
    }
}
