"""Construit le thème et l'extension WordPress à partir de la maquette.

La maquette (site/) reste la seule source : styles, polices, scripts et sprite
sont copiés dans wordpress/theme/hemato-gui et wordpress/extension/hemato-gui,
puis les deux dossiers sont zippés dans dist/ (non versionné) pour l'installation.

    python3 outils/wordpress/construire.py
"""
import shutil
import subprocess
import sys
import zipfile
from pathlib import Path

RACINE = Path(__file__).resolve().parents[2]
SITE = RACINE / 'site'
THEME = RACINE / 'wordpress' / 'theme' / 'hemato-gui'
EXTENSION = RACINE / 'wordpress' / 'extension' / 'hemato-gui'
DIST = RACINE / 'dist'


def copier(source, cible):
    cible.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source, cible)


def zipper(dossier, archive):
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as z:
        for f in sorted(dossier.rglob('*')):
            if f.is_file() and '__pycache__' not in f.parts:
                z.write(f, Path(dossier.name) / f.relative_to(dossier))


def main():
    # Contenu, gabarits de shortcodes et pied de page : régénérés depuis les sources.
    subprocess.run([sys.executable, str(RACINE / 'outils' / 'assembler.py')], check=True, stdout=subprocess.DEVNULL)

    for nom in ('icones.css', 'styles.css', 'pages.css', 'wp.css'):
        copier(SITE / 'assets/css' / nom, THEME / 'assets/css' / nom)
    for f in (SITE / 'assets/fonts').iterdir():
        copier(f, THEME / 'assets/fonts' / f.name)
    copier(SITE / 'assets/js/app.js', THEME / 'assets/js/app.js')

    for nom in ('outils.js', 'rendez-vous.js'):
        copier(SITE / 'assets/js' / nom, EXTENSION / 'assets/js' / nom)
    sprite = (SITE / 'src/parties/sprite.svg').read_text(encoding='utf-8')
    (EXTENSION / 'assets/sprite.svg').write_text(sprite, encoding='utf-8')

    DIST.mkdir(exist_ok=True)
    zipper(THEME, DIST / 'hemato-gui-theme.zip')
    zipper(EXTENSION, DIST / 'hemato-gui-extension.zip')
    print('thème      :', THEME.relative_to(RACINE), '->', (DIST / 'hemato-gui-theme.zip').relative_to(RACINE))
    print('extension  :', EXTENSION.relative_to(RACINE), '->', (DIST / 'hemato-gui-extension.zip').relative_to(RACINE))


if __name__ == '__main__':
    main()
