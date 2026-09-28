<?php
/**
 * Déploiement sur un WordPress accessible en local (php-cli + wp-load.php).
 *
 *   php deployer.php <racine-wp> <url-du-site> installer
 *   php deployer.php <racine-wp> <url-du-site> medias <sortie.json>
 *   php deployer.php <racine-wp> <url-du-site> pages  <dossier-contenu>
 *
 * Les étapes elles-mêmes sont dans deploiement.php, partagé avec le déploiement
 * en ligne (deployer-distant.py) : le site local est la répétition exacte du site réel.
 */

list( , $racine, $url, $etape ) = array_pad( $argv, 4, '' );
if ( ! $racine || ! $url || ! $etape ) {
	fwrite( STDERR, "usage : php deployer.php <racine-wp> <url> installer|medias|pages [arg]\n" );
	exit( 1 );
}
$hote                   = parse_url( $url );
$_SERVER['HTTP_HOST']   = $hote['host'] . ( isset( $hote['port'] ) ? ':' . $hote['port'] : '' );
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_ADMIN', true );
require rtrim( $racine, '/' ) . '/wp-load.php';
require __DIR__ . '/deploiement.php';
wp_set_current_user( 1 );

$depot = dirname( __DIR__, 2 );

/** Arrête le script sur un compte rendu en erreur. */
function hg_cli_verifier( $r ) {
	if ( isset( $r['erreur'] ) ) {
		fwrite( STDERR, wp_json_encode( $r, JSON_UNESCAPED_UNICODE ) . "\n" );
		exit( 1 );
	}
	return $r;
}

switch ( $etape ) {
	case 'installer':
		$r = hg_cli_verifier( hg_dep_installer( $depot . '/wordpress/theme/hemato-gui', $depot . '/wordpress/extension/hemato-gui' ) );
		echo 'thème actif : ', $r['theme'], ' · extension : ', $r['extension'], ' · ', $r['langue'], "\n";
		break;

	case 'medias':
		$sortie = $argv[4] ?? 'medias.json';
		$r      = hg_dep_medias( $depot . '/site/assets/img' );
		foreach ( $r['erreurs'] as $image => $message ) {
			fwrite( STDERR, "$image : $message\n" );
		}
		file_put_contents( $sortie, json_encode( $r['carte'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		echo count( $r['carte'] ), " images en médiathèque -> $sortie\n";
		break;

	case 'pages':
		$r = hg_cli_verifier( hg_dep_pages( $argv[4] ?? '' ) );
		foreach ( $r['erreurs'] as $slug => $message ) {
			fwrite( STDERR, "$slug : $message\n" );
		}
		echo $r['pages'], ' pages et ', $r['articles'], ' articles publiés · accueil = page ', $r['accueil'], ' · ', $r['menu'], "\n";
		hg_dep_purger();
		break;
}
