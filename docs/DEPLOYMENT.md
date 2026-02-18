# Production Deployment

## Components
- Nginx + PHP-FPM
- PostgreSQL
- Systemd worker + scheduler timer
- WireGuard policy routing for Grok egress

## Services
```bash
sudo systemctl restart autoposter-worker
sudo systemctl restart autoposter-scheduler.timer
```

## Backups
Daily example:
```bash
pg_dump -h 127.0.0.1 -U autoposter autoposter | gzip > /var/backups/autoposter-$(date +%F).sql.gz
```
Retention: keep 14-30 days. Restore:
```bash
gunzip -c backup.sql.gz | psql -h 127.0.0.1 -U autoposter autoposter
```

## Upgrades
```bash
git pull
composer install --no-dev
php bin/migrate.php
sudo systemctl restart autoposter-worker autoposter-scheduler.timer
```
Migrations are idempotent using `schema_migrations`.

## Security notes
- Keep `.env` mode 600
- Rotate bootstrap credentials
- Use HTTPS and optionally certbot
- Consider admin IP allowlist at nginx and app levels
