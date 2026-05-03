#!/usr/bin/env bash
# Copy current prototype images into the BGA asset folder.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC_DIR="$ROOT_DIR/src/resources/images"
DST_DIR="$ROOT_DIR/bga-hacknslash/img"

if [[ ! -d "$SRC_DIR" ]]; then
  echo "Source image folder not found: $SRC_DIR" >&2
  exit 1
fi

mkdir -p "$DST_DIR/cards" "$DST_DIR/tiles" "$DST_DIR/tokens" "$DST_DIR/metadata"

if [[ -d "$SRC_DIR/cards" ]]; then
  rsync -a --delete --exclude '.DS_Store' "$SRC_DIR/cards/" "$DST_DIR/cards/"
fi

if [[ -d "$SRC_DIR/tiles" ]]; then
  rsync -a --delete --exclude '.DS_Store' "$SRC_DIR/tiles/" "$DST_DIR/tiles/"
fi

if [[ -d "$SRC_DIR/icons" ]]; then
  rsync -a --delete --exclude '.DS_Store' "$SRC_DIR/icons/" "$DST_DIR/tokens/"
fi

if command -v cwebp >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    cwebp -quiet -mt -q 90 "$file" -o "${file%.*}.webp"
  done < <(find "$DST_DIR" -type f \( -iname '*.png' -o -iname '*.jpg' -o -iname '*.jpeg' \) -print0)
fi

echo "BGA assets prepared in $DST_DIR"
