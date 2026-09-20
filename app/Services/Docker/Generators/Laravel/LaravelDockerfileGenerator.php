<?php

namespace App\Services\Docker\Generators\Laravel;

use App\Models\Project;
use App\Services\Docker\Octane\OctaneServerStrategyFactory;
use App\Services\Docker\PhpExtensionResolver;

class LaravelDockerfileGenerator
{
    public function __construct(
        private readonly OctaneServerStrategyFactory $octaneStrategies,
        private readonly PhpExtensionResolver $phpExtensions
    ) {}

    /**
     * Generate Dockerfile template for Laravel applications.
     */
    public function generate(Project $project, ?string $projectPath = null): string
    {
        $phpVersion = $project->php_version ?: '8.4';
        $octaneStrategy = $project->octane_enabled
            ? $this->octaneStrategies->for($project->octane_server)
            : null;
        $applicationPath = $projectPath === null || ($project->app_path ?: '.') === '.'
            ? $projectPath
            : $projectPath.'/'.trim($project->app_path, '/');
        $requirements = $this->phpExtensions->resolve($applicationPath);

        // Base image
        $template = 'FROM '.($octaneStrategy?->baseImage($phpVersion) ?? "php:{$phpVersion}-fpm-alpine")."\n\n";

        // System dependencies
        $template .= "# Install system dependencies\n";
        $template .= "RUN apk add --no-cache \\\n";
        $template .= "    linux-headers \\\n";
        $template .= "    git \\\n";
        $template .= "    curl \\\n";
        $template .= "    libpng-dev \\\n";
        $template .= "    oniguruma-dev \\\n";
        $template .= "    libxml2-dev \\\n";
        $template .= "    zip \\\n";
        $template .= "    unzip \\\n";
        $template .= "    postgresql-dev \\\n";
        $template .= "    sqlite-dev \\\n";
        $template .= "    libzip-dev \\\n";
        $template .= "    icu-dev \\\n";
        foreach ($requirements['system_dependencies'] as $dependency) {
            $template .= "    {$dependency} \\\n";
        }
        $template .= "    brotli-dev \\\n";
        $template .= "    openssl-dev \\\n";
        $template .= "    nginx \\\n";
        $template .= "    nodejs \\\n";
        $template .= "    npm \\\n";
        $template .= "    supervisor\n\n";

        // Make `free` report the container's cgroup memory instead of the host memory.
        $template .= "# Make memory reporting cgroup-aware\n";
        $template .= "RUN if command -v free >/dev/null 2>&1; then \\\n";
        $template .= "        mv \"\$(command -v free)\" /usr/local/bin/free.host; \\\n";
        $template .= "        printf '%s\\n' '#!/bin/sh' \\\n";
        $template .= "            'limit_file=/sys/fs/cgroup/memory.max' \\\n";
        $template .= "            'usage_file=/sys/fs/cgroup/memory.current' \\\n";
        $template .= "            'if [ ! -r \"\$limit_file\" ]; then exec /usr/local/bin/free.host \"\$@\"; fi' \\\n";
        $template .= "            'limit=\$(cat \"\$limit_file\")' \\\n";
        $template .= "            'usage=\$(cat \"\$usage_file\")' \\\n";
        $template .= "            'if [ \"\$limit\" = max ] || [ -z \"\$limit\" ] || [ -z \"\$usage\" ]; then exec /usr/local/bin/free.host \"\$@\"; fi' \\\n";
        $template .= "            'awk -v total=\"\$limit\" -v used=\"\$usage\" '\\''BEGIN { print \"              total        used        free      shared  buff/cache   available\"; printf \"Mem: %12.1fM %11.1fM %11.1fM %11.1fM %11.1fM %11.1fM\", total/1048576, used/1048576, (total-used)/1048576, 0, 0, (total-used)/1048576; print \"\"; print \"Swap:              0B          0B          0B\" }'\\''' \\\n";
        $template .= "            > /usr/local/bin/free.cgroup; \\\n";
        $template .= "        chmod +x /usr/local/bin/free.cgroup; \\\n";
        $template .= "        mv /usr/local/bin/free.cgroup /usr/local/bin/free; \\\n";
        $template .= "    fi\n\n";

        // PHP extensions
        $template .= "# Install PHP extensions\n";
        $extensions = array_values(array_unique([
            'pdo', 'pdo_mysql', 'pdo_pgsql', 'pdo_sqlite', 'mbstring', 'exif',
            'pcntl', 'bcmath', 'gd', 'zip', 'sockets', 'intl', ...$requirements['extensions'],
        ]));
        $template .= 'RUN docker-php-ext-install '.implode(' ', $extensions)."\n\n";

        // Redis extension for queues
        if ($project->queue_enabled) {
            $template .= "# Install Redis extension for queues\n";
            $template .= "RUN apk add --no-cache redis \\\n";
            $template .= "    && pecl install redis \\\n";
            $template .= "    && docker-php-ext-enable redis\n\n";
        }

        if ($octaneStrategy) {
            $template .= $octaneStrategy->phpSetup();
        }

        // Composer
        $template .= "# Install Composer\n";
        $template .= "COPY --from=composer:latest /usr/bin/composer /usr/bin/composer\n\n";

        // Working directory
        $template .= "# Set working directory\n";
        $template .= "WORKDIR /var/www/html\n\n";

        // Copy application source code
        $template .= "# Copy project files\n";
        $appPath = $project->app_path ?: '.';
        $template .= $appPath === '.' ? "COPY . .\n\n" : "COPY {$appPath}/ .\n\n";

        // Composer dependencies
        $template .= "# Install dependencies\n";
        $template .= "RUN git config --global --add safe.directory /var/www/html \\\n";
        $template .= "    && composer install --no-dev --no-plugins --optimize-autoloader --no-interaction\n\n";

        if ($octaneStrategy) {
            $template .= $octaneStrategy->dependencySetup();
        }

        // Prepare Laravel runtime configuration
        $template .= "# Prepare Laravel runtime\n";
        $template .= "RUN if [ -f .env.example ] && [ ! -f .env ]; then cp .env.example .env; fi \\\n";
        $template .= "    && php artisan key:generate --force \\\n";
        $template .= "    && mkdir -p database \\\n";
        $template .= "    && touch database/database.sqlite \\\n";
        $template .= "    && if grep -q '^DB_CONNECTION=sqlite' .env; then php artisan migrate --force; fi\n\n";

        if ($project->octane_enabled) {
            $template .= "# Install Laravel Octane configuration without changing the source repository\n";
            $template .= "RUN php artisan octane:install --server={$octaneStrategy->server()->value} --no-interaction\n\n";
        }

        // Reverb configuration
        if ($project->reverb_enabled) {
            $template .= "# Configure Reverb credentials\n";
            $template .= "RUN if grep -q '^BROADCAST_CONNECTION=' .env; then sed -i 's/^BROADCAST_CONNECTION=.*/BROADCAST_CONNECTION=reverb/' .env; else echo 'BROADCAST_CONNECTION=reverb' >> .env; fi \\\n";
            $template .= "    && grep -q '^REVERB_APP_ID=' .env || echo 'REVERB_APP_ID=local' >> .env \\\n";
            $template .= "    && grep -q '^REVERB_APP_KEY=' .env || echo 'REVERB_APP_KEY=local' >> .env \\\n";
            $template .= "    && grep -q '^REVERB_APP_SECRET=' .env || echo 'REVERB_APP_SECRET=local' >> .env \\\n";
            $template .= "    && grep -q '^REVERB_HOST=' .env || echo 'REVERB_HOST=127.0.0.1' >> .env \\\n";
            $template .= "    && grep -q '^REVERB_PORT=' .env || echo 'REVERB_PORT=8080' >> .env \\\n";
            $template .= "    && grep -q '^REVERB_SCHEME=' .env || echo 'REVERB_SCHEME=http' >> .env \\\n";
            $template .= "    && grep -q '^VITE_REVERB_APP_KEY=' .env || echo 'VITE_REVERB_APP_KEY=local' >> .env \\\n";
            $template .= "    && grep -q '^VITE_REVERB_HOST=' .env || echo 'VITE_REVERB_HOST=127.0.0.1' >> .env \\\n";
            $template .= "    && grep -q '^VITE_REVERB_PORT=' .env || echo 'VITE_REVERB_PORT=8080' >> .env \\\n";
            $template .= "    && grep -q '^VITE_REVERB_SCHEME=' .env || echo 'VITE_REVERB_SCHEME=http' >> .env\n\n";

            $template .= "# Configure browser Reverb endpoint\n";
            $template .= "RUN if [ -f resources/js/echo.js ]; then \\\n";
            $template .= "        sed -i '/wsHost:/c\\    wsHost: window.location.hostname,' resources/js/echo.js \\\n";
            $template .= "        && sed -i '/wsPort:/c\\    wsPort: window.location.port || (window.location.protocol === '\''https:'\'' ? 443 : 80),' resources/js/echo.js \\\n";
            $template .= "        && sed -i '/wssPort:/c\\    wssPort: window.location.port || 443,' resources/js/echo.js \\\n";
            $template .= "        && sed -i '/forceTLS:/c\\    forceTLS: window.location.protocol === '\''https:'\'', ' resources/js/echo.js; \\\n";
            $template .= "    fi\n\n";
        }

        // Build frontend assets
        $template .= "# Build frontend assets\n";
        $template .= "RUN if [ -f package.json ] && node -e \"process.exit(require('./package.json').scripts?.build ? 0 : 1)\"; then \\\n";
        $template .= "        if [ -f package-lock.json ]; then npm ci; else npm install; fi \\\n";
        $template .= "        && npm run build; \\\n";
        $template .= "    fi\n\n";

        // Configure Nginx & Supervisor
        $template .= "# Configure Nginx\n";
        $template .= "COPY docker/nginx.conf /etc/nginx/nginx.conf\n";
        $template .= "COPY docker/default.conf /etc/nginx/http.d/default.conf\n\n";

        $template .= "# Configure Supervisor\n";
        $template .= "RUN mkdir -p /var/log/supervisor\n";
        $template .= "COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf\n\n";
        $template .= "RUN ln -sf /etc/supervisor/conf.d/supervisord.conf /etc/supervisord.conf\n\n";

        // Set permissions
        $template .= "# Set permissions\n";
        $template .= "RUN mkdir -p /var/lib/nginx/tmp /var/log/nginx /run/nginx \\\n";
        $template .= "    && chown -R www-data:www-data /var/www/html /var/lib/nginx /var/log/nginx /run/nginx \\\n";
        $template .= "    && chmod -R 755 /var/www/html/storage\n\n";

        // Expose port & start supervisor
        $template .= "# Expose port\n";
        $template .= "EXPOSE 80\n\n";

        $template .= "# Start services\n";
        $template .= "CMD [\"/usr/bin/supervisord\", \"-c\", \"/etc/supervisor/conf.d/supervisord.conf\"]\n";

        return $template;
    }
}
