# Ubuntu 22.04/24.04 Auto Installer

1. Copy config template:
```bash
cp install/config.env.example install/config.env
nano install/config.env
```
2. Run installer:
```bash
sudo ./install/install.sh --non-interactive --force
```
3. Optional flags:
- `--dry-run`
- `--skip-wireguard`
- `--skip-nginx`
- `--with-certbot`
- `--no-services`

Installer responsibilities:
- installs php/nginx/postgres/composer (+ certbot/wireguard optional)
- creates `autoposter` linux user
- deploys app into `APP_DIR`
- creates `.env` (mode 600)
- creates DB/role and runs migrations
- bootstraps admin user with Argon2 hash and forced 2FA confirmation
- configures nginx vhost with security headers and sensitive-path deny rules
- installs/enables systemd worker + scheduler timer
- applies WireGuard egress policy when enabled
