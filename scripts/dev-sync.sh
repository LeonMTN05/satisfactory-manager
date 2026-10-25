#!/usr/bin/env bash
# Kopiert ./extension nach .blueprint/dev und baut die Erweiterung ins Panel.
# Nutzung: ./scripts/dev-sync.sh          (einmal bauen)
#          ./scripts/dev-sync.sh --watch  (bei jeder Änderung neu bauen)
set -euo pipefail
PANEL="${PANEL:-/var/www/pterodactyl}"
SRC="$(cd "$(dirname "$0")/.." && pwd)/extension"

sync_build() {
  rsync -a --delete --exclude '.gitkeep' --exclude '.dist' --exclude 'node_modules' "$SRC/" "$PANEL/.blueprint/dev/"
  chown -R www-data:www-data "$PANEL/.blueprint/dev"
  (cd "$PANEL" && blueprint -build)
}

if [[ "${1:-}" == "--watch" ]]; then
  command -v inotifywait >/dev/null || { echo "inotify-tools fehlt: apt install inotify-tools"; exit 1; }
  sync_build
  while inotifywait -qq -r -e modify,create,delete,move "$SRC"; do sync_build || true; done
else
  sync_build
fi
