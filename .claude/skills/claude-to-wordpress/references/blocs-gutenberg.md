# Convertir du HTML en blocs Gutenberg natifs

Le but est qu'aucun bloc `core/html` ne subsiste : c'est ce qui rend le contenu éditable visuellement.

## Les deux faits qui commandent tout

1. **Le balisage écrit à la main doit correspondre exactement à ce que la fonction `save()` du bloc produirait**, sinon l'éditeur affiche « ce bloc contient du contenu inattendu ou invalide ».
2. **WordPress imprime le CSS d'un bloc au moment où il le rend, donc APRÈS `style.css`.** À spécificité égale, c'est le bloc qui gagne, quelle que soit la position dans la feuille du thème. Toute surcharge d'un style de bloc doit être *plus spécifique*, pas simplement plus tardive.

## Le principe de conversion

**Poser les classes CSS du composant directement sur le bloc via `className`.**

La classe `shot` sur un `core/image` produit `figure.wp-block-image.shot`. Le sélecteur écrit pour la maquette fonctionne sans modification. C'est ce qui évite de réécrire la feuille de style pour chaque composant.

**Et laisser toute la mise en page au CSS : `"layout":{"type":"default"}` sur chaque groupe.** Les layouts flex et grid de Gutenberg injectent des classes calculées dans le balisage sauvegardé (`is-content-justification-*`, `is-nowrap`…) qu'il faudrait reproduire à l'identique — une source d'invalidation permanente. Avec `default`, la sortie est toujours `<div class="wp-block-group ma-classe">`, prévisible au caractère près, et le CSS du thème fait le flex et le grid exactement comme dans la maquette.

## Table de correspondance

| Élément HTML | Bloc natif | Sortie |
|---|---|---|
| Conteneur | `core/group` | `div.wp-block-group.ma-classe` |
| Titre | `core/heading` | `h2.wp-block-heading` |
| Texte | `core/paragraph` | `p.ma-classe` |
| Image | `core/image` | `figure.wp-block-image` |
| Bouton | `core/buttons` + `core/button` | `a.wp-block-button__link` |
| Liste | `core/list` + `core/list-item` | `ul.wp-block-list` |
| Menu | `core/navigation` + `core/navigation-link` | `a.wp-block-navigation-item__content` |
| Citation | `core/quote` | `blockquote.wp-block-quote` |
| Formulaire | `core/shortcode` | Contact Form 7 |

## Gabarits de balisage

### Groupe

```html
<!-- wp:group {"className":"ma-classe","layout":{"type":"default"}} -->
<div class="wp-block-group ma-classe">
  <!-- contenu -->
</div>
<!-- /wp:group -->
```

`"layout":{"type":"default"}` donne un flux simple, sans classes calculées. Le flex et le grid restent dans la feuille de style.

### Image liée à la médiathèque

L'attribut `id` est ce qui rend l'image remplaçable en un clic.

```html
<!-- wp:image {"id":14,"sizeSlug":"full","linkDestination":"none","className":"shot"} -->
<figure class="wp-block-image size-full shot"><img src="https://site/wp-content/uploads/2026/09/photo.jpg" alt="Description" class="wp-image-14"/></figure>
<!-- /wp:image -->
```

Deux pièges :

- la classe personnalisée va **sur la figure**, jamais sur l'img — les règles de cadrage visent donc `.shot img`, plus l'élément directement ;
- déclarer `img { height: auto }` dans le thème, sinon l'attribut `height="1500"` de la balise l'emporte sur tout `aspect-ratio` du CSS et l'image sort à sa hauteur naturelle. Vécu : des vignettes 4/5 rendues en colonnes de 1 500 px.

### Bouton

```html
<!-- wp:buttons {"className":"bloc-boutons"} -->
<div class="wp-block-buttons bloc-boutons"><!-- wp:button {"className":"b-plein"} -->
<div class="wp-block-button b-plein"><a class="wp-block-button__link wp-element-button" href="/contact/">Prendre rendez-vous</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
```

La classe passée à `core/button` atterrit sur le **conteneur** `div.wp-block-button`, pas sur le lien. Deux conséquences, toutes deux vécues :

1. les styles se déclarent sur `.wp-block-button.b-plein .wp-block-button__link`, et le conteneur se neutralise — sinon il hérite des règles écrites pour la maquette et double la pastille comme la flèche :

```css
.wp-block-button.b-plein { display: inline-block; background: none; border: 0; padding: 0; border-radius: 0; }
.wp-block-button.b-plein::after { content: none; }
```

2. pour masquer un bouton en mobile, masquer le **conteneur** `core/buttons` (sur lequel rien d'autre ne statue) — la neutralisation ci-dessus, placée plus bas dans la feuille, gagnerait sinon le duel de `display` à spécificité égale et le bouton déborderait de l'écran.

### Navigation

`core/navigation` remplace les liens de menu de la maquette — c'est ce qui donne au client un menu réellement éditable. Trois choses à savoir :

- les styles visent `.ma-nav .wp-block-navigation-item__content` ; l'état actif est porté par `.current-menu-item` sur le `<li>` ;
- le panneau mobile (`"overlayMenu":"mobile"`) s'ouvre **blanc sur blanc** si on ne le surclasse pas. Qualifier le nav pour dépasser la règle du bloc, et forcer le fond :

```css
.wp-block-navigation.ma-nav .wp-block-navigation__responsive-container.is-menu-open {
  background-color: rgba(18, 14, 12, .985) !important;
  color: var(--craie) !important;
}
```

  La feuille qui pose le blanc n'est pas inspectable depuis la page ; c'est le seul endroit du thème où `!important` est justifié ;
- tester le panneau **déconnecté** : connecté en administrateur, des widgets d'hébergeur (le chatbot « Agent » de Hostinger, par exemple) recouvrent tout l'écran mobile et font croire que la navigation est cassée alors qu'elle est simplement dessous.

## Les cinq substitutions non évidentes

### 1 · Icônes SVG inline → masques CSS

Un SVG inline n'est pas un bloc natif, et il ne porte aucune information éditable : il n'a rien à faire dans le contenu.

```css
.carte::before {
  content: '';
  display: block;
  width: 30px; height: 30px;
  background: var(--accent);
  -webkit-mask: center / contain no-repeat;
  mask: center / contain no-repeat;
}
.carte-position::before {
  mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.5'%3E%3Cpath d='M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z'/%3E%3C/svg%3E");
}
```

Une simple flèche de bouton reste un pseudo-élément texte : `.b-plein .wp-block-button__link::after { content: "→" }`.

### 2 · Graphiques décoratifs → CSS pur

Une barre de plage de valeurs se dessine entièrement en CSS, sans balisage supplémentaire. `::before` trace la bande, `::after` porte deux dégradés radiaux qui font les poignées.

```css
.track { position: relative; height: 7px; border-radius: 99px; background: #EDF1FF }
.track::before {
  content: ""; position: absolute; inset: 0 26%; border-radius: 99px; background: #1F4BFF;
}
.track::after {
  content: ""; position: absolute; top: -3.5px; left: calc(26% - 7px); right: calc(26% - 7px);
  height: 14px;
  background:
    radial-gradient(circle 7px at 7px 7px, #fff 0 4px, #1F4BFF 4.5px 7px, transparent 7px),
    radial-gradient(circle 7px at calc(100% - 7px) 7px, #fff 0 4px, #1F4BFF 4.5px 7px, transparent 7px);
}
```

### 3 · Boutons d'onglet → paragraphes éditables

Un `<button>` n'est pas un bloc natif. Utiliser un `core/paragraph` portant la classe, et laisser le script ajouter `role="button"`, `tabindex="0"` et la gestion clavier.

### 4 · Accent typographique → `<em>` nu

`<em class="accent-serif">` est invalidé par l'éditeur : une classe sur un élément inline n'appartient à aucun format RichText enregistré. Utiliser un `<em>` nu — c'est le format italique natif, donc valide **et** éditable — et le styler par son contexte :

```css
.titre-hero em, .titre-cta em {
  font-family: var(--serif);
  font-style: italic;
  text-transform: none;
  color: var(--accent);
}
```

### 5 · Couches décoratives → pseudo-éléments

Voiles, lueurs, dégradés, sigles : tout `<div>` vide de la maquette devient un `::before`/`::after` de son parent. Un groupe vide est un bloc vide dans l'éditeur — le client le sélectionne, ne comprend pas ce que c'est, et le supprime.

## Formulaire : Contact Form 7

Aucun bloc natif de formulaire n'existe ; `core/shortcode` + Contact Form 7, avec les classes de la maquette dans le gabarit du formulaire. Trois réglages pour rester au pixel :

- fixer les lignes du textarea : `[textarea* message 40x6]` — CF7 en met 10 par défaut, soit une centaine de pixels de trop ;
- passer les champs en `display: block` : le `<span class="wpcf7-form-control-wrap">` d'enrobage garde sinon l'espace sous la ligne de base, +5 à 13 px par champ ;
- styler `.wpcf7-not-valid-tip` et `.wpcf7-response-output` aux couleurs du thème, et masquer `.wpcf7-spinner` si le design n'en veut pas.

## Interactions sans injection

Règle 7 : toutes les variantes sont présentes dans la page, le script bascule une classe. Conséquence directe — les quatre variantes d'un sélecteur sont toutes éditables dans Gutenberg, y compris celles qui ne sont pas affichées.

```js
function brancher(selecteurDeclencheur, ciblesParSelecteur) {
  const items = [...document.querySelectorAll(selecteurDeclencheur)];
  items.forEach((el, i) => {
    el.setAttribute('role', 'button');
    el.setAttribute('tabindex', '0');
    const activer = () => {
      items.forEach(x => x.classList.remove('est-actif'));
      el.classList.add('est-actif');
      Object.entries(ciblesParSelecteur).forEach(([sel, prefixe]) => {
        document.querySelectorAll(sel).forEach(c =>
          c.classList.toggle('est-actif', c.classList.contains(prefixe + (i + 1))));
      });
    };
    el.addEventListener('click', activer);
    el.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activer(); }
    });
  });
}
brancher('.opt', { '.cap': 'cap-', '.w-deal': 'w-deal-' });
```

Un détail : sélectionner par **classe**, jamais par `id` — un `core/group` ne sérialise pas d'attribut `id` de façon prévisible.

CSS correspondant :

```css
.cap { display: none }
.cap.est-actif { display: block }
```

## Neutraliser l'espacement automatique — sans détruire le vôtre

La version naïve est un piège :

```css
/* NE JAMAIS écrire ceci */
.wp-block-group > *, .wp-block-buttons { margin-block: 0 }
```

Sa spécificité de (0,1,0), placée en fin de feuille, écrase **toutes** les marges déclarées par les composants. Vécu : quatorze marges à zéro, une carte décalée remise à plat, 307 px de rythme vertical perdus — et l'œil ne l'a pas vu, seul le relevé comparatif de la porte 4 l'a montré.

La méthode, en deux temps :

1. **Couper l'espacement à la source**, dans `theme.json` :

```json
"settings": { "spacing": { "blockGap": true } },
"styles":   { "spacing": { "blockGap": "0px" } }
```

2. Si un filet de sécurité CSS reste souhaité, l'écrire **à spécificité nulle** :

```css
:where(.wp-block-group) > :where(*),
:where(.wp-block-buttons) { margin-block: 0 }
figure.wp-block-image { margin: 0 }
```

Le passage par `theme.json` n'est pas optionnel : le CSS de bloc étant imprimé après la feuille du thème, une neutralisation à spécificité nulle perd sinon contre le `blockGap` par défaut de WordPress — +24 px entre chaque bloc, partout.

## L'éditeur doit prévisualiser le site

`add_editor_style('style.css')` ne suffit pas : Gutenberg impose `position: relative` à chaque bloc pour y ancrer ses barres d'outils. Toute couche que la maquette sort du flux — photo de héros en fond, marqueur flottant — retombe dans le flux, vole la place au contenu, et les colonnes s'effondrent jusqu'au bouton rendu en texte vertical, lettre par lettre.

Prévoir une feuille dédiée, chargée uniquement dans l'éditeur :

```php
add_editor_style( array( 'style.css', 'assets/editor.css' ) );
```

```css
/* assets/editor.css — corrections propres au canevas de Gutenberg */
.hero-photo,
.hero-marqueur { position: absolute !important; }

/* pas d'en-tête fixe dans le canevas : la réserve de place saute */
.hero { min-height: 62vh !important; padding-block: 72px 52px !important; }

/* le fond du site, sinon le canevas reste blanc hors des blocs */
.editor-styles-wrapper { background: #120E0C; }
```

Et charger les polices dans l'éditeur via `enqueue_block_editor_assets`, sinon le client compose dans une typographie qui n'est pas celle du site. La porte 5 de la recette vérifie tout cela au pixel.

## Pièges de validation

- `core/image` : la classe personnalisée va **sur la figure**, pas sur l'img.
- `core/quote` : le `<cite>` se place après les blocs internes, à l'intérieur du `blockquote`.
- `core/button` : les deux classes `wp-block-button__link` et `wp-element-button` sont requises sur le lien.
- `core/list` : chaque `<li>` est un `core/list-item` avec ses propres délimiteurs.
- `core/heading` : aucun élément inline avec classe (`<em class="…">`, `<span class="…">`) — substitution n° 4.

La porte 2 de la recette est le seul moyen fiable de vérifier tout cela.
