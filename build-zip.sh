#!/usr/bin/env bash
# Build idoneo-custom-fields.zip for distribution.
# Packages this repository (the directory containing this script).

set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
rm -rf /tmp/idoneo-custom-fields
mkdir -p /tmp/idoneo-custom-fields
rsync -av "$ROOT/" /tmp/idoneo-custom-fields/ \
  --exclude='.git' --exclude='.gitignore' --exclude='.git/' --exclude='.*' \
  --exclude='build-zip.sh'
mkdir -p /Users/magoo/Sites/wordpress-plugins
rm -f /Users/magoo/Sites/wordpress-plugins/idoneo-custom-fields.zip
cd /tmp && zip -r /Users/magoo/Sites/wordpress-plugins/idoneo-custom-fields.zip idoneo-custom-fields
rm -rf /tmp/idoneo-custom-fields
echo "Built: idoneo-custom-fields.zip from $ROOT"
