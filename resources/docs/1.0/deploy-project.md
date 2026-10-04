# Deploying a Project

Deploy any Laravel or Node.js project from a Git repository in 4 steps — no Docker, Nginx, or server knowledge required.

---

- [Step 1: Source Repository](#step-1)
- [Step 2: Domain & Routing](#step-2)
- [Step 3: Resources & Queue](#step-3)
- [Step 4: Environment Variables](#step-4)
- [What Happens During Deployment](#deployment-pipeline)
- [Automated Runtime Detection](#runtime-detection)
- [Accessing Your Project](#accessing)
- [GitHub Auto-Sync (Webhook)](#github-sync)

<a name="step-1"></a>
## Step 1: Source Repository

### Repository URL

Enter the full Git clone URL of your repository:

| Repository type | Example URL |
|---|---|
| Public HTTPS | `https://github.com/user/my-app.git` |
| Private HTTPS (Token) | `https://github.com/user/private-app.git` |
| Private SSH | `git@github.com:user/private-app.git` |

### Branch

Specify the branch to deploy from (default: `main`).

### Application Path (`app_path`)

If the Laravel application lives in the **root** of your repository, leave this as `.`.

For **monorepo or subdirectory** setups, enter the relative path to the directory containing `artisan`:

```
Repository structure:
  apps/
    backend/        ← Laravel is here
      artisan
      composer.json
  frontend/

Application Path: apps/backend
```

> {warning} Do not include a leading `/` or the repository name. The path must be relative to the repository root.

### Authentication

Choose the method that matches your repository access:

| Method | When to use |
|--------|-------------|
| **None** | Public repositories — no credentials needed |
| **Personal Access Token** | Private GitHub/GitLab repos. Generate a token with `repo` scope from your Git provider's settings |
| **SSH Key** | Deploy keys. After selecting SSH, copy the generated **public key** shown in the UI and add it as a Deploy Key in your repository settings (GitHub: Settings → Deploy keys) |

> {info} Credentials are encrypted at rest in the database and are never exposed in logs or process listings.

<a name="step-2"></a>
## Step 2: Domain & Routing

### Custom Domain

Enter a domain you own (e.g. `myapp.com`, `api.example.com`) that points to your server's IP address. Traefik will route traffic from that domain to your project automatically.

Leave this field blank to access the project via a randomly assigned external port (`http://YOUR_IP:PORT`).

### PHP Version

Choose the PHP version for your container:

| Version | Status |
|---|---|
| PHP 8.2 | Supported |
| PHP 8.3 | Supported |
| PHP 8.4 | Recommended (default) |

> {info} If Laravel Octane is detected in your project's `composer.json`, the PHP version is used for the Octane base image selection (e.g. `dunglas/frankenphp:php8.4-alpine`).

<a name="step-3"></a>
## Step 3: Resources & Queue

### Resource Limits

Limit how much server capacity each project can consume. Available capacity is detected automatically from Docker's host metrics and existing project allocations.

| Setting | Default | Description |
|---|---|---|
| **Memory Limit** | Unlimited | Container memory cap in MB (e.g. `512`) |
| **Storage Limit** | Unlimited | Container writable layer quota in GB (e.g. `5`) |
| **CPU Limit** | Unlimited | Fractional CPU cores (e.g. `0.5` = half a core) |

### Queue Workers

Enable **Queue Workers** to process background jobs:

| Setting | Description |
|---|---|
| **Queue Connection** | `database`, `redis`, or any configured driver |
| **Number of Workers** | How many parallel Supervisor `queue:work` processes to run (1–10) |

> {info} When queue workers are enabled, a Redis PHP extension is automatically added to the Docker image and Supervisor is configured to run `php artisan queue:work {connection}` with the specified number of processes.

<a name="step-4"></a>
## Step 4: Environment Variables

Add your application's `.env` key-value pairs before the first deployment. Common variables:

```env
APP_NAME=MyApplication
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...

DB_CONNECTION=mysql
DB_HOST=your-database-host
DB_PORT=3306
DB_DATABASE=my_database
DB_USERNAME=db_user
DB_PASSWORD=secret_password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=your-redis-host
REDIS_PASSWORD=null
REDIS_PORT=6379
```

> {warning} `APP_KEY` is automatically generated during the Docker image build step (`php artisan key:generate --force`). You do not need to provide it manually unless you have an existing key.

> {info} Variables are encrypted and stored in the database. You can add, edit, or delete them at any time from the project dashboard under the **Environment** tab — no redeployment required.

<a name="deployment-pipeline"></a>
## What Happens During Deployment

When you click **Deploy**, Lumina queues a background job (`DeployProjectJob`) that executes the following steps:

1. **Clone repository** — Clones from your Git source into `storage/app/projects/{slug}/` using the configured authentication method
2. **Detect runtime** — Reads `composer.json` and `composer.lock` to identify PHP version, Octane server, Reverb, and required PHP extensions
3. **Generate Dockerfile** — Builds an optimised, framework-specific Dockerfile including:
   - Retry-safe `apk` package installation
   - Cgroup-aware `free` command wrapper (shows container memory, not host)
   - Detected PHP extensions installation via `docker-php-ext-install`
   - Composer install (respects composer-plugin lockfile entries)
   - Frontend asset build (`npm run build` if `package.json` exists)
   - Octane and Reverb setup if detected
4. **Generate Nginx config** — Creates `nginx.conf` and `default.conf` (or Octane proxy config)
5. **Generate Supervisor config** — Creates `supervisord.conf` with PHP-FPM/Octane, queue workers, and Reverb processes
6. **Generate Docker Compose file** — Includes Traefik labels, resource limits, and dual-stack IPv4/IPv6 network
7. **Build Docker image** — Runs `docker build` from the generated project directory
8. **Start container** — Runs `docker-compose up -d --force-recreate`
9. **Detect port** — Reads the Traefik-assigned external port via `docker port`
10. **Mark active** — Updates the project status to `active` and saves `container_id` and `port`

Progress and status updates are broadcast in real time via Laravel Reverb to the project's status indicator in the UI.

<a name="runtime-detection"></a>
## Automated Runtime Detection

Lumina automatically scans your project's dependencies and configures the container to match:

### PHP Version

Reads `require.php` from `composer.json` and `packages[].require.php` from `composer.lock` to find the **minimum compatible PHP version** using semantic version constraint solving.

### Laravel Octane

If `laravel/octane` is found in `composer.json`:

- Detects which Octane server is configured via `OCTANE_SERVER` in `.env.example`
- **Swoole** (default) — installs via PECL, runs as `php artisan octane:start --server=swoole`
- **RoadRunner** — installs `spiral/roadrunner-cli` and `vendor/bin/rr` binary, runs with `--rpc-port=6001`
- **FrankenPHP** — uses `dunglas/frankenphp:phpX.Y-alpine` as the base image

Nginx is automatically configured as a reverse proxy to the Octane port (8000) instead of PHP-FPM.

### Laravel Reverb

If `laravel/reverb` is found in `composer.json`, Lumina:
- Injects default `REVERB_*` and `VITE_REVERB_*` environment variables into the image
- Sets `BROADCAST_CONNECTION=reverb`
- Adds a `[program:laravel-reverb]` block to `supervisord.conf`
- Configures Nginx to proxy WebSocket connections (`/app/` and `/apps/`) to Reverb's port 8080

### PHP Extensions

Required PHP extensions are resolved by scanning all locked packages' `require` entries. Only extensions installable via `docker-php-ext-install` and present in the Alpine package registry are accepted. Unknown extensions cause a clear build error rather than a silent failure.

<a name="accessing"></a>
## Accessing Your Project

Once deployment completes and the project status shows **Active**:

| Configuration | URL |
|---|---|
| No domain configured | `http://YOUR_SERVER_IP:PORT` (port shown on project dashboard) |
| Custom domain configured | `http://your-domain.com` |

The project dashboard's **Visit Site** button links to the correct URL automatically.

<a name="github-sync"></a>
## GitHub Auto-Sync (Webhook)

Enable continuous deployment via GitHub webhooks:

1. Go to the project dashboard → **Settings** tab
2. Enable **GitHub Auto-Update** toggle
3. Copy the webhook URL shown: `https://your-panel.com/projects/{project}/github/webhook`
4. In GitHub: go to your repository → **Settings → Webhooks → Add webhook**
   - **Payload URL**: paste the copied URL
   - **Content type**: `application/json`
   - **Events**: Select "Just the push event"
5. Click **Add webhook**

Now every push to your configured branch triggers a fast in-place sync:
- Runs `git pull` directly inside the running container
- Gracefully restarts the container without a full image rebuild
- Container downtime is kept to a few seconds

> {info} Auto-sync only triggers when the push ref matches the project's configured git branch exactly. Pushes to other branches are silently ignored.
