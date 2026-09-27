<?php
/**
 * [hg_outil type="nfs|formule|clairance|ipssr|mentzer"] — calculateurs d'aide à la décision.
 *
 * Le calcul se fait dans le navigateur (outils.js) : aucune donnée saisie
 * n'est envoyée ni conservée.
 *
 * @package hemato-gui
 */

defined( 'ABSPATH' ) || exit;

add_shortcode(
	'hg_outil',
	function ( $attributs ) {
		$a    = shortcode_atts( array( 'type' => '' ), $attributs, 'hg_outil' );
		$type = sanitize_key( $a['type'] );
		if ( ! in_array( $type, array( 'nfs', 'formule', 'clairance', 'ipssr', 'mentzer' ), true ) ) {
			return '';
		}
		wp_enqueue_script( 'hg-outils', HG_URL . 'assets/js/outils.js', array(), HG_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		return hg_gabarit( 'outil-' . $type );
	}
);
