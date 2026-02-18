# Production Deployment Notes

## Nginx + HTTPS
- Use installer-generated vhost.
- Enable certbot with `--with-certbot`.
- Keep admin behind dedicated domain or custom path.

## Services
- `autoposter-worker.service` (`Restart=always`, user `autoposter`)
- `autoposter-scheduler.timer` + `autoposter-scheduler.service`

## Health
```bash
php bin/health.php
php bin/setup.php
```

## Backups
Daily backup example:
```bash
pg_dump -Fc "$DB_DATABASE" > /var/backups/autoposter/autoposter-$(date +%F).dump
```
Restore:
```bash
pg_restore -d "$DB_DATABASE" /var/backups/autoposter/autoposter-YYYY-MM-DD.dump
```

## Upgrades
```bash
git pull
composer install --no-dev --optimize-autoloader
php bin/migrate.php
systemctl restart autoposter-worker.service
systemctl restart autoposter-scheduler.timer
```
Migrations are idempotent via `schema_migrations`.

## Rollback
- deploy previous git revision
- run compatible rollback SQL manually if needed
- restart services
