#!/usr/bin/env bash
# Déploie thème, extension, médias et pages sur un WordPress local.
#   outils/wordpress/deployer-local.sh <racine-wp> <url-du-site> [dossier-de-travail]
set -euo pipefail
ICI="$(cd "$(dirname "$0")" && pwd)"
RACINE_WP="$1"; URL="$2"; TRAVAIL="${3:-$(mktemp -d)}"
mkdir -p "$TRAVAIL"
python3 "$ICI/construire.py"
php "$ICI/deployer.php" "$RACINE_WP" "$URL" installer
php "$ICI/deployer.php" "$RACINE_WP" "$URL" medias "$TRAVAIL/medias.json"
python3 "$ICI/contenu.py" "$TRAVAIL/medias.json" "$URL" "$TRAVAIL/contenu"
php "$ICI/deployer.php" "$RACINE_WP" "$URL" pages "$TRAVAIL/contenu"
