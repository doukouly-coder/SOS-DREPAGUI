# Pièges recensés

Quatorze pannes rencontrées en conditions réelles, avec leur symptôme exact et leur correctif.

Les deux premières sont à part : ce sont les seules qu'aucune porte de la phase 6 ne peut détecter, et elles sont jumelles. La même section du skill — « Le parti pris se décide, il ne se déduit pas » — produit l'une quand on l'ignore, et l'autre quand on l'applique à la lettre en présence d'une référence.

---

## « Ça n'a rien à voir avec ma référence »

*Règle 0′ — le registre appartient à la référence (SKILL.md).*

**Symptôme.** Le client, qui a fourni une image, ne reconnaît rien. Il ne dit pas « c'est moche » : il dit que ça n'a **aucun rapport** avec ce qu'il a envoyé. À l'inventaire, l'ordre des sections est pourtant respecté presque à l'identique, et souvent la couleur dominante aussi. Ce sont les seules choses qui ont survécu.

**Cause.** Les six exigences de parti pris ont été lues comme des consignes à appliquer à la page, alors qu'une référence était présente. Chacune pousse dans la même direction — ajouter une dominante, une couleur forte, de la profondeur, une typographie de caractère, un concept — et rien dans le skill ne tirait en sens inverse. Quand la référence est un gabarit calme, dense, centré, sans accent et sans dominante, le manifeste et la référence se contredisent point par point. L'agent tranche alors en faveur du manifeste, sans jamais énoncer la contradiction : le manifeste est du texte dans le contexte et se lit comme une instruction, la référence est une image et se lit comme une donnée. Le texte gagne.

Une version antérieure de la méthode aggravait la panne en exigeant « deux ou trois écarts volontaires » : un quota de déviation, qui mettait sur le même plan une section retirée et un changement de classe typographique.

**Diagnostic.** Un seul test, et il est brutal : **rouvrir l'image de référence et la capture dans le même message**, puis reprendre les sept invariants de registre un par un. Tant que la comparaison se fait de mémoire ou sur une description, la panne reste invisible — on se souvient de l'ordre des sections, jamais de la surface. Deuxième tell, en amont : le plan de phase 1 contenait un argumentaire de plusieurs paragraphes expliquant pourquoi on s'éloignait de la référence.

**Correctif.** Reprendre la surface, pas la composition — c'est l'inverse exact de la panne suivante. Classe typographique, système de couleur, température du fond, découpe de la page, géométrie, alignement, densité : les sept se réalignent sur la référence. Ce qui ne peut pas s'y réaligner se pose en **question** au client, et on attend la réponse. Puis passer la porte de registre avant tout nouvel envoi.

**Attention.** Cette panne ne vient pas d'un oubli mais d'un goût, et le goût revient à chaque projet en se présentant comme du jugement professionnel. Le résultat est en général *plus joli* que le centre statistique — c'est pour ça qu'il franchit toutes les relectures internes. Seule la comparaison côte à côte l'arrête.

---

## Maquette plate, validée sans enthousiasme

**Symptôme.** La page est propre, adaptative, cohérente — et ne provoque aucune réaction. Le client valide « c'est bien » sans rien demander. Les cinq portes de la phase 6 passent toutes. C'est précisément ce qui rend cette panne coûteuse : elle est la seule que le protocole de vérification ne voit pas.

**Cause.** Le design a été dessiné avec les contraintes de la phase 4 déjà appliquées. À chaque décision de composition, la question « comment est-ce que ça passera en blocs natifs ? » a écarté une idée : la superposition, le débordement, l'objet central, le mouvement, le champ de couleur dominant. Aucun de ces renoncements n'est visible isolément ; leur somme est une page sans intention.

**Diagnostic.** Trois questions, posées sur la capture et non sur l'intention :

- Peut-on nommer en un mot ce qui porte la page ? Si non, il n'y a pas de dominante.
- Sur toute la page, deux sections voisines partagent-elles exactement le même gabarit — largeur, marge, alignement ? Si oui, il n'y a pas de rythme.
- Le concept annoncé au client se lit-il sans qu'on l'explique ? S'il lui faut une légende, il n'est pas dans le design.

**Correctif.** Reprendre la composition, pas la retoucher : une maquette plate ne se sauve pas par ajustements successifs, parce que le défaut est dans la structure des décisions, pas dans leur exécution. Puis respecter l'ordre : dessiner librement, convertir ensuite. Règle 0, et phase 2.

**Attention.** Le réflexe revient à chaque projet, parce que l'agent connaît déjà les contraintes de la phase 4 en écrivant sa première ligne de CSS. C'est un piège de méthode, pas une erreur ponctuelle — il se reprend à chaque fois, pas une fois pour toutes.

**Ne pas la corriger en sortant du registre.** Le correctif est « reprendre la composition » — l'échelle, le rythme, les débordements, la hiérarchie. Pas « ajouter une police à caractère et une couleur d'accent » : ça soigne la platitude en provoquant la panne précédente. Si la référence est elle-même un gabarit calme et uniforme, la page doit l'être, et il n'y a pas de platitude à corriger.

---

## Page blanche après écriture d'une option de thème

**Symptôme.** « Il y a eu une erreur critique sur ce site ». Le journal d'erreurs ne contient rien d'utile.

**Cause.** Une option typée a reçu un tableau responsive là où le thème attendait une chaîne. Exemple vécu : `hb-primary-footer-height` dont le défaut est une chaîne vide, et qui provoque `strtolower(): Argument #1 must be of type string, array given`.

**Diagnostic.** Reproduire le rendu en PHP plutôt que chercher dans les logs :

```php
ob_start();
try { wp_head(); }
catch (\Throwable $e) {
  return get_class($e) . ': ' . $e->getMessage()
       . ' @ ' . str_replace(ABSPATH, '', $e->getFile()) . ':' . $e->getLine();
}
ob_end_clean();
```

La trace donne le fichier et la ligne exacts.

**Correctif.** Lire le défaut avant d'écrire, et respecter son type. Plus généralement : ne pas empiler un thème tiers sous un design sur mesure (règle 8).

**Attention.** Le cache statique du thème garde l'ancienne valeur dans la même requête PHP. Retester dans une requête neuve, sinon le correctif paraît inopérant.

---

## Toutes les images cassées après déploiement

**Symptôme.** Le texte alternatif s'affiche à la place de chaque image. Les URL se terminent sans extension.

**Cause.** Extension oubliée dans le générateur de patterns : `get_theme_file_uri('assets/img/nom')` au lieu de `'assets/img/nom.jpg'`.

**Correctif.** Corriger, régénérer, et ne renvoyer qu'une archive des patterns — 11 Ko au lieu de 2,3 Mo.

---

## Page d'accueil impossible à modifier

**Symptôme.** Aucune page « Accueil » dans la liste des pages. Le contenu n'apparaît que dans l'éditeur de site.

**Cause.** `front-page.html` appelle directement des `wp:pattern`. Le contenu appartient au template, pas à une page.

**Correctif.** Créer une page réelle, y placer le contenu, réduire `front-page.html` à `header + post-content + footer`, puis `show_on_front = page`. C'est la règle 4, et elle coûte une refonte complète quand on la découvre après coup.

---

## Le serveur de développement refuse de démarrer

**Symptôme.** `Error: EPERM: operation not permitted, uv_cwd` pour Node, `PermissionError: [Errno 1] Operation not permitted` pour Python.

**Cause.** Le processus est lancé avec un **répertoire courant** protégé par macOS sans que l'autorisation ait été accordée — typiquement sous `~/Documents` ou `~/Desktop`. C'est le `cwd` qui est en cause, pas l'emplacement des fichiers servis.

**Correctif.** Lancer depuis `/tmp` en passant `RACINE` en chemin absolu : le serveur lit sans difficulté un dossier que son `cwd` ne pourrait pas occuper. Ce n'est qu'en cas d'échec de **lecture** qu'il faut déplacer le projet — et ça se teste, voir phase 0.

---

## « Pourquoi mon dossier est vide ? »

**Symptôme.** Le site est en ligne, tout fonctionne, et l'utilisateur ne trouve aucun fichier là où il les attendait. Il le découvre seul, souvent longtemps après.

**Cause.** Le projet a été créé hors du dossier de travail « par précaution TCC », sans avoir vérifié que la protection s'appliquait vraiment, et sans le redire ensuite.

**Correctif.** Tester l'accès en phase 0, et ne sortir du dossier de travail que sur un échec réel. C'est la panne jumelle de la précédente, et la plus coûteuse des deux : une consigne de prudence appliquée sans vérification ne casse rien, donc rien ne la signale — seul l'utilisateur finit par buter dessus, et il a perdu confiance avant de poser la question.

---

## Composants sans style après passage en blocs natifs

**Symptôme.** Les grilles s'empilent, les cartes perdent leurs marges intérieures.

**Cause.** Le DOM a changé : une image est devenue `figure.wp-block-image`, un conteneur `div.wp-block-group`. Les sélecteurs écrits pour du HTML brut ne matchent plus.

**Correctif.** Poser les classes du composant directement sur le bloc via `className`. Voir `blocs-gutenberg.md`.

---

## Une itération de mise en page coûte quatre minutes

**Symptôme.** Chaque retouche exige de composer, mettre en file, sérialiser dans un onglet d'administration ouvert, purger le cache, recharger.

**Cause.** Le design est poussé dans le `post_content` par l'API de blocs au lieu d'être écrit en fichiers.

**Correctif.** C'est le principe même de la méthode. La file de blocs sert au contenu, jamais à la mise en forme.

---

## Le rythme vertical disparaît après le passage en blocs

**Symptôme.** Le site fonctionne, rien ne semble cassé — mais la page est ~300 px plus courte que la maquette, les titres collent aux paragraphes, une carte volontairement décalée est revenue dans l'alignement. L'œil ne le voit pas ; seul le relevé de la porte 4 le montre.

**Cause.** La neutralisation naïve de l'espacement automatique : `.wp-block-group > * { margin-block: 0 }`. Spécificité (0,1,0) en fin de feuille, elle écrase toutes les marges déclarées par les composants. Vécu : quatorze marges à zéro d'un coup.

**Correctif.** `blockGap: "0px"` dans `theme.json` + reset à spécificité nulle (`:where()`). La méthode complète est dans `blocs-gutenberg.md`, section « Neutraliser l'espacement automatique — sans détruire le vôtre ».

---

## Un correctif CSS ne « prend » pas

**Symptôme.** Le fichier est corrigé sur le serveur — `curl` le prouve — mais le navigateur affiche toujours l'ancien rendu, purge après purge.

**Cause.** Trois caches superposés : la page LiteSpeed, le navigateur (Hostinger sert le HTML avec `cache-control: max-age=604800` — sept jours), et le cache objet des en-têtes de thème qui fige le `?ver=` des assets.

**Diagnostic.** `curl -D -` sur l'URL nue : comparer le `?ver=` du HTML servi à la version réelle du thème, et le fichier servi au fichier local, à l'octet près.

**Correctif.** Incrémenter `Version:` dans `style.css`, purger (LiteSpeed + `wp_clean_themes_cache(true)` + `wp_cache_flush()`), contrôler avec `?frais=<n>`. Le détail est en phase 5, étape 7.

---

## 404 sur tout le site pendant les tests

**Symptôme.** Chaque page testée renvoie « Page non trouvée » alors qu'elles existent et que les permaliens sont bons.

**Cause.** Le paramètre choisi comme casse-cache est une variable de requête réservée de WordPress : `?m=1` demande l'archive du mois « 1 », qui n'existe pas. Idem `?p=`, `?s=`, `?cat=`, `?tag=`.

**Correctif.** Un nom neutre : `?frais=<n>`.

---

## Le panneau de navigation mobile s'ouvre blanc sur blanc

**Symptôme.** Le burger fonctionne, le panneau s'ouvre — fond blanc, liens crème, illisible. La règle du thème qui pose le fond sombre est bien dans la feuille servie.

**Cause.** WordPress imprime le CSS d'un bloc au moment où il le rend, donc après `style.css`. À spécificité égale, le style du bloc gagne — quelle que soit la feuille du thème. Et la feuille qui pose le blanc n'est pas inspectable depuis la page.

**Correctif.** Qualifier le nav (`.wp-block-navigation.ma-nav …`) et forcer `background-color` avec `!important` — le seul endroit du thème où il est justifié. Voir `blocs-gutenberg.md`, section « Navigation ».

---

## L'éditeur affiche des colonnes effondrées et des boutons en texte vertical

**Symptôme.** Le site est parfait, mais dans Gutenberg la photo du héros prend la moitié du canevas, l'enveloppe est écrasée, un bouton s'affiche lettre par lettre sur 100 px de large.

**Cause.** Gutenberg impose `position: relative` à chaque bloc pour ancrer ses barres d'outils : les couches que la maquette sort du flux y retombent et volent la place au contenu.

**Correctif.** Une feuille `assets/editor.css` chargée par `add_editor_style()`, qui rétablit les `position: absolute` (avec `!important`). Voir `blocs-gutenberg.md`, section « L'éditeur doit prévisualiser le site », et la porte 5 qui le vérifie.

---

## Captures noires ou mesures fausses pendant la recette

**Symptôme.** Des captures d'écran entièrement noires, des éléments mesurés à 0 px de haut alors qu'ils s'affichent, un défilement programmé qui ne semble pas s'appliquer.

**Cause.** `scroll-behavior: smooth` dans le CSS du site : chaque `scrollTo()` programmé devient une animation, et la mesure ou la capture part en plein mouvement.

**Correctif.** Avant toute mesure : `document.documentElement.style.scrollBehavior = 'auto'` et `scrollTo({top, behavior: 'instant'})`. Attendre ensuite le décodage des images (`img.complete && img.naturalWidth > 0`).

---

## Divers, à connaître

- **Sessions MCP en HTTP.** L'endpoint exige un en-tête `Mcp-Session-Id`. Ouvrir une session par `initialize`, récupérer l'identifiant dans les en-têtes de réponse, envoyer `notifications/initialized`, puis réutiliser l'identifiant. `scripts/wp.sh` le gère.
- **Réponses en flux.** Une réponse peut arriver en `text/event-stream` : lire la ligne préfixée `data:` avant de parser le JSON.
- **Captures trop précoces.** Les JPEG volumineux ne sont pas décodés avant cinq secondes environ. Vérifier `img.complete && img.naturalWidth > 0`.
- **Cache LiteSpeed.** Purger après chaque déploiement, et contrôler avec `?frais=<n>` — jamais `?m=` ni un autre paramètre réservé. Le protocole complet (version, purge, contrôle par `curl`) est en phase 5, étape 7.
- **Écriture PHP verrouillée.** Novamira n'écrit les fichiers `.php` que dans son bac à sable : `functions.php` et les patterns passent obligatoirement par l'archive du thème, les fichiers non-PHP (`style.css`, `theme.json`, `editor.css`) peuvent s'éditer directement.
- **Site cible jamais vide.** Sauvegarder l'existant avant d'écrire (phase 5, étape 0) : les slugs en collision sont écrasés.
