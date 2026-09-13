## Deploying a Project

Deploy any Laravel project from a Git repository in a few clicks — no Docker or server knowledge required.

---

- [Step 1: Git Repository](#step-1)
- [Step 2: Configuration](#step-2)
- [Step 3: Features](#step-3)
- [Step 4: Environment Variables](#step-4)
- [Accessing Your Project](#accessing)

<a name="step-1"></a>
### Step 1: Git Repository

Enter your repository URL and select a branch.

If the repository contains multiple applications, enter the Laravel application's relative folder path. Use `.` when Laravel is at the repository root. For example, if the repository structure is `apps/laravel/artisan`, enter `apps/laravel` in the Laravel Folder Path field. Do not include the repository name or an absolute server path.

For private repositories, choose an authentication method:

| Method | When to use |
|--------|-------------|
| None | Public repositories |
| Token | GitHub/GitLab personal access token |
| SSH Key | SSH deploy key added to your Git provider |

> {info} For SSH, copy the public key from **Settings → SSH Key** and add it as a deploy key in your repository.

<a name="step-2"></a>
### Step 2: Configuration

- **PHP Version** — Choose 8.2, 8.3, or 8.4
- **Domain** *(optional)* — Enter a custom domain (e.g. `myapp.com`). Leave blank to access via `http://YOUR_IP/project-name`

<a name="step-3"></a>
### Step 3: Features

Queue workers can be enabled for background job processing.

Laravel Reverb is detected automatically. If `laravel/reverb` is present in the application's
`composer.json`, the Reverb WebSocket server and proxy configuration are generated. Otherwise,
the deployment does not start a Reverb process.

Laravel Octane is detected automatically from the repository. If `laravel/octane` is present
in `composer.json`, the application runs through Octane. Swoole is used by default; set
`OCTANE_SERVER=roadrunner` or `OCTANE_SERVER=frankenphp` in `.env.example` to select
RoadRunner or FrankenPHP. If the package is not installed, the application runs through the
normal PHP-FPM/Nginx stack.

<a name="step-4"></a>
### Step 4: Environment Variables

Add your `.env` variables here. Common ones:

```
APP_KEY=base64:...
DB_HOST=your-db-host
DB_DATABASE=your-db
DB_USERNAME=your-user
DB_PASSWORD=your-password
```

> {warning} Variables are stored encrypted. You can edit them anytime from the project dashboard.

Containers are configured with dual-stack Docker networks (`IPv4` and `IPv6`). IPv6 connectivity still requires the deployment server and its Docker daemon to have an upstream IPv6 route. If the server is IPv4-only, use the provider's IPv4-compatible database endpoint or pooler.

<a name="accessing"></a>
### Accessing Your Project

Once deployed:

| Setup | URL |
|-------|-----|
| No domain | `http://YOUR_SERVER_IP/project-slug` |
| Custom domain | `http://your-domain.com` |

No nginx or server configuration required — routing is handled automatically.
