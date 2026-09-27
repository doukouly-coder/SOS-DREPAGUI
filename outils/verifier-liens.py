"""Vérifie les liens internes de la maquette : fichiers, ancres, images."""
import re
from pathlib import Path
from html.parser import HTMLParser

SITE = Path(__file__).resolve().parent.parent / 'site'

class Analyse(HTMLParser):
    def __init__(self):
        super().__init__(); self.ids = set(); self.liens = []
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if 'id' in a: self.ids.add(a['id'])
        for cle in ('href', 'src'):
            if cle in a and a[cle]: self.liens.append((tag, a[cle]))

pages = {}
for f in sorted(SITE.glob('*.html')):
    p = Analyse(); p.feed(f.read_text(encoding='utf-8')); pages[f.name] = p

erreurs = 0
for nom, p in pages.items():
    for tag, lien in p.liens:
        if lien.startswith(('http', 'mailto:', 'tel:', 'data:')) or (tag == 'use'):
            continue
        cible, _, ancre = lien.partition('#')
        cible = cible.split('?')[0]
        if lien == '#':
            print(f'{nom}: lien vide « # »'); erreurs += 1; continue
        if cible == '':
            if ancre and ancre not in p.ids: print(f'{nom}: ancre absente #{ancre}'); erreurs += 1
            continue
        chemin = SITE / cible
        if not chemin.exists():
            print(f'{nom}: fichier absent {lien}'); erreurs += 1; continue
        if ancre and cible in pages and ancre not in pages[cible].ids:
            print(f'{nom}: ancre absente {lien}'); erreurs += 1
print('erreurs :', erreurs)
