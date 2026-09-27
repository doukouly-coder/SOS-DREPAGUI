"""Articles d'actualité : un gabarit commun, rempli depuis les métadonnées de chaque article.

Une source d'article (site/src/articles/<slug>.html) ne contient que ses métadonnées
et son texte, en « HTML en forme de blocs » :

  <!--{"titre": "… | HEMATO GUI", "description": "…", "wp_titre": "Titre de l'article",
       "titre_court": "Fil d'Ariane", "categorie": "Sensibilisation", "date": "2026-09-22",
       "image": "assets/img/rendus/x.webp", "alt": "", "chapeau": "…", "lecture": 5}-->
  <h2>…</h2><p>…</p>…

L'en-tête (fil d'Ariane, catégorie, titre, chapeau, date, durée de lecture), l'illustration,
l'avertissement médical et la section « À lire aussi » sont composés ici : tous les articles
ont la même structure, et la section « À lire aussi » suit d'elle-même les nouveaux articles.
"""
import json
import re
from pathlib import Path

SRC_ARTICLES = Path(__file__).resolve().parent.parent / 'site' / 'src' / 'articles'
MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août',
        'septembre', 'octobre', 'novembre', 'décembre']
WHATSAPP_ACTUS = ('https://wa.me/224628733143?text=Bonjour%20HEMATO%20GUI%2C%20je%20souhaite%20'
                  'recevoir%20vos%20actualit%C3%A9s.')
AVERTISSEMENT = ('<p class="avertissement icone-avant ico-info">Les informations présentes sur ce site sont '
                 'destinées à l’éducation et à la sensibilisation. Elles ne remplacent pas une consultation '
                 'médicale.</p>')


def date_fr(iso):
    """2026-09-01 -> « 1<sup>er</sup> septembre 2026 »."""
    annee, mois, jour = (int(x) for x in iso.split('-'))
    return f'{"1<sup>er</sup>" if jour == 1 else jour} {MOIS[mois - 1]} {annee}'


def lire(chemin):
    brut = chemin.read_text(encoding='utf-8')
    entete = re.match(r'<!--(\{.*?\})-->\n?', brut, re.S)
    meta = json.loads(entete.group(1))
    meta['slug'] = chemin.stem
    return meta, brut[entete.end():]


def tous():
    """Métadonnées de tous les articles, du plus récent au plus ancien."""
    return sorted((lire(c)[0] for c in SRC_ARTICLES.glob('*.html')), key=lambda m: m['date'], reverse=True)


def carte(m):
    return (f'<div class="article carte-lien"><figure class="article-visuel"><img src="{m["image"]}" alt=""></figure>'
            f'<div class="article-corps"><p class="article-meta"><strong>{m["categorie"]}</strong><em>{date_fr(m["date"])}</em></p>'
            f'<h3 class="lien-etire"><a href="{m["slug"]}.html">{m["wp_titre"]}</a></h3></div></div>')


def composer(chemin, liste=None):
    """Source complète d'un article, au format des pages (commentaire JSON + corps)."""
    meta, texte = lire(chemin)
    liste = liste if liste is not None else tous()
    autres = [m for m in liste if m['slug'] != meta['slug']]
    autres.sort(key=lambda m: (m['categorie'] != meta['categorie'], -int(m['date'].replace('-', ''))))
    suite = '\n      '.join(carte(m) for m in autres[:3])
    corps = f'''<section class="page-hero page-hero-compact article-hero">
  <div class="wrap article-etroit">
    <p class="ariane"><a href="index.html">Accueil</a><a href="actualites.html">Actualités</a><strong>{meta["titre_court"]}</strong></p>
    <p class="surtitre">{meta["categorie"]}</p>
    <h1 class="titre-page titre-article">{meta["wp_titre"]}</h1>
    <p class="chapeau">{meta["chapeau"]}</p>
    <div class="reperes">
      <p class="repere icone-avant ico-calendrier">{date_fr(meta["date"])}</p>
      <p class="repere icone-avant ico-horloge">Lecture : {meta["lecture"]} min</p>
    </div>
  </div>
</section>

<div class="article-page">
  <div class="wrap article-etroit">
    <figure class="article-illustration"><img src="{meta["image"]}" alt="{meta.get("alt", "")}"></figure>
    <div class="article-texte">
{texte.strip()}
      {AVERTISSEMENT}
    </div>
  </div>
</div>

<section class="section section-grise article-suite">
  <div class="wrap">
    <div class="tete-gauche">
      <p class="surtitre">À lire aussi</p>
      <h2>Continuer à s’informer.</h2>
    </div>
    <div class="actus-liste actus-liste-3">
      {suite}
    </div>
    <div class="bande-wa">
      <div><h3>Recevoir nos actualités sur WhatsApp</h3><p>Campagnes, conseils et dates de dépistage, directement sur votre téléphone.</p></div>
      <div class="boutons"><a class="btn btn-wa btn-grand b-ico ico-whatsapp" href="{WHATSAPP_ACTUS}" target="_blank" rel="noopener">S’abonner</a></div>
    </div>
  </div>
</section>
'''
    entete = {
        'titre': meta['titre'], 'description': meta['description'], 'nav': 'actualites',
        'wp_titre': meta['wp_titre'], 'slug': meta['slug'], 'type': 'article',
        'date': meta['date'], 'categorie': meta['categorie'], 'image': meta['image'],
        'extrait': meta['chapeau'],
    }
    return '<!--' + json.dumps(entete, ensure_ascii=False) + '-->\n' + corps, entete
