<?php
/**
 * Thème HEMATO GUI — chargement des styles et du script de la maquette validée.
 *
 * Les feuilles sont copiées telles quelles depuis la maquette (site/assets) par
 * outils/wordpress/construire.py : la maquette reste la source, le thème la livre.
 *
 * @package hemato-gui
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		// Le canevas de l'éditeur reçoit exactement les feuilles du site, plus editor.css
		// qui corrige ce que Gutenberg casse (porte 5 de la recette).
		add_editor_style(
			array(
				'assets/css/icones.css',
				'assets/css/styles.css',
				'assets/css/pages.css',
				'assets/css/wp.css',
				'assets/css/editeur-calques.css',
				'assets/editor.css',
			)
		);
		// Contenus en français, rien à traduire côté thème, mais le domaine reste déclaré.
		load_theme_textdomain( 'hemato-gui' );
	}
);

/**
 * Feuilles du site. Priorité 20 : elles passent après les styles globaux de
 * WordPress 6.6 (`:root :where(.is-layout-flow) > *`, spécificité 0,1,0), sinon
 * ces derniers remettent à zéro les marges des composants.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$version = wp_get_theme()->get( 'Version' );
		$base    = get_theme_file_uri( 'assets/css/' );

		wp_enqueue_style( 'hg-icones', $base . 'icones.css', array(), $version );
		wp_enqueue_style( 'hg-styles', $base . 'styles.css', array( 'hg-icones' ), $version );
		wp_enqueue_style( 'hg-pages', $base . 'pages.css', array( 'hg-styles' ), $version );
		wp_enqueue_style( 'hg-wp', $base . 'wp.css', array( 'hg-pages' ), $version );

		wp_enqueue_script(
			'hg-app',
			get_theme_file_uri( 'assets/js/app.js' ),
			array(),
			$version,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	},
	20
);

/* La police est déclarée dans theme.json (fontFace) : WordPress l'imprime aussi dans l'éditeur. */

// Préchargement de la police principale : le texte s'affiche sans saut.
add_action(
	'wp_head',
	function () {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( get_theme_file_uri( 'assets/fonts/inter-latin-opsz-normal.woff2' ) )
		);
	},
	1
);

// Lien d'évitement vers le contenu, comme dans la maquette.
add_action(
	'wp_body_open',
	function () {
		echo '<a class="evitement" href="#contenu">' . esc_html__( 'Aller au contenu', 'hemato-gui' ) . '</a>';
	}
);

// Pas d'émojis convertis en images : inutile ici, et une requête de moins.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

// Le site public est entièrement en français, même si l'administration est dans une autre
// langue ou si le paquet de langue n'est pas installé : lecteurs d'écran et moteurs le savent.
add_filter(
	'language_attributes',
	function ( $attributs ) {
		return is_admin() ? $attributs : preg_replace( '/lang="[^"]*"/', 'lang="fr-FR"', $attributs );
	}
);
