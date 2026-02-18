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

## Troubleshooting

- Ошибка `generation expression is not immutable` при `php bin/migrate.php`:
  - обновите код до актуальной версии (`git pull`),
  - убедитесь, что в `db/migrations/001_initial.sql` строка `run_at_key` — это `BIGINT NOT NULL` (а не `GENERATED ALWAYS AS ...`),
  - если это новая установка без данных: пересоздайте БД и повторите миграцию.
- Если `git pull` возвращает `not a git repository`:
  - разверните проект заново из git-клона или скопируйте актуальный код в директорию установки перед запуском install-скрипта.
- Ошибка `no password supplied`:
  - убедитесь, что в `/opt/autoposter/.env` заполнены `DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS`.
- Если `autoposter-worker` падает из-за отсутствия таблиц (`relation "jobs" does not exist`):
  - завершите миграции (`php bin/migrate.php`),
  - затем перезапустите сервисы `systemctl restart autoposter-worker autoposter-scheduler.timer`.

## Полная переустановка (с удалением приложения и БД)

Если окружение сильно повреждено (частичные миграции, битые systemd-юниты, сломанный `.env`), используйте автоматический сценарий:

```bash
cd /path/to/repo
cp install/config.env.example install/config.env   # если ещё не создан
nano install/config.env
sudo bash install/reinstall_from_scratch.sh --yes
```

Скрипт удаляет старый `APP_DIR`, дропает `POSTGRES_DB` и `POSTGRES_USER`, удаляет systemd-юниты `autoposter-*`, затем делает `git pull`, `install.sh --dry-run`, `install.sh --non-interactive`.
