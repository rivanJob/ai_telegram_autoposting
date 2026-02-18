# Ubuntu 22.04/24.04 Turnkey Install

## 1. Prepare config
```bash
cp install/config.env.example install/config.env
nano install/config.env
```

## 2. Run installer
```bash
sudo bash install/install.sh --non-interactive
```

Useful flags:
- `--dry-run`
- `--force`
- `--skip-wireguard`
- `--skip-nginx`
- `--with-certbot`
- `--no-services`

## 3. Post install checks
```bash
php bin/setup.php
php bin/health.php
systemctl status autoposter-worker autoposter-scheduler.timer
```

## 4. First login
Open `https://ADMIN_DOMAIN_or_DOMAIN/ADMIN_PATH`, sign in with bootstrap credentials from config, and immediately rotate password + TOTP secret.
