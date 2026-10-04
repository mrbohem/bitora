# Lumina (Bitora) — Self-Hosted Laravel & Node.js Deployment Platform

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://php.net)
[![Livewire](https://img.shields.io/badge/Livewire-v4-purple.svg)](https://livewire.laravel.com)
[![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED.svg)](https://www.docker.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**Lumina** (branded as **Bitora**) is a modern, self-hosted deployment platform designed for deploying, scaling, and managing Laravel and Node.js applications directly on your own infrastructure. Think of it as your private Forge or Coolify, giving you complete containerized control with zero DevOps overhead.

---

## ⚡ Highlights & Key Features

- 🚀 **Zero-Config Git Deployments**: Paste your Git repository URL (public, GitHub token, or SSH key) to build and deploy.
- 📦 **Monorepo & Subdirectory Support**: Target nested Laravel or Node apps inside subdirectories (`app_path`).
- 🤖 **Automated Runtime Detection**:
  - Automatically identifies compatible PHP versions (PHP 7.4 through PHP 8.5) from locked `composer.json` / `composer.lock` dependencies.
  - Detects **Laravel Octane** and configures high-performance runtime engines (**FrankenPHP**, **RoadRunner**, or **Swoole**).
  - Detects **Laravel Reverb** and sets up WebSocket server credentials and reverse proxy routing.
- 🐳 **Isolated Container Architecture**: Every project runs in an isolated container with dedicated Nginx, PHP-FPM / Octane, and Supervisor daemon.
- 🌐 **Traefik Reverse Proxy**:
  - Dynamic domain routing (`http://yourdomain.com`).
  - Automatic external port mapping (`http://SERVER_IP:PORT`).
- 🔄 **GitHub Auto-Update & Webhooks**:
  - Automated continuous deployment on git push to your target branch.
  - In-place fast synchronization (`git pull` inside container + graceful restart) without waiting for a full rebuild.
- 💻 **Interactive Browser Terminal**:
  - Full-featured, PTY-driven interactive terminal right inside your project container.
  - Direct artisan command runner with output streaming.
- ⚙️ **Resource Quotas & Allocation**:
  - Granular CPU limits (`cpu_limit_cores`), Memory limits (`memory_limit_mb`), and Storage volume quotas (`storage_limit_gb`).
  - Real-time host and Docker resource capacity detection to prevent server oversubscription.
- 🔐 **Encrypted Credentials & Live Env Sync**:
  - Sensitive Git credentials (PATs and SSH keys) encrypted at rest in the database.
  - Bi-directional sync between database environment variables and the container's `.env` file.
- 🕒 **Cron Jobs & Supervisor Worker Pools**:
  - Project-scoped cron scheduling synced to system crontab.
  - Multi-process Supervisor background queue worker pools.
- 📊 **Activity Logs & Container Monitoring**:
  - Real-time CPU, memory, network I/O, and storage stats per container.
  - Full audit logging for deployments, updates, and configuration changes.

---

## 🚀 Quick Start & Installation

### Option 1: One-Line Production Server Install (Linux)

Run the automated installer on your Linux host (Ubuntu 22.04+ recommended):

```bash
curl -sSL https://raw.githubusercontent.com/mrbohem/bitora/main/install.sh | bash
```

This installer:
1. Installs Docker & Docker Compose plugin (if not already installed).
2. Starts the **Traefik** reverse proxy on ports `80` and `443` on the `traefik-network` bridge.
3. Pulls and boots the Lumina / Bitora panel container.

### Option 2: Local Development Setup (Source)

If you are contributing or running Lumina locally:

```bash
# 1. Clone the repository
git clone https://github.com/mrbohem/bitora.git
cd bitora

# 2. Install PHP and Node dependencies
composer install
npm install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Run database migrations
php artisan migrate

# 5. Build frontend assets
npm run build

# 6. Start the development server
composer run dev
```

### Option 3: Local Docker Stack

To test Lumina inside Docker locally:

```bash
./scripts/start-local.sh
```

---

## 👤 Creating an Admin User

After installation, create your administrative account using the artisan command:

```bash
php artisan admin:create
```

You will be prompted for your name, email, and a secure password.

Once created, log in at:
- Local development: `http://localhost:8000`
- Production / Docker: `http://YOUR_SERVER_IP:8080` (or configured domain)

---

## 📖 How to Deploy a Project

1. **Navigate to Projects**: Click **"Deploy New Project"** (`/projects/deploy`).
2. **Step 1 - Source**: Enter your Git repository URL, select the branch (`main`), set the relative application path (`.` or `subfolder`), and choose authentication (`None`, `Token`, or `SSH Key`).
3. **Step 2 - Domain**: Provide an optional custom domain (e.g., `api.example.com`). If left blank, Lumina assigns a dedicated external port automatically.
4. **Step 3 - Resources & Queue**: Configure memory limits (MB), storage limits (GB), CPU allocation, and optionally enable background queue workers (`redis`, `database`, etc.).
5. **Step 4 - Environment Variables**: Add initial `.env` key-value pairs (DB host, API keys, etc.).
6. **Deploy**: Click **"Deploy"**. Lumina dispatches `DeployProjectJob` to clone, auto-detect the runtime, build the optimized Docker image, and launch the container with Traefik routing.

---

## 🏗️ Architecture & Design Patterns

Lumina is built using clean, robust architectural design patterns adhering to SOLID principles:

- **Strategy Pattern (`FrameworkStrategyInterface`)**: Handles generation of Dockerfiles, Nginx configurations, and Supervisor configs across different frameworks (`LaravelFrameworkStrategy`, `NodeFrameworkStrategy`).
- **Octane Engine Strategies (`OctaneServerStrategy`)**: Dynamically configures FrankenPHP, RoadRunner, or Swoole based on project requirements.
- **Dedicated Domain Services**:
  - `DeploymentService`: Handles project provisioning, path resolution, and deployment dispatching.
  - `ContainerManagementService`: Low-level Docker and Docker Compose container operations.
  - `ProjectContainerService`: High-level container state orchestration (start, restart, rebuild, port allocation).
  - `ProjectSyncService`: Fast repository synchronization directly within running containers.
  - `ContainerTerminalService`: PTY terminal session manager and stream reader.
  - `CronJobService`: Single responsibility management of system crontab entries.
  - `ProcessService`: Supervisor worker pool management.
  - `ContainerResourceService`: Host memory and disk quota calculations and limit validations.

---

## 📚 In-App Documentation (LaRecipe)

Full documentation is built into the application using **LaRecipe**:

Visit: **`http://YOUR_PANEL_URL/docs`**

- **Overview**: System design, features, and core concepts.
- **Installation**: Step-by-step setup guides for production and local environments.
- **Deploying a Project**: Detailed guide on Git sources, runtimes, Octane, Reverb, and resource limits.
- **Managing Projects**: Project control, logs, terminal, environment variables, cron jobs, and auto-sync.
- **Architecture**: Deep dive into services, design patterns, and container orchestration.

---

## 🧪 Testing & Code Quality

Run tests using Pest:

```bash
# Run unit and feature tests
vendor/bin/pest

# Run code style formatting
vendor/bin/pint --dirty --format agent

# Run static analysis
vendor/bin/phpstan analyse --debug
```

---

## 📄 License

The Lumina / Bitora deployment platform is open-source software licensed under the [MIT license](LICENSE).
