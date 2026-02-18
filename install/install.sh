#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
CONFIG_FILE="$SCRIPT_DIR/config.env"
DRY_RUN=0; SKIP_WIREGUARD=0; SKIP_NGINX=0; WITH_CERTBOT=0; NO_SERVICES=0
run(){ [[ "$DRY_RUN" -eq 1 ]] && echo "[dry-run] $*" || eval "$@"; }
while [[ $# -gt 0 ]]; do case "$1" in
  --dry-run) DRY_RUN=1;; --non-interactive|--force) :;; --skip-wireguard) SKIP_WIREGUARD=1;; --skip-nginx) SKIP_NGINX=1;; --with-certbot) WITH_CERTBOT=1;; --no-services) NO_SERVICES=1;; *) echo "Unknown flag: $1"; exit 1;; esac; shift; done
[[ -f "$CONFIG_FILE" ]] || { echo "Missing $CONFIG_FILE"; exit 1; }
source "$CONFIG_FILE"

required=(DOMAIN TIMEZONE APP_DIR LOG_DIR POSTGRES_HOST POSTGRES_PORT POSTGRES_DB POSTGRES_USER POSTGRES_PASS TELEGRAM_BOT_TOKEN GROK_API_URL GROK_API_KEY GROK_MODEL ADMIN_BOOTSTRAP_USER ADMIN_BOOTSTRAP_PASS WIREGUARD_MODE WG_INTERFACE)
for key in "${required[@]}"; do [[ -n "${!key:-}" ]] || { echo "Missing required config: $key"; exit 1; }; done

export DEBIAN_FRONTEND=noninteractive
PKGS=(nginx php8.2-fpm php8.2-pgsql php8.2-curl php8.2-mbstring php8.2-intl php8.2-zip composer postgresql postgresql-client rsync)
[[ "$WITH_CERTBOT" -eq 1 || "${CERTBOT_ENABLE:-0}" == "1" ]] && PKGS+=(certbot python3-certbot-nginx)
[[ "$WIREGUARD_MODE" != "off" && "$SKIP_WIREGUARD" -eq 0 ]] && PKGS+=(wireguard-tools)
run "apt-get update"; run "apt-get install -y ${PKGS[*]}"

id autoposter >/dev/null 2>&1 || run "useradd --system --home ${APP_DIR} --shell /usr/sbin/nologin autoposter"
run "mkdir -p ${APP_DIR} ${LOG_DIR}"
[[ "$REPO_DIR" != "$APP_DIR" ]] && run "rsync -a --delete --exclude .git ${REPO_DIR}/ ${APP_DIR}/"
run "cp -n ${APP_DIR}/.env.example ${APP_DIR}/.env"

write_env(){ key="$1"; val="$2"; run "grep -q '^${key}=' ${APP_DIR}/.env && sed -i 's|^${key}=.*|${key}=${val}|' ${APP_DIR}/.env || echo '${key}=${val}' >> ${APP_DIR}/.env"; }
write_env APP_TIMEZONE "$TIMEZONE"; write_env DB_HOST "$POSTGRES_HOST"; write_env DB_PORT "$POSTGRES_PORT"; write_env DB_NAME "$POSTGRES_DB"; write_env DB_USER "$POSTGRES_USER"; write_env DB_PASS "$POSTGRES_PASS"
write_env TELEGRAM_BOT_TOKEN "$TELEGRAM_BOT_TOKEN"; write_env TELEGRAM_DEFAULT_CHANNEL "$TELEGRAM_CHANNEL_ID"; write_env GROK_API_URL "$GROK_API_URL"; write_env GROK_API_KEY "$GROK_API_KEY"; write_env GROK_MODEL "$GROK_MODEL"
write_env WIREGUARD_MODE "$WIREGUARD_MODE"; write_env WG_INTERFACE "$WG_INTERFACE"; write_env ADMIN_IP_ALLOWLIST "${ADMIN_IP_ALLOWLIST:-}"; run "chmod 600 ${APP_DIR}/.env"

run "sudo -u postgres psql -tc \"SELECT 1 FROM pg_roles WHERE rolname='${POSTGRES_USER}'\" | grep -q 1 || sudo -u postgres psql -c \"CREATE ROLE ${POSTGRES_USER} LOGIN PASSWORD '${POSTGRES_PASS}';\""
run "sudo -u postgres psql -tc \"SELECT 1 FROM pg_database WHERE datname='${POSTGRES_DB}'\" | grep -q 1 || sudo -u postgres createdb -O ${POSTGRES_USER} ${POSTGRES_DB}"
run "cd ${APP_DIR} && composer install --no-dev --optimize-autoloader"
run "cd ${APP_DIR} && php bin/migrate.php"

if [[ "$DRY_RUN" -eq 0 ]]; then
  ADMIN_BOOTSTRAP_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_ARGON2ID);' "$ADMIN_BOOTSTRAP_PASS")"
  php <<PHP
<?php
require '${APP_DIR}/vendor/autoload.php';
Autoposter\Core\App::boot('${APP_DIR}');
\$_ENV['DB_HOST']='${POSTGRES_HOST}';\$_ENV['DB_PORT']='${POSTGRES_PORT}';\$_ENV['DB_NAME']='${POSTGRES_DB}';\$_ENV['DB_USER']='${POSTGRES_USER}';\$_ENV['DB_PASS']='${POSTGRES_PASS}';
\$pdo=Autoposter\Core\App::db()->getConnection();
\$totp=(new Autoposter\Services\TotpService())->generateSecret();
\$stmt=\$pdo->prepare('INSERT INTO admin_users(email,password_hash,totp_secret,must_setup_totp) VALUES(:e,:p,:t,TRUE) ON CONFLICT(email) DO UPDATE SET password_hash=EXCLUDED.password_hash,updated_at=NOW()');
\$stmt->execute([':e'=>'${ADMIN_BOOTSTRAP_USER}',':p'=>'${ADMIN_BOOTSTRAP_HASH}',':t'=>\$totp]);
PHP
fi

if [[ "$SKIP_NGINX" -eq 0 && "${NGINX_ENABLE:-1}" == "1" ]]; then
  [[ "$DRY_RUN" -eq 0 ]] && DOMAIN="$DOMAIN" ADMIN_DOMAIN="${ADMIN_DOMAIN:-}" APP_DIR="$APP_DIR" envsubst < "${APP_DIR}/config/nginx.autoposter.conf" > /etc/nginx/sites-available/autoposter.conf
  run "ln -sf /etc/nginx/sites-available/autoposter.conf /etc/nginx/sites-enabled/autoposter.conf"
  run "nginx -t"; run "systemctl reload nginx"
fi
run "cp ${APP_DIR}/install/systemd/autoposter-worker.service /etc/systemd/system/"
run "cp ${APP_DIR}/install/systemd/autoposter-scheduler.service /etc/systemd/system/"
run "cp ${APP_DIR}/install/systemd/autoposter-scheduler.timer /etc/systemd/system/"
[[ "$NO_SERVICES" -eq 0 ]] && run "systemctl daemon-reload && systemctl enable --now autoposter-worker.service autoposter-scheduler.timer"

if [[ "$WIREGUARD_MODE" != "off" && "$SKIP_WIREGUARD" -eq 0 ]]; then
  run "wg-quick up ${WG_INTERFACE} || true"
  run "bash ${APP_DIR}/install/wireguard.sh ${APP_DIR}/install/config.env"
fi

echo "NEXT STEPS:"
echo "- cd ${APP_DIR} && php bin/setup.php"
echo "- Open https://${DOMAIN}/admin/login.php"
