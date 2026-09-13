<?php

namespace App\Services;

use App\Models\DeploymentConfig;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ConfigService
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    /**
     * Generate web server configuration for a project
     */
    public function generateWebServerConfig(Project $project): DeploymentConfig
    {
        $configPath = $this->getConfigPath($project);
        $content = $this->generateConfigContent($project);

        // In development, skip actual file write and web server reload
        if (app()->environment('local')) {
            // Just store in database for development
            return DeploymentConfig::create([
                'project_id' => $project->id,
                'config_type' => $project->web_server,
                'config_path' => $configPath,
                'content' => $content,
            ]);
        }

        File::put($configPath, $content);

        $config = DeploymentConfig::create([
            'project_id' => $project->id,
            'config_type' => $project->web_server,
            'config_path' => $configPath,
            'content' => $content,
        ]);

        $this->reloadWebServer($project->web_server);

        return $config;
    }

    /**
     * Read live configuration from server file
     */
    public function readLiveConfig(Project $project): string
    {
        $config = $project->deploymentConfigs()->where('config_type', $project->web_server)->latest()->first();

        if (! $config) {
            throw new \RuntimeException('No configuration found for this project');
        }

        // In development, return the stored content from database
        if (app()->environment('local')) {
            return $config->content;
        }

        if (! File::exists($config->config_path)) {
            throw new \RuntimeException('Configuration file not found on server');
        }

        return File::get($config->config_path);
    }

    /**
     * Update web server configuration
     */
    public function updateConfig(Project $project, string $content, User $user): DeploymentConfig
    {
        $config = $project->deploymentConfigs()->where('config_type', $project->web_server)->latest()->first();

        if (! $config) {
            throw new \RuntimeException('No configuration found for this project');
        }

        // Backup old config
        $backupPath = $config->config_path.'.backup.'.now()->timestamp;
        File::copy($config->config_path, $backupPath);

        // Write new config
        File::put($config->config_path, $content);

        // Test configuration
        if (! $this->testConfig($project->web_server)) {
            // Restore backup if test fails
            File::move($backupPath, $config->config_path);
            throw new \RuntimeException('Configuration test failed. Changes reverted.');
        }

        // Create new config record
        $newConfig = DeploymentConfig::create([
            'project_id' => $project->id,
            'config_type' => $project->web_server,
            'config_path' => $config->config_path,
            'content' => $content,
            'updated_by' => $user->id,
        ]);

        $this->reloadWebServer($project->web_server);

        $this->activityLogService->log(
            $project,
            $user,
            'config.updated',
            "Updated {$project->web_server} configuration"
        );

        // Clean up backup after successful update
        File::delete($backupPath);

        return $newConfig;
    }

    /**
     * Generate configuration content based on web server type
     */
    private function generateConfigContent(Project $project): string
    {
        return match ($project->web_server) {
            'nginx' => $this->generateNginxConfig($project),
            'apache' => $this->generateApacheConfig($project),
            default => throw new \InvalidArgumentException("Unsupported web server: {$project->web_server}"),
        };
    }

    /**
     * Generate Nginx configuration
     */
    private function generateNginxConfig(Project $project): string
    {
        $domain = $project->domain ?? $project->slug.'.local';
        $root = $project->document_root.'/public';

        return <<<NGINX
server {
    listen 80;
    server_name {$domain};
    root {$root};

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX;
    }

    /**
     * Generate Apache configuration
     */
    private function generateApacheConfig(Project $project): string
    {
        $domain = $project->domain ?? $project->slug.'.local';
        $root = $project->document_root.'/public';

        return <<<APACHE
<VirtualHost *:80>
    ServerName {$domain}
    DocumentRoot {$root}

    <Directory {$root}>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/{$project->slug}-error.log
    CustomLog \${APACHE_LOG_DIR}/{$project->slug}-access.log combined
</VirtualHost>
APACHE;
    }

    /**
     * Get configuration file path
     */
    private function getConfigPath(Project $project): string
    {
        return match ($project->web_server) {
            'nginx' => "/etc/nginx/sites-available/{$project->slug}",
            'apache' => "/etc/apache2/sites-available/{$project->slug}.conf",
            default => throw new \InvalidArgumentException("Unsupported web server: {$project->web_server}"),
        };
    }

    /**
     * Test web server configuration
     */
    private function testConfig(string $webServer): bool
    {
        $result = match ($webServer) {
            'nginx' => Process::run('nginx -t'),
            'apache' => Process::run('apachectl configtest'),
            default => throw new \InvalidArgumentException("Unsupported web server: {$webServer}"),
        };

        return $result->successful();
    }

    /**
     * Reload web server to apply changes
     */
    private function reloadWebServer(string $webServer): void
    {
        match ($webServer) {
            'nginx' => Process::run('systemctl reload nginx'),
            'apache' => Process::run('systemctl reload apache2'),
            default => throw new \InvalidArgumentException("Unsupported web server: {$webServer}"),
        };
    }
}
