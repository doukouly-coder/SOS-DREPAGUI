<?php
/**
 * Fonctions du thème.
 *
 * @package mon-theme
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
		// editor.css corrige ce que le canevas de Gutenberg casse (position:relative
		// forcé sur chaque bloc) : sans lui, l'aperçu s'effondre. Porte 5 de la recette.
		add_editor_style( array( 'style.css', 'assets/editor.css' ) );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$version = wp_get_theme()->get( 'Version' );

		// Remplacer par la ou les familles retenues au plan de design.
		wp_enqueue_style(
			'mon-theme-polices',
			'https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);

		wp_enqueue_style( 'mon-theme', get_stylesheet_uri(), array( 'mon-theme-polices' ), $version );

		// Ne jamais enqueue un fichier absent : 404 sur chaque page sinon.
		if ( file_exists( get_theme_file_path( 'assets/app.js' ) ) ) {
			wp_enqueue_script(
				'mon-theme',
				get_theme_file_uri( 'assets/app.js' ),
				array(),
				$version,
				array( 'strategy' => 'defer' )
			);
		}
	}
);

/**
 * Les polices doivent aussi être chargées dans l'éditeur, sinon le client
 * compose sur une typographie qui n'est pas celle du site.
 */
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_style(
			'mon-theme-polices-editeur',
			'https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);
	}
);

add_action(
	'init',
	function () {
		register_block_pattern_category(
			'mon-theme',
			array( 'label' => __( 'Mon thème', 'mon-theme' ) )
		);
	}
);
