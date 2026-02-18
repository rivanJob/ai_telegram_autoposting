# AI Telegram Autoposting

Готовый к продакшену Telegram-автопостер уровня SaaS с защищённой админ-панелью, PostgreSQL, генерацией JSON через Grok, архитектурой планировщик/воркер и принудительным исходящим трафиком через WireGuard.

## Быстрая установка (Ubuntu 22.04/24.04)

1. Скопируйте конфиг установщика:
   ```bash
   cp install/config.env.example install/config.env
   ```
2. Пройдите preflight: `docs/PREINSTALL_CHECKLIST.md` (обязательно перед первым запуском).
3. Отредактируйте `install/config.env`: укажите домен, БД, Telegram, Grok и параметры WireGuard.
4. Выполните dry-run:
   ```bash
   sudo bash install/install.sh --dry-run --non-interactive
   ```
5. Запустите установщик:
   ```bash
   sudo bash install/install.sh --non-interactive
   ```
6. Проверьте развёртывание:
   ```bash
   php bin/setup.php
   php bin/health.php
   ```

См. также:
- `docs/INSTALL_UBUNTU.md`
- `docs/PREINSTALL_CHECKLIST.md`
- `docs/DEPLOYMENT.md`
- `docs/WIREGUARD_EGRESS.md`

## Локальная разработка

```bash
composer install
cp .env.example .env
php bin/migrate.php
php -S 0.0.0.0:8080 -t public
```

Откройте `http://localhost:8080/admin`.
