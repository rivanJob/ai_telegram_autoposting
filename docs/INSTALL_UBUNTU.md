# Автоматическая установка на Ubuntu (22.04 / 24.04)

## 1) Preflight-подготовка (обязательно)

```bash
cp install/config.env.example install/config.env
nano install/config.env
```

Перед запуском установщика обязательно пройдите чеклист: `docs/PREINSTALL_CHECKLIST.md`.

## 2) Dry-run (обязательно)

```bash
sudo bash install/install.sh --dry-run --non-interactive
```

## 3) Боевая установка

```bash
sudo bash install/install.sh --non-interactive
```

Необязательные флаги:
- `--skip-wireguard`
- `--skip-nginx`
- `--with-certbot`
- `--no-services`
- `--allow-wireguard-off` (только dev/test, не для production)

## 4) Проверка

```bash
cd /opt/autoposter
php bin/setup.php
php bin/health.php
systemctl status autoposter-worker autoposter-scheduler.timer
```

## 5) Первый вход

Перейдите на `/admin/login.php`, войдите под bootstrap-пользователем и паролем, затем завершите настройку TOTP в приложении-аутентификаторе.
