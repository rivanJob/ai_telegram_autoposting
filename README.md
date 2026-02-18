# AI Telegram Autoposting

Production-ready autoposter SaaS stack with PostgreSQL, secure admin UI (password hash + TOTP + CSRF + rate limiting), scheduler/worker architecture, strict-JSON Grok integration, Telegram publishing, and Ubuntu turnkey installer.

## Quick install (Ubuntu 22.04/24.04)
1. Copy installer config:
   ```bash
   cp install/config.env.example install/config.env
   ```
2. Edit `install/config.env` and fill secrets.
3. Run installer:
   ```bash
   sudo bash install/install.sh --non-interactive
   ```
4. Validate setup:
   ```bash
   php bin/setup.php
   php bin/health.php
   ```

Detailed instructions: `docs/INSTALL_UBUNTU.md`, `docs/DEPLOYMENT.md`, `docs/WIREGUARD_EGRESS.md`.

## Development bootstrap
```bash
cp .env.example .env
composer install
php bin/migrate.php
php -S 127.0.0.1:8080 -t public
```

## Core architecture
- **Admin UI:** `public/index.php` + `admin/views/*`
- **Scheduler:** `bin/scheduler.php`
- **Worker:** `bin/worker.php`
- **Migrations:** `db/migrations/*.sql`
- **WireGuard policy scripts:** `install/wireguard.sh`, `install/unwireguard.sh`

## Supported Telegram post types
- text (`sendMessage`)
- photo (`sendPhoto`)
- video (`sendVideo`)
- album (`sendMediaGroup`)
- card (`sendMessage` with inline keyboard)
