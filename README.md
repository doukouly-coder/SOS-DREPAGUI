# SOS-DREPAGUI — site WordPress

Site WordPress sur mesure construit avec la méthode **Claude to WordPress**
(maquette HTML locale → thème bloc 100 % éditable dans Gutenberg).

La méthode est installée comme compétence du projet dans
`.claude/skills/claude-to-wordpress/` : toute session Claude Code ouverte
sur ce dépôt la charge automatiquement.

## Avancement

| Phase | Sortie | État |
|---|---|---|
| 0 · Préparation | Dossier de travail | ✅ fait |
| 1 · Brief | Plan de design validé | ⏳ en attente du brief et de l'image de référence |
| 2 · Maquette | Accueil HTML validé (`site/`) | — |
| 3 · Déclinaison | Toutes les pages, CSS partagé | — |
| 4 · Thème bloc | Thème complet (`theme/`) | — |
| 5 · Déploiement | Site en ligne | — |
| 6 · Recette | Réception | — |

## Arborescence prévue

```
site/     maquette HTML (phases 2 et 3)
theme/    thème bloc WordPress (phase 4)
.claude/skills/claude-to-wordpress/   la méthode, ses scripts et le thème squelette
```

## Sécurité

Le bundle de connexion `.mcpb` du plugin Novamira (phase 5) contient un mot de
passe applicatif en clair : il ne doit jamais être commité (voir `.gitignore`).
