# Ubuntu Auto Installer (22.04 / 24.04)

## 1) Prepare
```bash
cp install/config.env.example install/config.env
nano install/config.env
```

## 2) Install
```bash
sudo bash install/install.sh --non-interactive
```

Optional flags:
- `--dry-run`
- `--force`
- `--skip-wireguard`
- `--skip-nginx`
- `--with-certbot`
- `--no-services`

## 3) Validate
```bash
cd /opt/autoposter
php bin/setup.php
php bin/health.php
systemctl status autoposter-worker autoposter-scheduler.timer
```

## 4) First login
Go to `/admin/login.php`, authenticate with bootstrap user/password, then complete TOTP in authenticator app.
