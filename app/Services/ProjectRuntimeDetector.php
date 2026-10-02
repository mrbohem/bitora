<?php

namespace App\Services;

use App\Enums\OctaneServer;
use App\Models\Project;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\File;

class ProjectRuntimeDetector
{
    /**
     * Detect runtime requirements from the application's Composer manifest.
     */
    public function detect(Project $project, string $projectPath): void
    {
        $composerPath = "{$projectPath}/composer.json";
        $hasOctane = false;
        $hasReverb = false;
        $phpVersion = '8.4';

        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true, 512, JSON_THROW_ON_ERROR);
            $dependencies = $composer['require'] ?? [];
            $hasOctane = array_key_exists('laravel/octane', $dependencies);
            $hasReverb = array_key_exists('laravel/reverb', $dependencies);
            $phpVersion = $this->findCompatiblePhpVersion($projectPath, $composer);
        }

        $envExamplePath = "{$projectPath}/.env.example";
        $server = File::exists($envExamplePath)
            ? OctaneServer::fromEnvironment(File::get($envExamplePath))->value
            : OctaneServer::Swoole->value;

        $project->update([
            'php_version' => $phpVersion,
            'octane_enabled' => $hasOctane,
            'octane_server' => $hasOctane ? $server : null,
            'reverb_enabled' => $hasReverb,
        ]);
        $project->refresh();
    }

    /**
     * Select the newest supported PHP version accepted by the complete locked dependency graph.
     *
     * @param  array<string, mixed>  $composer
     */
    private function findCompatiblePhpVersion(string $projectPath, array $composer): string
    {
        $constraints = [];
        $rootConstraint = $composer['require']['php'] ?? null;

        if (is_string($rootConstraint)) {
            $constraints[] = $rootConstraint;
        }

        $lockPath = "{$projectPath}/composer.lock";

        if (File::exists($lockPath)) {
            $lock = json_decode(File::get($lockPath), true, 512, JSON_THROW_ON_ERROR);

            foreach ($lock['packages'] ?? [] as $package) {
                if (is_string($package['require']['php'] ?? null)) {
                    $constraints[] = $package['require']['php'];
                }
            }
        }

        $parser = new VersionParser;
        $candidates = ['8.5', '8.4', '8.3', '8.2', '8.1', '8.0', '7.4'];

        foreach ($candidates as $candidate) {
            $candidateVersion = new Constraint('==', "{$candidate}.9999999-stable");

            if (collect($constraints)
                ->map(fn (string $constraint) => $parser->parseConstraints($constraint))
                ->every(fn ($constraint) => $constraint->matches($candidateVersion))) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No compatible PHP version was found for the application Composer requirements.');
    }
}
