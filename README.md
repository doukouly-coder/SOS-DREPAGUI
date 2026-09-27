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
| 2 · Maquette | Accueil HTML validé (`site/`) | ⏳ maquette prête, en attente de validation |
| 3 · Déclinaison | Toutes les pages, CSS partagé | — |
| 4 · Thème bloc | Thème complet (`theme/`) | — |
| 5 · Déploiement | Site en ligne | — |
| 6 · Recette | Réception | — |

## Arborescence

```
site/                  maquette HTML de l'accueil (phase 2), puis des autres pages (phase 3)
theme/                 thème bloc WordPress (phase 4, à venir)
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

Ou ouvrir directement les captures : `docs/captures/accueil-bureau.jpg` et `docs/captures/accueil-mobile.jpg`.

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

## Sécurité

Le bundle de connexion `.mcpb` du plugin Novamira (phase 5) contient un mot de
passe applicatif en clair : il ne doit jamais être commité (voir `.gitignore`).
