<?php
/**
 * Gabarits HTML des shortcodes.
 *
 * Chaque fichier de gabarits/ est extrait de la maquette validée par
 * outils/assembler.py : l'extension sert exactement le HTML qui a été mesuré
 * au pixel. Seules les parties dynamiques (liens, jetons, dates) sont réécrites.
 *
 * @package hemato-gui
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lit un gabarit et réécrit les liens de la maquette (« page.html#ancre ») en permaliens.
 *
 * @param string $nom Nom du fichier, sans extension.
 * @return string
 */
function hg_gabarit( $nom ) {
	$fichier = HG_DIR . 'gabarits/' . sanitize_file_name( $nom ) . '.html';
	if ( ! is_readable( $fichier ) ) {
		return '';
	}
	hg_besoin_sprite();
	return hg_liens( (string) file_get_contents( $fichier ) );
}

/**
 * « drepanocytose.html#urgence » → https://site/drepanocytose/#urgence ; index.html → accueil.
 *
 * @param string $html HTML de la maquette.
 * @return string
 */
function hg_liens( $html ) {
	return preg_replace_callback(
		'/\bhref="([\w-]+)\.html([^"]*)"/',
		function ( $m ) {
			$url = 'index' === $m[1] ? home_url( '/' ) : home_url( '/' . $m[1] . '/' );
			return 'href="' . esc_url( $url . $m[2] ) . '"';
		},
		$html
	);
}

/**
 * Les gabarits utilisent le sprite d'icônes de la maquette (<svg><use href="#i-…">).
 * Il n'est imprimé qu'une fois, et seulement sur les pages qui en ont besoin.
 */
function hg_besoin_sprite() {
	static $inscrit = false;
	if ( $inscrit ) {
		return;
	}
	$inscrit = true;
	add_action(
		'wp_footer',
		function () {
			$sprite = HG_DIR . 'assets/sprite.svg';
			if ( is_readable( $sprite ) ) {
				echo file_get_contents( $sprite ); // phpcs:ignore WordPress.Security.EscapeOutput -- fichier de l'extension.
			}
		},
		1
	);
}

/**
 * Transforme un <form … onsubmit="return false"> de la maquette en vrai formulaire.
 *
 * @param string $html    Gabarit.
 * @param string $classe  Classe du formulaire visé.
 * @param string $action  URL d'envoi.
 * @param string $champs  Champs cachés (nonce, action…).
 * @return string
 */
function hg_brancher_formulaire( $html, $classe, $action, $champs ) {
	$motif = '/<form class="' . preg_quote( $classe, '/' ) . '" onsubmit="return false">/';
	$ouverture = '<form class="' . esc_attr( $classe ) . '" method="post" action="' . esc_url( $action ) . '">' . $champs;
	return preg_replace( $motif, $ouverture, $html, 1 );
}

/**
 * Champ piège invisible : les robots le remplissent, les humains ne le voient pas.
 *
 * @return string
 */
function hg_champ_piege() {
	return '<p class="screen-reader-text" aria-hidden="true"><label>Site web<input type="text" name="site_web" value="" tabindex="-1" autocomplete="off"></label></p>';
}

/**
 * Message de retour après un envoi (paramètre ?hg=…), affiché dans le formulaire.
 *
 * @param string $html     Gabarit.
 * @param string $classe   Classe du formulaire.
 * @param string $message  Texte.
 * @param bool   $erreur   Message d'erreur ?
 * @return string
 */
function hg_message_formulaire( $html, $classe, $message, $erreur = false ) {
	$bloc = '<p class="formulaire-retour' . ( $erreur ? ' formulaire-retour-erreur' : '' ) . '" role="status">' . esc_html( $message ) . '</p>';
	return preg_replace( '/(<form class="' . preg_quote( $classe, '/' ) . '"[^>]*>)/', '$1' . $bloc, $html, 1 );
}
