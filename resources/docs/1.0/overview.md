# Overview

---

- [What is Bitora?](#what-is-bitora)
- [How it works](#how-it-works)
- [Getting Started](#getting-started)

<a name="what-is-bitora"></a>
## What is Bitora?

Bitora is a **self-hosted deployment panel for Laravel applications**. Deploy any Laravel project from a Git repository to your own server — no DevOps knowledge required.

Think of it like Laravel Cloud or Coolify, but running entirely on your server.

<a name="how-it-works"></a>
## How it works

Bitora uses Docker under the hood. Every project runs in an isolated container with its own Nginx, PHP-FPM, and Supervisor setup. Traefik handles all routing automatically.

You never interact with Docker, Nginx, or server configs — Bitora handles everything.

<a name="getting-started"></a>
## Getting Started

1. [Install Bitora](/docs/1.0/installation) on your server
2. Create an admin account via CLI
3. [Deploy your first project](/docs/1.0/deploy-project)
4. Access it at `http://YOUR_IP/project-name`
