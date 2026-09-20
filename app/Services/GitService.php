<?php

namespace App\Services;

use App\Models\Project;
use App\Services\Contracts\ContainerCommandExecutor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class GitService
{
    /**
     * Clone a Git repository
     */
    public function cloneRepository(Project $project, string $destinationPath): void
    {
        File::ensureDirectoryExists($destinationPath, 0755, true);

        $cloneCommand = $this->buildCloneCommand($project, $destinationPath);

        $result = Process::timeout(300)->run($cloneCommand);

        if ($result->failed()) {
            throw new \RuntimeException("Failed to clone repository: {$result->errorOutput()}");
        }
    }

    /**
     * Pull latest changes from repository
     */
    public function pullRepository(Project $project, string $projectPath): void
    {
        $pullCommand = "cd {$projectPath} && git pull origin {$project->git_branch}";

        $result = Process::timeout(120)->run($pullCommand);

        if ($result->failed()) {
            throw new \RuntimeException("Failed to pull repository: {$result->errorOutput()}");
        }
    }

    /**
     * Pull the latest repository changes from inside the running application container.
     */
    public function pullRepositoryInContainer(
        Project $project,
        ContainerCommandExecutor $containerCommandExecutor
    ): void {
        $branch = escapeshellarg($project->git_branch ?? 'main');
        $command = sprintf(
            'sh -c %s',
            escapeshellarg("cd /var/www/html && git pull origin {$branch}")
        );

        $containerCommandExecutor->execCommand($project, $command);
    }

    /**
     * Build Git clone command based on auth type
     */
    private function buildCloneCommand(Project $project, string $destination): string
    {
        $branch = $project->git_branch ?? 'main';

        return match ($project->git_auth_type) {
            'ssh' => $this->buildSshCloneCommand($project, $destination, $branch),
            'token' => $this->buildTokenCloneCommand($project, $destination, $branch),
            default => "git clone --branch {$branch} --single-branch {$project->git_repo} {$destination}",
        };
    }

    /**
     * Build SSH clone command
     */
    private function buildSshCloneCommand(Project $project, string $destination, string $branch): string
    {
        // Save SSH key to temporary file
        $sshKeyPath = storage_path("app/ssh-keys/{$project->slug}.key");
        File::ensureDirectoryExists(dirname($sshKeyPath), 0700, true);
        File::put($sshKeyPath, $project->git_credentials);
        File::chmod($sshKeyPath, 0600);

        return "GIT_SSH_COMMAND='ssh -i {$sshKeyPath} -o StrictHostKeyChecking=no' git clone --branch {$branch} --single-branch {$project->git_repo} {$destination}";
    }

    /**
     * Build token-based clone command (HTTPS with PAT)
     */
    private function buildTokenCloneCommand(Project $project, string $destination, string $branch): string
    {
        $token = $project->git_credentials;
        $repoUrl = $project->git_repo;

        // Convert git@github.com:user/repo.git to https://token@github.com/user/repo.git
        if (str_starts_with($repoUrl, 'git@')) {
            $repoUrl = preg_replace('/git@([^:]+):(.+)\.git/', 'https://$1/$2.git', $repoUrl);
        }

        // Inject token into URL
        $authenticatedUrl = str_replace('https://', "https://{$token}@", $repoUrl);

        return "git clone --branch {$branch} --single-branch {$authenticatedUrl} {$destination}";
    }

    /**
     * Clean up SSH key after clone
     */
    public function cleanupSshKey(Project $project): void
    {
        $sshKeyPath = storage_path("app/ssh-keys/{$project->slug}.key");

        if (File::exists($sshKeyPath)) {
            File::delete($sshKeyPath);
        }
    }

    /**
     * Validate Git repository URL
     */
    public function validateRepository(string $repoUrl, string $authType = 'none', ?string $credentials = null): bool
    {
        // Basic URL validation
        if (empty($repoUrl)) {
            return false;
        }

        // Check if it's a valid git URL format
        if (! preg_match('/^(https?:\/\/|git@)/', $repoUrl)) {
            return false;
        }

        return true;
    }
}
