# Architecture

An in-depth look at how Lumina is built — from Docker isolation to the deployment pipeline.

---

- [System Overview](#system-overview)
- [Docker Isolation Model](#docker-isolation)
- [Traefik Routing](#traefik-routing)
- [Framework Strategy Pattern](#framework-strategies)
- [Octane Server Strategies](#octane-strategies)
- [Automated Runtime Detection](#runtime-detection)
- [Deployment Pipeline (Detailed)](#deployment-pipeline)
- [Queue Worker Model](#queue-workers)
- [Cron Job Model](#cron-jobs)
- [Service Architecture](#services)

<a name="system-overview"></a>
## System Overview

Lumina is a **Laravel 12 + Livewire 4** web application that manages Docker containers on behalf of users. Every deployed project runs in a completely isolated Docker container on the same host as the panel.

```
┌──────────────────────────────────────────────────────────────┐
│ Host Server                                                  │
│                                                              │
│  ┌─────────────┐     ┌───────────────────────────────────┐  │
│  │   Traefik   │────▶│   Lumina Panel (bitora-panel)     │  │
│  │  :80 / :443 │     │   Laravel 12 + Livewire 4         │  │
│  └──────┬──────┘     │   Reverb WebSocket + Queue        │  │
│         │             └───────────────────────────────────┘  │
│         │                                                    │
│         ├─▶ domain-a.com ──▶ bitora-project-a-app :80       │
│         │                                                    │
│         ├─▶ domain-b.com ──▶ bitora-project-b-app :80       │
│         │                                                    │
│         └─▶ :PORT        ──▶ bitora-project-c-app :80       │
│                                                              │
│   [traefik-network] — shared Docker bridge network          │
└──────────────────────────────────────────────────────────────┘
```

<a name="docker-isolation"></a>
## Docker Isolation Model

Each deployed project is represented by a **pair of Docker artifacts**:

| Artifact | Naming Convention | Example |
|----------|-------------------|---------|
| Docker image | `bitora-{project-slug}` | `bitora-my-laravel-app` |
| Docker container | `bitora-{project-slug}-app` | `bitora-my-laravel-app-app` |

All containers are attached to the `traefik-network` bridge network, allowing Traefik to discover them via Docker labels.

### Container Internals

Each container runs a full application stack managed by **Supervisor**:

```
supervisord
├── php-fpm (or Octane server)
├── nginx (reverse proxy to php-fpm/Octane on port 80)
├── laravel-worker-{n} × N (queue:work processes, if enabled)
└── laravel-reverb (WebSocket server, if Laravel Reverb detected)
```

The working directory inside every container is `/var/www/html`.

### Base Images

| Framework | Octane Server | Base Image |
|-----------|---------------|------------|
| Laravel (default) | None / Swoole | `php:{version}-fpm-alpine` |
| Laravel | FrankenPHP | `dunglas/frankenphp:php{version}-alpine` |
| Laravel | RoadRunner | `php:{version}-fpm-alpine` + RoadRunner binary |
| Node.js | — | `node:{version}-alpine` |

All images are built from Alpine Linux for minimal image size.

<a name="traefik-routing"></a>
## Traefik Routing

Traefik acts as the entry point for all HTTP traffic on the host. Projects are routed in two ways:

### Domain-Based Routing

When a project is deployed with a custom domain, Docker Compose labels instruct Traefik to route all HTTP requests for that domain to the container:

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.bitora-my-app.rule=Host(`myapp.com`)"
  - "traefik.http.routers.bitora-my-app.entrypoints=web"
  - "traefik.http.services.bitora-my-app.loadbalancer.server.port=80"
```

### Port-Based Routing (No Domain)

When no domain is configured, Traefik assigns a random available port on the host. The panel reads the assigned port via `docker port {container} 80` and stores it in the `projects.port` column. The project URL becomes `http://HOST_IP:PORT`.

<a name="framework-strategies"></a>
## Framework Strategy Pattern

Dockerfile and configuration generation is abstracted behind a **Strategy Pattern** via the `FrameworkStrategyFactory`:

```
FrameworkStrategyFactory::make(Project $project)
    │
    ├─▶ LaravelFrameworkStrategy  (when composer.json detected)
    └─▶ NodeFrameworkStrategy     (when package.json detected, no composer.json)
```

Each strategy implements `FrameworkStrategyInterface` with three methods:

| Method | Returns |
|--------|---------|
| `generateDockerfile()` | Multi-stage Dockerfile string |
| `generateNginxConfig()` | Nginx `nginx.conf` + `default.conf` strings |
| `generateSupervisorConfig()` | `supervisord.conf` string |

This design means adding support for a new framework (e.g. Django, Go) requires only a new strategy class — no changes to the orchestrating services.

<a name="octane-strategies"></a>
## Octane Server Strategies

When `laravel/octane` is present, a second strategy layer selects the Octane server implementation via `OctaneServerStrategyFactory`:

```
OctaneServerStrategyFactory::make(string $server)
    │
    ├─▶ FrankenPhpStrategy   → uses FrankenPHP base image, no php-fpm
    ├─▶ RoadRunnerStrategy   → installs spiral/roadrunner-cli binary
    └─▶ SwooleStrategy       → installs Swoole via PECL
```

Each strategy modifies the Dockerfile and Supervisor configuration to run the correct Octane server and, optionally, proxy via Nginx.

<a name="runtime-detection"></a>
## Automated Runtime Detection

Runtime detection happens in `DockerImageService` before Dockerfile generation:

### PHP Version Detection

1. Reads `require.php` constraint from `composer.json`
2. Cross-references with every package's `require.php` from `composer.lock`
3. Resolves the **highest minimum-compatible version** using semantic version constraint evaluation
4. Selects the closest available PHP-FPM Alpine image (8.2, 8.3, 8.4)

### PHP Extension Detection

Scans `composer.lock` for all required PHP extensions across every locked package. Filters to only extensions available in `docker-php-ext-install` + the Alpine `php{N}-*` package namespace.

### Octane Detection

Checks if `laravel/octane` exists in `composer.json`. If yes, reads `OCTANE_SERVER` from `.env.example` (defaults to `swoole`).

### Reverb Detection

Checks if `laravel/reverb` exists in `composer.json`. If yes, injects default WebSocket environment variables and adds the Reverb Supervisor program.

<a name="deployment-pipeline"></a>
## Deployment Pipeline (Detailed)

```
Browser: Deploy wizard submitted
  │
  ▼
DeployProjectJob dispatched (queued)
  │
  ├─ 1. Clone repository via GitService
  │       Uses HTTPS token or SSH key
  │       Clones to: storage/app/projects/{slug}/
  │
  ├─ 2. Detect runtime via DockerImageService
  │       PHP version, extensions, Octane, Reverb
  │
  ├─ 3. Generate configs via DockerImageService
  │       Dockerfile, nginx.conf, default.conf, supervisord.conf
  │       Written to: storage/app/projects/{slug}/
  │
  ├─ 4. Generate docker-compose.yml via ContainerManagementService
  │       Traefik labels, resource limits, env vars injected
  │
  ├─ 5. docker build -t bitora-{slug} .
  │       Runs from: storage/app/projects/{slug}/
  │
  ├─ 6. docker-compose up -d --force-recreate
  │       Starts container: bitora-{slug}-app
  │
  ├─ 7. Detect port via docker port bitora-{slug}-app 80
  │       Stored in: projects.port
  │
  ├─ 8. Sync environment variables via EnvironmentService
  │       Writes .env into container via docker exec
  │
  └─ 9. Mark project active
          projects.status = 'active'
          projects.container_id = {container_id}
```

Status updates during each step are broadcast in real-time via **Laravel Reverb** to the waiting browser using the `ProjectStatusUpdated` event.

<a name="queue-workers"></a>
## Queue Worker Model

Queue workers are managed by **Supervisor** inside each project's container:

1. During deployment (Step 3), if workers are configured, `supervisord.conf` includes `[program:laravel-worker]` block
2. Post-deployment, additional workers can be added via the panel
3. Adding a worker via the panel calls `ProcessService::startWorker()` which:
   - Writes a new `[program:...]` block to the Supervisor config inside the container via `docker exec`
   - Calls `supervisorctl reread && supervisorctl update` to apply it
   - Calls `supervisorctl start {name}:*` to start the processes

Each worker can specify its queue name, connection, timeout, and number of parallel processes.

<a name="cron-jobs"></a>
## Cron Job Model

Cron jobs are managed via the **host system's crontab** (not inside containers) using `CronJobService`:

1. All active cron jobs for all projects are loaded from the database
2. Each entry is written as a crontab line: `{expression} docker exec {container} {command}`
3. The entire crontab is atomically replaced via a temp file → `crontab /tmp/lumina-crontab`

This approach means cron jobs run on the host but execute commands inside their project's container. They are therefore **container lifecycle-aware**: if the container is stopped, the cron job command silently fails (Docker exec returns an error which cron swallows).

<a name="services"></a>
## Service Architecture

| Service | Responsibility |
|---------|---------------|
| `DeploymentService` | Orchestrates the deployment pipeline, manages project path |
| `GitService` | Clone, pull, SSH key generation, token URL construction |
| `DockerImageService` | Dockerfile/Nginx/Supervisor config generation, `docker build` |
| `ContainerManagementService` | Container lifecycle (start/stop/restart/rebuild/delete), stats, port detection, execute command |
| `ProcessService` | Supervisor worker CRUD inside containers |
| `CronJobService` | Host crontab sync from database-stored cron jobs |
| `EnvironmentService` | Encrypted env var storage + `.env` sync to container |
| `ProjectSyncService` | GitHub webhook-triggered `git pull` + in-place restart |
| `ContainerTerminalService` | PTY terminal session management via WebSockets |
| `ActivityLogService` | Write-only audit trail for user-initiated actions |
| `FrameworkStrategyFactory` | Selects `LaravelFrameworkStrategy` or `NodeFrameworkStrategy` |
| `OctaneServerStrategyFactory` | Selects Octane server strategy (Swoole/RoadRunner/FrankenPHP) |
