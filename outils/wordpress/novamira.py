"""Client minimal de l'extension Novamira (serveur MCP d'un site WordPress), en HTTP direct.

Même protocole que .claude/skills/claude-to-wordpress/scripts/wp.sh, sans troncature des
réponses et sans guillemets à échapper : le code PHP part tel quel, le résultat revient en JSON.

Les identifiants viennent du bundle .mcpb (lu en mémoire, jamais décompressé sur le disque)
ou des variables WP_API_URL, WP_API_USERNAME et WP_API_PASSWORD. Ils ne sont jamais écrits
ni affichés : ne pas committer de bundle (voir .gitignore).
"""
import base64
import json
import os
import re
import urllib.error
import urllib.parse
import urllib.request
import zipfile

MARQUE = re.compile(r'HGR:([A-Za-z0-9+/=]+):HGR')


class ErreurNovamira(Exception):
    pass


def identifiants(bundle=None):
    """(url, utilisateur, mot_de_passe) depuis un bundle .mcpb, un dossier de bundle ou l'environnement."""
    if bundle:
        if os.path.isdir(bundle):
            with open(os.path.join(bundle, 'manifest.json'), encoding='utf-8') as f:
                manifeste = json.load(f)
        else:
            with zipfile.ZipFile(bundle) as z:
                manifeste = json.loads(z.read('manifest.json'))
        env = manifeste['server']['mcp_config']['env']
    else:
        env = os.environ
    url, util, mdp = env.get('WP_API_URL'), env.get('WP_API_USERNAME'), env.get('WP_API_PASSWORD')
    if not (url and util and mdp):
        raise ErreurNovamira('Identifiants absents : fournir le bundle .mcpb du site, '
                             'ou WP_API_URL, WP_API_USERNAME et WP_API_PASSWORD.')
    return url, util, mdp


def _chaines(x):
    """Toutes les chaînes d'une structure JSON, en décodant au passage le JSON imbriqué."""
    if isinstance(x, str):
        yield x
        s = x.strip()
        if s[:1] in '{["':
            try:
                yield from _chaines(json.loads(s))
            except ValueError:
                pass
    elif isinstance(x, dict):
        for v in x.values():
            yield from _chaines(v)
    elif isinstance(x, list):
        for v in x:
            yield from _chaines(v)


def _cherche(x, cle):
    """Premier dictionnaire (JSON imbriqué compris) qui contient la clé."""
    if isinstance(x, dict):
        if cle in x:
            return x
        for v in x.values():
            r = _cherche(v, cle)
            if r:
                return r
    elif isinstance(x, list):
        for v in x:
            r = _cherche(v, cle)
            if r:
                return r
    elif isinstance(x, str) and x.strip()[:1] in '{[':
        try:
            return _cherche(json.loads(x), cle)
        except ValueError:
            return None
    return None


class Novamira:
    def __init__(self, url, utilisateur, mot_de_passe, delai=300):
        self.url = url
        self.hote = urllib.parse.urlsplit(url).netloc
        self.delai = delai
        self._auth = 'Basic ' + base64.b64encode(f'{utilisateur}:{mot_de_passe}'.encode()).decode()
        self._session = None
        self._n = 1

    # -- transport -----------------------------------------------------------------------
    def _post(self, corps, session=True):
        entetes = {'Content-Type': 'application/json', 'Accept': 'application/json, text/event-stream',
                   'Authorization': self._auth}
        if session and self._session:
            entetes['Mcp-Session-Id'] = self._session
        req = urllib.request.Request(self.url, data=json.dumps(corps).encode(), headers=entetes, method='POST')
        try:
            with urllib.request.urlopen(req, timeout=self.delai) as r:
                return r.headers, r.read().decode('utf-8', 'replace')
        except urllib.error.HTTPError as e:
            texte = e.read().decode('utf-8', 'replace')[:400]
            if e.code == 401:
                raise ErreurNovamira(f'{self.hote} refuse les identifiants (401) : mot de passe '
                                     'd’application révoqué ou erroné.') from None
            if e.code == 403 and texte.lstrip().startswith('{'):
                raise ErreurNovamira(f'{self.hote} : WordPress refuse (403) — droits insuffisants ou '
                                     f'Novamira désactivée. {texte}') from None
            if e.code == 403:
                raise ErreurNovamira(f'{self.hote} : accès refusé (403) avant WordPress — hôte non autorisé '
                                     'par la politique réseau de la session, ou pare-feu de l’hébergeur.') from None
            raise ErreurNovamira(f'{self.hote} : HTTP {e.code} — {texte}') from None
        except urllib.error.URLError as e:
            raise ErreurNovamira(f'{self.hote} injoignable ({e.reason}) : vérifier l’adresse du site et '
                                 'la politique réseau de la session.') from None

    @staticmethod
    def _json(brut):
        for ligne in brut.splitlines():
            if ligne.startswith('data:'):
                brut = ligne[5:].strip()
        try:
            return json.loads(brut)
        except ValueError:
            raise ErreurNovamira('Réponse illisible : ' + brut[:300]) from None

    def ouvrir(self):
        entetes, _ = self._post({'jsonrpc': '2.0', 'id': 1, 'method': 'initialize', 'params': {
            'protocolVersion': '2024-11-05', 'capabilities': {},
            'clientInfo': {'name': 'hemato-gui-deploiement', 'version': '1'}}}, session=False)
        self._session = entetes.get('Mcp-Session-Id')
        if not self._session:
            raise ErreurNovamira('Pas d’identifiant de session MCP renvoyé : Novamira est-elle active ?')
        self._post({'jsonrpc': '2.0', 'method': 'notifications/initialized'})
        return self

    # -- abilities -----------------------------------------------------------------------
    def ability(self, nom, parametres=None):
        if not self._session:
            self.ouvrir()
        self._n += 1
        corps = {'jsonrpc': '2.0', 'id': self._n, 'method': 'tools/call', 'params': {
            'name': 'mcp-adapter-execute-ability',
            'arguments': {'ability_name': nom, 'parameters': parametres or {}}}}
        _, brut = self._post(corps)
        if 'Missing Mcp-Session-Id' in brut or 'Invalid session' in brut:
            self.ouvrir()
            _, brut = self._post(corps)
        d = self._json(brut)
        if 'error' in d:
            raise ErreurNovamira(f'{nom} : ' + json.dumps(d['error'], ensure_ascii=False)[:600])
        resultat = d.get('result', {})
        if resultat.get('isError'):
            texte = ' '.join(b.get('text', '') for b in resultat.get('content', []))
            raise ErreurNovamira(f'{nom} : {texte[:600]}')
        return resultat

    def php(self, code):
        """Exécute du PHP dans WordPress ; `code` se termine par un `return` : sa valeur revient en Python.
        Le retour est encodé en JSON puis en base64 entre deux marques : il survit à n'importe quel
        format de réponse de l'ability (JSON imbriqué, échappement HTML) et à ce que le code affiche."""
        enveloppe = ("ob_start(); try { $hg_r = (function () { " + code + " })(); } "
                     "catch (\\Throwable $e) { $hg_r = array('erreur' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'); } "
                     "$hg_bruit = ob_get_clean(); "
                     "$hg_s = 'HGR:' . base64_encode(wp_json_encode(array('r' => $hg_r, 'bruit' => substr((string) $hg_bruit, 0, 1500)))) . ':HGR'; "
                     "echo $hg_s; return $hg_s;")
        resultat = self.ability('novamira/execute-php', {'code': enveloppe})
        for s in _chaines(resultat):
            m = MARQUE.search(s)
            if m:
                return json.loads(base64.b64decode(m.group(1)))['r']
        raise ErreurNovamira('execute-php : aucun résultat reconnu — ' + json.dumps(resultat, ensure_ascii=False)[:600])

    def televerser(self, local, distant):
        """Téléverse un fichier vers ABSPATH/<distant> (lien d'envoi à usage unique de Novamira)."""
        lien = _cherche(self.ability('novamira/create-upload-link', {'path': distant, 'overwrite': True}), 'upload_url')
        if not lien or not lien.get('upload_token'):
            raise ErreurNovamira('create-upload-link : pas de upload_url / upload_token dans la réponse')
        with open(local, 'rb') as f:
            donnees = f.read()
        req = urllib.request.Request(lien['upload_url'], data=donnees, method='PUT', headers={
            'X-Novamira-Upload-Token': lien['upload_token'], 'Content-Type': 'application/octet-stream'})
        try:
            with urllib.request.urlopen(req, timeout=self.delai) as r:
                r.read()
        except urllib.error.HTTPError as e:
            raise ErreurNovamira(f'téléversement de {os.path.basename(local)} : HTTP {e.code} — '
                                 + e.read().decode('utf-8', 'replace')[:300]) from None
        return len(donnees)

