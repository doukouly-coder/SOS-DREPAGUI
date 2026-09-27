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

// En-têtes de sécurité (le HSTS se règle chez l'hébergeur, une fois le HTTPS en place).
add_action(
	'send_headers',
	function () {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
	}
);

add_filter( 'xmlrpc_enabled', '__return_false' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

// Pas d'énumération des comptes : ni ?author=N, ni /wp/v2/users pour les visiteurs.
add_action(
	'template_redirect',
	function () {
		if ( is_author() || isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
);
add_filter(
	'rest_endpoints',
	function ( $routes ) {
		if ( ! is_user_logged_in() ) {
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
