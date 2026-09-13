<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'git_repo' => fake()->url(),
            'git_branch' => fake()->randomElement(['main', 'master', 'develop']),
            'github_auto_update' => false,
            'git_auth_type' => 'none',
            'git_credentials' => null,
            'app_path' => '.',
            'php_version' => fake()->randomElement(['8.2', '8.3', '8.4']),
            'framework' => 'laravel',
            'domain' => fake()->optional()->domainName(),
            'document_root' => storage_path('app/projects/'.fake()->slug()),
            'container_id' => null,
            'docker_network' => null,
            'reverb_enabled' => false,
            'octane_enabled' => false,
            'octane_server' => null,
            'queue_enabled' => false,
            'queue_connection' => null,
            'queue_workers' => 1,
            'status' => 'pending',
            'deployment_error' => null,
            'deployed_at' => null,
        ];
    }

    /**
     * Indicate that the project has Reverb enabled.
     */
    public function withReverb(): static
    {
        return $this->state(fn (array $attributes) => [
            'reverb_enabled' => true,
        ]);
    }

    /**
     * Indicate that the project has Octane enabled.
     */
    public function withOctane(string $server = 'swoole'): static
    {
        return $this->state(fn (array $attributes) => [
            'octane_enabled' => true,
            'octane_server' => $server,
        ]);
    }

    /**
     * Indicate that the project has Queue enabled.
     */
    public function withQueue(string $connection = 'redis', int $workers = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'queue_enabled' => true,
            'queue_connection' => $connection,
            'queue_workers' => $workers,
        ]);
    }

    /**
     * Indicate that the project is deployed.
     */
    public function deployed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'container_id' => fake()->sha256(),
            'docker_network' => 'lumina-'.fake()->slug().'-network',
            'deployed_at' => now(),
        ]);
    }

    /**
     * Indicate that the project deployment failed.
     */
    public function failed(string $error = 'Deployment failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'deployment_error' => $error,
        ]);
    }
}
