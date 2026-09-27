# HEMATO GUI — plan de design (phase 1) et porte de registre (phase 2)

## 1 · Relevé de la référence

Aucune image de référence ni logo n'a été fourni. La référence est donc **le brief écrit** :
« Apple Health + plateforme médicale premium + design africain contemporain », rouge hématologique,
blanc, gris très clair, touches de noir, beaucoup d'espace blanc, éviter les couleurs multiples.

| Invariant | Ce que dit le brief |
|---|---|
| 1 · Classe typographique | « typographie moderne », « excellente lisibilité » — registre Apple : une seule sans-serif, sans italique |
| 2 · Système de couleur | un rouge + neutres (blanc, gris très clair, noir/gris foncé) ; « éviter les couleurs multiples » |
| 3 · Température du fond clair | blanc et gris très clair (froid) |
| 4 · Découpe de la page | « cartes élégantes », « beaucoup d'espace blanc » |
| 5 · Géométrie | non fixée |
| 6 · Alignement dominant | non fixé |
| 7 · Densité et échelle | « éviter trop de texte », « hiérarchie visuelle forte » |

## 2 · Sujet concret

- **Entreprise** : HEMATO GUI, plateforme d'hématologie pour la Guinée et l'Afrique francophone (Conakry).
- **Audience** : patients et familles (d'abord sur smartphone), professionnels de santé.
- **Mission unique de l'accueil** : orienter en un geste vers la bonne porte — comprendre une maladie,
  prendre rendez-vous, ou accéder aux ressources professionnelles — avec une impression de confiance.

## 3 · Palette

| Nom | Hex | Usage |
|---|---|---|
| Rouge | `#C8102E` | la seule couleur : boutons, titres d'accent, champs de couleur (Drépanocytose, Septembre Rouge) |
| Rouge foncé | `#A00C25` | survol des boutons (même teinte) |
| Encre | `#1D1D1F` | texte, bande professionnelle, pied de page |
| Graphite | `#6E6E73` | texte secondaire |
| Gris | `#F5F5F7` | fonds de section et de cartes |
| Trait | `#E4E4E9` | filets et bordures |

Le rouge est **provisoire** : il sera aligné sur la charte officielle dès réception du logo.

## 4 · Typographies

**Inter** (variable, axe de taille optique), une seule famille, sans italique, hébergée localement
(aucune dépendance à Google Fonts : plus rapide en Guinée, et pas de transfert de données de visiteurs).

## 5 · Ce que j'ajoute

- Un motif géométrique inspiré du bogolan : lecture du « design africain contemporain », limitée à la bande Septembre Rouge et au liseré du pied de page.
- Des données d'exemple (cartes du héros, outil NFS, eBooks, prix, articles), toutes marquées ou à remplacer.

**Décision du client** (validation de l'accueil) : le bouton WhatsApp prend le **vert officiel `#25D366`**,
réservé aux éléments WhatsApp (bouton flottant, icône d'en-tête, boutons « Écrire sur WhatsApp »).
Le texte posé sur ce vert est en encre (contraste 9:1) : le blanc n'atteindrait que 2:1.

## Porte de registre (relevé sur la maquette, styles calculés à 1440 px)

| Invariant | Référence (brief) | Maquette (relevé) | Verdict |
|---|---|---|---|
| 1 · Classe typographique | une sans-serif moderne | 1 famille (Inter), 0 italique | conforme |
| 2 · Système de couleur | un rouge + neutres | 1 teinte non neutre `rgb(200,16,46)` + vert WhatsApp `#25D366` sur les seuls éléments WhatsApp | autorisé par le client : « je préfère le vert officiel de WhatsApp » |
| 3 · Température du fond clair | blanc, gris très clair | `#FFFFFF` / `#F5F5F7` (froid) | conforme |
| 4 · Découpe de la page | cartes, espace blanc | bandes pleine largeur + cartes arrondies encartées | conforme |
| 5 · Géométrie | non fixée par le brief | rayons 22 à 40 px, aucune rotation ni diagonale | autorisé par le client : « coins bien arrondis, sans éléments inclinés […] ça me convient » |
| 6 · Alignement dominant | non fixé par le brief | héros et 2 titres centrés ; sections en deux colonnes alignées à gauche | autorisé par le client (même message) |
| 7 · Densité et échelle | peu de texte, hiérarchie forte | corps 17 px · H1 82 px · H2 60 px ; peu de grands éléments | conforme |

Contrôles de la maquette : aucune image cassée, police appliquée, aucun défilement horizontal à 390 px et à 1440 px.
