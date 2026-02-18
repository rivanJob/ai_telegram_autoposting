#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
CONFIG_FILE="$ROOT_DIR/install/config.env"
DRY_RUN=0
SKIP_WG=0
SKIP_NGINX=0
WITH_CERTBOT=0
NO_SERVICES=0

run(){
  if [[ "$DRY_RUN" -eq 1 ]]; then echo "[dry-run] $*"; else eval "$*"; fi
}

for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --non-interactive|--force) ;;
    --skip-wireguard) SKIP_WG=1 ;;
    --skip-nginx) SKIP_NGINX=1 ;;
    --with-certbot) WITH_CERTBOT=1 ;;
    --no-services) NO_SERVICES=1 ;;
    *) echo "Unknown arg: $arg"; exit 1 ;;
  esac
done

[[ -f "$CONFIG_FILE" ]] || { echo "Missing install/config.env"; exit 1; }
set -a; source "$CONFIG_FILE"; set +a

: "${APP_DIR:?}" "${LOG_DIR:?}" "${POSTGRES_DB:?}" "${POSTGRES_USER:?}" "${POSTGRES_PASS:?}" "${ADMIN_BOOTSTRAP_USER:?}" "${ADMIN_BOOTSTRAP_PASS:?}"

run "apt-get update"
run "apt-get install -y nginx php8.2-fpm php8.2-pgsql php8.2-curl php8.2-mbstring php8.2-intl php8.2-zip composer postgresql postgresql-contrib"
if [[ "$WITH_CERTBOT" -eq 1 || "${CERTBOT_ENABLE:-0}" == "1" ]]; then
  run "apt-get install -y certbot python3-certbot-nginx"
fi
if [[ "${WIREGUARD_MODE:-off}" != "off" && "$SKIP_WG" -eq 0 ]]; then
  run "apt-get install -y wireguard-tools iptables"
fi

run "id -u autoposter >/dev/null 2>&1 || useradd --system --home '$APP_DIR' --shell /usr/sbin/nologin autoposter"
run "mkdir -p '$APP_DIR' '$LOG_DIR'"
run "rsync -a --delete --exclude '.git' '$ROOT_DIR/' '$APP_DIR/'"
run "cp '$APP_DIR/.env.example' '$APP_DIR/.env'"

replace_env(){
  local key="$1" value="$2"
  run "sed -i \"s|^${key}=.*|${key}=${value//|/\\|}|\" '$APP_DIR/.env'"
}

replace_env APP_TIMEZONE "$TIMEZONE"
replace_env DB_HOST "$POSTGRES_HOST"
replace_env DB_PORT "$POSTGRES_PORT"
replace_env DB_DATABASE "$POSTGRES_DB"
replace_env DB_USERNAME "$POSTGRES_USER"
replace_env DB_PASSWORD "$POSTGRES_PASS"
replace_env TELEGRAM_BOT_TOKEN "$TELEGRAM_BOT_TOKEN"
replace_env TELEGRAM_DEFAULT_CHANNEL "$TELEGRAM_CHANNEL_ID"
replace_env GROK_API_URL "$GROK_API_URL"
replace_env GROK_API_KEY "$GROK_API_KEY"
replace_env GROK_MODEL "$GROK_MODEL"
replace_env WIREGUARD_MODE "$WIREGUARD_MODE"
replace_env WG_INTERFACE "$WG_INTERFACE"
replace_env ADMIN_IP_ALLOWLIST "$ADMIN_IP_ALLOWLIST"
replace_env LOG_FILE "$LOG_DIR/app.log"

run "chmod 600 '$APP_DIR/.env'"
run "chown -R autoposter:autoposter '$APP_DIR' '$LOG_DIR'"
run "sudo -u postgres psql -tc \"SELECT 1 FROM pg_roles WHERE rolname='${POSTGRES_USER}'\" | grep -q 1 || sudo -u postgres psql -c \"CREATE ROLE ${POSTGRES_USER} LOGIN PASSWORD '${POSTGRES_PASS}';\""
run "sudo -u postgres psql -tc \"SELECT 1 FROM pg_database WHERE datname='${POSTGRES_DB}'\" | grep -q 1 || sudo -u postgres psql -c \"CREATE DATABASE ${POSTGRES_DB} OWNER ${POSTGRES_USER};\""

run "cd '$APP_DIR' && composer install --no-dev --optimize-autoloader"
run "cd '$APP_DIR' && php bin/migrate.php"

HASH_CMD="password_hash(getenv('P'), PASSWORD_ARGON2ID);"
ADMIN_HASH=$(P="$ADMIN_BOOTSTRAP_PASS" php -r "echo $HASH_CMD")
TOTP_SECRET=$(php -r 'echo substr(str_shuffle(str_repeat("ABCDEFGHIJKLMNOPQRSTUVWXYZ234567",2)),0,32);')
run "psql 'postgresql://${POSTGRES_USER}:${POSTGRES_PASS}@${POSTGRES_HOST}:${POSTGRES_PORT}/${POSTGRES_DB}' -c \"INSERT INTO admin_users(username,password_hash,totp_secret,enabled,created_at,updated_at) VALUES ('${ADMIN_BOOTSTRAP_USER}','${ADMIN_HASH}','${TOTP_SECRET}',true,NOW(),NOW()) ON CONFLICT (username) DO NOTHING;\""

if [[ "$SKIP_NGINX" -eq 0 && "${NGINX_ENABLE:-1}" == "1" ]]; then
  cat > /tmp/autoposter.nginx <<NG
server {
  listen 80;
  server_name ${ADMIN_DOMAIN:-$DOMAIN};
  root ${APP_DIR}/public;
  index index.php;

  add_header X-Frame-Options DENY always;
  add_header X-Content-Type-Options nosniff always;
  add_header Referrer-Policy no-referrer always;
  add_header Permissions-Policy "geolocation=(),camera=(),microphone=()" always;

  location ~ /(\\.env|db|install|vendor|docs|config) { deny all; }
  location / { try_files \$uri \$uri/ /admin/index.php?\$query_string; }
  location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.2-fpm.sock; }
}
NG
  run "mv /tmp/autoposter.nginx /etc/nginx/sites-available/autoposter"
  run "ln -sf /etc/nginx/sites-available/autoposter /etc/nginx/sites-enabled/autoposter"
  run "nginx -t"
  run "systemctl restart nginx"
  if [[ "$WITH_CERTBOT" -eq 1 || "${CERTBOT_ENABLE:-0}" == "1" ]]; then
    run "certbot --nginx -d ${ADMIN_DOMAIN:-$DOMAIN} --non-interactive --agree-tos -m admin@${DOMAIN}"
  fi
fi

run "cp '$APP_DIR/install/systemd/'*.service /etc/systemd/system/"
run "cp '$APP_DIR/install/systemd/'*.timer /etc/systemd/system/"
run "sed -i \"s|/opt/autoposter|$APP_DIR|g\" /etc/systemd/system/autoposter-*.service"
run "systemctl daemon-reload"
if [[ "$NO_SERVICES" -eq 0 ]]; then
  run "systemctl enable --now autoposter-worker.service"
  run "systemctl enable --now autoposter-scheduler.timer"
fi

if [[ "${WIREGUARD_MODE:-off}" != "off" && "$SKIP_WG" -eq 0 ]]; then
  run "wg-quick up '${WG_INTERFACE}' || true"
  run "WG_INTERFACE='${WG_INTERFACE}' '$APP_DIR/install/wireguard.sh' '${WIREGUARD_MODE}'"
fi

echo "Install completed. Next steps:"
echo "1) Open admin URL and complete TOTP bootstrap."
echo "2) Run php $APP_DIR/bin/health.php"
echo "3) Verify wireguard egress if enabled."
