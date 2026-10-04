# Installation

Lumina is a self-hosted deployment platform. Install it on any Linux server in minutes.

---

- [Requirements](#requirements)
- [Production Server Install](#production-install)
- [Local Development Setup](#local-development)
- [Local Docker Setup](#local-docker)
- [Create Admin User](#create-admin)
- [Access the Panel](#access-panel)

<a name="requirements"></a>
## Requirements

**Server (Production):**
- Linux server — Ubuntu 22.04 LTS or Debian 12+ recommended
- Minimum 1 vCPU, 1 GB RAM (2 GB+ recommended for running multiple containers)
- Port `80` and `443` open in your firewall
- A user with `sudo` privileges

> {info} Docker and Docker Compose are installed automatically by the installer if not already present.

**Local Development:**
- PHP 8.4+
- Composer 2.x
- Node.js 20+ and npm
- Docker Desktop

<a name="production-install"></a>
## Production Server Install

Run the one-line installer on your Linux server:

```bash
curl -sSL https://raw.githubusercontent.com/mrbohem/bitora/main/install.sh | bash
```

The installer will:
1. Install Docker Engine and Docker Compose plugin (if not already installed)
2. Create the `traefik-network` Docker bridge network
3. Start the **Traefik** reverse proxy container (listening on ports 80 and 443)
4. Pull and start the **Lumina panel** container

After the installer completes, proceed to [Create Admin User](#create-admin).

<a name="local-development"></a>
## Local Development Setup (Source)

Use this setup for contributing to Lumina or customising the platform:

```bash
# 1. Clone the repository
git clone https://github.com/mrbohem/bitora.git lumina
cd lumina

# 2. Install PHP and JavaScript dependencies
composer install
npm install

# 3. Create and configure your environment file
cp .env.example .env
php artisan key:generate

# 4. Run database migrations
php artisan migrate

# 5. Build frontend assets
npm run build

# 6. Start the development server (Laravel + Vite hot reload)
composer run dev
```

The panel will be available at `http://localhost:8000`.

> {warning} The local dev server does not have access to Docker by default. To test full deployment features you need Docker Desktop installed and running with your user having Docker socket access.

<a name="local-docker"></a>
## Local Docker Setup

To run Lumina inside Docker locally (simulating a production environment):

```bash
./scripts/start-local.sh
```

This script will:
1. Build the local Lumina Docker image
2. Start the Traefik reverse proxy
3. Launch the Lumina panel container mounted at port `8080`
4. Run migrations inside the container automatically

Access the panel at `http://localhost:8080`.

<a name="create-admin"></a>
## Create Admin User

After installation, create your admin account using the built-in Artisan command:

**For local development:**
```bash
php artisan admin:create
```

**For Docker installs:**
```bash
docker exec -it bitora-panel php artisan admin:create
```

You will be prompted for:
- **Name** — your display name
- **Email** — your login email
- **Password** — minimum 8 characters
- **Confirm Password** — must match

```
Creating admin user for Bitora...

 Admin name: John Doe
 Admin email: john@example.com
 Admin password:
 Confirm password:

Admin user 'John Doe' created successfully!
Email: john@example.com
```

<a name="access-panel"></a>
## Access the Panel

Open your browser and visit:

| Install Type | URL |
|---|---|
| Local development | `http://localhost:8000` |
| Local Docker | `http://localhost:8080` |
| Production (no domain) | `http://YOUR_SERVER_IP` |
| Production (with domain) | `http://yourdomain.com` |

Log in with the credentials you created in the step above.

> {info} Once logged in, head to [Deploy a Project](/{{route}}/{{version}}/deploy-project) to get started.
