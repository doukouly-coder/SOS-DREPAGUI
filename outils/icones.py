"""Génère site/assets/css/icones.css : chaque icône du sprite devient une variable --ico
(masque CSS), posée par une classe ico-<nom>. Substitution n° 1 de blocs-gutenberg.md :
aucune icône SVG en ligne dans le contenu."""
import re
from pathlib import Path
from urllib.parse import quote

RACINE = Path(__file__).resolve().parent.parent
sprite = (RACINE / 'site/src/parties/sprite.svg').read_text(encoding='utf-8')
regles = ['/* Généré par outils/icones.py — ne pas modifier à la main. */']
for m in re.finditer(r'<symbol id="i-([\w-]+)" viewBox="([^"]+)">(.*?)</symbol>', sprite, re.S):
    nom, vb, corps = m.groups()
    plein = 'fill="currentColor"' in corps
    corps = corps.replace('currentColor', '#000')
    svg = (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vb}" fill="none" stroke="#000" '
           f'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{corps.strip()}</svg>')
    if plein:
        svg = svg.replace(' stroke="#000" stroke-width="1.8"', '')
    regles.append(f'.ico-{nom}{{--ico:url("data:image/svg+xml,{quote(svg, safe=" =:/,.-")}")}}')
regles.append('''
/* Dessin générique : l'élément porte ico-<nom> + icone-avant, l'icône est un masque en currentColor */
.icone-avant::before,.b-ico>.wp-block-button__link::before,.lien-fleche a::after,.icone-apres::after{
  content:"";display:inline-block;flex:none;width:1em;height:1em;background:currentColor;
  -webkit-mask:var(--ico) center/contain no-repeat;mask:var(--ico) center/contain no-repeat}
.lien-fleche a::after{--ico:var(--ico-fleche)}''')
fleche = re.search(r'\.ico-fleche\{--ico:(url\([^)]*\))\}', '\n'.join(regles)).group(1)
regles.append(f':root{{--ico-fleche:{fleche}}}')
loupe = re.search(r'\.ico-recherche\{--ico:(url\([^)]*\))\}', '\n'.join(regles)).group(1)
regles.append(f':root{{--ico-loupe:{loupe}}}')
wa = re.search(r'<symbol id="i-whatsapp" viewBox="([^"]+)">(.*?)</symbol>', sprite, re.S)
svg_blanc = f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{wa.group(1)}">{wa.group(2).strip().replace("currentColor", "#fff")}</svg>'
regles.append(f':root{{--wa-blanc:url("data:image/svg+xml,{quote(svg_blanc, safe=" =:/,.-")}")}}')
(RACINE / 'site/assets/css/icones.css').write_text('\n'.join(regles) + '\n', encoding='utf-8')
print(len(regles) - 3, 'icônes')
