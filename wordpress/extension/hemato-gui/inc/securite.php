<?php
/**
 * Durcissement : en-têtes HTTP, XML-RPC coupé, énumération des comptes bloquée,
 * limitation des tentatives de connexion.
 *
 * Données de santé : rien de personnel n'est rendu public par cette extension ;
 * les demandes sont privées et réservées aux rôles habilités.
 *
 * @package hemato-gui
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site relié à WordPress.com (hébergement WordPress.com ou Jetpack) : la connexion passe par
 * XML-RPC et par des requêtes REST signées, et wordpress.com affiche le site en aperçu dans un cadre.
 * Évalué à l'usage, une fois toutes les extensions chargées (Jetpack se charge après celle-ci).
 *
 * @return bool
 */
function hg_relie_wordpress_com() {
	return defined( 'IS_ATOMIC' ) || defined( 'WPCOMSH_VERSION' ) || defined( 'IS_WPCOM' ) || defined( 'JETPACK__VERSION' ) || class_exists( 'Automattic\Jetpack\Connection\Manager' );
}

// En-têtes de sécurité (le HSTS se règle chez l'hébergeur, une fois le HTTPS en place).
add_action(
	'send_headers',
	function () {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		if ( hg_relie_wordpress_com() ) {
			header( "Content-Security-Policy: frame-ancestors 'self' https://wordpress.com https://*.wordpress.com" );
		} else {
			header( 'X-Frame-Options: SAMEORIGIN' );
		}
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
	}
);

// XML-RPC coupé, sauf si le site est relié à WordPress.com : Jetpack en a besoin.
add_filter( 'xmlrpc_enabled', fn( $actif ) => hg_relie_wordpress_com() ? $actif : false );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

// Pas d'énumération des comptes : ni ?author=N (intercepté avant la redirection canonique
// de WordPress, qui révélerait l'identifiant), ni pages d'auteur, ni plan de site des comptes,
// ni /wp/v2/users pour les visiteurs.
add_action(
	'init',
	function () {
		if ( ! is_admin() && isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);
add_action(
	'template_redirect',
	function () {
		if ( is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);
add_filter( 'wp_sitemaps_add_provider', fn( $fournisseur, $nom ) => 'users' === $nom ? false : $fournisseur, 10, 2 );
add_filter(
	'rest_endpoints',
	function ( $routes ) {
		// Une requête signée par le site lui-même, vérifiée par Jetpack, garde l'accès : WordPress.com gère les comptes par là.
		$jetpack = 'Automattic\\Jetpack\\Connection\\Rest_Authentication';
		$signee  = class_exists( $jetpack ) && method_exists( $jetpack, 'is_signed_with_blog_token' ) && $jetpack::is_signed_with_blog_token();
		if ( ! is_user_logged_in() && ! $signee ) {
			unset( $routes['/wp/v2/users'], $routes['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $routes;
	}
);

// Message d'erreur de connexion neutre : ne révèle pas si l'identifiant existe.
add_filter( 'login_errors', fn() => 'Identifiant ou mot de passe incorrect.' );

// Tentatives de connexion : 5 échecs en 15 minutes bloquent l'adresse IP 15 minutes.
function hg_cle_connexion() {
	return 'hg_echecs_' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
}
add_filter(
	'authenticate',
	function ( $utilisateur ) {
		if ( (int) get_transient( hg_cle_connexion() ) >= 5 ) {
			return new WP_Error( 'hg_bloque', 'Trop de tentatives. Réessayez dans 15 minutes.' );
		}
		return $utilisateur;
	},
	99
);
add_action(
	'wp_login_failed',
	function () {
		$cle = hg_cle_connexion();
		set_transient( $cle, (int) get_transient( $cle ) + 1, 15 * MINUTE_IN_SECONDS );
	},
	1
);
add_action( 'wp_login', fn() => delete_transient( hg_cle_connexion() ) );
