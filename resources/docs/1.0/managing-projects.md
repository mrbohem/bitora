# Managing Projects

Everything you need to control, monitor, and maintain a deployed project from the Lumina dashboard.

---

- [Project Dashboard Overview](#overview)
- [Starting, Stopping, and Restarting](#start-stop-restart)
- [Rebuilding](#rebuild)
- [Environment Variables](#environment-variables)
- [Cron Jobs](#cron-jobs)
- [Queue Workers (Supervisor)](#workers)
- [GitHub Auto-Update (Webhook)](#github-sync)
- [Execute Command](#execute-command)
- [Live Terminal](#terminal)
- [Container Stats](#stats)
- [Logs](#logs)
- [Settings](#settings)
- [Deleting a Project](#deleting)

<a name="overview"></a>
## Project Dashboard Overview

The project dashboard is your one-stop control panel for a deployed project. It contains:

| Tab / Section | Purpose |
|---|---|
| **Status bar** | Shows container state (active / stopped / deploying), the live URL, and quick-action buttons |
| **Environment** | Manage `.env` key-value pairs |
| **Cron** | Manage scheduled tasks |
| **Workers** | Manage Supervisor queue workers |
| **Logs** | Stream live container logs |
| **Terminal** | Browser-based terminal into the container |
| **Stats** | Real-time resource usage metrics |
| **Settings** | GitHub sync, project name, delete |

<a name="start-stop-restart"></a>
## Starting, Stopping, and Restarting

Use the buttons in the status bar at the top of the project dashboard:

| Action | What it does |
|--------|--------------|
| **Start** | Runs `docker-compose up -d` — starts the container from the existing image |
| **Stop** | Runs `docker stop {container}` — gracefully stops the container |
| **Restart** | Runs `docker restart {container}` — restarts the container in place |

> {info} Start and Stop do **not** rebuild the image. They only affect the running state of the existing container.

<a name="rebuild"></a>
## Rebuilding

The **Rebuild** button triggers a full re-deployment:

1. Pulls the latest code from your configured branch (`git pull`)
2. Rebuilds the Docker image with the updated code
3. Recreates the container from the new image

Use this after:
- Changing your Dockerfile-affecting settings (PHP version, Octane server, resource limits)
- Updating your base repository significantly
- Recovering from a corrupted container state

> {warning} Rebuild causes a brief period of downtime (typically 30–60 seconds while the new image is built).

<a name="environment-variables"></a>
## Environment Variables

The **Environment** tab lets you manage your project's `.env` file directly from the browser.

### Adding a Variable

1. Click **Add Variable** (or the `+` button)
2. Enter the **key** and **value**
3. Click **Save**

The variable is:
- Encrypted and stored in the Lumina database
- Synced immediately into the container's `.env` file via `docker exec`

No restart or rebuild needed — Laravel reads the updated `.env` on the next request.

### Editing a Variable

Click the **pencil** icon next to any variable, update the value, and click **Save**.

### Deleting a Variable

Click the **trash** icon next to a variable. The key is removed from both the database and the container's `.env` file immediately.

> {info} Sensitive values (passwords, tokens, API keys) are masked in the UI and stored encrypted in the database.

<a name="cron-jobs"></a>
## Cron Jobs

Schedule recurring tasks for your project using standard cron expressions.

### Adding a Cron Job

1. Go to the **Cron** tab
2. Click **Add Cron Job**
3. Enter:
   - **Command** — e.g. `php artisan schedule:run`
   - **Expression** — e.g. `* * * * *` (every minute)
4. Click **Save**

The cron job is added to the system crontab on the Lumina host and will run from the project's directory at the specified interval.

> {info} The command runs in the context of the container, not the host. Standard Artisan commands like `php artisan schedule:run` are the most common use case.

### Editing and Deleting Cron Jobs

- **Edit**: Click the pencil icon → modify the command or expression → Save
- **Delete**: Click the trash icon — the entry is removed from the crontab immediately

### Common Cron Expressions

| Expression | Meaning |
|---|---|
| `* * * * *` | Every minute |
| `0 * * * *` | Every hour |
| `0 0 * * *` | Daily at midnight |
| `0 0 * * 0` | Every Sunday at midnight |

<a name="workers"></a>
## Queue Workers (Supervisor)

Run multiple parallel queue workers inside your container using Supervisor.

### Adding a Worker

1. Go to the **Workers** tab
2. Click **Add Worker**
3. Configure:
   - **Name** — unique identifier (e.g. `mail-queue`)
   - **Queue** — the queue to consume (e.g. `default`, `emails`, `notifications`)
   - **Connection** — e.g. `database`, `redis`
   - **Timeout** — job timeout in seconds (default: `60`)
   - **Processes** — number of parallel worker processes (1–10)

The worker is registered with Supervisor inside the running container and started immediately.

### Worker Actions

| Action | What it does |
|--------|--------------|
| **Start** | Sends `supervisorctl start {name}:*` |
| **Stop** | Sends `supervisorctl stop {name}:*` |
| **Restart** | Sends `supervisorctl restart {name}:*` |
| **Delete** | Removes the Supervisor program definition and stops all processes |

> {warning} Workers are configured inside the running container. If the container is rebuilt, workers defined during deployment (via Deploy Step 3) are restored. Workers added post-deployment will need to be re-added after a full rebuild.

<a name="github-sync"></a>
## GitHub Auto-Update (Webhook)

Enable automatic deployments whenever you push to your repository.

### Enabling Auto-Update

1. Go to the project dashboard → **Settings** tab
2. Toggle **Enable GitHub Auto-Update** on
3. Copy the displayed **Webhook URL**

### Configuring the GitHub Webhook

1. In GitHub, go to your repository → **Settings** → **Webhooks** → **Add webhook**
2. Fill in:
   - **Payload URL**: the URL you copied from Lumina
   - **Content type**: `application/json`
   - **Secret**: leave blank (see security note)
   - **Events**: Only select **"Just the push event"**
3. Click **Add webhook**

### How Sync Works

When a push event arrives and the ref matches your configured branch:

1. Lumina runs `git pull` inside the running container (no re-clone needed)
2. The container is restarted in place (`docker restart`)
3. No image rebuild — downtime is typically a few seconds

> {info} Pushes to other branches are automatically ignored. Only the branch configured in the original deployment triggers a sync.

<a name="execute-command"></a>
## Execute Command

Run a one-shot shell command inside your container directly from the browser — no SSH required.

### Running a Command

1. Go to the project dashboard
2. Click **Execute Command** (or the terminal icon in the toolbar)
3. Enter any valid shell command in the input field, e.g.:
   ```bash
   php artisan migrate --force
   php artisan cache:clear
   php artisan config:cache
   ls -la storage/logs
   ```
4. Click **Run**

The command output is displayed inline in the UI as a scrollable log.

> {info} Commands are executed as the container's default user inside the project's working directory (`/var/www/html`).

> {warning} This is an unrestricted command runner inside your container. Do not expose the panel to untrusted users.

<a name="terminal"></a>
## Live Terminal

The **Terminal** tab opens a full interactive PTY terminal inside your running container — just like SSH, but from your browser.

### Using the Terminal

1. Go to the project dashboard → **Terminal** tab
2. An interactive shell (`/bin/sh`) is started inside the container
3. Type commands as you would in any terminal

```bash
# Browse the project directory
ls -la /var/www/html

# Run Artisan commands interactively
php artisan tinker

# Inspect logs
tail -f storage/logs/laravel.log

# Run database migrations
php artisan migrate

# Debug environment
php artisan env
```

> {warning} The terminal connects to the **running container**. If the container is stopped, the terminal will show a connection error.

> {info} Session reconnects automatically for up to 30 seconds if the browser tab briefly loses focus.

<a name="stats"></a>
## Container Stats

The **Stats** section shows real-time resource metrics for the running container, refreshed every few seconds:

| Metric | Description |
|--------|-------------|
| **CPU Usage** | Percentage of CPU cores being consumed |
| **Memory** | Current memory usage vs container limit (or host available if no limit set) |
| **Network I/O** | Total bytes received and transmitted since container start |
| **Block I/O** | Total bytes read/written to disk |

Stats are streamed live using Laravel Reverb without requiring a page refresh.

> {info} Memory stats inside the container are Cgroup-aware — they reflect the container's actual usage, not the full host memory.

<a name="logs"></a>
## Logs

The **Logs** tab streams the live output from your container — the equivalent of `docker logs -f {container}`.

All output to `stdout` and `stderr` is captured, including:
- PHP-FPM / Octane server startup
- Nginx access and error logs
- Supervisor worker logs
- Artisan command output (when run inside the container)

> {info} Log output auto-scrolls as new lines arrive. Use the **Pause** button to freeze the output for inspection.

<a name="settings"></a>
## Settings

The **Settings** modal (accessible via the gear icon or Settings tab) contains:

| Setting | Description |
|---------|-------------|
| **Project Name** | Display name shown in the dashboard |
| **GitHub Auto-Update** | Toggle webhook-based continuous deployment |
| **Webhook URL** | The URL to register in GitHub for push events |

<a name="deleting"></a>
## Deleting a Project

> {caution} Deleting a project is **permanent and irreversible**. This action removes the Docker container, the Docker image, and all project files from disk.

### Steps to Delete

1. Go to the project dashboard → **Settings** tab (or gear icon)
2. Click **Delete Project**
3. In the confirmation modal, type the **exact project slug** to confirm
4. Click **Delete**

### What Gets Deleted

| Resource | Deleted? |
|----------|----------|
| Docker container | ✅ Yes — `docker rm -fv` |
| Docker image | ✅ Yes — `docker rmi -f` |
| Project files on disk | ✅ Yes — `storage/app/projects/{slug}/` |
| SSH keys | ✅ Yes — `storage/app/ssh-keys/{slug}.key` |
| Database record | ✅ Yes — project, env vars, cron jobs, workers, logs |

> {info} The Traefik routing rule for the project's domain is removed automatically when the container is deleted.
