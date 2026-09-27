# HEMATO GUI — site WordPress

Site WordPress sur mesure construit avec la méthode **Claude to WordPress**
(maquette HTML locale → thème bloc 100 % éditable dans Gutenberg).

La méthode est installée comme compétence du projet dans
`.claude/skills/claude-to-wordpress/` : toute session Claude Code ouverte
sur ce dépôt la charge automatiquement.

## Avancement

| Phase | Sortie | État |
|---|---|---|
| 0 · Préparation | Dossier de travail | ✅ fait |
| 1 · Brief | Plan de design validé | ✅ brief reçu — plan dans `docs/plan-de-design.md` |
| 2 · Maquette | Accueil HTML validé (`site/`) | ✅ validée par le client |
| 3 · Déclinaison | Toutes les pages, CSS partagé | ✅ 15 pages (`site/`), assemblées par `outils/assembler.py` |
| 4 · Thème bloc | Thème + extension (`wordpress/`) | ✅ recette locale sur WordPress 6.6 : les 5 portes franchies |
| 5 · Déploiement | Site en ligne | ⏳ en attente de l’accès à l’hébergement |
| 6 · Recette | Réception | — |

## Arborescence

```
site/                  maquette HTML (phases 2-3) ; sources des pages dans site/src/, en « HTML en forme de blocs »
wordpress/theme/       thème bloc WordPress « hemato-gui » (phase 4)
wordpress/extension/   extension « hemato-gui » : formulaires, demandes, rôles, SEO, sécurité (phase 4)
wordpress/contenu/     contenu des pages en blocs Gutenberg natifs, généré depuis site/src/pages/
outils/wordpress/      construction des archives, déploiement, recette (portes 1 à 5)
docs/                  plan de design, architecture, captures de la maquette
outils/rendus-3d/      scènes 3D des cellules sanguines (three.js) → site/assets/img/rendus/
outils/couvertures/    gabarit des couvertures d'eBooks → site/assets/img/ebooks/
outils/captures/       captures bureau (1440 px) et mobile (390 px) avec contrôles
.claude/skills/claude-to-wordpress/   la méthode, ses scripts et le thème squelette
```

## Voir la maquette

Depuis la racine du dépôt :

```bash
P="$(pwd)"; cd /tmp && RACINE="$P/site" node "$P/.claude/skills/claude-to-wordpress/scripts/serve.js"
# puis http://127.0.0.1:4321
```

Ou ouvrir directement les captures : `docs/captures/planche-pages.jpg` (vue d’ensemble des 15 pages)
et `docs/captures/pages/<page>-bureau.jpg` / `<page>-mobile.jpg`.

## Modifier les pages

Les pages se modifient dans `site/src/pages/`, l’en-tête et le pied de page dans `site/src/parties/`,
puis on régénère : `python3 outils/assembler.py`. Contrôles : `python3 outils/verifier-liens.py`
et `node outils/captures/interactions.mjs` (25 tests d’interaction).

Les sources des pages sont écrites en « HTML en forme de blocs » : `outils/blocs.py` les convertit
à l’identique en blocs Gutenberg natifs (groupes, titres, paragraphes, listes, images, boutons,
détails). Une balise hors de ce dialecte arrête la conversion : aucun bloc `core/html` n’est produit.

## WordPress

```bash
python3 outils/wordpress/construire.py        # dist/hemato-gui-theme.zip et dist/hemato-gui-extension.zip
```

- **Thème** : à installer tel quel (Apparence › Thèmes › Ajouter › Téléverser). Aucun thème parent.
- **Extension** : Extensions › Ajouter › Téléverser, puis activer. Elle crée les rôles Patient,
  Professionnel de santé et Secrétariat, et le menu « HEMATO GUI » (tableau de bord, demandes, réglages).
- **Pages** : chaque page est une vraie page WordPress, modifiable dans l’éditeur ; l’accueil
  est la page « Accueil » définie comme page d’accueil. Les formulaires et calculateurs sont
  des blocs « shortcode » de l’extension : `[hg_rendez_vous]`, `[hg_contact]`, `[hg_connexion]`,
  `[hg_acces_pro]`, `[hg_outil type="nfs|formule|clairance|ipssr|mentzer"]`.
- **Menu** : Apparence › Éditeur › Navigation, menu « Menu principal ».

Déploiement complet sur un WordPress local (thème, extension, médias, pages, menu) :

```bash
outils/wordpress/deployer-local.sh <racine-wordpress> <url-du-site>
```

Recette : `porte2.mjs` (éditeur), `porte3.mjs` (rendu et formulaires, déconnecté), `porte5.mjs`
(éditeur fidèle au site), dans `outils/wordpress/`.

### Ce que fait réellement le site

- **Rendez-vous** : le parcours en cinq étapes enregistre une **demande** (calendrier réel des
  cinq prochaines semaines, jours ouvrés, créneaux déjà demandés grisés, capacité par jour réglable).
  L’équipe la confirme par WhatsApp ou SMS ; la page le dit. Un module d’agenda (Amelia, Bookly…)
  pourra le remplacer.
- **Demandes** (rendez-vous, messages, accès pro) : privées, hors API REST, visibles seulement par
  l’administrateur et le rôle Secrétariat ; l’e-mail d’alerte ne contient ni identité ni motif ;
  export et effacement RGPD ; suppression automatique après 24 mois (réglable).
- **Comptes patients** : création par téléphone, connexion avec le numéro tel qu’on le tape ;
  le patient voit ses propres demandes. Pas d’accès à l’administration.
- **Accès professionnel** : demande vérifiée à la main ; une case dans la fiche crée le compte
  et envoie le lien de connexion.
- **Sécurité** : en-têtes HTTP, XML-RPC coupé, comptes non énumérables, 5 échecs de connexion
  = 15 minutes de blocage. À ajouter chez l’hébergeur : HTTPS + HSTS, sauvegardes, double
  authentification (extension « Two-Factor »), journal d’activité.
- **SEO** : titre et description de chaque page, Open Graph, données structurées de
  l’organisation ; s’efface si Yoast, Rank Math ou SEOPress est installé.

## Régénérer les visuels

Depuis la racine du dépôt (Node, Playwright et Pillow requis) :

```bash
P="$(pwd)"
(cd outils/rendus-3d && npm install)
(cd /tmp && RACINE="$P/outils/rendus-3d" PORT=4330 node "$P/.claude/skills/claude-to-wordpress/scripts/serve.js" &)
(cd outils/rendus-3d && node rendre.mjs && python3 webp.py)   # PNG sources dans png/, WebP pour le site
(cd outils/couvertures && node exporter.mjs)                   # couvertures d'eBooks
```

## À fournir

- **Logo officiel HEMATO GUI** (SVG ou PNG haute définition) et, si elle existe, la charte (valeur exacte du rouge).
  L'en-tête affiche pour l'instant le nom en texte : le logo n'est ni recréé ni imité.
- La liste réelle des eBooks (titres, auteurs, pages, niveaux, prix) : ceux de la maquette sont des exemples.
- Les contenus d’exemple à remplacer ou à brancher : articles d’actualités (liens `#`), boutons
  « Acheter » et « Aperçu » des eBooks (boutique à choisir : WooCommerce + paiement), formations
  et webinaires de l’espace pro, adresse exacte et mentions légales (éditeur, hébergeur).
- L’aperçu « tableau de bord patient » (documents, notifications, favoris) est une illustration
  de données fictives : le dépôt sécurisé de documents médicaux demande un module dédié.

## Sécurité

Le bundle de connexion `.mcpb` du plugin Novamira (phase 5) contient un mot de
passe applicatif en clair : il ne doit jamais être commité (voir `.gitignore`).
