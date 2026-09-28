<?php
/**
 * Porte 1 : dans le contenu enregistré, aucun bloc core/html, aucun bloc hors cœur,
 * et toutes les images liées à la médiathèque (pages, articles, parties du thème).
 * Le contrôle lui-même est hg_dep_porte1() (deploiement.php), repris par l'installation en ligne.
 *
 *   php porte1.php <racine-wp> <url-du-site>
 */
list( , $racine, $url ) = array_pad( $argv, 3, '' );
$hote                   = parse_url( $url );
$_SERVER['HTTP_HOST']   = $hote['host'] . ( isset( $hote['port'] ) ? ':' . $hote['port'] : '' );
$_SERVER['REQUEST_URI'] = '/';
require rtrim( $racine, '/' ) . '/wp-load.php';
require __DIR__ . '/deploiement.php';

$r = hg_dep_porte1();
foreach ( $r['detail'] as $d ) {
	printf( "%-6s %-36s blocs %4d | core/html %d | hors cœur %d | images liées %d/%d\n", $d['type'], $d['nom'], $d['blocs'], $d['html'], $d['hors'], $d['liees'], $d['images'] );
}
printf( "PORTE 1 : %s — %d contenus · core/html %d · hors cœur %d · images liées %d/%d\n", $r['ok'] ? 'franchie' : 'ÉCHEC', $r['contenus'], $r['html'], $r['hors'], $r['liees'], $r['images'] );
exit( $r['ok'] ? 0 : 1 );
