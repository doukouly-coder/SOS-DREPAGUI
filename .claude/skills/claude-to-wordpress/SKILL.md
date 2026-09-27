---
name: claude-to-wordpress
description: Construit un site WordPress sur mesure en partant d'une maquette HTML locale, puis la convertit en thème bloc 100 % éditable dans Gutenberg. Déclenche cette compétence dès qu'il s'agit de créer, refondre ou moderniser un site WordPress, de fabriquer un thème, un thème bloc ou un site vitrine, même si l'utilisateur ne dit pas explicitement « thème » — et aussi quand il évoque Elementor, Divi, Astra, un page builder, ZipWP ou un générateur de site, car la méthode consiste justement à les remplacer. À utiliser également pour diagnostiquer un site WordPress dont le design paraît générique, lent à itérer ou impossible à modifier par le client. Se déclenche immédiatement quand un message commence par « Détail du site : » avec une image en pièce jointe — c'est le brief type de la méthode, et il suffit : tout le reste (contenu, photos, validations) est décidé par cette compétence sans autre question.
---

# Claude to WordPress

## Pourquoi cette méthode existe

Un agent produit un design médiocre quand sa boucle de retour est lente. Écrire la mise en page directement dans WordPress impose une boucle de trois à cinq minutes par itération : composer, envoyer, sérialiser, purger le cache, recharger, regarder. En HTML local avec un serveur de développement, la même boucle prend cinq secondes.

La qualité d'un design est à peu près proportionnelle au nombre d'itérations. Multiplier les itérations par cinquante change le résultat plus sûrement que n'importe quelle consigne esthétique.

La seconde moitié du principe est une contrainte de livraison : le site doit rester modifiable par quelqu'un qui n'écrit pas de code. C'est ce qui interdit les deux raccourcis tentants décrits en règles 4 et 5. Cette contrainte pèse sur la conversion, jamais sur le dessin — c'est tout l'objet de la règle 0.

> **En une ligne :** le design vit dans des fichiers, pas dans le contenu des pages. WordPress reçoit un thème fini, pas des instructions de mise en page.

## La règle 0

Elle passe avant les dix autres parce qu'elle est la seule dont l'infraction ne se voit nulle part : ni dans le code, ni dans la recette, ni dans l'éditeur. Elle ne produit pas une panne, elle produit un site fade — et un site fade passe toutes les portes.

> **Règle 0 — Dessiner la phase 2 comme si la phase 4 n'existait pas.**
>
> Aucune contrainte de conversion n'entre dans la maquette. « Pas de `core/html` », « blocs natifs », « éditable par le client » sont des problèmes de phase 4. Les anticiper en phase 2 fait disparaître, une par une et sans qu'on s'en aperçoive, la profondeur, les superpositions, les débordements, le mouvement, l'objet central. Le résultat se convertit parfaitement et ne plaît à personne.
>
> La méthode dessine en HTML local **parce que** c'est gratuit à cet endroit. S'auto-censurer là, c'est payer le prix de WordPress sans jamais en toucher le bénéfice — et cumuler les deux inconvénients.
>
> Si un composant s'avère trop riche à convertir, c'est un arbitrage de phase 4, pris devant un design fini et, s'il le faut, devant le client. Jamais un renoncement de phase 2.

## La règle 0′ — le registre appartient à la référence

Règle 0 libère la main sur la **technique**. Elle ne la libère pas sur le **registre**. Les deux règles se ressemblent et disent le contraire l'une de l'autre ; les confondre est la panne la plus fréquente de cette méthode.

> **Règle 0′ — Aucune liberté esthétique ne sort de la référence.**
>
> Règle 1 dit que sans référence, un modèle dérive vers le centre statistique de ses données : police neutre, dégradé violet, fond blanc. C'est vrai, et c'est la moitié visible du problème.
>
> **Avec une référence, un modèle dérive vers sa propre maison.** Beige chaud là où la référence est en blanc froid, serif à caractère là où elle n'a qu'une sans-serif, un accent terreux là où elle n'a aucun accent, des diagonales là où elle n'a que des rectangles, des titres éditoriaux à gauche là où elle centre tout. Le résultat est souvent *plus joli* que le centre statistique — et c'est exactement pour ça qu'il passe : il ne déclenche aucun signal d'alarme, ni pendant le travail, ni à la relecture.
>
> Le client, lui, le voit en une seconde. Il a envoyé une image ; il reçoit autre chose, et on lui explique pourquoi c'est mieux.

### Les sept invariants de registre

Ils se **relèvent** sur la référence et se reproduisent. Ils ne s'interprètent pas, ne se « traduisent » pas dans le secteur du client, et ne comptent jamais comme un écart volontaire.

| Invariant | Ce qu'on relève sur la référence |
|---|---|
| 1 · Classe typographique | Serif ou sans-serif ? Combien de familles ? Des italiques, ou aucune ? |
| 2 · Système de couleur | Combien de couleurs non neutres ? **« Aucun accent » est une réponse fréquente, et c'est une contrainte, pas un manque.** |
| 3 · Température du fond clair | Blanc froid, gris, blanc cassé, beige ? |
| 4 · Découpe de la page | Bandes pleine largeur bord à bord, ou blocs arrondis encartés avec de la marge autour ? |
| 5 · Géométrie | Rayon des angles. Présence ou absence de rotation, de diagonale, de forme non rectangulaire. |
| 6 · Alignement dominant | Titres de section centrés ou à gauche ? Grilles symétriques ou asymétriques ? |
| 7 · Densité et échelle | Beaucoup de petits éléments, ou peu de grands ? Rapport entre le corps de texte et les titres. |

Changer l'un des sept, c'est livrer un autre site. Si un invariant paraît mauvais, ce n'est pas un arbitrage d'agent : **c'est une question posée au client avant de coder**, et formulée comme une question — « ta référence n'a aucune couleur d'accent, je te propose d'en ajouter une, tu valides ? » On attend la réponse.

### Ce que deviennent les six exigences de parti pris

Les six exigences de la phase 2 — dominante, couleur, poids des sections, profondeur, typographie, concept — **ne sont pas des consignes de dessin quand une référence existe. Ce sont six questions posées à la référence, et sa réponse est la consigne.**

- La référence n'a pas d'élément dominant ? La page n'en invente pas un.
- La référence n'a aucune couleur saturée ? « La couleur forte occupe du terrain » veut dire : *sa* couleur occupe du terrain. Pas : ajoute-lui un accent.
- Toutes ses sections ont le même poids ? Les égaliser est la bonne réponse.
- Elle n'a qu'une famille typographique ? « La typo de caractère travaille » ne justifie pas d'en ajouter une seconde.
- Elle n'a pas de concept lisible ? Alors le concept n'est pas à inventer : il n'est pas commandé.

Ces six exigences ne redeviennent des consignes que dans un seul cas : **il n'y a pas de référence.** C'est leur domaine entier, et la règle 1 le rend rare.

> **Comment cette panne s'installe.** Le manifeste est du texte dans le contexte — il se lit comme une instruction. La référence est une image — elle se lit comme une donnée. Quand les deux se contredisent, le texte gagne, sans que la contradiction soit jamais énoncée. D'où la règle 0′ : elle énonce l'arbitrage à l'avance, pour qu'il n'ait pas à être pris dans l'instant.

## Les dix règles

Chacune vient d'une erreur réellement commise et de son coût. Les enfreindre ne casse pas le site tout de suite : ça produit un livrable que le client ne peut pas utiliser.

1. **Exiger une référence visuelle.** Sans direction imposée, un modèle échantillonne le centre statistique de ses données d'entraînement : police neutre, dégradé violet, fond blanc. C'est la définition du générique. Une capture ou une URL suffit à faire basculer le résultat en une seule passe.
2. **Jamais de maquette sans photos réelles.** Un aplat gris à la place d'une photo ressemble à une image cassée ; aucune composition ne le rattrape. Les visuels se règlent avant la première capture.
3. **Rien ne part sur WordPress avant validation.** La phase 2 est la seule où itérer est bon marché.
4. **La page d'accueil est une page, jamais un template.** Un contenu rendu par `front-page.html` via `wp:pattern` n'est pas modifiable depuis l'éditeur de page.
5. **Zéro bloc `core/html`.** Un bloc HTML brut n'est pas éditable visuellement. Tout composant se compose en groupes, paragraphes, titres et images.
6. **Images en médiathèque, avec identifiant.** Une image référencée par URL de fichier de thème n'est pas remplaçable en un clic.
7. **Les variantes interactives vivent dans la page.** Onglets et sélecteurs : toutes les variantes sont présentes, le script bascule une classe. Rien n'est injecté en JavaScript, donc tout reste éditable.
8. **Pas de thème tiers sous le design.** Astra, Kadence et consorts imposent leur CSS dynamique et leurs options typées ; la moitié de la feuille de style finit en surcharges de spécificité.
9. **Recette dans l'éditeur, pas en façade.** Une page peut s'afficher parfaitement et rester inutilisable dans Gutenberg.
10. **Le pixel perfect se mesure, il ne se regarde pas.** La recette compare un relevé de styles calculés et de géométrie entre la maquette et le site, sélecteur par sélecteur. « Ça a l'air pareil » a laissé passer 307 px d'écart de rythme vertical : une seule règle de neutralisation avait mis quatorze marges à zéro sans que l'œil le voie.

## Déroulé

Sept phases. Chacune a une porte de sortie : tant qu'elle n'est pas franchie, ne pas passer à la suivante.

| Phase | Qui | Durée | Sortie |
|---|---|---|---|
| 0 · Préparation | Agent | 2 min | Dossier de travail |
| 1 · Brief | Client | 10 min | Plan de design validé |
| 2 · Maquette | Itératif | 30-60 min | Accueil HTML validé |
| 3 · Déclinaison | Agent | 20-30 min | Toutes les pages, CSS partagé |
| 4 · Thème bloc | Agent | 45-60 min | Thème complet, hors ligne |
| 5 · Déploiement | Agent | 20 min | Accès au site, puis site en ligne |
| 6 · Recette | Agent | 15 min | Réception, ou retour en 4 |

Le détail complet de chaque phase, avec les commandes exactes, est dans `references/phases.md`. Le lire avant de commencer la phase 2 : c'est là que se trouvent le serveur de développement fourni et le protocole d'itération.

## Phase 1 : obtenir un brief exploitable

Le brief type tient en un message — c'est celui que la formation fait copier-coller :

```
Détail du site :
[Nom]
[Activité]
[Description]
[Contact]

Pages du site :
Accueil
[...]

Référence :
Image en pièce jointe
```

Tout ce que le gabarit ne dit pas est un défaut de la méthode, jamais une question à poser :

- l'image jointe **est** la référence visuelle — sans image, la demander avant d'écrire du code, c'est le levier le plus puissant de toute la méthode ;
- le contenu est inventé à partir de [Activité] et [Description] ; ce qui est fourni ([Nom], [Contact]) est repris tel quel ;
- les photos viennent d'Unsplash, sauf si l'utilisateur fournit les siennes ;
- rien ne part sur WordPress avant validation de la maquette (règle 3) — l'utilisateur n'a pas à le préciser ;
- les cinq portes de la recette sont passées d'office, sans qu'on les demande.

**Une seule question reste légitime, et elle est obligatoire quand le cas se présente** : toucher à l'un des sept invariants de registre (règle 0′). Le « sans autre question » de cette méthode couvre le contenu, les photos et les validations — il ne couvre pas le droit de changer la classe typographique, le système de couleur ou la géométrie de la référence. Ça se demande, et on attend la réponse.

Avant de coder, annoncer dans cet ordre, et avec ces intitulés :

1. **Relevé de la référence** — les sept invariants de registre, lus sur l'image. De l'observation, aucun choix.
2. **Sujet concret** — entreprise, audience, mission unique de la page.
3. **Palette** — 4 à 6 hexadécimaux nommés, *prélevés sur la référence*, pas choisis à côté.
4. **Typographies** — la classe vient du relevé ; seule la famille exacte est un choix, et elle reste dans la classe relevée.
5. **Ce que j'ajoute** — la liste **exhaustive** de tout ce qui ne figure pas dans la référence. Si un invariant de registre y apparaît, ce n'est plus une annonce : c'est une question, et on attend la réponse avant d'écrire une ligne.

Deux points de contrôle, et ils tirent en sens inverse :

- Si ce plan ressemble à ce qu'on produirait pour n'importe quel site du même secteur, le refaire.
- Si la rubrique « Ce que j'ajoute » dépasse deux lignes, ou touche un seul invariant sans le poser en question, on est en train de livrer un autre site. **Un plan qui a besoin de trois paragraphes pour justifier ses écarts en a déjà trop** — l'argumentaire est le symptôme, pas la défense.

## Phase 2 : la maquette, où se joue la qualité

Une seule page — l'accueil — en HTML et CSS, en local, avec un serveur de développement.

`scripts/serve.js` est un serveur statique sans dépendance, prêt à l'emploi. Le lancer depuis `/tmp` — c'est le `cwd` du processus qui doit être accessible, jamais `RACINE`, qui peut pointer n'importe où, y compris dans le dossier de travail de l'utilisateur :

```bash
cd /tmp && RACINE=/chemin/projet/site nohup node /chemin/projet/scripts/serve.js > /tmp/serve.log 2>&1 &
```

`RACINE` désigne le dossier qui contient `index.html`. Le serveur affiche dans le journal le port réellement utilisé — il bascule tout seul sur le suivant si 4321 est pris.

Régler les visuels **avant** la première capture. Pour Unsplash, récupérer les identifiants depuis une page de résultats plutôt que de deviner des URL — la procédure est dans `references/phases.md`.

### Le parti pris se décide, il ne se déduit pas

> **À lire dans le sens de la règle 0′.** Avec une référence, les six exigences qui suivent sont des **questions posées à la référence**, jamais des consignes appliquées à la page : on relève la réponse de la référence, et c'est elle qu'on exécute. Elles ne deviennent des consignes que s'il n'y a pas de référence du tout.

Aucun outil tiers ne porte l'esthétique à la place de l'agent. Ce qui suit la porte, et chaque point se constate sur la capture — pas dans une intention annoncée.

Une liste d'interdits ne fabrique pas un design. Éviter les dégradés, les ombres molles, les capitales et les flèches ne donne pas une belle page : ça donne une page poncée. Les exigences sont donc formulées en positif.

- **Un élément domine.** Une page, un objet : une image qui déborde, un champ de couleur qui prend la moitié de l'écran, un titre hors d'échelle, une composition superposée. Sans dominante, dix sections propres font un document, pas un site.
- **La couleur forte occupe du terrain.** Ne retenir qu'une seule couleur saturée est une bonne discipline ; ne lui accorder que deux bandeaux sur dix sections en est la caricature. Si la référence est dominée par sa couleur, la maquette doit l'être aussi.
- **Les sections n'ont pas toutes le même poids.** Même `padding`, même largeur, même alignement, titre-paragraphe-grille à chaque fois : c'est le symptôme le plus fiable d'une page sans intention. Faire varier l'échelle, la largeur, l'alignement et la densité d'une section à l'autre.
- **Il y a de la profondeur.** Au moins un élément sort de son conteneur : chevauchement, calque, décalage, ombre assumée, rotation, matière. Le plat n'est pas le raffiné.
- **La typographie de caractère travaille.** Si une seconde famille est choisie pour son caractère, elle porte du contenu réel — pas quatre chiffres et une citation en figuration.
- **Le concept est visible sans légende.** S'il faut l'expliquer dans la conversation pour qu'il existe, il n'est pas dans le design. Un concept se traduit en échelle, en composition, en couleur — pas en coin coupé à 34 px.

Reprendre une référence, c'est reprendre ce qui lui donne son énergie, pas seulement l'ordre de ses sections. En copier la structure sans les dispositifs — profondeur, superpositions, objet central, champ de couleur — produit le wireframe de la référence, jamais son équivalent.

Il existe une manière de rater exactement symétrique, et elle est plus coûteuse parce qu'elle ressemble à du travail : **garder l'ordre des sections et remplacer toute la surface par la sienne.** Même enchaînement de blocs, mais une autre classe typographique, un accent que la référence n'a pas, une autre température de fond, une autre géométrie. Le squelette est respecté, la peau est neuve, et le client ne reconnaît rien. C'est la panne de la règle 0′.

### La porte de registre — avant de montrer au client

Aucune des cinq portes de la phase 6 ne mesure la ressemblance avec la référence. C'est le trou de la méthode, et il se bouche ici, dans la phase 2, avant le premier envoi.

**Rouvrir l'image de référence dans le même message que la capture.** Pas la décrire de mémoire, pas la résumer : l'afficher, elle et la capture, l'une sous l'autre, et les regarder ensemble. C'est de mémoire qu'on se persuade d'avoir été fidèle — la mémoire retient l'ordre des sections, jamais la surface.

Puis remplir ce tableau, et le remplir *avant* d'écrire au client :

| Invariant | Référence | Maquette | Verdict |
|---|---|---|---|
| 1 · Classe typographique | | | |
| 2 · Système de couleur | | | |
| 3 · Température du fond clair | | | |
| 4 · Découpe de la page | | | |
| 5 · Géométrie | | | |
| 6 · Alignement dominant | | | |
| 7 · Densité et échelle | | | |

Un verdict ne peut prendre que deux valeurs : **conforme**, ou **autorisé par le client** avec la citation de son accord. Toute autre valeur — « proche », « équivalent », « réinterprété », « adapté au secteur » — vaut non conforme : on corrige la maquette, on ne montre pas. La colonne « Maquette » se remplit avec ce que la page fait réellement, relevé sur la capture et sur les styles calculés, jamais avec ce qu'on avait l'intention de faire.

Point de contrôle : si la réponse à « est-ce que c'est bien ? » est un argumentaire, c'est non.

### Protocole d'itération

Écrire la page complète avec du contenu réel, prendre une capture, corriger ce qu'elle montre, montrer au client. Deux à trois tours. Au-delà, c'est le brief qui est en cause.

Ces tours ne se ressemblent pas. Remonter la taille d'une icône ou corriger un prénom qui ne colle pas à la photo, c'est un **tour de justesse** : nécessaire, sans le moindre effet sur la qualité du design. **Au moins un tour est consacré au design lui-même**, avant de montrer au client : regarder la capture et pousser la page quelque part, au lieu de la réparer. Un plan validé en prose ne remplace jamais ce tour-là — tant qu'il n'y a rien à regarder, ni l'agent ni le client ne peuvent juger.

Attention aux captures prises trop tôt : les JPEG ne sont pas décodés avant cinq secondes environ, et l'image paraît absente. Vérifier `img.complete && img.naturalWidth > 0` avant de conclure à un bug.

## Phase 4 : convertir en thème bloc

`assets/theme-squelette/` contient un thème minimal fonctionnel : `theme.json`, `functions.php`, les template parts et les templates. Le copier, puis remplacer les jetons et injecter les sections.

La correspondance entre HTML et blocs natifs est dans `references/blocs-gutenberg.md`. Le consulter systématiquement : il contient les cinq substitutions non évidentes (icônes SVG, graphiques décoratifs, boutons d'onglet, accents typographiques, couches décoratives), la seule façon sûre de neutraliser l'espacement automatique de Gutenberg — la version naïve détruit le rythme vertical du design —, et la feuille `editor.css` qui rend l'éditeur fidèle au site.

Le principe de conversion tient en une phrase : **poser les classes CSS du composant directement sur le bloc via `className`**. La classe `shot` sur un `core/image` produit `figure.wp-block-image.shot`, et le sélecteur écrit pour la maquette fonctionne sans modification.

## Phase 6 : la recette, cinq portes

Aucune ne remplace les autres, et elles se passent d'office — le client n'a jamais à les demander ni à pointer un problème. Le code exact est dans `references/phases.md`.

**Porte 1, côté serveur.** Parser le contenu et compter : `core/html` doit valoir 0, et le nombre de blocs hors `core/` doit valoir 0.

**Porte 2, dans l'éditeur.** Le seul contrôle qui détecte les blocs marqués « contenu inattendu ou invalide ». Ouvrir la page en édition et interroger le store :

```js
const aplatir = bs => bs.reduce((a, b) => a.concat([b], aplatir(b.innerBlocks || [])), []);
const tous = aplatir(wp.data.select('core/block-editor').getBlocks());
const invalides = tous.filter(b => b.isValid === false);
```

Critère de réception : `invalides.length === 0`, et autant d'images liées à la médiathèque (`b.attributes.id` présent) que de blocs `core/image`. Tant que ce n'est pas atteint, le client ne peut pas travailler.

**Porte 3, rendu.** Images en HTTP 200, polices réellement appliquées, interactions fonctionnelles **après déconnexion**, aucun défilement horizontal à 390 px.

**Porte 4, fidélité au pixel.** Relever la géométrie et les styles calculés des mêmes sélecteurs sur la maquette locale et sur le site, puis différer les deux relevés (règle 10). Réception : hauteur de page identique à ±2 px, aucun composant au-delà de 2 px d'écart. C'est cette porte qui attrape ce que l'œil rate — marges écrasées, blockGap résiduel, textarea à la mauvaise hauteur.

**Porte 5, fidélité de l'éditeur.** Dans le canevas de Gutenberg, mesurer que les couches sorties du flux le sont restées (`position: absolute`), que l'enveloppe occupe toute la largeur du canevas et qu'aucun bouton ne s'affiche en texte vertical. Sans `editor.css`, Gutenberg force `position: relative` sur chaque bloc et l'aperçu s'effondre.

## Quand ça part de travers

`references/pieges.md` recense quatorze pannes rencontrées en conditions réelles, chacune avec son symptôme exact, sa cause et son correctif. Les plus coûteuses :

- **Page blanche après écriture d'une option de thème** — une option typée a reçu un tableau là où une chaîne était attendue. Diagnostic par `ob_start(); try { wp_head(); } catch (\Throwable $e)`.
- **Page d'accueil impossible à modifier** — le contenu appartient au template, pas à une page. C'est la règle 4, et elle coûte une refonte complète quand on la découvre après coup.
- **Composants sans style après passage en blocs natifs** — le DOM a changé ; voir la solution en phase 4.
- **Rythme vertical détruit après passage en blocs** — la neutralisation naïve de l'espacement (`.wp-block-group > *`) écrase toutes les marges du design. La porte 4 le mesure, `blocs-gutenberg.md` donne la seule méthode sûre.
- **Maquette plate, validée sans enthousiasme** — le design a été dessiné avec les contraintes de la phase 4 déjà aux poignets. C'est la règle 0. Cette panne ne se voit dans aucune porte de recette : le seul symptôme est l'absence de réaction du client.
- **Maquette hors registre : « ça n'a rien à voir avec ma référence »** — l'ordre des sections a été respecté et toute la surface remplacée. C'est la règle 0′, et c'est la panne jumelle de la précédente : la même section du skill produit l'une quand on la lit à l'envers et l'autre quand on l'ignore. Le client le voit en une seconde ; l'agent, jamais.
- **Correctif CSS « sans effet »** — trois caches se superposent (page LiteSpeed, navigateur à sept jours, en-têtes de thème). Version, purge, contrôle par `curl`, jamais `?m=` comme casse-cache.

## Ce que la méthode ne couvre pas

Le dire au client plutôt que de le laisser le découvrir en production.

- **Formulaires** — aucun bloc natif n'existe. Contact Form 7 posé par `core/shortcode` ; les champs se règlent dans l'interface du plugin.
- **Commerce** — WooCommerce impose ses propres templates et sort du cadre.
- **Multilingue** — à trancher avant la phase 4, le choix de l'extension conditionne la structure des templates.
- **Performance** — les visuels sont livrés en JPEG pleine taille. Prévoir une passe de compression et de formats modernes.

## Fichiers fournis

| Fichier | Quand le lire |
|---|---|
| `references/phases.md` | Avant la phase 2, puis à chaque phase |
| `references/blocs-gutenberg.md` | Pendant la phase 4, systématiquement |
| `references/pieges.md` | Dès qu'un symptôme inattendu apparaît |
| `scripts/serve.js` | Phase 2, serveur de développement |
| `scripts/wp.sh` | Phase 5, appel HTTP à Novamira avec session |
| `assets/theme-squelette/` | Phase 4, point de départ du thème |
| `assets/methode.html` | Document lisible à transmettre au client |
