<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;

class DockerComposeService
{
    /**
     * Generate docker-compose.yml for the project
     */
    public function generateComposeFile(Project $project, string $projectPath): string
    {
        $composeFilePath = "{$projectPath}/docker-compose.yml";

        $composeContent = $this->getComposeTemplate($project);

        File::put($composeFilePath, $composeContent);

        return $composeFilePath;
    }

    /**
     * Get docker-compose template based on project configuration
     */
    private function getComposeTemplate(Project $project): string
    {
        $traefikNetwork = 'traefik-network';
        $imageName = "bitora-{$project->slug}";
        $containerName = "bitora-{$project->slug}-app";

        $compose = [
            'services' => [
                'app' => [
                    'image' => "{$imageName}:latest",
                    'container_name' => $containerName,
                    'restart' => 'unless-stopped',
                    'working_dir' => '/var/www/html',
                    'ports' => ['80'],
                    'networks' => [$traefikNetwork, 'default'],
                    'labels' => $this->getTraefikLabels($project),
                ],
            ],
            'networks' => [
                $traefikNetwork => [
                    'external' => true,
                ],
                'default' => [
                    'driver' => 'bridge',
                    'enable_ipv6' => true,
                ],
            ],
        ];

        if ($project->memory_limit_mb !== null) {
            $compose['services']['app']['mem_limit'] = "{$project->memory_limit_mb}m";
            $compose['services']['app']['memswap_limit'] = "{$project->memory_limit_mb}m";
        }

        if ($project->storage_limit_gb !== null) {
            $compose['services']['app']['storage_opt'] = [
                'size' => "{$project->storage_limit_gb}G",
            ];
        }

        return $this->arrayToYaml($compose);
    }

    /**
     * Get Traefik labels for automatic routing
     */
    private function getTraefikLabels(Project $project): array
    {
        $routerName = "bitora-{$project->slug}";
        $serviceName = "bitora-{$project->slug}";

        $labels = [
            'traefik.enable' => 'true',
            'traefik.docker.network' => 'traefik-network',
            "traefik.http.routers.{$routerName}.service" => $serviceName,
            "traefik.http.services.{$serviceName}.loadbalancer.server.port" => '80',
        ];

        // Domain-based routing if domain is provided
        if (! empty($project->domain)) {
            $domain = $this->sanitizeDomain($project->domain);
            $labels["traefik.http.routers.{$routerName}.rule"] = "Host(`{$domain}`)";
        } else {
            // Path-based routing: /project-slug
            $labels["traefik.http.routers.{$routerName}.rule"] = "PathPrefix(`/{$project->slug}`)";

            // Strip path prefix before forwarding to container
            $labels["traefik.http.middlewares.{$routerName}-stripprefix.stripprefix.prefixes"] = "/{$project->slug}";
            $labels["traefik.http.routers.{$routerName}.middlewares"] = "{$routerName}-stripprefix";
        }

        $labels["traefik.http.routers.{$routerName}.entrypoints"] = 'web,websecure';

        return $labels;
    }

    /**
     * Sanitize domain string for Traefik host matching
     */
    private function sanitizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = rtrim($domain, '/');

        return strtolower($domain);
    }

    /**
     * Convert array to YAML format
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $spaces = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isSequentialArray($value)) {
                    // Sequential array (list)
                    $yaml .= "{$spaces}{$key}:\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= "{$spaces}  -\n";
                            $yaml .= $this->arrayToYaml($item, $indent + 2);
                        } else {
                            $yaml .= "{$spaces}  - {$this->escapeYamlValue($item)}\n";
                        }
                    }
                } else {
                    // Associative array (map)
                    $yaml .= "{$spaces}{$key}:\n";
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= "{$spaces}{$key}: {$this->escapeYamlValue($value)}\n";
            }
        }

        return $yaml;
    }

    /**
     * Check if array is sequential (list) or associative (map)
     */
    private function isSequentialArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Escape YAML value if needed
     */
    private function escapeYamlValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $stringValue = (string) $value;

        // Quote if contains special characters
        if (preg_match('/[:\{\}\[\],&*#?|\-<>=!%@`]/', $stringValue)) {
            return '"'.str_replace('"', '\\"', $stringValue).'"';
        }

        return $stringValue;
    }
}
