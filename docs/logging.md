# Application logging

## Daily log files

Logs are written to **one file per day** under `storage/logs/`:

```
storage/logs/2026-05-19.log
storage/logs/2026-05-20.log
```

### Environment (production: queue-dev-api)

```env
LOG_CHANNEL=stack
LOG_STACK=daily_by_date
LOG_DAILY_DAYS=30
LOG_LEVEL=debug
```

After changing `.env` on the server:

```bash
php artisan config:clear
php artisan config:cache
```

New log entries go to dated files only. The legacy `storage/logs/laravel.log` is not written to once `daily_by_date` is active.

### Log viewer

Browse logs at `/logs` (e.g. `https://queue-dev-api.nssf.go.tz/logs`).

- Use the **Date** dropdown to switch days.
- **Download** / **Clear** apply to the selected date file only.

### Retention

`LOG_DAILY_DAYS` (default 30) controls how many dated files Monolog keeps. Older files are deleted automatically on rotation.

### Verify locally

```bash
php artisan tinker --execute="\\Illuminate\\Support\\Facades\\Log::info('daily log test');"
ls -la storage/logs/*.log
```

You should see today's file named `YYYY-MM-DD.log`.
