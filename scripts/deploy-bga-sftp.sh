#!/usr/bin/env bash
# Deploy bga-hacknslash to BGA Studio over SFTP.
# Requires lftp (Homebrew: brew install lftp).
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

if [[ -f "$ENV_FILE" ]]; then
  while IFS= read -r line; do
    [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
    if [[ "$line" =~ ^(BGA_SFTP_HOST|BGA_SFTP_PORT|BGA_SFTP_USER|BGA_SFTP_PASSWORD|BGA_SFTP_REMOTE_DIR|BGA_DB_USER)= ]]; then
      key="${line%%=*}"
      val="${line#*=}"
      export "$key=$val"
    fi
  done < "$ENV_FILE"
fi

if [[ -n "${BGA_SFTP_REMOTE_DIR:-}" && -n "${BGA_DB_USER:-}" ]]; then
  BGA_SFTP_REMOTE_DIR="${BGA_SFTP_REMOTE_DIR//\$\{BGA_DB_USER\}/$BGA_DB_USER}"
fi

: "${BGA_SFTP_HOST:?BGA_SFTP_HOST is required}"
: "${BGA_SFTP_PORT:?BGA_SFTP_PORT is required}"
: "${BGA_SFTP_USER:?BGA_SFTP_USER is required}"
: "${BGA_SFTP_PASSWORD:?BGA_SFTP_PASSWORD is required}"
: "${BGA_SFTP_REMOTE_DIR:?BGA_SFTP_REMOTE_DIR is required}"

LOCAL_PATH="$ROOT_DIR/bga-hacknslash"
if [[ ! -d "$LOCAL_PATH" ]]; then
  echo "Local deploy source not found: $LOCAL_PATH" >&2
  exit 1
fi

if ! command -v lftp >/dev/null 2>&1; then
  echo "lftp not found. Install it with: brew install lftp" >&2
  exit 1
fi

EXCLUDES=(
  "node_modules"
  ".git"
  ".DS_Store"
  "vendor"
  "vendor/**"
  "vendors"
  "vendors/**"
  ".phpunit.result.cache"
  ".*"
  "tests"
  "composer.json"
  "composer.lock"
  "phpunit.xml"
  "AGENTS.md"
  "README.md"
  "readme.md"
  "docs"
)

BGAIGNORE_FILE="$LOCAL_PATH/.bgaignore"
if [[ -f "$BGAIGNORE_FILE" ]]; then
  while IFS= read -r line || [[ -n "$line" ]]; do
    line="${line%%#*}"
    line="${line%$'\r'}"
    line="${line#${line%%[![:space:]]*}}"
    line="${line%${line##*[![:space:]]}}"
    [[ -z "$line" ]] && continue

    EXCLUDES+=("$line")
    if [[ "$line" == */ ]]; then
      EXCLUDES+=("${line%/}" "${line%/}/**")
    fi
  done < "$BGAIGNORE_FILE"
fi

excludeFlags=()
for e in "${EXCLUDES[@]}"; do
  excludeFlags+=("--exclude-glob" "$e")
done

# lftp mirror --delete does not remove paths that are excluded from the mirror.
# Clean known non-BGA development artifacts explicitly before uploading.
REMOTE_CLEANUP_PATHS=(
  "vendor"
  "vendors"
  "tests"
  "docs"
  "composer.json"
  "composer.lock"
  "phpunit.xml"
  "AGENTS.md"
  "README.md"
  "readme.md"
  ".phpunit.result.cache"
)

remoteCleanupCommands=""

DRY_RUN_FLAG=""
CLEAN_REMOTE=false
for arg in "$@"; do
  case "$arg" in
    --dry-run)
      DRY_RUN_FLAG="--dry-run"
      ;;
    --clean-remote)
      CLEAN_REMOTE=true
      ;;
    *)
      echo "Unknown option: $arg" >&2
      echo "Usage: $0 [--dry-run] [--clean-remote]" >&2
      exit 2
      ;;
  esac
done

if [[ -z "$DRY_RUN_FLAG" ]]; then
  echo "Performing actual deployment."
  if [[ "$CLEAN_REMOTE" == true ]]; then
    echo "Cleaning remote development artifacts."
    for path in "${REMOTE_CLEANUP_PATHS[@]}"; do
      remoteCleanupCommands+="rm -rf $path"$'\n'
    done
  fi
  #  sudo route -n add -host  37.187.205.147 192.168.1.1
  # ps aux | awk 'tolower($0) ~ /globalprotect|pangp|palo|gpsplit|firewall|socketfilter|crowdstrike|falcon/ && $0 !~ /awk/ {print $2}' | xargs kill -9 || echo "ok"
elif [[ "$CLEAN_REMOTE" == true ]]; then
  echo "Skipping remote cleanup during dry-run."
fi

echo "Deploying bga-hacknslash to $BGA_SFTP_USER@$BGA_SFTP_HOST:$BGA_SFTP_REMOTE_DIR"

lftp -u "$BGA_SFTP_USER","$BGA_SFTP_PASSWORD" "sftp://$BGA_SFTP_HOST:$BGA_SFTP_PORT" <<EOF
set net:max-retries 2
set net:timeout 30
set sftp:auto-confirm yes
set cmd:fail-exit yes
lcd "$LOCAL_PATH"
cd "$BGA_SFTP_REMOTE_DIR"
$remoteCleanupCommands
mirror -R --parallel=4 --delete $DRY_RUN_FLAG ${excludeFlags[@]}
bye
EOF

echo "SFTP deployment complete."
