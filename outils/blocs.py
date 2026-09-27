"""Convertit le HTML « en forme de blocs » des pages en balisage Gutenberg natif.

Le dialecte source (site/src/pages/*.html) n'utilise que ce que les blocs du cœur
savent sauvegarder à l'identique :
  div/section/header/article/aside/footer   -> core/group (classes -> className, id -> ancre)
  h1…h6, p                                   -> core/heading, core/paragraph (texte en ligne seulement)
  ul/ol > li                                 -> core/list > core/list-item
  figure > img                               -> core/image (id de médiathèque au déploiement)
  div.boutons > a                            -> core/buttons > core/button
  details > summary + blocs                  -> core/details
  div[data-shortcode="[…]"]                  -> core/shortcode (la maquette affiche le contenu du div)
  div[data-bloc="search|loginout"]           -> core/search, core/loginout (idem)

Règle 5 de la méthode : aucun core/html n'est jamais produit ; toute balise hors
dialecte lève une erreur plutôt que de passer en silence.
"""
import html
import json
import re
from html.parser import HTMLParser

EN_LIGNE = {'a', 'strong', 'em', 'sup', 'sub', 'br', 's', 'code'}
VIDES = {'img', 'br', 'hr', 'input', 'meta', 'link', 'source'}
GROUPES = {'div', 'section', 'header', 'article', 'aside', 'footer', 'main', 'nav'}
TAGS_GROUPE = {'section', 'header', 'article', 'aside', 'footer', 'main'}


class Noeud:
    def __init__(self, tag, attrs, parent=None):
        self.tag, self.attrs, self.parent, self.enfants = tag, dict(attrs), parent, []

    def classes(self):
        return self.attrs.get('class', '').split()


class Arbre(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.racine = Noeud('racine', {})
        self.courant = self.racine

    def handle_starttag(self, tag, attrs):
        n = Noeud(tag, attrs, self.courant)
        self.courant.enfants.append(n)
        if tag not in VIDES:
            self.courant = n

    def handle_startendtag(self, tag, attrs):
        self.courant.enfants.append(Noeud(tag, attrs, self.courant))

    def handle_endtag(self, tag):
        n = self.courant
        while n is not self.racine and n.tag != tag:
            n = n.parent
        if n is not self.racine:
            self.courant = n.parent

    def handle_data(self, data):
        self.courant.enfants.append(data)

    def handle_entityref(self, name):
        self.courant.enfants.append(f'&{name};')

    def handle_charref(self, name):
        self.courant.enfants.append(f'&#{name};')

    def handle_comment(self, data):
        pass


class ErreurBloc(Exception):
    pass


def attrs_json(d):
    """Sérialise comme WordPress (JSON.stringify + échappements de serializeAttributes)."""
    s = json.dumps(d, ensure_ascii=False, separators=(',', ':'))
    return (s.replace('--', '\\u002d\\u002d').replace('<', '\\u003c').replace('>', '\\u003e')
             .replace('&', '\\u0026').replace('\\"', '\\u0022'))


def en_ligne(noeud, chemin):
    """HTML en ligne d'un paragraphe/titre ; refuse toute balise que RichText ne restitue pas."""
    sortie = []
    for e in noeud.enfants:
        if isinstance(e, str):
            sortie.append(e)
            continue
        if e.tag not in EN_LIGNE:
            raise ErreurBloc(f'{chemin} : <{e.tag}> interdit dans un texte (classes : {e.classes()})')
        if e.tag == 'br':
            sortie.append('<br>')
            continue
        attrs = ''
        for cle, val in e.attrs.items():
            if e.tag == 'a' and cle in ('href', 'target', 'rel'):
                attrs += f' {cle}="{val}"'
            else:
                raise ErreurBloc(f'{chemin} : attribut {cle} interdit sur <{e.tag}>')
        sortie.append(f'<{e.tag}{attrs}>{en_ligne(e, chemin)}</{e.tag}>')
    return ''.join(sortie).strip()


def html_brut(noeud):
    """Contenu d'un nœud tel quel (pour la maquette des shortcodes)."""
    sortie = []
    for e in noeud.enfants:
        if isinstance(e, str):
            sortie.append(e)
        else:
            a = ''.join(f' {k}="{html.escape(v, quote=True)}"' if v is not None else f' {k}' for k, v in e.attrs.items())
            if e.tag in VIDES:
                sortie.append(f'<{e.tag}{a}>')
            else:
                sortie.append(f'<{e.tag}{a}>{html_brut(e)}</{e.tag}>')
    return ''.join(sortie)


class Convertisseur:
    def __init__(self, medias=None, maquette=False):
        # medias : {chemin source: {"id": n, "url": "…"}} ; maquette=True garde les chemins locaux
        self.medias = medias or {}
        self.maquette = maquette

    def blocs(self, noeud, chemin='page'):
        return ''.join(self.bloc(e, chemin) for e in noeud.enfants if not isinstance(e, str) or e.strip())

    def bloc(self, e, chemin):
        if isinstance(e, str):
            raise ErreurBloc(f'{chemin} : texte hors paragraphe « {e.strip()[:40]} »')
        cls = ' '.join(e.classes())
        ici = f'{chemin} > {e.tag}{"." + ".".join(e.classes()) if cls else ""}'
        a = {}

        if e.tag in GROUPES and 'data-shortcode' in e.attrs:
            if self.maquette:
                return html_brut(e).strip() + '\n'
            return f'<!-- wp:shortcode -->\n{e.attrs["data-shortcode"]}\n<!-- /wp:shortcode -->\n'

        if e.tag in GROUPES and e.attrs.get('data-bloc') in ('search', 'loginout'):
            nom = e.attrs['data-bloc']
            if self.maquette:
                return html_brut(e).strip() + '\n'
            if nom == 'search':
                a = {'label': e.attrs.get('data-label', 'Rechercher'), 'showLabel': False,
                     'placeholder': e.attrs.get('data-placeholder', ''), 'buttonText': 'Rechercher',
                     'buttonPosition': 'button-inside', 'buttonUseIcon': True}
            else:
                a = {'displayLoginAsForm': True}
            if cls:
                a['className'] = cls
            return f'<!-- wp:{nom} {attrs_json(a)} /-->\n'

        if e.tag in GROUPES and 'boutons' in e.classes():
            if cls:
                a['className'] = cls
            interieur = ''.join(self.bouton(b, ici) for b in e.enfants if not isinstance(b, str) or b.strip())
            ouvre = f'<!-- wp:buttons {attrs_json(a)} -->' if a else '<!-- wp:buttons -->'
            return f'{ouvre}\n<div class="wp-block-buttons{" " + cls if cls else ""}">{interieur}</div>\n<!-- /wp:buttons -->\n'

        if e.tag in GROUPES:
            tag = e.tag if e.tag in TAGS_GROUPE else 'div'
            if tag != 'div':
                a['tagName'] = tag
            if cls:
                a['className'] = cls
            a['layout'] = {'type': 'default'}
            ident = f' id="{e.attrs["id"]}"' if 'id' in e.attrs else ''
            classes = 'wp-block-group' + (' ' + cls if cls else '')
            return (f'<!-- wp:group {attrs_json(a)} -->\n<{tag}{ident} class="{classes}">'
                    f'{self.blocs(e, ici)}</{tag}>\n<!-- /wp:group -->\n')

        if re.fullmatch(r'h[1-6]', e.tag):
            niveau = int(e.tag[1])
            if niveau != 2:
                a['level'] = niveau
            if cls:
                a['className'] = cls
            ident = f' id="{e.attrs["id"]}"' if 'id' in e.attrs else ''
            ouvre = f'<!-- wp:heading {attrs_json(a)} -->' if a else '<!-- wp:heading -->'
            return f'{ouvre}\n<{e.tag} class="wp-block-heading{" " + cls if cls else ""}"{ident}>{en_ligne(e, ici)}</{e.tag}>\n<!-- /wp:heading -->\n'

        if e.tag == 'p':
            if cls:
                a['className'] = cls
            ident = f' id="{e.attrs["id"]}"' if 'id' in e.attrs else ''
            ouvre = f'<!-- wp:paragraph {attrs_json(a)} -->' if a else '<!-- wp:paragraph -->'
            classe = f' class="{cls}"' if cls else ''
            return f'{ouvre}\n<p{classe}{ident}>{en_ligne(e, ici)}</p>\n<!-- /wp:paragraph -->\n'

        if e.tag in ('ul', 'ol'):
            if e.tag == 'ol':
                a['ordered'] = True
            if cls:
                a['className'] = cls
            items = ''
            for li in e.enfants:
                if isinstance(li, str):
                    if li.strip():
                        raise ErreurBloc(f'{ici} : texte hors <li>')
                    continue
                if li.tag != 'li':
                    raise ErreurBloc(f'{ici} : <{li.tag}> dans une liste')
                lc = ' '.join(li.classes())
                la = {'className': lc} if lc else {}
                ouvre = f'<!-- wp:list-item {attrs_json(la)} -->' if la else '<!-- wp:list-item -->'
                items += f'{ouvre}\n<li{f" class={chr(34)}{lc}{chr(34)}" if lc else ""}>{en_ligne(li, ici + " > li")}</li>\n<!-- /wp:list-item -->'
            ouvre = f'<!-- wp:list {attrs_json(a)} -->' if a else '<!-- wp:list -->'
            return f'{ouvre}\n<{e.tag} class="wp-block-list{" " + cls if cls else ""}">{items}</{e.tag}>\n<!-- /wp:list -->\n'

        if e.tag == 'figure':
            imgs = [x for x in e.enfants if not isinstance(x, str)]
            if len(imgs) != 1 or imgs[0].tag != 'img':
                raise ErreurBloc(f'{ici} : une figure contient exactement une image')
            img = imgs[0]
            src, alt = img.attrs.get('src', ''), img.attrs.get('alt', '')
            media = self.medias.get(src)
            if media and not self.maquette:
                a['id'] = media['id']
                src = media['url']
            a['sizeSlug'] = 'full'
            a['linkDestination'] = 'none'
            if cls:
                a['className'] = cls
            classe_img = f' class="wp-image-{a["id"]}"' if 'id' in a else ''
            return (f'<!-- wp:image {attrs_json(a)} -->\n<figure class="wp-block-image size-full{" " + cls if cls else ""}">'
                    f'<img src="{src}" alt="{alt}"{classe_img}/></figure>\n<!-- /wp:image -->\n')

        if e.tag == 'details':
            enfants = [x for x in e.enfants if not isinstance(x, str) or x.strip()]
            if not enfants or isinstance(enfants[0], str) or enfants[0].tag != 'summary':
                raise ErreurBloc(f'{ici} : <details> commence par <summary>')
            resume = en_ligne(enfants[0], ici)
            reste = Noeud('div', {})
            reste.enfants = enfants[1:]
            if cls:
                a['className'] = cls
            ouvre = f'<!-- wp:details {attrs_json(a)} -->' if a else '<!-- wp:details -->'
            return (f'{ouvre}\n<details class="wp-block-details{" " + cls if cls else ""}"><summary>{resume}</summary>'
                    f'{self.blocs(reste, ici)}</details>\n<!-- /wp:details -->\n')

        raise ErreurBloc(f'{ici} : balise hors dialecte')

    def bouton(self, b, chemin):
        if isinstance(b, str) or b.tag != 'a':
            raise ErreurBloc(f'{chemin} : un conteneur .boutons ne contient que des <a>')
        a = {}
        cls = ' '.join(b.classes())
        if cls:
            a['className'] = cls
        href = b.attrs.get('href', '')
        extra = ''
        if b.attrs.get('target'):
            a['linkTarget'] = b.attrs['target']
            extra += f' target="{b.attrs["target"]}"'
        if b.attrs.get('rel'):
            a['rel'] = b.attrs['rel']
            extra += f' rel="{b.attrs["rel"]}"'
        ouvre = f'<!-- wp:button {attrs_json(a)} -->' if a else '<!-- wp:button -->'
        return (f'{ouvre}\n<div class="wp-block-button{" " + cls if cls else ""}"><a class="wp-block-button__link wp-element-button" href="{href}"{extra}>'
                f'{en_ligne(b, chemin + " > a")}</a></div>\n<!-- /wp:button -->')


def convertir(source_html, medias=None, maquette=False):
    arbre = Arbre()
    arbre.feed(source_html)
    return Convertisseur(medias, maquette).blocs(arbre.racine)


def sans_commentaires(blocs):
    """Le HTML sauvegardé par les blocs, sans leurs délimiteurs : c'est aussi la maquette."""
    return re.sub(r'<!-- /?wp:[^>]*?-->\n?', '', blocs)


def gabarits(source_html):
    """{shortcode: HTML} : le rendu attendu de chaque shortcode, tel que la maquette l'affiche.
    L'extension WordPress sert ces fichiers tels quels, d'où l'égalité au pixel."""
    arbre = Arbre()
    arbre.feed(source_html)
    trouves = {}

    def parcourir(noeud):
        for e in noeud.enfants:
            if isinstance(e, str):
                continue
            if 'data-shortcode' in e.attrs:
                trouves[e.attrs['data-shortcode']] = html_brut(e).strip() + '\n'
            else:
                parcourir(e)
    parcourir(arbre.racine)
    return trouves


def nom_gabarit(shortcode):
    """[hg_outil type="nfs"] -> outil-nfs"""
    m = re.match(r'\[(\w+)(.*?)\]', shortcode)
    nom = m.group(1).removeprefix('hg_').replace('_', '-')
    return '-'.join([nom] + re.findall(r'=\s*"([^"]*)"', m.group(2)))
