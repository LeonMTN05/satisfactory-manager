#!/usr/bin/env bash
# Baut ein installierbares sfmanager.blueprint (ZIP) nach ./dist
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
mkdir -p "$ROOT/dist"
cd "$ROOT/extension"
rm -f "$ROOT/dist/sfmanager.blueprint"
zip -qr "$ROOT/dist/sfmanager.blueprint" . -x '*.gitkeep' '.dist/*' 'node_modules/*'
echo "-> $ROOT/dist/sfmanager.blueprint"
