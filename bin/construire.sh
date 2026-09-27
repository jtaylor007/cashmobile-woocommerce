#!/usr/bin/env bash
#
# Construit l'archive a publier.
#
# Le zip n'est PAS commite : il se reconstruit a l'identique depuis la source,
# et un binaire dans l'historique ne se relit pas en revue.
#
# L'archive contient un seul dossier racine, ce qu'attend l'installateur de
# WordPress.
set -euo pipefail

cd "$(dirname "$0")/.."

PLUGIN="cashmobile-gateway-for-woocommerce"
VERSION="$(grep -m1 '^ \* Version:' "$PLUGIN/cashmobile.php" | awk '{print $3}')"

if [ -z "$VERSION" ]; then
  echo "Version introuvable dans l'entete du plugin." >&2
  exit 1
fi

mkdir -p dist
ARCHIVE="dist/${PLUGIN}-v${VERSION}.zip"
rm -f "$ARCHIVE"

# -x : rien de ce qui n'a pas sa place chez un marchand.
zip -rq "$ARCHIVE" "$PLUGIN" \
  -x '*.DS_Store' -x '*Thumbs.db' -x '*/.git/*'

echo "$ARCHIVE"
echo "sha256 : $(sha256sum "$ARCHIVE" | awk '{print $1}')"
