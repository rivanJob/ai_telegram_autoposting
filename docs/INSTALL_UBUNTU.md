# Автоматическая установка на Ubuntu (22.04 / 24.04)

## 1) Подготовка
```bash
cp install/config.env.example install/config.env
nano install/config.env
```

## 2) Установка
```bash
sudo bash install/install.sh --non-interactive
```

Необязательные флаги:
- `--dry-run`
- `--force`
- `--skip-wireguard`
- `--skip-nginx`
- `--with-certbot`
- `--no-services`

## 3) Проверка
```bash
cd /opt/autoposter
php bin/setup.php
php bin/health.php
systemctl status autoposter-worker autoposter-scheduler.timer
```

## 4) Первый вход
Перейдите на `/admin/login.php`, войдите под bootstrap-пользователем и паролем, затем завершите настройку TOTP в приложении-аутентификаторе.
