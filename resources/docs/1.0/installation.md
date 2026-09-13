## Installation

Bitora is a self-hosted Laravel deployment panel. Install it on any Linux server with a single command.

---

- [Requirements](#requirements)
- [Quick Install](#quick-install)
- [Create Admin User](#create-admin-user)
- [Access the Panel](#access-the-panel)

<a name="requirements"></a>
### Requirements

- A Linux server (Ubuntu 20.04+ recommended)
- Port 80 and 443 open in your firewall
- A user with `sudo` access

> {info} Docker and Docker Compose will be installed automatically if not present.

<a name="quick-install"></a>
### Quick Install

Run the following command on your server:

```bash
curl -sSL https://raw.githubusercontent.com/yourrepo/bitora/main/install.sh | bash
```

This will:

1. Install Docker and Docker Compose (if not already installed)
2. Start the Traefik reverse proxy on port 80/443
3. Create the required Docker network

<a name="create-admin-user"></a>
### Create Admin User

After installation, create your admin account using the CLI:

```bash
php artisan admin:create
```

You will be prompted for:
- Name
- Email
- Password

<a name="access-the-panel"></a>
### Access the Panel

Open your browser and visit:

```
http://YOUR_SERVER_IP/
```

Log in with the credentials you created above.
