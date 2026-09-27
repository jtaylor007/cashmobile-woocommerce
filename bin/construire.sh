#!/usr/bin/env bash
#
# Enveloppe du constructeur. Le travail est en PHP : Git Bash sur Windows livre
# `unzip` mais pas `zip`, donc un zip en shell echoue sur la machine meme ou ce
# plugin se construit.
set -euo pipefail
exec php "$(dirname "$0")/construire.php"
