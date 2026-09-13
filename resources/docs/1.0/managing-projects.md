## Managing Projects

After deployment, manage your project from the project dashboard.

---

- [Start / Stop / Restart](#controls)
- [Redeploy](#redeploy)
- [Logs](#logs)
- [Environment Variables](#env)
- [Cron Jobs](#cron)

<a name="controls"></a>
### Start / Stop / Restart

Use the action buttons on the project dashboard to control your project.

| Action | Description |
|--------|-------------|
| Start | Start a stopped project |
| Stop | Stop a running project |
| Restart | Restart without redeploying |
| Redeploy | Pull latest code and rebuild |

<a name="redeploy"></a>
### Redeploy

Click **Redeploy** to:

1. Pull the latest code from Git
2. Rebuild the Docker image
3. Restart the container

> {info} Redeploy is safe — your environment variables are preserved.

<a name="logs"></a>
### Logs

View real-time application and container logs from the **Logs** tab.

- Select number of lines (50, 100, 200, 500)
- Click **Refresh** to fetch latest logs

<a name="env"></a>
### Environment Variables

Add, edit, or remove environment variables from the **Environment** tab.

> {warning} After changing environment variables, restart or redeploy the project for changes to take effect.

<a name="cron"></a>
### Cron Jobs

Add scheduled tasks from the **Cron Jobs** tab.

Enter an artisan command and a cron expression:

```
# Run every hour
0 * * * *    php artisan my:command

# Run daily at midnight
0 0 * * *    php artisan my:command
```

Toggle jobs on/off without deleting them.
