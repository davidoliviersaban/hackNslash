#!/usr/bin/env bash
# Pull the BGA Studio project folder into bga-hacknslash-remote.
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

if ! command -v lftp >/dev/null 2>&1; then
  echo "lftp not found. Install it with: brew install lftp" >&2
  exit 1
fi

LOCAL_SNAPSHOT="$ROOT_DIR/bga-hacknslash-remote"
mkdir -p "$LOCAL_SNAPSHOT"

echo "Pulling $BGA_SFTP_REMOTE_DIR into $LOCAL_SNAPSHOT"

lftp -u "$BGA_SFTP_USER","$BGA_SFTP_PASSWORD" "sftp://$BGA_SFTP_HOST:$BGA_SFTP_PORT" <<EOF
set net:max-retries 2
set net:timeout 30
set sftp:auto-confirm yes
set cmd:fail-exit yes
lcd "$LOCAL_SNAPSHOT"
cd "$BGA_SFTP_REMOTE_DIR"
mirror --parallel=4 --delete --exclude-glob ".DS_Store" --exclude-glob ".*"
bye
EOF

echo "SFTP pull complete: $LOCAL_SNAPSHOT"
