"""Convertit les rendus PNG (png/) en WebP transparents pour le site (site/assets/img/rendus/)."""
from pathlib import Path
from PIL import Image

ICI = Path(__file__).parent
SORTIE = ICI.parent.parent / 'site' / 'assets' / 'img' / 'rendus'
SORTIE.mkdir(parents=True, exist_ok=True)
for png in sorted((ICI / 'png').glob('*.png')):
    im = Image.open(png).convert('RGBA')
    cible = SORTIE / (png.stem + '.webp')
    im.save(cible, 'WEBP', quality=84, method=6)
    print(f'{cible.name:24} {cible.stat().st_size // 1024:>5} Ko')
