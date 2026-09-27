"""Assemble les pages de la maquette : site/src/pages/*.html -> site/*.html.

Chaque page source commence par un commentaire JSON :
  <!--{"titre": "...", "description": "...", "nav": "accueil", "gabarit": "public"}-->
L'en-tête, le pied de page et le sprite d'icônes viennent d'une source unique
(site/src/parties/), ce qui les garantit strictement identiques partout.
"""
import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from blocs import convertir, sans_commentaires, gabarits, nom_gabarit, ErreurBloc

RACINE = Path(__file__).resolve().parent.parent / 'site'
CONTENU_WP = Path(__file__).resolve().parent.parent / 'wordpress' / 'contenu'
GABARITS_WP = Path(__file__).resolve().parent.parent / 'wordpress' / 'extension' / 'hemato-gui' / 'gabarits'
SRC = RACINE / 'src'
PARTIES = SRC / 'parties'

JSONLD = '''<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "MedicalOrganization",
  "name": "HEMATO GUI",
  "slogan": "Informer, former et traiter les maladies hématologiques",
  "medicalSpecialty": "Hematologic",
  "telephone": "+224628733143",
  "address": { "@type": "PostalAddress", "addressLocality": "Conakry", "addressCountry": "GN" },
  "areaServed": ["Guinée", "Afrique francophone"],
  "availableLanguage": "fr"
}
</script>
'''

WA_FLOTTANT = '''<a class="wa-flottant" href="https://wa.me/224628733143?text=Bonjour%20HEMATO%20GUI%2C%20je%20souhaite%20obtenir%20des%20informations%20concernant%20une%20consultation%20d%E2%80%99h%C3%A9matologie." target="_blank" rel="noopener" aria-label="Écrire à HEMATO GUI sur WhatsApp">
  <svg><use href="#i-whatsapp"/></svg><span class="wa-bulle">Une question ? Écrivez-nous</span>
</a>
'''


def espaces_insecables(html):
    """Typographie française dans le texte visible : espace insécable avant « : », fine avant « ; ? ! »."""
    morceaux = re.split(r'(<[^>]+>)', html)
    sortie, protege = [], False
    for m in morceaux:
        if m.startswith('<'):
            bas = m.lower()
            if bas.startswith(('<script', '<style')):
                protege = True
            elif bas.startswith(('</script', '</style')):
                protege = False
            sortie.append(m)
        elif protege:
            sortie.append(m)
        else:
            m = re.sub(r' :', ' :', m)
            m = re.sub(r' ([;?!])', ' \\1', m)
            sortie.append(m)
    return ''.join(sortie)


def activer_nav(html, nav):
    html = re.sub(rf'data-nav="{re.escape(nav)}"', 'aria-current="page"', html) if nav else html
    return re.sub(r' data-nav="[^"]*"', '', html)


def assembler(source):
    brut = source.read_text(encoding='utf-8')
    entete_json = re.match(r'<!--(\{.*?\})-->\n?', brut, re.S)
    meta = json.loads(entete_json.group(1))
    corps = brut[entete_json.end():]
    if meta.get('gabarit', 'public') != 'admin':
        try:
            blocs = convertir(corps)
            corps = sans_commentaires(convertir(corps, maquette=True))
            CONTENU_WP.mkdir(parents=True, exist_ok=True)
            slug = meta.get('slug', source.stem)
            (CONTENU_WP / f'{slug}.html').write_text(espaces_insecables(blocs), encoding='utf-8')
            (CONTENU_WP / f'{slug}.json').write_text(json.dumps({'titre': meta.get('wp_titre', meta['titre']), 'slug': slug,
                'description': meta['description'], 'titre_seo': meta['titre']}, ensure_ascii=False, indent=1), encoding='utf-8')
            for code, rendu in gabarits(brut[entete_json.end():]).items():
                GABARITS_WP.mkdir(parents=True, exist_ok=True)
                (GABARITS_WP / f'{nom_gabarit(code)}.html').write_text(espaces_insecables(rendu), encoding='utf-8')
        except ErreurBloc as err:
            print(f'  ⚠ {source.name} pas encore en blocs : {err}')
    gabarit = meta.get('gabarit', 'public')
    sprite = (PARTIES / 'sprite.svg').read_text(encoding='utf-8')
    classe_body = f' class="{meta["body"]}"' if meta.get('body') else ''

    tete = f'''<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{meta["titre"]}</title>
<meta name="description" content="{meta["description"]}">
<meta name="theme-color" content="#FFFFFF">
{'<meta name="robots" content="noindex">' if meta.get('noindex') else ''}
<link rel="preload" href="assets/fonts/inter-latin-opsz-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="assets/css/polices.css">
<link rel="stylesheet" href="assets/css/icones.css">
<link rel="stylesheet" href="assets/css/styles.css">
<link rel="stylesheet" href="assets/css/pages.css">
{JSONLD if meta.get('jsonld') else ''}</head>
<body{classe_body}>

{sprite}
'''
    if gabarit == 'admin':
        scripts = ''.join(f'<script src="{js}" defer></script>\n' for js in meta.get('scripts', []))
        page = f'{tete}\n{corps}\n<script src="assets/js/app.js" defer></script>\n{scripts}</body>\n</html>\n'
    else:
        entete = activer_nav((PARTIES / 'entete.html').read_text(encoding='utf-8'), meta.get('nav'))
        pied = (PARTIES / 'pied.html').read_text(encoding='utf-8')
        scripts = ''.join(f'<script src="{js}" defer></script>\n' for js in meta.get('scripts', []))
        page = (f'{tete}\n{entete}\n<main id="contenu">\n{corps}\n</main>\n\n{pied}\n{WA_FLOTTANT}\n'
                f'<script src="assets/js/app.js" defer></script>\n{scripts}</body>\n</html>\n')
    page = re.sub(r'\n{3,}', '\n\n', page)
    (RACINE / source.name).write_text(espaces_insecables(page), encoding='utf-8')
    return source.name


if __name__ == '__main__':
    for src in sorted((SRC / 'pages').glob('*.html')):
        print('assemblé :', assembler(src))
