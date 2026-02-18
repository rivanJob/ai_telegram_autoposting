# Продакшен-развёртывание

## Компоненты
- Nginx + PHP-FPM
- PostgreSQL
- Systemd-воркер + таймер планировщика
- Политическая маршрутизация WireGuard для исходящего трафика Grok

## Сервисы
```bash
sudo systemctl restart autoposter-worker
sudo systemctl restart autoposter-scheduler.timer
```

## Резервные копии
Ежедневный пример:
```bash
pg_dump -h 127.0.0.1 -U autoposter autoposter | gzip > /var/backups/autoposter-$(date +%F).sql.gz
```
Хранение: 14–30 дней. Восстановление:
```bash
gunzip -c backup.sql.gz | psql -h 127.0.0.1 -U autoposter autoposter
```

## Обновления
```bash
git pull
composer install --no-dev
php bin/migrate.php
sudo systemctl restart autoposter-worker autoposter-scheduler.timer
```
Миграции идемпотентны и используют таблицу `schema_migrations`.

## Примечания по безопасности
- Держите `.env` с правами 600
- Регулярно меняйте bootstrap-учётные данные
- Используйте HTTPS и при необходимости certbot
- Рассмотрите allowlist IP для админки на уровне nginx и приложения
