<?php

namespace App\Services\Docker\Generators\Laravel;

use App\Models\Project;

class LaravelNginxGenerator
{
    /**
     * Generate Nginx main and site configurations for Laravel.
     *
     * @return array{main: string, site: string}
     */
    public function generate(Project $project): array
    {
        return [
            'main' => $this->getMainConfig(),
            'site' => $this->getSiteConfig($project),
        ];
    }

    /**
     * Get main Nginx configuration.
     */
    public function getMainConfig(): string
    {
        return <<<'NGINX'
user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;
    error_log /var/log/nginx/error.log warn;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 100M;

    gzip on;
    gzip_disable "msie6";

    include /etc/nginx/http.d/*.conf;
}
NGINX;
    }

    /**
     * Get site-specific Nginx configuration (FPM or Octane).
     */
    public function getSiteConfig(Project $project): string
    {
        if ($project->octane_enabled) {
            return $this->getOctaneSiteConfig($project);
        }

        return $this->getFpmSiteConfig($project);
    }

    /**
     * Get PHP-FPM site Nginx config.
     */
    private function getFpmSiteConfig(Project $project): string
    {
        $reverbLocation = $project->reverb_enabled ? $this->getReverbProxyLocation() : '';

        return str_replace('__REVERB_LOCATION__', $reverbLocation, <<<'NGINX'
server {
    listen 80;
    server_name _;
    root /var/www/html/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

__REVERB_LOCATION__

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX);
    }

    /**
     * Get Octane site Nginx config.
     */
    private function getOctaneSiteConfig(Project $project): string
    {
        $reverbLocation = $project->reverb_enabled ? $this->getReverbProxyLocation() : '';

        return str_replace('__REVERB_LOCATION__', $reverbLocation, <<<'NGINX'
server {
    listen 80;
    server_name _;
    root /var/www/html/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

__REVERB_LOCATION__

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location / {
        try_files $uri @octane;
    }

    location ~ \.php$ {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Connection "";
    }

    location @octane {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Connection "";
        proxy_read_timeout 60s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX);
    }

    /**
     * Get Reverb proxy location block.
     */
    private function getReverbProxyLocation(): string
    {
        return <<<'NGINX'
    location ^~ /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $http_host;
        proxy_read_timeout 600s;
        proxy_send_timeout 600s;
    }

    location ^~ /apps/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
    }
NGINX;
    }
}
