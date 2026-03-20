#!/bin/bash

set -euo pipefail

export PATH="/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/opt/homebrew/bin:$PATH"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="tpw-core"
OUTPUT_PATH="${1:-}"

if [[ -z "$OUTPUT_PATH" ]]; then
	OUTPUT_PATH="$(mktemp "/tmp/${PLUGIN_SLUG}-XXXXXX.zip")"
fi

if [[ ! -f "$ROOT_DIR/.distignore" ]]; then
	echo "Missing .distignore in $ROOT_DIR" >&2
	exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
	echo "rsync is required to build the release package" >&2
	exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
	echo "zip is required to build the release package" >&2
	exit 1
fi

WORK_DIR="$(mktemp -d)"
STAGE_DIR="$WORK_DIR/$PLUGIN_SLUG"

cleanup() {
	rm -rf "$WORK_DIR"
}

trap cleanup EXIT

mkdir -p "$STAGE_DIR"

rsync -a \
	--delete \
	--exclude-from="$ROOT_DIR/.distignore" \
	"$ROOT_DIR/" "$STAGE_DIR/"

mkdir -p "$(dirname "$OUTPUT_PATH")"
rm -f "$OUTPUT_PATH"

(
	cd "$WORK_DIR"
	zip -qr "$OUTPUT_PATH" "$PLUGIN_SLUG"
)

printf '%s\n' "$OUTPUT_PATH"
