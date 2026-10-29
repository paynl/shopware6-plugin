#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ACCEPTANCE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${ACCEPTANCE_DIR}/.env"
DIAGNOSTICS_DIR="${ACCEPTANCE_DIR}/diagnostics"
TUNNEL_LOG="${ACCEPTANCE_DIR}/diagnostics/tunnel.log"
TUNNEL_PID_FILE="${ACCEPTANCE_DIR}/diagnostics/tunnel.pid"

SHOPWARE_VERSION="${SHOPWARE_VERSION:-6.7.10.0}"
SHOPWARE_PORT="${SHOPWARE_PORT:-8080}"
SHOPWARE_WAIT_SECONDS="${SHOPWARE_WAIT_SECONDS:-600}"

# Compose names the container from the project. A fixed container_name collides
# across checkouts, ports, and GitHub runs that share one Docker daemon.
if [[ -z "${COMPOSE_PROJECT_NAME:-}" ]]; then
    if [[ -n "${GITHUB_RUN_ID:-}" ]]; then
        COMPOSE_PROJECT_NAME="paynl-sw6-e2e-${GITHUB_RUN_ID}"
    else
        COMPOSE_PROJECT_NAME="paynl-sw6-e2e-$(cksum <<<"${ACCEPTANCE_DIR}:${SHOPWARE_PORT}" | awk '{print $1}')"
    fi
fi
export COMPOSE_PROJECT_NAME SHOPWARE_VERSION SHOPWARE_PORT

COMPOSE=(docker compose --project-name "${COMPOSE_PROJECT_NAME}" -f "${ACCEPTANCE_DIR}/docker-compose.yml")

log() {
    printf '%s %s\n' "$(date '+%H:%M:%S')" "$*"
}

fail() {
    log "error: $*"
    exit 1
}

lock_env_file() {
    [[ -f "${ENV_FILE}" && -O "${ENV_FILE}" ]] || return 0
    chmod 600 "${ENV_FILE}" || fail "Could not restrict ${ENV_FILE} to mode 600"
}

ensure_env_file() {
    if [[ ! -f "${ENV_FILE}" ]]; then
        cp "${ACCEPTANCE_DIR}/.env.example" "${ENV_FILE}"
    fi
    lock_env_file
}

set_env_value() {
    local key="$1"
    local value="$2"
    ensure_env_file
    if grep -q "^${key}=" "${ENV_FILE}"; then
        local escaped
        escaped="$(printf '%s' "${value}" | sed 's/[&|]/\\&/g')"
        if sed --version >/dev/null 2>&1; then
            sed -i "s|^${key}=.*|${key}=${escaped}|" "${ENV_FILE}"
        else
            sed -i '' "s|^${key}=.*|${key}=${escaped}|" "${ENV_FILE}"
        fi
    else
        printf '%s=%s\n' "${key}" "${value}" >> "${ENV_FILE}"
    fi
    lock_env_file
    export "${key}=${value}"
}

load_env_file() {
    ensure_env_file
    while IFS= read -r line || [[ -n "${line}" ]]; do
        [[ "${line}" =~ ^[[:space:]]*# ]] && continue
        [[ -z "${line// }" ]] && continue
        [[ "${line}" != *=* ]] && continue
        local key="${line%%=*}"
        local value="${line#*=}"
        value="${value%\"}"
        value="${value#\"}"
        value="${value%\'}"
        value="${value#\'}"
        if [[ -z "${!key:-}" ]]; then
            export "${key}=${value}"
        fi
    done < "${ENV_FILE}"
}

require_pay_secrets() {
    [[ -n "${PAY_TOKEN_CODE:-}" ]] || fail "PAY_TOKEN_CODE is not set"
    [[ -n "${PAY_API_TOKEN:-}" ]] || fail "PAY_API_TOKEN is not set"
    [[ -n "${PAY_SERVICE_ID:-}" ]] || fail "PAY_SERVICE_ID is not set"
}

shopware_container_id() {
    local id
    id="$("${COMPOSE[@]}" ps -aq shopware 2>/dev/null || true)"
    printf '%s' "${id%%$'\n'*}"
}

shopware_container() {
    local id
    id="$(shopware_container_id)"
    if [[ -z "${id}" ]]; then
        log "error: Shopware container for project ${COMPOSE_PROJECT_NAME} does not exist" >&2
        exit 1
    fi
    printf '%s' "${id}"
}

shopware_exec() {
    docker exec -u dockware -i "$(shopware_container)" bash -lc "$*"
}

remove_legacy_container() {
    if docker inspect paynl_sw6_e2e >/dev/null 2>&1; then
        log "Removing leftover container paynl_sw6_e2e"
        docker rm -f paynl_sw6_e2e >/dev/null
    fi
}

http_code() {
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 2 --max-time 5 "$1" 2>/dev/null || true)"
    printf '%s' "${code:-000}"
}

wait_for_dockware_ready() {
    log "Waiting for Dockware to unpack Shopware (Apache is down during this; connection resets are normal)"
    local elapsed=0
    while true; do
        if docker logs "$(shopware_container)" 2>&1 | grep -q 'container IS READY'; then
            log "Dockware reports the container is ready"
            # Dockware chowns the bind mount, and the image user cannot chown it back.
            # Restore only what the host writes. Skip node_modules: Docker Desktop rejects that chown.
            local uid gid acceptance
            uid="$(id -u)"
            gid="$(id -g)"
            acceptance="/var/www/html/custom/plugins/PaynlPaymentShopware6/tests/Acceptance"
            docker exec -u root "$(shopware_container)" bash -lc \
                "chown ${uid}:${gid} '${acceptance}' '${acceptance}/.env'
                 if [[ -d '${acceptance}/diagnostics' ]]; then chown -R ${uid}:${gid} '${acceptance}/diagnostics'; fi" \
                >/dev/null 2>&1 || true
            lock_env_file
            if [[ -f "${ENV_FILE}" && ! -O "${ENV_FILE}" ]]; then
                fail "Could not restore ownership of ${ENV_FILE}; refusing to loosen its permissions"
            fi
            sleep 3
            return
        fi
        if (( elapsed >= SHOPWARE_WAIT_SECONDS )); then
            "${COMPOSE[@]}" logs --tail 80 shopware || true
            fail "Dockware did not finish startup within ${SHOPWARE_WAIT_SECONDS}s"
        fi
        sleep 5
        elapsed=$((elapsed + 5))
        log "still unpacking Dockware (${elapsed}s)"
    done
}

wait_for_shopware() {
    wait_for_dockware_ready
    local health="http://127.0.0.1:${SHOPWARE_PORT}/api/_info/health-check"
    local storefront="http://127.0.0.1:${SHOPWARE_PORT}/"
    log "Waiting for Shopware HTTP on port ${SHOPWARE_PORT}"
    local elapsed=0
    local health_code="000"
    local store_code="000"
    while true; do
        health_code="$(http_code "${health}")"
        store_code="$(http_code "${storefront}")"
        if [[ "${health_code}" == "200" || "${health_code}" == "204" ]]; then
            break
        fi
        # 500 is Shopware's "unknown domain" help page; 200 is a mapped storefront.
        if [[ "${store_code}" == "200" || "${store_code}" == "400" || "${store_code}" == "500" ]]; then
            log "Shopware HTTP is up (storefront ${store_code}, health-check ${health_code})"
            break
        fi
        if (( elapsed >= 120 )); then
            "${COMPOSE[@]}" logs --tail 80 shopware || true
            fail "Shopware HTTP did not become ready within 120s after Dockware startup (health-check ${health_code}, storefront ${store_code})"
        fi
        sleep 5
        elapsed=$((elapsed + 5))
        log "still waiting for HTTP (${elapsed}s, health-check ${health_code}, storefront ${store_code})"
    done
    log "Shopware is responding"
}

shopware_api_url() {
    # Payment-method install only needs the shop. The trycloudflare name is for PAY. and the browser.
    local container_id
    container_id="$(shopware_container_id)"
    if [[ -n "${container_id}" ]] && docker inspect -f '{{.State.Running}}' "${container_id}" 2>/dev/null | grep -qx true; then
        printf 'http://127.0.0.1:%s' "${SHOPWARE_PORT}"
        return
    fi
    local base_url="${ADMIN_API_URL:?ADMIN_API_URL is not set}"
    printf '%s' "${base_url%/}"
}

admin_token() {
    [[ -n "${SHOPWARE_ADMIN_PASSWORD:-}" ]] || fail "SHOPWARE_ADMIN_PASSWORD is not set"
    local base_url body
    base_url="$(shopware_api_url)"
    body="$(curl -fsS -X POST "${base_url}/api/oauth/token" \
        -H 'Content-Type: application/json' \
        -H 'ngrok-skip-browser-warning: true' \
        -d "$(printf '{"client_id":"administration","grant_type":"password","username":"%s","password":"%s"}' \
            "${SHOPWARE_ADMIN_USERNAME:-admin}" "${SHOPWARE_ADMIN_PASSWORD}")")" \
        || fail "Could not reach the Shopware admin API at ${base_url}"
    python3 -c 'import json,sys; print(json.load(sys.stdin)["access_token"])' <<<"${body}"
}

write_github_env() {
    local key="$1"
    local value="$2"
    if [[ -n "${GITHUB_ENV:-}" ]]; then
        printf '%s=%s\n' "${key}" "${value}" >> "${GITHUB_ENV}"
    fi
}

start_tunnel() {
    mkdir -p "${DIAGNOSTICS_DIR}"
    : > "${TUNNEL_LOG}"
    local target="http://127.0.0.1:${SHOPWARE_PORT}"
    local public_url=""

    if [[ -n "${NGROK_AUTHTOKEN:-}" ]] && command -v ngrok >/dev/null 2>&1; then
        log "Starting ngrok tunnel to ${target}"
        # Pin IPv4. "ngrok http 8080" dials localhost, which is [::1] first.
        launch_tunnel ngrok http "127.0.0.1:${SHOPWARE_PORT}" --request-header-add "X-Forwarded-Proto:https" --log=stdout
        local elapsed=0
        while (( elapsed < 45 )); do
            if curl -fsS http://127.0.0.1:4040/api/tunnels >/dev/null 2>&1; then
                public_url="$(curl -fsS http://127.0.0.1:4040/api/tunnels \
                    | python3 -c 'import json,sys
t=json.load(sys.stdin).get("tunnels",[])
https=[x["public_url"] for x in t if x.get("public_url","").startswith("https://")]
print(https[0] if https else "")')"
                [[ -n "${public_url}" ]] && break
            fi
            sleep 2
            elapsed=$((elapsed + 2))
        done
    elif command -v cloudflared >/dev/null 2>&1; then
        log "Starting cloudflared tunnel to ${target}"
        # GitHub-hosted runners block UDP, so QUIC never registers the quick-tunnel name.
        launch_tunnel cloudflared tunnel --protocol http2 --url "${target}" --no-autoupdate
        local elapsed=0
        while (( elapsed < 45 )); do
            public_url="$(grep -oE 'https://[a-z0-9.-]+\.trycloudflare\.com' "${TUNNEL_LOG}" | head -n 1 || true)"
            [[ -n "${public_url}" ]] && break
            sleep 2
            elapsed=$((elapsed + 2))
        done
    else
        fail "Install cloudflared or ngrok so PAY. can reach a public HTTPS callback URL"
    fi

    [[ -n "${public_url}" ]] || fail "Could not determine the public tunnel URL. See ${TUNNEL_LOG}"
    public_url="${public_url%/}/"
    log "Public shop URL: ${public_url}"
    wait_for_public_url "${public_url}"
    # Both hosts must match. Shopware builds administration asset URLs from the API host.
    set_env_value APP_URL "${public_url}"
    set_env_value ADMIN_API_URL "${public_url}"
    write_github_env APP_URL "${public_url}"
    write_github_env ADMIN_API_URL "${public_url}"
}

tunnel_is_running() {
    [[ -f "${TUNNEL_PID_FILE}" ]] || return 1
    local pid
    pid="$(cat "${TUNNEL_PID_FILE}")"
    [[ -n "${pid}" ]] && kill -0 "${pid}" >/dev/null 2>&1
}

wait_for_public_url() {
    local url="${1%/}/"
    local elapsed=0
    local limit=90
    local code="000"
    log "Waiting until ${url} resolves"
    while (( elapsed < limit )); do
        if ! tunnel_is_running; then
            tail -n 40 "${TUNNEL_LOG}" >&2 || true
            fail "Tunnel exited before ${url} was reachable"
        fi
        code="$(http_code "${url}")"
        # 500 is Shopware's unknown-domain page, so the request reached the shop.
        # 530 is Cloudflare's "tunnel not connected" and is not ready yet.
        case "${code}" in
            200|204|301|302|303|307|308|400|401|403|404|500)
                log "Public shop URL is reachable (HTTP ${code})"
                return 0
                ;;
        esac
        sleep 3
        elapsed=$((elapsed + 3))
        if (( elapsed % 15 == 0 )); then
            log "still waiting for the tunnel (${elapsed}s, HTTP ${code})"
        fi
    done
    curl -sS -o /dev/null --connect-timeout 5 --max-time 15 "${url}" >&2 || true
    tail -n 40 "${TUNNEL_LOG}" >&2 || true
    fail "Could not resolve ${url} within ${limit}s"
}

launch_tunnel() {
    # New session so stop_tunnel can signal this tunnel without touching any other.
    python3 -c 'import os,sys; os.setsid(); os.execvp(sys.argv[1], sys.argv[1:])' "$@" >"${TUNNEL_LOG}" 2>&1 &
    echo $! > "${TUNNEL_PID_FILE}"
}

stop_tunnel() {
    if [[ ! -f "${TUNNEL_PID_FILE}" ]]; then
        return
    fi
    local pid
    pid="$(cat "${TUNNEL_PID_FILE}")"
    if [[ -n "${pid}" ]] && kill -0 "${pid}" >/dev/null 2>&1; then
        kill -- "-${pid}" >/dev/null 2>&1 || kill "${pid}" >/dev/null 2>&1 || true
        wait "${pid}" 2>/dev/null || true
    fi
    rm -f "${TUNNEL_PID_FILE}"
}

build_plugin_administration() {
    log "Building the PaynlPaymentShopware6 administration before tests"
    # Shopware 6.7 Vite writes plugin admin assets into the plugin, then assets:install publishes them.
    # A previous bundle that already contains paynl-config-section-api is not current: config.xml
    # and administration sources can change after that component was first compiled.
    shopware_exec 'set -e
cd /var/www/html
PLUGIN=custom/plugins/PaynlPaymentShopware6
ADMIN_SRC="$PLUGIN/src/Resources/app/administration"
ADMIN_OUT="$PLUGIN/src/Resources/public/administration"
PUBLIC_OUT=public/bundles/paynlpaymentshopware6/administration
STAMP="$ADMIN_OUT/.source-stamp"
SOURCE_STAMP="$(find "$ADMIN_SRC" "$PLUGIN/src/Resources/config/config.xml" -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | cut -d" " -f1)"
if [[ -f "$STAMP" && "$(cat "$STAMP")" == "$SOURCE_STAMP" ]] && grep -Rqs "paynl-config-section-api" "$ADMIN_OUT" "$PUBLIC_OUT" 2>/dev/null; then
  echo "Administration already built from the current plugin sources"
  exit 0
fi
STOREFRONT=$PLUGIN/src/Resources/app/storefront
if [[ -f "$STOREFRONT/package-lock.json" ]]; then
  npm ci --prefix "$STOREFRONT" --no-audit --no-fund
elif [[ -f "$STOREFRONT/package.json" ]]; then
  npm install --prefix "$STOREFRONT" --no-audit --no-fund
fi
export NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=4096}"
php bin/console bundle:dump
bin/build-administration.sh
php bin/console assets:install
php bin/console cache:clear -n
if ! grep -Rqs "paynl-config-section-api" "$ADMIN_OUT" "$PUBLIC_OUT" 2>/dev/null; then
  echo "Administration build did not include paynl-config-section-api"
  echo "---- built administration files ----"
  find "$ADMIN_OUT" "$PUBLIC_OUT" -type f 2>/dev/null | head -n 40 || true
  exit 1
fi
printf "%s\n" "$SOURCE_STAMP" > "$STAMP"
'
}

install_plugin_sdk() {
    log "Installing paynl/php-sdk into Shopware so the plugin requirement check passes"
    shopware_exec 'set -e
cd /var/www/html
SDK="$(php -r '"'"'echo json_decode(file_get_contents("custom/plugins/PaynlPaymentShopware6/composer.json"), true)["require"]["paynl/php-sdk"];'"'"')"
composer require "paynl/php-sdk:${SDK}" --no-interaction --ignore-platform-reqs --no-scripts
PLUGIN=/var/www/html/custom/plugins/PaynlPaymentShopware6
mkdir -p "$PLUGIN/vendor"
if [[ -d vendor/paynl ]]; then cp -a vendor/paynl "$PLUGIN/vendor/"; fi
if [[ -d vendor/php-curl-class ]]; then cp -a vendor/php-curl-class "$PLUGIN/vendor/"; fi
'
}

rotate_admin_password() {
    local username="${SHOPWARE_ADMIN_USERNAME:-admin}"
    local new_password
    new_password="$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 32)"
    log "Setting a random Shopware admin password for this run"
    docker exec -u dockware -e ADMIN_PW="${new_password}" -i "$(shopware_container)" bash -lc \
        "cd /var/www/html && php bin/console user:change-password '${username}' --password=\"\${ADMIN_PW}\" -n"
    set_env_value SHOPWARE_ADMIN_PASSWORD "${new_password}"
    # Later steps print the job environment. Mask before GITHUB_ENV, or the password shows there.
    if [[ -n "${GITHUB_ENV:-}" ]]; then
        echo "::add-mask::${new_password}"
    fi
    write_github_env SHOPWARE_ADMIN_PASSWORD "${new_password}"
    export SHOPWARE_ADMIN_PASSWORD="${new_password}"
}

configure_shopware() {
    load_env_file
    require_pay_secrets
    local app_url="${APP_URL:?APP_URL is not set}"
    app_url="${app_url%/}"

    log "Pointing Shopware APP_URL at ${app_url}"
    docker exec -u dockware -e APP_URL_VALUE="${app_url}" -i "$(shopware_container)" bash -lc 'set -e
cd /var/www/html
if [[ -f .env ]]; then
  grep -q "^APP_URL=" .env && sed -i "s|^APP_URL=.*|APP_URL=${APP_URL_VALUE}|" .env || echo "APP_URL=${APP_URL_VALUE}" >> .env
  # Port 80 is reached from the Docker bridge, which forwards the tunnel X-Forwarded-Proto.
  # Shopware 6.7 reads the SYMFONY_TRUSTED_* variables. private_ranges includes that bridge.
  set_shop_env() {
    if grep -q "^$1=" .env; then
      sed -i "s|^$1=.*|$1=$2|" .env
    else
      echo "$1=$2" >> .env
    fi
  }
  set_shop_env APP_ENV prod
  forwarded_headers="x-forwarded-for,x-forwarded-host,x-forwarded-proto,x-forwarded-port,x-forwarded-prefix"
  set_shop_env TRUSTED_PROXIES private_ranges
  set_shop_env TRUSTED_HEADERS "$forwarded_headers"
  set_shop_env SYMFONY_TRUSTED_PROXIES private_ranges
  set_shop_env SYMFONY_TRUSTED_HEADERS "$forwarded_headers"
  # Empty update check means the admin "new Shopware version" notice is never created.
  set_shop_env SHOPWARE_DISABLE_UPDATE_CHECK 1
  # Merchants run with the storefront HTTP cache on. Finalize and return are tested that way.
  set_shop_env SHOPWARE_HTTP_CACHE_ENABLED 1
fi
'
    install_plugin_sdk

    log "Installing and activating PaynlPaymentShopware6"
    shopware_exec 'set -e
cd /var/www/html
php bin/console plugin:refresh -n
if ! php bin/console plugin:install --activate PaynlPaymentShopware6 -n; then
  php bin/console plugin:activate PaynlPaymentShopware6 -n
fi
php bin/console plugin:list -n | awk "/PaynlPaymentShopware6/" | grep -q "Yes" \
  || { echo "PaynlPaymentShopware6 is not installed"; php bin/console plugin:list -n; exit 1; }
php bin/console cache:clear -n
'

    build_plugin_administration

    log "Writing PAY. test-mode credentials"
    docker exec \
        -u dockware \
        -e PAY_TOKEN_CODE="${PAY_TOKEN_CODE}" \
        -e PAY_API_TOKEN="${PAY_API_TOKEN}" \
        -e PAY_SERVICE_ID="${PAY_SERVICE_ID}" \
        -i "$(shopware_container)" bash -lc 'set -e
cd /var/www/html
php bin/console system:config:set PaynlPaymentShopware6.config.tokenCode "$PAY_TOKEN_CODE" -n
php bin/console system:config:set PaynlPaymentShopware6.config.apiToken "$PAY_API_TOKEN" -n
php bin/console system:config:set PaynlPaymentShopware6.config.serviceId "$PAY_SERVICE_ID" -n
php bin/console system:config:set PaynlPaymentShopware6.config.testMode 1 -n
php bin/console system:config:set PaynlPaymentShopware6.config.useSinglePaymentMethod 1 -n
php bin/console system:config:set PaynlPaymentShopware6.config.logging 1 -n
php bin/console system:config:set PaynlPaymentShopware6.config.enablePayPartsCreditCardWidget 1 -n
php bin/console system:config:set PaynlPaymentShopware6.config.paymentScreenLanguage en -n
php bin/console system:config:set core.loginRegistration.minPasswordLength 8 -n
php bin/console cache:clear -n
'

    log "Installing PAY. payment methods"
    local token api_url response
    token="$(admin_token)"
    api_url="$(shopware_api_url)"
    response="$(curl -fsS "${api_url}/api/paynl/install-payment-methods?_nocache=$(date +%s)" \
        -H "Authorization: Bearer ${token}" \
        -H 'Accept: application/json' \
        -H 'ngrok-skip-browser-warning: true' \
        -H 'Cache-Control: no-cache, no-store' \
        -H 'Pragma: no-cache')" \
        || fail "Could not install PAY. payment methods via ${api_url}"
    python3 -c 'import json,sys
payload=json.loads(sys.argv[1])
if not payload.get("success", True):
    raise SystemExit(payload.get("message", "install-payment-methods failed"))
print(payload.get("message", "payment methods installed"))
' "${response}"

    log "Declining Shopware usage-data consent"
    local consent
    for consent in product_analytics backend_data; do
        curl -fsS -X POST "${api_url}/api/consents/revoke" \
            -H "Authorization: Bearer ${token}" \
            -H 'Content-Type: application/json' \
            -H 'Accept: application/json' \
            -H 'ngrok-skip-browser-warning: true' \
            -d "{\"consent\":\"${consent}\"}" \
            >/dev/null \
            || fail "Could not decline Shopware consent ${consent}"
    done

    log "Shop configuration complete"
}

collect_diagnostics() {
    mkdir -p "${DIAGNOSTICS_DIR}"
    log "Collecting Shopware diagnostics into ${DIAGNOSTICS_DIR}"
    local container_id
    container_id="$(shopware_container_id)"
    if [[ -n "${container_id}" ]]; then
        docker ps -a --filter "id=${container_id}" > "${DIAGNOSTICS_DIR}/docker-ps.txt" || true
    else
        echo "No container for project ${COMPOSE_PROJECT_NAME}" > "${DIAGNOSTICS_DIR}/docker-ps.txt"
    fi
    "${COMPOSE[@]}" logs --no-color shopware > "${DIAGNOSTICS_DIR}/shopware-container.log" 2>/dev/null || true
    if [[ -n "${container_id}" ]]; then
        docker exec -u dockware -i "${container_id}" bash -lc 'cd /var/www/html
php bin/console plugin:list -n || true
echo "---- .env APP_URL ----"
grep -E "^(APP_URL|TRUSTED_PROXIES|SYMFONY_TRUSTED_PROXIES|SHOPWARE_HTTP_CACHE_ENABLED)=" .env || true
echo "---- paynl logs ----"
ls -la var/log || true
' > "${DIAGNOSTICS_DIR}/shopware-console.txt" 2>&1 || true
        docker cp "${container_id}:/var/www/html/var/log/." "${DIAGNOSTICS_DIR}/shopware-var-log" 2>/dev/null || true
    fi
}

cmd_up() {
    load_env_file
    mkdir -p "${DIAGNOSTICS_DIR}"
    remove_legacy_container
    log "Starting Shopware ${SHOPWARE_VERSION} on port ${SHOPWARE_PORT} (project ${COMPOSE_PROJECT_NAME})"
    "${COMPOSE[@]}" pull shopware
    "${COMPOSE[@]}" up -d --force-recreate
    wait_for_shopware
    rotate_admin_password
    start_tunnel
    configure_shopware
    wait_for_public_url "${APP_URL}"
    log "Environment is ready. APP_URL=${APP_URL}"
}

cmd_down() {
    stop_tunnel
    "${COMPOSE[@]}" down -v --remove-orphans || true
    remove_legacy_container
    log "Temporary Shopware environment removed"
}

cmd_redact() {
    if [[ -f "${ENV_FILE}" ]]; then
        load_env_file
    fi
    local acceptance="${ACCEPTANCE_DIR}"
    local -a php=(php)
    if ! command -v php >/dev/null 2>&1; then
        # GitHub-hosted runners have no PHP. The Shopware container does, and these directories are bind-mounted.
        acceptance="/var/www/html/custom/plugins/PaynlPaymentShopware6/tests/Acceptance"
        php=(docker exec -u "$(id -u):$(id -g)"
            -e PAY_SANDBOX_SECRET="${PAY_SANDBOX_SECRET:-}"
            -e PAY_API_TOKEN="${PAY_API_TOKEN:-}"
            -e PAY_TOKEN_CODE="${PAY_TOKEN_CODE:-}"
            -e PAY_SERVICE_ID="${PAY_SERVICE_ID:-}"
            -e NGROK_AUTHTOKEN="${NGROK_AUTHTOKEN:-}"
            -e SHOPWARE_ADMIN_PASSWORD="${SHOPWARE_ADMIN_PASSWORD:-}"
            "$(shopware_container)" php)
    fi

    # A redactor that no longer scrubs every format must block the upload, not pass silently.
    "${php[@]}" "${acceptance}/scripts/redact-secrets.php" --self-check
    "${php[@]}" "${acceptance}/scripts/redact-secrets.php" \
        "${acceptance}/test-results" \
        "${acceptance}/playwright-report" \
        "${acceptance}/diagnostics"
}

usage() {
    cat <<'EOF'
Usage: e2e-env.sh <up|down|diagnostics|configure|ensure-plugin|tunnel|redact>

  up            Start Shopware, open a public HTTPS tunnel, install the plugin
  configure     Re-run plugin/PAY. configuration (requires APP_URL)
  ensure-plugin Install and activate PaynlPaymentShopware6, build its administration, then PAY. test methods
  tunnel        Start only the public HTTPS tunnel
  diagnostics   Copy Shopware logs for CI artifacts
  redact        Strip PAY. credentials, the admin password, and Bearer tokens from test artifacts
  down          Stop this run's tunnel and delete the Shopware containers
EOF
}

case "${1:-}" in
    up) cmd_up ;;
    down) cmd_down ;;
    diagnostics) collect_diagnostics ;;
    configure) load_env_file; configure_shopware ;;
    ensure-plugin) load_env_file; configure_shopware ;;
    tunnel) load_env_file; start_tunnel ;;
    redact) cmd_redact ;;
    *) usage; exit 1 ;;
esac
