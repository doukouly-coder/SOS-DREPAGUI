"""Contenu final des pages pour un site donné : images liées à la médiathèque (id + URL)
et liens de la maquette réécrits en permaliens de ce site.

    python3 outils/wordpress/contenu.py <medias.json> <url-du-site> <dossier-de-sortie>
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


def main(medias_json, url, sortie):
    medias = json.loads(Path(medias_json).read_text(encoding='utf-8'))
    base = url.rstrip('/') + '/'
    sortie = Path(sortie)
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
    main(*sys.argv[1:4])
