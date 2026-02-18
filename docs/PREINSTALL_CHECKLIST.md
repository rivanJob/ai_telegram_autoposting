# Preflight checklist перед `install/install.sh`

Используйте этот список как обязательную проверку production-готовности перед запуском установщика.

## 1) Конфигурация установщика

- [ ] Создан файл `install/config.env` из шаблона:
  ```bash
  cp install/config.env.example install/config.env
  ```
- [ ] Все обязательные переменные заполнены реальными значениями (без пустых строк).
- [ ] Все значения-заглушки заменены:
  - `change_me`, `change_me_strong`
  - `example.com`, `admin@example.com`, `@example_channel`
  - тестовые/временные токены API

## 2) WireGuard egress (обязателен для production)

- [ ] В production `WIREGUARD_MODE` не равен `off`.
- [ ] `WG_INTERFACE` существует на сервере (обычно `wg0`) и конфиг интерфейса подготовлен.
- [ ] Ознакомились с `docs/WIREGUARD_EGRESS.md` и настроили policy routing для воркера.

## 3) Инфраструктурная готовность

- [ ] DNS-записи доменов (`DOMAIN`, `ADMIN_DOMAIN`) указывают на сервер.
- [ ] Открыты необходимые порты (как минимум 80/443; SSH по вашей политике).
- [ ] Проверены ресурсы сервера (CPU/RAM/диск) под нагрузку генерации и публикации.
- [ ] Доступны внешние интеграции: Telegram Bot API и Grok API.

## 4) Dry-run (обязателен)

- [ ] Выполнен dry-run установщика:
  ```bash
  sudo bash install/install.sh --dry-run --non-interactive
  ```
- [ ] Dry-run завершился без ошибок обязательных проверок.

## 5) Боевой запуск

- [ ] После успешного dry-run выполнен реальный запуск:
  ```bash
  sudo bash install/install.sh --non-interactive
  ```
- [ ] Проверка после установки:
  ```bash
  php bin/setup.php
  php bin/health.php
  ```

## 6) Безопасные значения DB user/password

- [ ] Для `POSTGRES_USER` и `POSTGRES_PASS` выбраны безопасные значения без экзотических спецсимволов, **или**
- [ ] Вы осознанно протестировали сценарий с нестандартными символами в preflight и подтверждаете совместимость.
