# Bitora - Self-Hosted Laravel Deployment Panel

Deploy Laravel projects to your own server with zero DevOps knowledge required.

## Features

- **Zero-config deployment** — Git URL → Live project
- **Path/Domain routing** — Access via `/project-name` or custom domain
- **Docker-based isolation** — Each project in its own container
- **Laravel features support** — Reverb, Octane, Queue workers
- **Traefik integration** — Automatic SSL (Let's Encrypt ready)
- **Code protection** — Obfuscated with Yakpro-Po

---

## For End Users

### Installation

Run this command on your Linux server:

```bash
curl -sSL https://raw.githubusercontent.com/mrbohem/bitora/main/install.sh | bash
```

This will:
- Install Docker & Docker Compose (if needed)
- Setup Traefik reverse proxy
- Pull and start Bitora panel

### Access Panel

Visit: `http://YOUR_SERVER_IP:bitora-panel-port`

### Deploy Your First Project

1. Log in to the panel using the demo credentials: `test@example.com` / `password`
2. Click "Deploy Project"
3. Enter the Git repository URL
4. Configure the PHP version and features
5. Click Deploy

Your project will be available at:
- **No domain:** `http://YOUR_IP:project-port`
- **With domain:** `http://your-domain.com`

---

## For Developers/Maintainers

### Building & Publishing

**Requirements:**
- PHP 8.4+
- Composer
- Docker
- Docker Hub account

### Development Setup

```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start dev server
php artisan serve
```

---

## Documentation

Full documentation available at: `http://YOUR_IP:bitora-panel-port/docs`

- Installation Guide
- Deploy Project Tutorial
- Managing Projects
- Troubleshooting
