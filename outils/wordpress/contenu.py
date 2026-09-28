"""Contenu final des pages pour un site donné : images liées à la médiathèque (id + URL)
et liens de la maquette réécrits en permaliens de ce site.

    python3 outils/wordpress/contenu.py <medias.json> <url-du-site> <dossier-de-sortie>
    python3 outils/wordpress/contenu.py --modele <dossier-de-sortie>

--modele : contenu à remplir sur le serveur (extension d'installation), quand ni les identifiants
des visuels ni l'adresse du site ne sont connus ici. Chaque visuel y porte un jeton numérique
(98765NNNN) et une adresse https://hg-modele.invalid/hg-media/<chemin>, le site l'adresse
https://hg-modele.invalid/ ; hg_dep_remplir() (deploiement.php) les remplace, et le résultat est
identique à la première forme.
"""
import json
import re
import shutil
import sys
from pathlib import Path

RACINE = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(RACINE / 'outils'))
from assembler import espaces_insecables  # noqa: E402
from blocs import convertir, liens_wordpress  # noqa: E402
import articles  # noqa: E402


MODELE = 'https://hg-modele.invalid/'


def medias_modele():
    images = sorted(f for f in (RACINE / 'site/assets/img').glob('*/*') if f.suffix.lower() in ('.webp', '.jpg', '.jpeg', '.png'))
    return {f'assets/img/{f.parent.name}/{f.name}': {'id': 987650001 + i, 'url': f'{MODELE}hg-media/assets/img/{f.parent.name}/{f.name}'}
            for i, f in enumerate(images)}


def main(*args):
    if args[0] == '--modele':
        medias, base, sortie = medias_modele(), MODELE, Path(args[1])
        sortie.mkdir(parents=True, exist_ok=True)
        (sortie / 'modele.json').write_text(json.dumps({k: v['id'] for k, v in medias.items()}, indent=1), encoding='utf-8')
    else:
        medias_json, url, sortie = args[:3]
        medias = json.loads(Path(medias_json).read_text(encoding='utf-8'))
        base = url.rstrip('/') + '/'
    generer(medias, base, Path(sortie))


def generer(medias, base, sortie):
    sortie.mkdir(parents=True, exist_ok=True)
    n = 0
    for source in sorted((RACINE / 'site/src/pages').glob('*.html')):
        brut = source.read_text(encoding='utf-8')
        entete = re.match(r'<!--(\{.*?\})-->\n?', brut, re.S)
        meta = json.loads(entete.group(1))
        if meta.get('gabarit') == 'admin':
            continue
        slug = meta.get('slug', source.stem)
        blocs = convertir(brut[entete.end():], medias=medias, liens=liens_wordpress(base))
        (sortie / f'{slug}.html').write_text(espaces_insecables(blocs), encoding='utf-8')
        shutil.copy2(RACINE / 'wordpress/contenu' / f'{slug}.json', sortie / f'{slug}.json')
        n += 1
    (sortie / 'articles').mkdir(exist_ok=True)
    liste = articles.tous()
    for source in sorted(articles.SRC_ARTICLES.glob('*.html')):
        brut, meta = articles.composer(source, liste)
        corps = brut[re.match(r'<!--(\{.*?\})-->\n?', brut, re.S).end():]
        blocs = convertir(corps, medias=medias, liens=liens_wordpress(base))
        (sortie / 'articles' / f'{meta["slug"]}.html').write_text(espaces_insecables(blocs), encoding='utf-8')
        shutil.copy2(RACINE / 'wordpress/contenu/articles' / f'{meta["slug"]}.json', sortie / 'articles' / f'{meta["slug"]}.json')
        n += 1
    print(n, 'pages et articles prêts pour', base, '->', sortie)


if __name__ == '__main__':
    main(*sys.argv[1:])
