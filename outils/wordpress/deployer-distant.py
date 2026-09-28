"""Déploiement du site sur le WordPress en ligne, par l'extension Novamira (phase 5 de la méthode).

    python3 outils/wordpress/deployer-distant.py --bundle <site.mcpb> diagnostic   # lecture seule
    python3 outils/wordpress/deployer-distant.py --bundle <site.mcpb> deployer     # étapes 0 à 7
    python3 outils/wordpress/deployer-distant.py --bundle <site.mcpb> verifier     # contrôle HTTP seul
    python3 outils/wordpress/deployer-distant.py --bundle <site.mcpb> retour       # retour à l'état sauvegardé

Sans --bundle, les identifiants sont lus dans WP_API_URL, WP_API_USERNAME et WP_API_PASSWORD.

Les étapes serveur sont celles de deploiement.php, envoyées avec chaque appel : c'est exactement
le code répété sur le WordPress local (deployer-local.sh). Les fichiers de transfert (archives,
contenu) ne restent sur le serveur que le temps d'une étape, et sont supprimés même en cas d'échec.
Sauvegarde, table des médias et compte rendu sont écrits dans deploiement/<hôte>/ (non versionné).
"""
import argparse
import datetime
import hashlib
import json
import re
import secrets
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile
from pathlib import Path

ICI = Path(__file__).resolve().parent
DEPOT = ICI.parents[1]
sys.path.insert(0, str(ICI))
from novamira import ErreurNovamira, Novamira, identifiants  # noqa: E402

LOT_MEDIAS = 12
WP_MINI = (6, 6)
PHP_MINI = (8, 0)


def lib():
    """deploiement.php sans sa balise d'ouverture, à préfixer au code envoyé."""
    return (ICI / 'deploiement.php').read_text(encoding='utf-8').replace('<?php', '', 1)


def php(nm, code):
    r = nm.php(lib() + '\n' + code)
    if isinstance(r, dict) and r.get('erreur'):
        raise ErreurNovamira(json.dumps(r, ensure_ascii=False)[:800])
    return r


def etape(titre):
    print(f'\n— {titre}', flush=True)


def slugs_deployes():
    slugs = [json.loads(f.read_text(encoding='utf-8'))['slug'] for f in sorted((DEPOT / 'wordpress/contenu').glob('*.json'))]
    slugs += [json.loads(f.read_text(encoding='utf-8'))['slug'] for f in sorted((DEPOT / 'wordpress/contenu/articles').glob('*.json'))]
    return slugs


def zipper(archive, entrees):
    """entrees : [(dossier_source, prefixe_dans_l_archive)]"""
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as z:
        for dossier, prefixe in entrees:
            for f in sorted(Path(dossier).rglob('*')):
                if f.is_file() and '__pycache__' not in f.parts and f.name != '.DS_Store':
                    z.write(f, f'{prefixe}/{f.relative_to(dossier)}')
    return archive


# -- étapes -------------------------------------------------------------------------------

def diagnostic(nm):
    d = php(nm, 'return hg_dep_diagnostic() + array("uploads_rel" => ltrim(substr(wp_upload_dir()["basedir"], strlen(ABSPATH)), "/"));')
    for cle in ('site', 'wordpress', 'php', 'theme_actif', 'langue', 'permaliens', 'accueil', 'zip',
                'methode_fichier', 'nb_pages', 'nb_articles', 'nb_medias', 'utilisateur'):
        print(f'  {cle:16} {d.get(cle)}')
    print(f'  {"extensions":16} {", ".join(d.get("extensions") or []) or "aucune"}')
    bloquants = []
    version = lambda v: tuple(int(x) for x in re.findall(r'\d+', v)[:2])  # noqa: E731
    if version(d['wordpress']) < WP_MINI:
        bloquants.append(f'WordPress {d["wordpress"]} : il faut {WP_MINI[0]}.{WP_MINI[1]} ou plus')
    if version(d['php']) < PHP_MINI:
        bloquants.append(f'PHP {d["php"]} : il faut {PHP_MINI[0]}.{PHP_MINI[1]} ou plus (extension HEMATO GUI)')
    if d.get('multisite'):
        bloquants.append('multisite : non prévu par ce déploiement')
    if not d.get('peut_tout'):
        bloquants.append(f'le compte {d.get("utilisateur")} n’est pas administrateur')
    for cle, quoi in (('ecriture_themes', 'dossier des thèmes'), ('ecriture_ext', 'dossier des extensions'), ('ecriture_upload', 'dossier uploads')):
        if not d.get(cle):
            bloquants.append(f'{quoi} non inscriptible par PHP')
    if d.get('methode_fichier') != 'direct':
        print(f'  ! méthode d’écriture « {d.get("methode_fichier")} » : la copie directe peut échouer')
    caches = [e for e in d.get('extensions') or [] if re.search(r'cache|rocket|litespeed|optimi|speed', e, re.I)]
    if caches:
        print(f'  ! extensions de cache : {", ".join(caches)} (purgées en fin de déploiement)')
    d['bloquants'] = bloquants
    print('  bloquants       ', '; '.join(bloquants) if bloquants else 'aucun')
    return d


def sauvegarder(nm, dossier):
    s = php(nm, f'return hg_dep_sauvegarde(json_decode({json.dumps(json.dumps(slugs_deployes()))}, true));')
    horodatage = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
    fichier = dossier / f'sauvegarde-avant-theme-{horodatage}.json'
    fichier.write_text(json.dumps(s, ensure_ascii=False, indent=1), encoding='utf-8')
    print(f'  {len(s["contenus"])} contenus sauvegardés -> {fichier.relative_to(DEPOT)} · en base : {s["en_base"]}')
    print(f'  thème actuel : {s["theme_avant"]} (reste installé : retour arrière possible)')
    if s['collisions']:
        print(f'  ! {len(s["collisions"])} contenus existants portent un slug du nouveau site et seront réécrits '
              '(ancien contenu dans la sauvegarde et les révisions) :')
        for c in s['collisions']:
            print(f'    - {c["type"]} « {c["titre"]} » ({c["slug"]}, {c["statut"]}, {c["taille"]} caractères)')
    return s


def televerser_et_deballer(nm, archive, uploads_rel, jeton, sous_dossier=''):
    distant = f'{uploads_rel}/hg-{jeton}{"-" + sous_dossier if sous_dossier else ""}.zip'
    taille = nm.televerser(archive, distant)
    cible = f'hg-{jeton}' + (f'/{sous_dossier}' if sous_dossier else '')
    php(nm, f'''
        $zip = ABSPATH . {json.dumps(distant)};
        $r = hg_dep_deballer($zip, WP_CONTENT_DIR . '/upgrade/' . {json.dumps(cible)});
        @unlink($zip);
        return is_wp_error($r) ? array('erreur' => $r->get_error_message()) : array('ok' => true);''')
    print(f'  {archive.name} ({taille // 1024} Ko) téléversé, décompressé, archive supprimée du serveur')


def nettoyer(nm, uploads_rel):
    """Supprime tout fichier de transfert, y compris ceux d'un déploiement interrompu."""
    r = php(nm, f'''
        $partis = array();
        foreach ((array) glob(WP_CONTENT_DIR . '/upgrade/hg-*', GLOB_ONLYDIR) as $d) {{
            if (preg_match('/hg-[a-f0-9]{{16}}$/', $d)) {{ hg_dep_supprimer($d); $partis[] = basename($d); }}
        }}
        foreach ((array) glob(ABSPATH . {json.dumps(uploads_rel)} . '/hg-*.zip') as $f) {{
            if (preg_match('/hg-[a-f0-9]{{16}}(-[a-z]+)?\\.zip$/', $f)) {{ @unlink($f); $partis[] = basename($f); }}
        }}
        return $partis;''')
    print(f'  fichiers de transfert supprimés du serveur : {len(r)}')
    return r


def deployer(nm, dossier):
    d = diagnostic(nm)
    if d['bloquants']:
        raise ErreurNovamira('déploiement arrêté : ' + '; '.join(d['bloquants']))
    uploads_rel = d['uploads_rel']
    site = d['site']
    jeton = secrets.token_hex(8)
    rapport = {'site': site, 'date': datetime.datetime.now().isoformat(timespec='seconds')}

    etape('0 · Sauvegarde de l’existant')
    s = sauvegarder(nm, dossier)
    rapport['collisions'] = s['collisions']
    try:
        etape('1 · Construction et téléversement du thème, de l’extension et des visuels')
        subprocess.run([sys.executable, str(ICI / 'construire.py')], check=True, stdout=subprocess.DEVNULL)
        paquet = zipper(DEPOT / 'dist' / 'hg-paquet.zip', [
            (DEPOT / 'wordpress/theme/hemato-gui', 'theme/hemato-gui'),
            (DEPOT / 'wordpress/extension/hemato-gui', 'extension/hemato-gui'),
            (DEPOT / 'site/assets/img', 'img'),
        ])
        televerser_et_deballer(nm, paquet, uploads_rel, jeton)

        etape('2 · Thème et extension activés')
        r = php(nm, f'''$t = WP_CONTENT_DIR . '/upgrade/hg-{jeton}';
            return hg_dep_installer($t . '/theme/hemato-gui', $t . '/extension/hemato-gui');''')
        print(f'  thème {r["theme"]} {r["version"]} · extension {r["extension"]} · {r["langue"]}')
        rapport['installation'] = r

        etape('3 · Visuels en médiathèque')
        while True:
            m = php(nm, f'return hg_dep_medias(WP_CONTENT_DIR . "/upgrade/hg-{jeton}/img", {LOT_MEDIAS});')
            for image, message in (m['erreurs'] or {}).items():
                print(f'  ! {image} : {message}')
            print(f'  {len(m["carte"])} en médiathèque (+{m["importees"]}), {m["restant"]} restantes', flush=True)
            if not m['restant']:
                break
            if not m['importees']:
                raise ErreurNovamira('import des visuels bloqué : ' + json.dumps(m['erreurs'], ensure_ascii=False)[:400])
        medias = dossier / 'medias.json'
        medias.write_text(json.dumps(m['carte'], ensure_ascii=False, indent=1), encoding='utf-8')
        total = len(list((DEPOT / 'site/assets/img').glob('*/*.*')))
        if len(m['carte']) != total:
            raise ErreurNovamira(f'{len(m["carte"])} visuels en médiathèque sur {total}')

        etape('5 · Pages, articles et menu (contenu lié aux identifiants de la médiathèque)')
        contenu = dossier / 'contenu'
        shutil.rmtree(contenu, ignore_errors=True)
        subprocess.run([sys.executable, str(ICI / 'contenu.py'), str(medias), site, str(contenu)], check=True)
        archive = zipper(DEPOT / 'dist' / 'hg-contenu.zip', [(contenu, '.')])
        televerser_et_deballer(nm, archive, uploads_rel, jeton, 'contenu')
        p = php(nm, f'return hg_dep_pages(WP_CONTENT_DIR . "/upgrade/hg-{jeton}/contenu");')
        for slug, message in (p['erreurs'] or {}).items():
            print(f'  ! {slug} : {message}')
        print(f'  {p["pages"]} pages, {p["articles"]} articles · {p["menu"]} · exemples retirés : {", ".join(p["retires"]) or "aucun"}')

        etape('6 · Page d’accueil statique et permaliens')
        a = php(nm, 'return array("show_on_front" => get_option("show_on_front"), "accueil" => get_the_title((int) get_option("page_on_front")), "permaliens" => get_option("permalink_structure"));')
        print(f'  accueil : page « {a["accueil"]} » ({a["show_on_front"]}) · permaliens {a["permaliens"]}')
        rapport['pages'] = p
    finally:
        etape('7 · Purge des caches et nettoyage')
        try:
            print('  purgés :', ', '.join(php(nm, 'return hg_dep_purger();')))
        finally:
            rapport['transfert_supprime'] = nettoyer(nm, uploads_rel)

    rapport['verification'] = verifier(nm, site, uploads_rel, jeton)
    fichier = dossier / f'rapport-{datetime.datetime.now():%Y%m%d-%H%M%S}.json'
    fichier.write_text(json.dumps(rapport, ensure_ascii=False, indent=1), encoding='utf-8')
    print(f'\nCompte rendu : {fichier.relative_to(DEPOT)}')
    return rapport


# -- vérification de ce qui est réellement servi ------------------------------------------

def obtenir(url, redirections=True):
    class SansRedirection(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None
    ouvreur = urllib.request.build_opener() if redirections else urllib.request.build_opener(SansRedirection)
    req = urllib.request.Request(url, headers={'User-Agent': 'hemato-gui-recette/1', 'Cache-Control': 'no-cache'})
    try:
        with ouvreur.open(req, timeout=60) as r:
            return r.status, r.read(), dict(r.headers)
    except urllib.error.HTTPError as e:
        return e.code, e.read(), dict(e.headers)


def frais(url):
    return url + ('&' if '?' in url else '?') + f'frais={int(time.time())}'


def verifier(nm, site=None, uploads_rel=None, jeton=None):
    etape('Vérification de ce que le site sert réellement')
    if not site:
        site = php(nm, 'return home_url("/");')
    controles = []

    def ok(nom, condition, detail=''):
        controles.append({'controle': nom, 'ok': bool(condition), 'detail': detail})
        print(f'  {"✓" if condition else "✗"} {nom}{" — " + detail if detail else ""}')

    statut, html, _ = obtenir(frais(site))
    page = html.decode('utf-8', 'replace')
    ok('accueil en HTTP 200', statut == 200, str(statut))
    ok('langue fr-FR', 'lang="fr-FR"' in page)
    ok('thème hemato-gui servi', '/themes/hemato-gui/' in page)
    ok('page d’accueil = contenu HEMATO GUI', 'hero' in page and 'HEMATO GUI' in page)

    liens = php(nm, '''$l = array();
        foreach (get_posts(array("post_type" => array("page", "post"), "post_status" => "publish", "numberposts" => -1)) as $p) { $l[] = get_permalink($p); }
        return $l;''')
    erreurs, actifs = [], set()
    motif = re.compile(r'''(?:href|src)=["']([^"']+/(?:themes|plugins)/hemato-gui/[^"']+\.(?:css|js|woff2|svg)[^"']*)["']''')
    for lien in liens:
        s, corps, _ = obtenir(frais(lien))
        if s != 200:
            erreurs.append(f'{lien} ({s})')
        actifs.update(motif.findall(corps.decode('utf-8', 'replace')))
    ok(f'{len(liens)} pages et articles en HTTP 200', not erreurs, ', '.join(erreurs[:5]))

    # Chaque feuille, police et script du thème et de l'extension : identique à l'octet près au fichier local.
    locaux = {'themes/hemato-gui/': DEPOT / 'wordpress/theme/hemato-gui', 'plugins/hemato-gui/': DEPOT / 'wordpress/extension/hemato-gui'}
    chemins = sorted({urllib.parse.urlsplit(u.replace('&#038;', '&')).path for u in actifs})
    differents = []
    for chemin in chemins:
        for marque, dossier in locaux.items():
            if marque in chemin:
                local = dossier / chemin.split(marque, 1)[1]
                s, corps, _ = obtenir(frais(urllib.parse.urljoin(site, chemin)))
                if s != 200 or not local.exists() or hashlib.sha256(corps).digest() != hashlib.sha256(local.read_bytes()).digest():
                    differents.append(f'{chemin} ({s})')
    ok(f'{len(chemins)} fichiers du thème et de l’extension identiques à l’octet près', chemins and not differents,
       ', '.join(differents) or ', '.join(c.rsplit('/', 1)[1] for c in chemins))

    s, _, entetes = obtenir(frais(site.rstrip('/') + '/?author=1'), redirections=False)
    cible = entetes.get('Location', '')
    ok('?author=1 ne révèle aucun identifiant', '/author/' not in cible, f'{s} {cible}'.strip())
    s, corps, _ = obtenir(site.rstrip('/') + '/wp-json/wp/v2/users')
    ok('liste des comptes fermée aux visiteurs', s in (401, 403, 404) or corps.strip() in (b'[]', b''), str(s))
    if uploads_rel and jeton:
        s, corps, _ = obtenir(urllib.parse.urljoin(site, f'/{uploads_rel}/hg-{jeton}.zip'), redirections=False)
        ok('archive de transfert absente du serveur', s != 200 or not corps.startswith(b'PK'), str(s))
    echecs = [c for c in controles if not c['ok']]
    print(f'  {len(controles) - len(echecs)}/{len(controles)} contrôles passés')
    return controles


def retour(nm):
    """Retour à l'état sauvegardé : thème, réglages de lecture, titres, contenus réécrits ;
    les pages et articles créés par le déploiement passent en brouillon (rien n'est supprimé)."""
    r = php(nm, '''
        $s = get_option('hg_sauvegarde_avant_theme');
        if (!$s) { return array('erreur' => 'aucune sauvegarde en base (hg_sauvegarde_avant_theme)'); }
        switch_theme($s['theme_avant']);
        foreach (array('show_on_front', 'page_on_front', 'page_for_posts', 'permalink_structure' => 'permaliens', 'blogname' => 'titre_site', 'blogdescription' => 'slogan', 'WPLANG' => 'langue', 'timezone_string' => 'fuseau') as $option => $cle) {
            if (is_int($option)) { $option = $cle; }
            update_option($option, $s[$cle]);
        }
        $anciens = array();
        foreach ($s['contenus'] as $c) { $anciens[(int) $c['ID']] = $c; }
        $restaures = 0;
        foreach ($s['collisions'] as $c) {
            $a = $anciens[$c['id']];
            wp_update_post(wp_slash(array('ID' => $c['id'], 'post_title' => $a['post_title'], 'post_content' => $a['post_content'], 'post_status' => $a['post_status'])));
            $restaures++;
        }
        $brouillons = 0;
        foreach (get_posts(array('post_type' => array('page', 'post'), 'post_status' => 'publish', 'numberposts' => -1, 'post__not_in' => array_keys($anciens))) as $p) {
            wp_update_post(array('ID' => $p->ID, 'post_status' => 'draft'));
            $brouillons++;
        }
        deactivate_plugins('hemato-gui/hemato-gui.php');
        flush_rewrite_rules(false);
        hg_dep_purger();
        return array('theme' => get_stylesheet(), 'restaures' => $restaures, 'brouillons' => $brouillons);''')
    print(f'  thème {r["theme"]} rétabli · {r["restaures"]} contenus restaurés · {r["brouillons"]} pages et articles HEMATO GUI en brouillon')
    return r


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--bundle', help='bundle .mcpb de Novamira (ou dossier décompressé)')
    ap.add_argument('action', choices=['diagnostic', 'deployer', 'verifier', 'retour'])
    args = ap.parse_args()
    try:
        url, utilisateur, mdp = identifiants(args.bundle)
        nm = Novamira(url, utilisateur, mdp).ouvrir()
        hote = urllib.parse.urlsplit(url).netloc
        print(f'Connecté à {hote} en tant que {utilisateur}.')
        dossier = DEPOT / 'deploiement' / re.sub(r'[^\w.-]', '_', hote)
        dossier.mkdir(parents=True, exist_ok=True)
        if args.action == 'diagnostic':
            etape('Diagnostic (lecture seule)')
            diagnostic(nm)
        elif args.action == 'deployer':
            deployer(nm, dossier)
        elif args.action == 'verifier':
            verifier(nm)
        else:
            etape('Retour à l’état sauvegardé')
            retour(nm)
    except ErreurNovamira as e:
        print(f'\nArrêt : {e}', file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
