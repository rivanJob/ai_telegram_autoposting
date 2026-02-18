# Production Deployment Guide

## Nginx + HTTPS
- Installer writes `/etc/nginx/sites-available/autoposter.conf`
- Includes deny rules for sensitive paths and security headers.
- Optional certbot via `--with-certbot`.

## Services
- `autoposter-worker.service` (always on)
- `autoposter-scheduler.service` + timer (minute cadence)

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now autoposter-worker.service autoposter-scheduler.timer
```

## Backups
Daily `pg_dump` example:
```bash
pg_dump -h $DB_HOST -U $DB_USER $DB_NAME > /var/backups/autoposter-$(date +%F).sql
```
Retention: keep 14+ days and test restore monthly.

Restore:
```bash
psql -h $DB_HOST -U $DB_USER $DB_NAME < backup.sql
```

## Upgrade workflow
1. Deploy new code.
2. `composer install --no-dev --optimize-autoloader`
3. `php bin/migrate.php` (safe/idempotent)
4. Restart services:
   ```bash
   systemctl restart autoposter-worker.service autoposter-scheduler.timer
   ```
5. Rollback: restore previous release directory and DB backup if migration is destructive.

## Log management
Prefer journald for service logs; optionally add logrotate for app file logs.
