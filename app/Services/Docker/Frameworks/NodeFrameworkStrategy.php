<?php

namespace App\Services\Docker\Frameworks;

use App\Models\Project;
use App\Services\Docker\Contracts\FrameworkStrategyInterface;

class NodeFrameworkStrategy implements FrameworkStrategyInterface
{
    public function generateDockerfile(Project $project, ?string $projectPath = null): string
    {
        $nodeVersion = $project->node_version ?? '20-alpine';

        return <<<DOCKERFILE
FROM node:{$nodeVersion}

WORKDIR /var/www/html

COPY package*.json ./
RUN npm ci --only=production

COPY . .

EXPOSE 3000

CMD ["npm", "start"]
DOCKERFILE;
    }

    public function generateNginxConfig(Project $project): array
    {
        $mainConfig = <<<'NGINX'
user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    sendfile on;
    keepalive_timeout 65;
    include /etc/nginx/http.d/*.conf;
}
NGINX;

        $siteConfig = <<<'NGINX'
server {
    listen 80;
    server_name _;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
NGINX;

        return [
            'main' => $mainConfig,
            'site' => $siteConfig,
        ];
    }

    public function generateSupervisorConfig(Project $project): string
    {
        return <<<'SUPERVISOR'
[supervisord]
nodaemon=true
user=root

[program:node-app]
command=npm start
directory=/var/www/html
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr
SUPERVISOR;
    }
}
