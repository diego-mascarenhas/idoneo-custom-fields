#!/usr/bin/env bash
# Build a distribution ZIP with the directory structure WordPress expects.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUTPUT_DIR="${1:-"$ROOT/dist"}"
PACKAGE_NAME="idoneo-custom-fields"
OUTPUT_DIR="$(mkdir -p "$OUTPUT_DIR" && cd "$OUTPUT_DIR" && pwd)"
WORK_DIR="$(mktemp -d)"

cleanup() {
  rm -rf "$WORK_DIR"
}
trap cleanup EXIT

mkdir -p "$WORK_DIR/$PACKAGE_NAME"
rsync -a "$ROOT/" "$WORK_DIR/$PACKAGE_NAME/" \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.gitignore' \
  --exclude='.DS_Store' \
  --exclude='dist/' \
  --exclude='scripts/' \
  --exclude='build-zip.sh' \
  --exclude='README.md'

rm -f "$OUTPUT_DIR/$PACKAGE_NAME.zip"
(
  cd "$WORK_DIR"
  zip -qr "$OUTPUT_DIR/$PACKAGE_NAME.zip" "$PACKAGE_NAME"
)

echo "Built: $OUTPUT_DIR/$PACKAGE_NAME.zip"
