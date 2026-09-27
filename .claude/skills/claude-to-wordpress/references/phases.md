# Déroulé détaillé des phases

Table des matières
- [Phase 0 — Préparation](#phase-0--préparation)
- [Phase 1 — Brief](#phase-1--brief)
- [Phase 2 — Maquette](#phase-2--maquette)
- [Phase 3 — Déclinaison](#phase-3--déclinaison)
- [Phase 4 — Thème bloc](#phase-4--thème-bloc)
- [Phase 5 — Déploiement](#phase-5--déploiement)
- [Phase 6 — Recette](#phase-6--recette)

---

## Phase 0 — Préparation

À faire une seule fois par poste de travail.

### Aucun outillage à installer

Rien à installer, aucun plugin à vérifier. Si une version antérieure de la méthode vous a laissé un réflexe d'installation de plugin de design, il est caduc : ne pas aller en chercher un, ne pas en charger un.

Le parti pris esthétique est porté par deux choses, et dans cet ordre : **la référence fournie par le client d'abord** (règle 0′ et les sept invariants de registre), la section « Le parti pris se décide, il ne se déduit pas » ensuite, et seulement pour ce que la référence ne tranche pas. L'inverse — le manifeste d'abord, la référence comme vague inspiration — est la panne recensée en tête de `pieges.md`.

### Espace de travail

**Créer le projet dans le dossier auquel l'utilisateur a donné accès** — le répertoire de travail de la session. C'est là qu'il ira chercher ses fichiers. Un projet posé ailleurs « par précaution » se solde par un dossier vide qu'il faudra lui expliquer, et il aura raison de ne pas comprendre.

Sur macOS, `~/Documents` et `~/Desktop` sont protégés par TCC — mais **cette protection ne joue que si l'autorisation n'a pas été accordée**, et elle l'est presque toujours pour l'outil qui vient justement d'ouvrir ce dossier. Ne jamais le supposer, dans un sens ni dans l'autre : ça se teste en trois secondes.

```bash
mkdir -p "$PWD/.essai-tcc" \
  && node -e "process.chdir('$PWD/.essai-tcc'); require('fs').writeFileSync('t','x'); console.log('acces ok')" \
  ; rm -rf "$PWD/.essai-tcc"
```

- **`acces ok`** — le cas courant. Créer le projet dans le dossier de travail, et ne pas y revenir.
- **`EPERM: uv_cwd`** — alors seulement, créer le projet hors des dossiers protégés (`~/projets-wp/<nom>`), **le dire à l'utilisateur dans le même message**, et poser un lien symbolique pour qu'il retrouve son travail :

```bash
ln -sfn ~/projets-wp/<nom> <dossier-de-travail>/<nom>
```

> Le serveur de développement, lui, n'a jamais besoin qu'on déplace quoi que ce soit : il se lance depuis `/tmp` avec `RACINE` en chemin absolu (phase 2). Un `cwd` protégé le ferait échouer, pas une `RACINE` protégée.

Rien à préparer côté WordPress ici : les phases 1 à 4 sont entièrement locales, et l'accès au site n'intervient qu'au début du déploiement — il est décrit en tête de phase 5, au moment où on en a besoin.

---

## Phase 1 — Brief

Le brief tient en un message. Ce qui compte est la référence visuelle, pas la prose.

### Gabarit

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

### Ce que le gabarit ne dit pas, la méthode le décide

Aucune de ces questions ne se pose à l'utilisateur :

- **Référence.** L'image jointe est la référence visuelle. Sans image, la demander avant d'écrire du code — c'est le levier le plus puissant de la méthode.
- **Contenu.** Inventé à partir de [Activité] et [Description] ; tout élément réel fourni ([Nom], [Contact]) est repris tel quel. [Contact] alimente les coordonnées du pied de page et de la page contact.
- **Photos.** Unsplash par défaut, sauf photos fournies.
- **WordPress.** Rien n'y part avant validation de la maquette (règle 3) — le gabarit n'a pas besoin de le dire.
- **Recette.** Les cinq portes de la phase 6 se passent d'office, sans que l'utilisateur les demande ni pointe un problème.

**L'unique exception, et elle est obligatoire :** toucher à l'un des sept invariants de registre (règle 0′) se **demande** au client avant de coder, et on attend la réponse. Le « sans autre question » de cette méthode couvre le contenu, les photos et les validations. Il ne couvre pas le droit de changer la classe typographique, le système de couleur, la température du fond, la découpe, la géométrie, l'alignement ou la densité de la référence.

### Ce que l'agent produit avant de coder

Dans cet ordre, et avec ces intitulés :

1. Le **relevé de la référence** : les sept invariants de registre (SKILL.md, règle 0′), lus sur l'image. Observation pure, aucun choix.
2. Le **sujet concret** : entreprise, audience, mission unique de la page.
3. La **palette**, 4 à 6 valeurs hexadécimales nommées, **prélevées sur la référence**.
4. Les **typographies** : la classe est imposée par le relevé ; seule la famille exacte est un choix, à l'intérieur de cette classe.
5. **Ce que j'ajoute** : la liste exhaustive de ce qui ne figure pas dans la référence. Un invariant qui apparaît ici n'est pas une annonce mais une **question**, et on attend la réponse.

> **Il n'y a plus de quota d'écarts.** Une version antérieure demandait « deux ou trois écarts volontaires, justifiés ». C'était une commande de déviation : l'agent en produisait toujours deux ou trois, au besoin en inventant, et un changement de classe typographique ou d'accent y passait au même titre qu'une section retirée. La rubrique « Ce que j'ajoute » la remplace, et sa bonne valeur est souvent **zéro**.

Deux points de contrôle, en sens inverse l'un de l'autre :

- Si ce plan ressemble à ce qu'on produirait pour n'importe quel autre site du même secteur, le refaire avant d'écrire une ligne de code.
- Si « Ce que j'ajoute » dépasse deux lignes, ou touche un invariant sans le poser en question, c'est un autre site qui se prépare. Un plan qui a besoin de trois paragraphes pour justifier ses écarts en a déjà trop.

---

## Phase 2 — Maquette

Une seule page, l'accueil, en HTML et CSS, en local.

### La porte d'entrée : règle 0

Avant d'ouvrir un fichier, se rappeler que **rien de la phase 4 n'a le droit d'entrer ici**. Ni `core/html`, ni blocs natifs, ni éditabilité client. La maquette se dessine librement ; la conversion est un problème qui se résout en phase 4, avec un design fini sous les yeux.

Le test est simple et se pose à chaque décision de composition : *est-ce que j'écarte cette idée parce qu'elle est mauvaise, ou parce que je me demande comment elle passera en blocs ?* Si c'est la seconde réponse, l'idée reste.

### La seconde porte d'entrée : règle 0′

Règle 0 libère la main sur la technique. **Elle ne la libère pas sur le registre.** Les sept invariants — classe typographique, système de couleur, température du fond clair, découpe de la page, géométrie, alignement dominant, densité — appartiennent à la référence et se reproduisent tels quels. Un invariant qu'on veut changer se pose en question au client avant de coder, jamais en arbitrage d'agent.

Le test, à chaque décision de surface : *est-ce que je choisis ça parce que la référence le fait, ou parce que je le trouve plus beau ?* Si c'est la seconde réponse, ce n'est pas une décision, c'est une question.

### Le parti pris se décide, il ne se déduit pas

> **Avec une référence, ce tableau se lit à l'envers** : chaque ligne est une question posée à la référence, et sa réponse est la consigne. Si la référence n'a pas de dominante, la page n'en invente pas ; si elle n'a aucun accent, on n'en ajoute pas ; si toutes ses sections ont le même poids, on les égalise. Ces exigences ne redeviennent des consignes que **s'il n'y a pas de référence**.

Aucun outil tiers ne porte l'esthétique. Ce qui suit la porte, et chaque point se constate sur la capture.

Une liste d'interdits ne fabrique pas un design — elle fabrique une page poncée. Exigences, en positif :

| Exigence | Ce qu'on regarde sur la capture | Ce qu'on demande d'abord à la référence |
|---|---|---|
| Un élément domine | On peut nommer en un mot ce qui porte la page | A-t-elle une dominante, ou est-elle uniforme ? |
| La couleur forte occupe du terrain | Elle couvre une surface réelle, pas deux bandeaux | Combien de couleurs non neutres ? Zéro est une réponse. |
| Les sections n'ont pas le même poids | Deux sections voisines ne partagent pas le même gabarit | Ses sections varient-elles, ou sont-elles calibrées pareil ? |
| Il y a de la profondeur | Au moins un élément sort de son conteneur | Combien de débordements réels chez elle ? |
| La typo de caractère travaille | Elle porte du contenu, pas une figuration | Combien de familles, et de quelle classe ? |
| Le concept est visible sans légende | Il se lit en échelle et en composition, pas en détail | A-t-elle un concept, ou est-ce un gabarit sobre ? |

Reprendre une référence, c'est reprendre ce qui lui donne son énergie — profondeur, superpositions, objet central, champ de couleur — et pas seulement l'ordre de ses sections. Sans ces dispositifs, on produit le wireframe de la référence.

**Et l'inverse est pire, parce qu'il ressemble à du travail :** garder l'ordre des sections et remplacer toute la surface par la sienne. Même enchaînement de blocs, autre classe typographique, un accent qu'elle n'a pas, une autre température de fond, une autre géométrie. Le squelette tient, la peau est neuve, le client ne reconnaît rien.

### Serveur de développement

`scripts/serve.js` est prêt à l'emploi. Le lancer depuis un répertoire accessible :

```bash
cd /tmp && RACINE=/chemin/projet/site nohup node /chemin/projet/scripts/serve.js > /tmp/serve.log 2>&1 &
sleep 1 && tail -1 /tmp/serve.log   # le serveur affiche le port réellement utilisé
```

Si le port 4321 est occupé (un ancien projet qui tourne encore), le serveur essaie automatiquement les ports suivants — lire le journal plutôt que de supposer le port, et ne jamais tuer un processus qui l'occupe : il appartient peut-être à un autre projet.

### Sourcer les visuels

| Source | Coût | Cohérence | Quand |
|---|---|---|---|
| Photos du client | Nulle | Maximale | Dès qu'elles existent |
| Unsplash | Nulle | Moyenne | Démo, secteur générique |
| Génération | Crédits | Élevée | Direction très typée |

Pour Unsplash, récupérer les identifiants depuis la page de résultats plutôt que de deviner :

```js
[...document.querySelectorAll('figure img[src*="images.unsplash.com/photo-"]')]
  .slice(0, 16).map(i => ({
    id: (i.src.match(/photo-[\w-]+/) || [''])[0],
    alt: (i.alt || '').slice(0, 70)
  }));
```

Téléchargement : `https://images.unsplash.com/{id}?w=1200&q=80&fm=jpg&fit=crop`. La licence Unsplash autorise l'usage commercial sans attribution obligatoire.

### Protocole d'itération

1. Écrire la page complète, avec du contenu réel, jamais de lorem.
2. Prendre **une** capture, corriger ce qu'elle montre.
3. **Un tour de design avant de montrer au client** : reprendre la capture et pousser la page, au lieu de la réparer. Un plan validé en prose ne le remplace pas. *Pousser la page veut dire la rapprocher de la référence, pas s'en éloigner* — si le tour de design ajoute une famille typographique, une couleur ou une géométrie absentes de la référence, ce n'est pas un tour de design, c'est une sortie de registre.
4. **Passer la porte de registre** (ci-dessous). Elle se passe avant l'envoi, jamais après.
5. Montrer au client, qui nomme précisément ce qui cloche.
6. Deux à trois tours maximum.

### La porte de registre

Les cinq portes de la phase 6 mesurent la fidélité de la maquette au site. **Aucune ne mesure la fidélité de la maquette à la référence** — c'est le trou de la méthode, et il se bouche ici.

**D'abord, rouvrir l'image de référence dans le même message que la capture.** Les deux images, l'une sous l'autre, regardées ensemble. Jamais la référence de mémoire : la mémoire retient l'ordre des sections et perd la surface, et c'est précisément de mémoire qu'on se persuade d'avoir été fidèle.

Ensuite, relever la maquette telle qu'elle est — pas telle qu'on l'a voulue :

```js
const cs = e => getComputedStyle(e);
const titres = [...document.querySelectorAll('h1,h2,h3')];
const blocs  = [...document.querySelectorAll('section,article,figure,aside')];
const teinte = c => { const [r,g,b] = c.match(/\d+/g).map(Number);
  return Math.max(r,g,b) - Math.min(r,g,b) > 18 ? c : null; };   // non neutre

({
  famillesTypo : [...new Set([cs(document.body).fontFamily, ...titres.map(t => cs(t).fontFamily)])],
  italiques    : titres.filter(t => cs(t).fontStyle === 'italic').length,
  fondClair    : cs(document.body).backgroundColor,
  nonNeutres   : [...new Set(blocs.flatMap(e => [cs(e).backgroundColor, cs(e).color]).map(teinte).filter(Boolean))],
  rayons       : [...new Set(blocs.map(e => cs(e).borderRadius))],
  rotations    : blocs.filter(e => cs(e).transform !== 'none' && !cs(e).transform.startsWith('matrix(1, 0, 0, 1')).length,
  centres      : titres.filter(t => cs(t).textAlign === 'center').length + ' / ' + titres.length,
  corpsVsTitre : cs(document.body).fontSize + ' · ' + (titres[0] && cs(titres[0]).fontSize),
})
```

Puis remplir ce tableau, **avant** d'écrire au client :

| Invariant | Référence | Maquette (relevé) | Verdict |
|---|---|---|---|
| 1 · Classe typographique | | | |
| 2 · Système de couleur | | | |
| 3 · Température du fond clair | | | |
| 4 · Découpe de la page | | | |
| 5 · Géométrie | | | |
| 6 · Alignement dominant | | | |
| 7 · Densité et échelle | | | |

Un verdict ne prend que deux valeurs : **conforme**, ou **autorisé par le client** avec la citation de son accord. « Proche », « équivalent », « réinterprété », « adapté au secteur » valent non conforme — on corrige la maquette et on ne montre pas.

> **Pourquoi une porte et pas un principe.** Les portes qui tiennent dans cette méthode sont celles qui ont un critère falsifiable calculé sur l'artefact : `core/html === 0`, `invalides.length === 0`, `±2 px`. Le côté design n'en avait aucune — rien que des exhortations, évaluées par l'agent qui vient de produire le travail, à travers les goûts qui ont causé l'écart. Un tableau qu'il faut remplir publiquement se saute beaucoup moins facilement qu'un principe qu'il faut se rappeler.

Un tour qui remonte la taille d'une icône ou corrige un prénom est un *tour de justesse* : nécessaire, et sans aucun effet sur la qualité du design. Ne pas le compter comme le tour de design de l'étape 3.

> **Piège d'affichage.** Les captures prises moins de cinq secondes après le chargement montrent souvent des images absentes : les JPEG ne sont pas encore décodés. Vérifier `img.complete && img.naturalWidth > 0` avant de conclure à un bug.

### Points de contrôle avant validation

- Aucune décision de composition n'a été prise en pensant à la conversion (règle 0).
- **La porte de registre est passée : sept invariants, sept verdicts, et pas un seul « proche » (règle 0′).**
- **La référence a été rouverte et regardée à côté de la capture, dans ce tour-ci.**
- Un élément domine la page, et on peut le nommer en un mot.
- La couleur forte couvre une surface réelle.
- Deux sections voisines ne partagent pas exactement le même gabarit.
- Au moins un élément sort de son conteneur.
- Le titre principal tient sur deux lignes au plus, sans césure au milieu d'un mot. Poser `hyphens: none` et `text-wrap: balance`.
- Aucun rectangle vide ni aplat de remplacement.
- Les composants d'un même type partagent marges intérieures et lignes de base.
- Rendu à 390 px sans défilement horizontal.

---

## Phase 3 — Déclinaison

L'accueil validé, le système de design l'est aussi. Les pages internes ne rouvrent aucun débat esthétique.

Extraire le CSS et le JS vers des fichiers partagés, puis générer les pages internes depuis une ossature commune. Générer par script plutôt qu'à la main garantit que l'en-tête et le pied de page sont strictement identiques partout.

| Maquette | Devient en phase 4 |
|---|---|
| L'en-tête commun (généré une seule fois par le script d'assemblage) | `parts/header.html` |
| Le pied de page commun (même source unique) | `parts/footer.html` |
| Chaque section | Un fichier dans `patterns/` |
| `styles.css` | `theme.json` + `style.css` |
| `app.js` | `assets/app.js` |

---

## Phase 4 — Thème bloc

Voir `blocs-gutenberg.md` pour la conversion HTML → blocs natifs, et `assets/theme-squelette/` pour le point de départ.

Arborescence cible :

```
mon-theme/            <- le nom du dossier EST le slug passé à switch_theme()
├── style.css          en-tête de thème + tout le CSS de la maquette + adaptations Gutenberg
├── theme.json         jetons : couleurs, typo, espacements — et blockGap à "0px" (voir blocs-gutenberg.md)
├── functions.php      polices (site ET éditeur), styles, script, catégorie de patterns
├── parts/
│   ├── header.html
│   └── footer.html
├── templates/
│   ├── front-page.html    header + post-content + footer
│   ├── page.html          identique
│   ├── index.html         boucle de requête
│   └── 404.html
├── patterns/          un fichier .php par section réutilisable
└── assets/
    ├── app.js         sélecteurs par classe, jamais par id
    ├── editor.css     corrections propres au canevas de Gutenberg (porte 5)
    └── img/           copie des visuels, embarquée dans le zip
```

> **Au passage.** Les patterns sont des fichiers PHP : ils partent dans l'archive du thème, jamais en écritures individuelles côté serveur — le pourquoi est en phase 5, étape 1. De toute façon plus rapide qu'une trentaine d'écritures.

---

## Phase 5 — Déploiement

### Prérequis · L'accès au site

C'est seulement maintenant que WordPress entre en scène — les phases 0 à 4 n'ont eu besoin d'aucun accès au site. Prérequis de cette phase : le plugin **Novamira** actif sur le site cible et son bundle de connexion `.mcpb` téléchargé (leur mise en place est montrée dans la formation à ce moment précis). Novamira expose les capacités indispensables, chacune étant une « ability » appelable par son nom :

| Capacité | Ability |
|---|---|
| Exécution PHP (tous les extraits des phases 5 et 6) | `novamira/execute-php`, paramètre `{"code": "…"}` |
| Écriture de fichiers non-PHP | `novamira/write-file`, `novamira/edit-file` |
| Téléversement de fichiers volumineux | `novamira/create-upload-link` |
| Session wp-admin sans mot de passe | `novamira/create-admin-access-link` |

Exemple complet avec le script fourni : `./scripts/wp.sh novamira/execute-php '{"code":"return get_option(\"blogname\");"}'`. En cas de doute sur un nom, l'ability `novamira-mcp-adapter/discover-abilities` liste tout ce que le site expose.

Le bundle `.mcpb` fourni par le site est une archive zip :

```bash
unzip -o site.mcpb -d bundle
python3 -c "import json;print(json.load(open('bundle/manifest.json'))['server']['mcp_config']['env'])"
```

Deux voies d'accès :

1. **Serveur MCP** — `claude mcp add nom -s user -e WP_API_URL=… -e WP_API_USERNAME=… -e WP_API_PASSWORD=… -- npx -y @automattic/mcp-wordpress-remote@latest`. Nécessite un redémarrage de session.
2. **HTTP direct** — `scripts/wp.sh`, fourni. Aucun redémarrage, utilisable immédiatement.

> **Sécurité.** Le bundle contient un mot de passe applicatif en clair. Ne jamais le committer. Le stocker au niveau utilisateur, pas dans le dossier du projet. S'il a transité par un canal non fiable, le révoquer depuis *wp-admin → Utilisateurs → Mots de passe d'application*.

Huit opérations, numérotées de 0 à 7, dans cet ordre. L'ordre n'est pas décoratif : les visuels s'importent depuis le dossier du thème côté serveur, donc après son téléversement.

### 0 · Sauvegarder l'existant

Le site cible n'est jamais supposé vide. Avant toute écriture : sérialiser les contenus et réglages dans un fichier de sauvegarde, et signaler les collisions de slug — une page « accueil » ou « contact » déjà en place sera écrasée par la création des pages, son contenu d'origine ne survivra que là.

```php
global $wpdb;
$rows = $wpdb->get_results("SELECT ID, post_name, post_title, post_status, post_type, post_content
  FROM {$wpdb->posts} WHERE post_type IN ('page','post')
  AND post_status NOT IN ('auto-draft','revision')", ARRAY_A);
file_put_contents(ABSPATH . 'wp-content/uploads/sauvegarde-avant-theme.json',
  wp_json_encode([
    'date' => current_time('mysql'),
    'theme_avant' => get_stylesheet(),
    'show_on_front' => get_option('show_on_front'),
    'page_on_front' => get_option('page_on_front'),
    'titre_site' => get_option('blogname'),
    'contenus' => $rows,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
```

L'ancien thème reste installé : le retour en arrière tient en un `switch_theme()` plus cette sauvegarde. Le dire au client dans le compte rendu.

### 1 · Empaqueter et téléverser le thème

```bash
zip -rq mon-theme.zip mon-theme -x '*.DS_Store'
```

L'archive doit contenir un dossier portant **exactement le slug du thème** (`mon-theme/`) : c'est lui que `switch_theme()` attend. Et elle n'est pas qu'une commodité : l'écriture de fichiers `.php` côté serveur est verrouillée hors du bac à sable de Novamira — `functions.php` et les patterns ne peuvent pas s'écrire un par un. Puis `novamira/create-upload-link` avec `{"path":"wp-content/uploads/theme.zip","overwrite":true}` — sa réponse contient `upload_url` et `upload_token` ; ce sont eux qui remplissent les variables du `curl` :

```bash
curl -X PUT -H "X-Novamira-Upload-Token: $TOKEN" \
     --data-binary @mon-theme.zip "$UPLOAD_URL"
```

### 2 · Décompresser et activer

```php
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

// vider les dossiers versionnés avant de réécrire,
// sinon d'anciens patterns survivent au déploiement
foreach (['patterns', 'templates', 'parts'] as $d) {
  foreach (glob(get_theme_root() . "/mon-theme/$d/*") as $f) @unlink($f);
}

unzip_file(ABSPATH . 'wp-content/uploads/theme.zip', get_theme_root());
wp_clean_themes_cache();
switch_theme('mon-theme');
```

### 3 · Importer les visuels dans la médiathèque

Le thème est maintenant sur le serveur : ses `assets/img/` servent de source. Sans cette étape, les images ne sont pas remplaçables (règle 6). Elle vient **après** le téléversement du thème — avant, il n'y a tout simplement rien à copier côté serveur.

```php
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$tmp = wp_tempnam($slug);
copy(get_theme_root() . "/mon-theme/assets/img/$slug.jpg", $tmp);
$id = media_handle_sideload(['name' => "$slug.jpg", 'tmp_name' => $tmp], 0, $alt);
wp_update_post(['ID' => $id, 'post_name' => $slug]);
update_post_meta($id, '_wp_attachment_image_alt', $alt);
```

Conserver la table `slug → identifiant` : elle alimente l'attribut `id` de chaque bloc image.

### 4 · Créer le formulaire Contact Form 7

Avant les pages, puisque leur contenu référence son shortcode. Vérifier d'abord que l'extension est là — rien dans la méthode ne l'installe implicitement :

```php
if (!class_exists('WPCF7_ContactForm')) { return 'Contact Form 7 absent : installer et activer contact-form-7 d’abord.'; }
```

Si elle manque, l'installer depuis le dépôt officiel (slug `contact-form-7`) puis l'activer — par l'ability d'installation d'extensions du site s'il en expose une, sinon depuis wp-admin → Extensions. Ensuite : gabarit du formulaire avec les classes de la maquette, textarea limité (`40x6` : CF7 en met 10 par défaut), `Reply-To` sur le courriel du visiteur. Réutiliser le formulaire s'il existe déjà (idempotence).

```php
$titre = 'Mon thème — Demande de contact';
$existants = get_posts(['post_type' => 'wpcf7_contact_form', 'title' => $titre,
                        'posts_per_page' => 1, 'post_status' => 'any']);
$form = $existants ? WPCF7_ContactForm::get_instance($existants[0]->ID)
                   : WPCF7_ContactForm::get_template(['title' => $titre]);
$form->set_properties(['form' => $gabarit_html_avec_classes, 'mail' => [/* … */]]);
$id = $form->save();   // -> [contact-form-7 id="…"] dans un core/shortcode
```

### 5 · Créer les pages

Passer le contenu par un fichier JSON téléversé : un contenu de page fait facilement 90 Ko.

```php
$pages = json_decode(file_get_contents(ABSPATH . 'wp-content/uploads/content.json'), true);
foreach ($pages as $pg) {
  $existe = get_page_by_path($pg['slug']);
  $data = [
    'post_type'    => 'page',
    'post_title'   => $pg['title'],
    'post_name'    => $pg['slug'],
    'post_status'  => 'publish',
    'post_content' => $pg['content'],
  ];
  if ($existe) { $data['ID'] = $existe->ID; wp_update_post($data); }
  else { wp_insert_post($data); }
}
```

### 6 · Déclarer la page d'accueil statique

L'étape la plus facile à oublier, et celle qui rend le site inutilisable si on la saute (règle 4).

```php
$accueil = get_page_by_path('accueil');
update_option('show_on_front', 'page');
update_option('page_on_front', $accueil->ID);

global $wp_rewrite;
$wp_rewrite->set_permalink_structure('/%postname%/');
$wp_rewrite->flush_rules();
```

### 7 · Purger, versionner, vérifier ce qui est réellement servi

Trois caches se superposent et chacun a fait croire, en conditions réelles, qu'un correctif « ne prenait pas » :
le cache de page (LiteSpeed), le cache navigateur (Hostinger sert le HTML avec `max-age=604800` — sept jours),
et le cache objet des en-têtes de thème.

Après **chaque** modification de `style.css` ou `theme.json` :

1. incrémenter `Version:` dans l'en-tête de `style.css` — c'est ce qui change le `?ver=` des assets ;
2. purger : le vidage LiteSpeed de l'hébergeur, plus
   `wp_clean_themes_cache(true); wp_cache_flush();` et, si `theme.json` a changé,
   `WP_Theme_JSON_Resolver::clean_cached_data();` ;
3. vérifier par `curl` que le fichier servi est identique **à l'octet près** au fichier local,
   et que le HTML servi référence bien la nouvelle version ;
4. contrôler dans le navigateur avec un paramètre unique — `?frais=<n>`, jamais `?m=`, `?p=`, `?s=`, `?cat=` ni `?tag=` :
   ce sont des variables de requête réservées de WordPress, et `?m=1` renvoie un 404 d'archive
   qui fait croire que le site est cassé.

À la fin du déploiement, supprimer les fichiers de transfert (`theme.zip`, `content.json`) : ils sont servis publiquement depuis `uploads/`.

---

## Phase 6 — Recette

### Porte 1 · Intégrité des blocs, côté serveur

```php
function compter($blocs, &$n) {
  foreach ($blocs as $b) {
    if (empty($b['blockName'])) continue;
    $n[$b['blockName']] = ($n[$b['blockName']] ?? 0) + 1;
    if (!empty($b['innerBlocks'])) compter($b['innerBlocks'], $n);
  }
}
$n = []; compter(parse_blocks(get_page_by_path('accueil')->post_content), $n);
return [
  'total'     => array_sum($n),
  'html_brut' => $n['core/html'] ?? 0,                        // doit valoir 0
  'hors_core' => count(array_filter(array_keys($n),
                   fn($k) => strpos($k, 'core/') !== 0)),     // doit valoir 0
];
```

### Porte 2 · Validation dans l'éditeur

Ouvrir `post.php?post=ID&action=edit`, puis dans la console :

```js
const aplatir = bs => bs.reduce((a, b) => a.concat([b], aplatir(b.innerBlocks || [])), []);
const tous = aplatir(wp.data.select('core/block-editor').getBlocks());
const invalides = tous.filter(b => b.isValid === false);

({
  total: tous.length,
  invalides: invalides.length,                   // doit valoir 0
  noms: invalides.map(b => b.name),
  images_total: tous.filter(b => b.name === 'core/image').length,
  images_liees: tous.filter(b => b.name === 'core/image' && b.attributes.id).length
});
```

Le bloc se termine par une expression, pas par `return` : un `return` de premier niveau est une erreur de syntaxe dans une console. Critère : `invalides === 0` **et** `images_liees === images_total`.

Pour ouvrir wp-admin sans mot de passe, `novamira/create-admin-access-link` retourne un échange à usage unique — sa réponse fournit `access_token`, `access_nonce` et `exchange_url`, qui remplissent les variables du `curl` :

```bash
curl -s -X POST \
  -H "X-Novamira-Admin-Access-Token: $TOKEN" \
  -H "X-Novamira-Admin-Access-Nonce: $NONCE" \
  "$EXCHANGE_URL"
```

L'URL renvoyée est valable 60 secondes : l'ouvrir immédiatement.

### Porte 3 · Rendu et responsive

- Toutes les images servies en HTTP 200, taille non nulle.
- Polices réellement appliquées : `getComputedStyle(document.body).fontFamily`.
- Interactions fonctionnelles **après déconnexion**, pas seulement en session administrateur — connecté, les widgets d'hébergeur (chatbot Hostinger) recouvrent l'écran mobile et faussent le test.
- Rendu à 390 px sans défilement horizontal.
- La barre d'administration ne recouvre pas l'en-tête en position absolue.

### Porte 4 · Fidélité au pixel (règle 10)

Le seul contrôle qui attrape ce que l'œil rate : marges écrasées par une neutralisation trop large, `blockGap` résiduel, textarea à la mauvaise hauteur. Relever géométrie et styles calculés des **mêmes sélecteurs** sur la maquette locale puis sur le site, et différer les deux relevés.

```js
// À exécuter tel quel sur la maquette PUIS sur le site, même viewport (1440×900).
// Adapter uniquement la liste des sélecteurs aux classes du projet.
document.documentElement.style.scrollBehavior = 'auto';           // voir « Hygiène de mesure »
for (let y = 0; y < 12000; y += 800) { window.scrollTo({top:y, behavior:'instant'}); await new Promise(r=>setTimeout(r,120)); }
window.scrollTo({top:0, behavior:'instant'}); await new Promise(r=>setTimeout(r,2500));

const SEL = ['.hero', '.titre-hero', '.grille-services', '.carte-service', '.pied' /* … toutes les classes de composant */];
const P = ['fontSize','fontFamily','fontWeight','lineHeight','letterSpacing','color','backgroundColor',
           'display','gridTemplateColumns','gap','paddingTop','paddingBottom','marginTop','marginBottom'];
const out = {};
for (const s of SEL) {
  const e = document.querySelector(s); if (!e) { out[s] = null; continue; }
  const c = getComputedStyle(e), r = e.getBoundingClientRect();
  out[s] = Object.fromEntries([['l', Math.round(r.width)], ['h', Math.round(r.height)], ['x', Math.round(r.x)],
                               ...P.map(p => [p, c[p]])]);
}
out.__page = document.documentElement.scrollHeight;
JSON.stringify(out);
```

Critère de réception : **hauteur de page identique à ±2 px, aucun composant au-delà de 2 px d'écart**, aucune propriété divergente. Un écart n'est jamais « du bruit » : +24 px répétés = blockGap résiduel ; marges toutes à 0 = neutralisation trop large ; quelques pixels par champ de formulaire = les `<span>` d'enrobage de CF7.

### Porte 5 · Fidélité de l'éditeur

Ouvrir `post.php?post=ID&action=edit` et mesurer dans le canevas — l'éditeur moderne vit dans une iframe :

```js
const doc = document.querySelector('iframe[name="editor-canvas"]').contentDocument;
const m = s => { const e = doc.querySelector(s); if (!e) return null;
  const r = e.getBoundingClientRect();
  return { pos: getComputedStyle(e).position, l: Math.round(r.width), h: Math.round(r.height) }; };
({
  canvas:    doc.documentElement.clientWidth,
  photo:     m('.hero-photo'),        // doit être position: "absolute"
  enveloppe: m('.hero .enveloppe'),   // doit occuper toute la largeur du canevas
  bouton:    m('.wp-block-button'),   // largeur normale, jamais ~100 px (texte vertical)
});
```

Réception : les couches sorties du flux le sont restées, l'enveloppe fait la largeur du canevas, aucun bouton effondré. Si ça échoue, c'est `assets/editor.css` qui manque ou n'est pas enregistré — voir `blocs-gutenberg.md`, section « L'éditeur doit prévisualiser le site ».

### Hygiène de mesure

Trois artefacts de test ont, en conditions réelles, fait diagnostiquer des pannes imaginaires :

- **`scroll-behavior: smooth`** anime les `scrollTo()` programmés : captures noires, mesures prises en plein défilement. Forcer `document.documentElement.style.scrollBehavior = 'auto'` et `behavior: 'instant'` avant toute mesure.
- **JPEG non décodés** pendant ~5 s après le chargement. Vérifier `img.complete && img.naturalWidth > 0` avant de conclure à une image cassée.
- **Paramètres réservés WordPress.** `?m=`, `?p=`, `?s=`, `?cat=`, `?tag=` déclenchent des requêtes d'archive — `?m=1` renvoie un 404 sur tout le site. Casse-cache : `?frais=<n>`.
