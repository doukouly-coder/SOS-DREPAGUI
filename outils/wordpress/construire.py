"""Construit le thème et l'extension WordPress à partir de la maquette.

La maquette (site/) reste la seule source : styles, polices, scripts et sprite
sont copiés dans wordpress/theme/hemato-gui et wordpress/extension/hemato-gui,
puis les deux dossiers sont zippés dans dist/ (non versionné) pour l'installation.

dist/hemato-gui-installation.zip est l'installation en un clic : une extension qui
embarque thème, extension, visuels et contenu modèle, et les installe depuis
l'administration (Outils › Installer HEMATO GUI), avec sauvegarde et retour arrière.

    python3 outils/wordpress/construire.py
"""
import shutil
import subprocess
import sys
import tempfile
import zipfile
from pathlib import Path

RACINE = Path(__file__).resolve().parents[2]
SITE = RACINE / 'site'
THEME = RACINE / 'wordpress' / 'theme' / 'hemato-gui'
EXTENSION = RACINE / 'wordpress' / 'extension' / 'hemato-gui'
INSTALLATION = RACINE / 'wordpress' / 'installation'
DIST = RACINE / 'dist'


def copier(source, cible):
    cible.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source, cible)


def zipper(dossier, archive):
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as z:
        for f in sorted(dossier.rglob('*')):
            if f.is_file() and '__pycache__' not in f.parts:
                z.write(f, Path(dossier.name) / f.relative_to(dossier))


EXCLUS_EDITEUR = ('evitement', 'screen-reader-text', '.sr', 'wa-', 'sous-menu', 'menu', 'entete', 'lien-etire', 'jauge', 'acces-titre')


def calques_editeur():
    """Gutenberg impose position:relative à chaque bloc (0,2,0) : les calques que la maquette sort
    du flux y retombent. On relève dans les feuilles toutes les règles position:absolute visant
    un élément (pas un pseudo-élément) et on les rétablit dans l'éditeur, avec !important."""
    import re
    selecteurs = []
    for nom in ('styles.css', 'pages.css'):
        css = (SITE / 'assets/css' / nom).read_text(encoding='utf-8')
        css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
        for sel, corps in re.findall(r'([^{}@]+)\{([^{}]*)\}', css):
            if not re.search(r'(^|;)\s*position\s*:\s*absolute', corps):
                continue
            for un in sel.split(','):
                un = un.strip()
                if not un or '::' in un or ':' in un.replace(':not(', '') or any(x in un for x in EXCLUS_EDITEUR):
                    continue
                if un not in selecteurs:
                    selecteurs.append(un)
    regles = ',\n'.join(f'.editor-styles-wrapper {x}' for x in selecteurs)
    return ('/* Généré par outils/wordpress/construire.py — calques de la maquette rétablis dans l\'éditeur. */\n'
            f'{regles}{{position:absolute!important}}\n'
            '/* Le conteneur de redimensionnement des images ne doit pas devenir le repère des images en absolu.\n'
            '   L\'éditeur impose width:inherit à l\'image : elle hérite donc de la largeur posée sur ce conteneur. */\n'
            '.editor-styles-wrapper .components-resizable-box__container{position:static!important;width:100%!important;height:auto!important;display:contents}\n'
            + largeurs_images_editeur()), len(selecteurs)


def largeurs_images_editeur():
    """Chaque règle « … img{width:…} » de la maquette, reportée sur le conteneur de redimensionnement
    de l'éditeur (dont l'image hérite), en gardant les requêtes média."""
    import re
    sortie = []

    def regles(css, prefixe=''):
        for sel, corps in re.findall(r'([^{}]+)\{([^{}]*)\}', css):
            largeur = re.search(r'(?:^|;)\s*width\s*:\s*([^;!]+)', corps)
            if not largeur:
                continue
            cibles = [x.strip()[:-4].strip() for x in sel.split(',') if x.strip().endswith(' img') and '::' not in x]
            for c in cibles:
                sortie.append(f'{prefixe}.editor-styles-wrapper {c} .components-resizable-box__container{{width:{largeur.group(1).strip()}!important}}'
                              + ('}' if prefixe else ''))

    for nom in ('styles.css', 'pages.css'):
        css = re.sub(r'/\*.*?\*/', '', (SITE / 'assets/css' / nom).read_text(encoding='utf-8'), flags=re.S)
        # blocs @media (un niveau d'imbrication) puis le reste
        for media, interieur in re.findall(r'(@media[^{]+)\{((?:[^{}]*\{[^{}]*\})*[^{}]*)\}', css):
            regles(interieur, media.strip() + '{')
        regles(re.sub(r'@media[^{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}', '', css))
    return '/* Largeurs d\'images propres aux composants. */\n' + '\n'.join(sortie) + '\n'


def installation(archive):
    """Extension d'installation en un clic : son fichier principal, deploiement.php, et le paquet."""
    with tempfile.TemporaryDirectory() as t:
        racine = Path(t) / 'hemato-gui-installation'
        copier(INSTALLATION / 'hemato-gui-installation.php', racine / 'hemato-gui-installation.php')
        copier(RACINE / 'outils/wordpress/deploiement.php', racine / 'deploiement.php')
        shutil.copytree(THEME, racine / 'paquet/theme/hemato-gui', ignore=shutil.ignore_patterns('__pycache__', '.DS_Store'))
        shutil.copytree(EXTENSION, racine / 'paquet/extension/hemato-gui', ignore=shutil.ignore_patterns('__pycache__', '.DS_Store'))
        for f in (SITE / 'assets/img').glob('*/*'):
            if f.suffix.lower() in ('.webp', '.jpg', '.jpeg', '.png'):
                copier(f, racine / 'paquet/img' / f.parent.name / f.name)
        subprocess.run([sys.executable, str(RACINE / 'outils/wordpress/contenu.py'), '--modele', str(racine / 'paquet/contenu')],
                       check=True, stdout=subprocess.DEVNULL)
        zipper(racine, archive)


def main():
    # Contenu, gabarits de shortcodes et pied de page : régénérés depuis les sources.
    subprocess.run([sys.executable, str(RACINE / 'outils' / 'assembler.py')], check=True, stdout=subprocess.DEVNULL)

    for nom in ('icones.css', 'styles.css', 'pages.css', 'wp.css'):
        copier(SITE / 'assets/css' / nom, THEME / 'assets/css' / nom)
    for f in (SITE / 'assets/fonts').iterdir():
        copier(f, THEME / 'assets/fonts' / f.name)
    copier(SITE / 'assets/js/app.js', THEME / 'assets/js/app.js')
    css, n = calques_editeur()
    (THEME / 'assets/css/editeur-calques.css').write_text(css, encoding='utf-8')

    for nom in ('outils.js', 'rendez-vous.js'):
        copier(SITE / 'assets/js' / nom, EXTENSION / 'assets/js' / nom)
    sprite = (SITE / 'src/parties/sprite.svg').read_text(encoding='utf-8')
    (EXTENSION / 'assets/sprite.svg').write_text(sprite, encoding='utf-8')

    DIST.mkdir(exist_ok=True)
    zipper(THEME, DIST / 'hemato-gui-theme.zip')
    zipper(EXTENSION, DIST / 'hemato-gui-extension.zip')
    installation(DIST / 'hemato-gui-installation.zip')
    print('thème      :', THEME.relative_to(RACINE), '->', (DIST / 'hemato-gui-theme.zip').relative_to(RACINE))
    print('extension  :', EXTENSION.relative_to(RACINE), '->', (DIST / 'hemato-gui-extension.zip').relative_to(RACINE))
    print('installation en un clic ->', (DIST / 'hemato-gui-installation.zip').relative_to(RACINE),
          f'({(DIST / "hemato-gui-installation.zip").stat().st_size // 1024} Ko)')


if __name__ == '__main__':
    main()
