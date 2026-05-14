#!/usr/bin/env bash
# Convert source PNG assets to WebP, then sync WebP assets to BGA.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RESOURCES_DIR="$ROOT_DIR/src/resources"
SRC_DIR="$RESOURCES_DIR/images"
DST_DIR="$ROOT_DIR/bga-hacknslash/img"
WEBP_QUALITY="${WEBP_QUALITY:-90}"

if [[ ! -d "$RESOURCES_DIR" ]]; then
  echo "Source resources folder not found: $RESOURCES_DIR" >&2
  exit 1
fi

if [[ ! -d "$SRC_DIR" ]]; then
  echo "Source image folder not found: $SRC_DIR" >&2
  exit 1
fi

if ! command -v cwebp >/dev/null 2>&1; then
  echo "Missing cwebp. Install the webp tools first." >&2
  exit 1
fi

while IFS= read -r -d '' file; do
  output="${file%.*}.webp"
  if [[ ! -f "$output" || "$file" -nt "$output" ]]; then
    cwebp -quiet -mt -q "$WEBP_QUALITY" "$file" -o "$output"
  fi
done < <(find "$RESOURCES_DIR" -type f -iname '*.png' -print0)

mkdir -p "$DST_DIR"
rsync -a --delete \
  --include '*/' \
  --exclude 'illustrations/***' \
  --exclude 'cards/boards/***' \
  --exclude 'cards/placeholders/***' \
  --exclude 'tiles/actions/***' \
  --exclude 'tiles/free/***' \
  --include '*.webp' \
  --exclude '*' \
  "$SRC_DIR/" "$DST_DIR/"

echo "BGA WebP assets prepared in $DST_DIR"
