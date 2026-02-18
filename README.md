# AI Telegram Autoposting

Production-ready SaaS-grade Telegram autoposter with secure admin UI, PostgreSQL, Grok JSON generation, scheduler/worker architecture, and WireGuard egress enforcement.

## Quick install (Ubuntu 22.04/24.04)

1. Copy installer config:
   ```bash
   cp install/config.env.example install/config.env
   ```
2. Edit `install/config.env` with your domain, DB, Telegram, Grok, and WireGuard settings.
3. Run installer:
   ```bash
   sudo bash install/install.sh --non-interactive
   ```
4. Validate deployment:
   ```bash
   php bin/setup.php
   php bin/health.php
   ```

See:
- `docs/INSTALL_UBUNTU.md`
- `docs/DEPLOYMENT.md`
- `docs/WIREGUARD_EGRESS.md`

## Local development

```bash
composer install
cp .env.example .env
php bin/migrate.php
php -S 0.0.0.0:8080 -t public
```

Open `http://localhost:8080/admin`.
