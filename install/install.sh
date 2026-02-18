#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_FILE="$ROOT_DIR/install/config.env"
DRY_RUN=0
NON_INTERACTIVE=0
FORCE=0
SKIP_WIREGUARD=0
SKIP_NGINX=0
WITH_CERTBOT=0
NO_SERVICES=0

for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --non-interactive) NON_INTERACTIVE=1 ;;
    --force) FORCE=1 ;;
    --skip-wireguard) SKIP_WIREGUARD=1 ;;
    --skip-nginx) SKIP_NGINX=1 ;;
    --with-certbot) WITH_CERTBOT=1 ;;
    --no-services) NO_SERVICES=1 ;;
    *) echo "Unknown flag: $arg"; exit 1 ;;
  esac
done

[[ -f "$CONFIG_FILE" ]] || { echo "Missing install/config.env"; exit 1; }
# shellcheck disable=SC1090
source "$CONFIG_FILE"

run() {
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "[dry-run] $*"
  else
    eval "$@"
  fi
}

echo "Installing autoposter to ${APP_DIR}"
run "apt-get update"
run "apt-get install -y nginx php-fpm php-cli php-pgsql php-curl php-mbstring php-intl php-zip composer postgresql postgresql-client"
if [[ "$WITH_CERTBOT" -eq 1 || "${CERTBOT_ENABLE:-0}" == "1" ]]; then
  run "apt-get install -y certbot python3-certbot-nginx"
fi
if [[ "${WIREGUARD_MODE:-off}" != "off" && "$SKIP_WIREGUARD" -eq 0 ]]; then
  run "apt-get install -y wireguard-tools"
fi

id autoposter >/dev/null 2>&1 || run "useradd --system --home ${APP_DIR} --shell /usr/sbin/nologin autoposter"
run "mkdir -p ${APP_DIR} ${LOG_DIR}"
run "rsync -a --delete --exclude .git ${ROOT_DIR}/ ${APP_DIR}/"

run "cp ${APP_DIR}/.env.example ${APP_DIR}/.env"
run "sed -i 's|^APP_TIMEZONE=.*|APP_TIMEZONE=${TIMEZONE}|' ${APP_DIR}/.env"
run "sed -i 's|^ADMIN_PATH=.*|ADMIN_PATH=${ADMIN_PATH}|' ${APP_DIR}/.env"
run "sed -i 's|^ADMIN_IP_ALLOWLIST=.*|ADMIN_IP_ALLOWLIST=${ADMIN_IP_ALLOWLIST}|' ${APP_DIR}/.env"
run "sed -i 's|^DB_HOST=.*|DB_HOST=${POSTGRES_HOST}|' ${APP_DIR}/.env"
run "sed -i 's|^DB_PORT=.*|DB_PORT=${POSTGRES_PORT}|' ${APP_DIR}/.env"
run "sed -i 's|^DB_NAME=.*|DB_NAME=${POSTGRES_DB}|' ${APP_DIR}/.env"
run "sed -i 's|^DB_USER=.*|DB_USER=${POSTGRES_USER}|' ${APP_DIR}/.env"
run "sed -i 's|^DB_PASS=.*|DB_PASS=${POSTGRES_PASS}|' ${APP_DIR}/.env"
run "sed -i 's|^TELEGRAM_BOT_TOKEN=.*|TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}|' ${APP_DIR}/.env"
run "sed -i 's|^TELEGRAM_CHANNEL_ID=.*|TELEGRAM_CHANNEL_ID=${TELEGRAM_CHANNEL_ID}|' ${APP_DIR}/.env"
run "sed -i 's|^GROK_API_URL=.*|GROK_API_URL=${GROK_API_URL}|' ${APP_DIR}/.env"
run "sed -i 's|^GROK_API_KEY=.*|GROK_API_KEY=${GROK_API_KEY}|' ${APP_DIR}/.env"
run "sed -i 's|^GROK_MODEL=.*|GROK_MODEL=${GROK_MODEL}|' ${APP_DIR}/.env"
run "sed -i 's|^WIREGUARD_MODE=.*|WIREGUARD_MODE=${WIREGUARD_MODE}|' ${APP_DIR}/.env"
run "sed -i 's|^WG_INTERFACE=.*|WG_INTERFACE=${WG_INTERFACE}|' ${APP_DIR}/.env"
run "chmod 600 ${APP_DIR}/.env"
run "chown -R autoposter:autoposter ${APP_DIR} ${LOG_DIR}"

run "sudo -u postgres psql -tc \"SELECT 1 FROM pg_roles WHERE rolname='${POSTGRES_USER}'\" | grep -q 1 || sudo -u postgres psql -c \"CREATE ROLE ${POSTGRES_USER} LOGIN PASSWORD '${POSTGRES_PASS}'\""
run "sudo -u postgres psql -tc \"SELECT 1 FROM pg_database WHERE datname='${POSTGRES_DB}'\" | grep -q 1 || sudo -u postgres createdb -O ${POSTGRES_USER} ${POSTGRES_DB}"

run "cd ${APP_DIR} && composer install --no-dev --optimize-autoloader"
run "cd ${APP_DIR} && php bin/migrate.php"
HASH_CMD="php -r 'echo password_hash(getenv(\"BOOT_PASS\"), PASSWORD_ARGON2ID), PHP_EOL;'"
if [[ "$DRY_RUN" -eq 0 ]]; then
  ADMIN_HASH=$(BOOT_PASS="$ADMIN_BOOTSTRAP_PASS" bash -c "$HASH_CMD")
  sudo -u "${POSTGRES_USER}" psql -h "${POSTGRES_HOST}" -p "${POSTGRES_PORT}" -d "${POSTGRES_DB}" -c "INSERT INTO admin_users (username,password_hash,totp_secret,require_totp_setup) VALUES ('${ADMIN_BOOTSTRAP_USER}','${ADMIN_HASH}','JBSWY3DPEHPK3PXP',true) ON CONFLICT (username) DO NOTHING;"
fi

if [[ "$SKIP_NGINX" -eq 0 && "${NGINX_ENABLE:-1}" == "1" ]]; then
cat > /etc/nginx/sites-available/autoposter.conf <<EOF
server {
    listen 80;
    server_name ${ADMIN_DOMAIN:-$DOMAIN};
    root ${APP_DIR}/public;
    index index.php;
    location / { try_files \$uri /index.php?\$query_string; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.2-fpm.sock; }
    location ~ /(\.env|db|install|vendor|docs|config) { deny all; }
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;
    add_header Referrer-Policy no-referrer always;
    add_header Permissions-Policy "geolocation=(),camera=(),microphone=()" always;
}
EOF
  run "ln -sf /etc/nginx/sites-available/autoposter.conf /etc/nginx/sites-enabled/autoposter.conf"
  run "nginx -t && systemctl reload nginx"
  if [[ "$WITH_CERTBOT" -eq 1 || "${CERTBOT_ENABLE:-0}" == "1" ]]; then
    run "certbot --nginx -d ${ADMIN_DOMAIN:-$DOMAIN} --non-interactive --agree-tos -m admin@${DOMAIN}"
  fi
fi

run "cp ${APP_DIR}/install/systemd/* /etc/systemd/system/"
run "sed -i 's|{{APP_DIR}}|${APP_DIR}|g' /etc/systemd/system/autoposter-*.service"
if [[ "$NO_SERVICES" -eq 0 ]]; then
  run "systemctl daemon-reload"
  run "systemctl enable --now autoposter-worker.service autoposter-scheduler.timer"
fi

if [[ "${WIREGUARD_MODE:-off}" != "off" && "$SKIP_WIREGUARD" -eq 0 ]]; then
  run "wg-quick up ${WG_INTERFACE} || true"
  run "bash ${APP_DIR}/install/wireguard.sh ${WIREGUARD_MODE} ${WG_INTERFACE}"
fi

echo "Installation complete. Next steps:"
echo "1) php ${APP_DIR}/bin/setup.php"
echo "2) php ${APP_DIR}/bin/health.php"
echo "3) Login at https://${ADMIN_DOMAIN:-$DOMAIN}${ADMIN_PATH} and rotate bootstrap password"
