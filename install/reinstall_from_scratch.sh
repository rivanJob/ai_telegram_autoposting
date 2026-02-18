#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
CONFIG_FILE="$SCRIPT_DIR/config.env"

if [[ ! -f "$CONFIG_FILE" ]]; then
  echo "Отсутствует $CONFIG_FILE"
  echo "Создайте его из шаблона: cp install/config.env.example install/config.env"
  exit 1
fi

source "$CONFIG_FILE"

required=(APP_DIR POSTGRES_DB POSTGRES_USER)
for key in "${required[@]}"; do
  [[ -n "${!key:-}" ]] || { echo "Отсутствует обязательный параметр в config.env: $key"; exit 1; }
done

if [[ "${1:-}" != "--yes" ]]; then
  cat <<EOF
ВНИМАНИЕ: это ПОЛНАЯ ПЕРЕУСТАНОВКА.
Будет удалено:
- приложение в APP_DIR: ${APP_DIR}
- база данных PostgreSQL: ${POSTGRES_DB}
- роль PostgreSQL: ${POSTGRES_USER}
- systemd-юниты autoposter-*

Запустите снова с --yes для подтверждения:
  sudo bash install/reinstall_from_scratch.sh --yes
EOF
  exit 1
fi

echo "[1/6] Остановка и удаление systemd-юнитов..."
systemctl disable --now autoposter-worker.service autoposter-scheduler.timer autoposter-scheduler.service 2>/dev/null || true
rm -f /etc/systemd/system/autoposter-worker.service \
      /etc/systemd/system/autoposter-scheduler.service \
      /etc/systemd/system/autoposter-scheduler.timer
systemctl daemon-reload

echo "[2/6] Удаление каталога приложения и логов..."
rm -rf "${APP_DIR}"
rm -rf "${LOG_DIR:-/var/log/autoposter}"

echo "[3/6] Сброс БД и роли..."
PG_ROLE_ESCAPED="${POSTGRES_USER//\'/\'\'}"
PG_DB_ESCAPED="${POSTGRES_DB//\'/\'\'}"

sudo -u postgres psql -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='${PG_DB_ESCAPED}' AND pid <> pg_backend_pid();" >/dev/null 2>&1 || true
sudo -u postgres psql -c "DROP DATABASE IF EXISTS \"${POSTGRES_DB}\";"
sudo -u postgres psql -c "DROP ROLE IF EXISTS \"${POSTGRES_USER}\";"

echo "[4/6] Обновление репозитория..."
cd "$REPO_DIR"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  git pull --ff-only
else
  echo "Пропуск git pull: ${REPO_DIR} не является git-репозиторием"
fi

echo "[5/6] Dry-run установщика..."
bash "$SCRIPT_DIR/install.sh" --dry-run --non-interactive

echo "[6/6] Боевая установка..."
bash "$SCRIPT_DIR/install.sh" --non-interactive

echo
echo "Готово. Выполните проверку:"
echo "  cd ${APP_DIR}"
echo "  php bin/setup.php"
echo "  php bin/health.php"
echo "  systemctl status autoposter-worker autoposter-scheduler.timer --no-pager"
