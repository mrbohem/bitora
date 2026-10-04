# Overview

---

- [What is Lumina?](#what-is-lumina)
- [How It Works](#how-it-works)
- [Key Features](#key-features)
- [Technology Stack](#technology-stack)
- [Getting Started](#getting-started)

<a name="what-is-lumina"></a>
## What is Lumina?

**Lumina** (branded as **Bitora**) is a **self-hosted deployment platform** for Laravel and Node.js applications. It lets you take any Git repository and deploy it as a fully isolated, production-ready containerized application on your own server — no DevOps knowledge required.

Think of it as your private version of [Laravel Forge](https://forge.laravel.com) or [Coolify](https://coolify.io), running entirely on infrastructure you control.

<a name="how-it-works"></a>
## How It Works

Lumina automates the entire deployment pipeline from a single Git URL:

```
Git Repository
     │
     ▼
Clone & Runtime Detection
(PHP version, Octane, Reverb)
     │
     ▼
Dockerfile Generation
(Framework-specific, optimized build)
     │
     ▼
Docker Image Build
     │
     ▼
Container Launch + Traefik Routing
     │
     ▼
Live at http://domain.com or http://IP:PORT
```

Every project runs in a **completely isolated Docker container** with its own:
- Nginx web server (or Octane server: FrankenPHP / RoadRunner / Swoole)
- PHP-FPM runtime
- Supervisor process manager
- Queue workers (optional)
- Reverb WebSocket server (optional)

**Traefik** handles all reverse proxy routing — whether you're routing by domain or by path-based ports.

<a name="key-features"></a>
## Key Features

| Feature | Description |
|---------|-------------|
| **Git Deployments** | Public, private (token or SSH), monorepo subpath support |
| **Automated Runtime Detection** | PHP version, Octane (FrankenPHP/RoadRunner/Swoole), Reverb |
| **Container Isolation** | Every project in its own Docker container |
| **Traefik Integration** | Automatic domain and port-based routing |
| **GitHub Auto-Sync** | Webhook-driven continuous deployment on `git push` |
| **Live Terminal** | Browser-based PTY terminal into any running container |
| **Environment Variables** | Encrypted storage with live sync to container `.env` |
| **Resource Limits** | CPU, memory, and storage quotas per project |
| **Cron Jobs** | Project-scoped cron expressions synced to system crontab |
| **Supervisor Workers** | Multi-process queue worker pool management |
| **Activity Logs** | Full deployment and configuration audit trail |
| **Container Stats** | Real-time CPU, memory, network I/O, and storage metrics |

<a name="technology-stack"></a>
## Technology Stack

| Layer | Technology |
|-------|------------|
| Backend Framework | Laravel 12 (PHP 8.4+) |
| Reactive Frontend | Livewire v4 + Flux UI |
| UI Components | Flux UI + Alpine.js |
| Styling | Tailwind CSS v4 |
| Containers | Docker + Docker Compose |
| Reverse Proxy | Traefik v2 |
| Database | SQLite (default) / MySQL / PostgreSQL |
| Queue | Laravel Queue (database, redis) |
| Real-time Events | Laravel Reverb |

<a name="getting-started"></a>
## Getting Started

1. [Install Lumina](/{{route}}/{{version}}/installation) on your server
2. Create an admin account: `php artisan admin:create`
3. Log in and [deploy your first project](/{{route}}/{{version}}/deploy-project)
4. [Manage your project](/{{route}}/{{version}}/managing-projects) — environment variables, cron jobs, logs, terminal

> {info} First time? Start with the [Installation guide](/{{route}}/{{version}}/installation).
