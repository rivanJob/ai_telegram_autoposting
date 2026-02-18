# AI Telegram AutoPosting (Turnkey SaaS-grade)

Production-ready PHP 8.2+ autoposter with PostgreSQL, secure admin panel, scheduler/worker processing, Grok strict JSON generation, Telegram publishing, and mandatory WireGuard egress controls.

## Quick install (Ubuntu 22.04/24.04)
```bash
cp install/config.env.example install/config.env
nano install/config.env
sudo ./install/install.sh --non-interactive --force
```
Then run:
```bash
php bin/health.php
```

## Features
- PostgreSQL-first config and runtime state.
- Secure admin: argon2 password hash, mandatory TOTP confirmation, CSRF, strict session cookies, rate limit, optional IP allowlist, audit log.
- Admin UI modules: dashboard, channels, themes, prompts (versioned), schedule builder area, jobs monitor, test lab area, audit viewer.
- Scheduler idempotency via unique `(channel_id, slot_id, scheduled_at)`.
- Worker with retries + exponential backoff and stale RUNNING recovery.
- Telegram post types: text/photo/video/album/card.
- WireGuard enforcement docs + scripts (`install/wireguard.sh`).

## Core commands
```bash
composer install
php bin/migrate.php
php -S 0.0.0.0:8080 -t .
php bin/scheduler.php
php bin/worker.php --once
php bin/health.php
```

## Documentation
- `docs/INSTALL_UBUNTU.md`
- `docs/DEPLOYMENT.md`
- `docs/WIREGUARD_EGRESS.md`

## Security defaults
- `HttpOnly + Secure + SameSite=Strict` cookies
- CSP / XFO / XCTO / Referrer-Policy / Permissions-Policy
- CSRF required on POST
- secrets redacted from logs
- no secret printing in installer output
