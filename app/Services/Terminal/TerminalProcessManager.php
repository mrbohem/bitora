<?php

namespace App\Services\Terminal;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class TerminalProcessManager
{
    public function start(TerminalSession $session, string $containerId): void
    {
        $runner = $this->runner($session, $containerId);

        if (file_put_contents($session->runnerPath(), $runner, LOCK_EX) === false
            || ! chmod($session->runnerPath(), 0600)) {
            throw new RuntimeException('Unable to initialize terminal process');
        }

        $result = Process::timeout(10)
            ->run(sprintf('nohup php %s >/dev/null 2>&1 & echo started', escapeshellarg($session->runnerPath())));

        if ($result->failed()) {
            throw new RuntimeException('Unable to start terminal: '.trim($result->errorOutput() ?: $result->output()));
        }

        $deadline = microtime(true) + 1;

        while (! is_file($session->pidPath()) && microtime(true) < $deadline) {
            usleep(10000);
        }

        if (! is_file($session->pidPath()) || ! ctype_digit(trim((string) file_get_contents($session->pidPath())))) {
            throw new RuntimeException('Unable to start terminal process');
        }
    }

    public function interrupt(TerminalSession $session): void
    {
        $this->signal($session, SIGINT);
    }

    public function stop(TerminalSession $session): void
    {
        $this->signal($session, 15);
    }

    private function signal(TerminalSession $session, int $signal): void
    {
        if (! is_file($session->pidPath()) || ! function_exists('posix_kill')) {
            return;
        }

        $pid = trim((string) file_get_contents($session->pidPath()));

        if (ctype_digit($pid) && (int) $pid > 0) {
            posix_kill((int) $pid, $signal);
        }
    }

    private function runner(TerminalSession $session, string $containerId): string
    {
        $inputFollower = sprintf(
            '$path=%s;$offset=0;while(true){clearstatcache(true,$path);$size=filesize($path);if($size>$offset){$handle=fopen($path,"rb");fseek($handle,$offset);$data=fread($handle,$size-$offset);fclose($handle);if($data!==false){fwrite(STDOUT,$data);fflush(STDOUT);$offset+=$size-$offset;}}usleep(10000);}',
            var_export($session->inputPath(), true)
        );
        $docker = config('app.docker_use_sudo', false) ? 'sudo docker' : 'docker';
        $shell = sprintf(
            '%s exec -it %s /bin/sh -c %s',
            $docker,
            escapeshellarg($containerId),
            escapeshellarg('stty -echo; exec /bin/sh')
        );
        $pty = PHP_OS_FAMILY === 'Darwin'
            ? sprintf('script -q /dev/null %s', $shell)
            : sprintf('script -q -c %s /dev/null', escapeshellarg($shell));
        $command = base64_encode(sprintf(
            'php -r %s | %s > %s 2>&1',
            escapeshellarg($inputFollower),
            $pty,
            escapeshellarg($session->outputPath())
        ));

        return sprintf(
            '<?php
if (function_exists("posix_setsid")) {
    @posix_setsid();
}
$process = proc_open(base64_decode(%s), [], $pipes);
if (! is_resource($process)) {
    exit(1);
}
$status = proc_get_status($process);
file_put_contents(%s, (string) ($status["pid"] ?? 0), LOCK_EX);
proc_close($process);
file_put_contents(%s, "done", LOCK_EX);
',
            var_export($command, true),
            var_export($session->pidPath(), true),
            var_export($session->statusPath(), true),
        );
    }
}
