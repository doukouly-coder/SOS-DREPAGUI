<?php
/**
 * Porte 1 : dans le contenu enregistré, aucun bloc core/html, aucun bloc hors cœur,
 * et toutes les images liées à la médiathèque (pages, articles, parties du thème).
 *
 *   php porte1.php <racine-wp> <url-du-site>
 */
list( , $racine, $url ) = array_pad( $argv, 3, '' );
$hote                   = parse_url( $url );
$_SERVER['HTTP_HOST']   = $hote['host'] . ( isset( $hote['port'] ) ? ':' . $hote['port'] : '' );
$_SERVER['REQUEST_URI'] = '/';
require rtrim( $racine, '/' ) . '/wp-load.php';

function hg_aplatir( $blocs ) {
	$r = array();
	foreach ( $blocs as $b ) {
		if ( $b['blockName'] ) {
			$r[] = $b;
			$r   = array_merge( $r, hg_aplatir( $b['innerBlocks'] ) );
		}
	}
	return $r;
}
$html = $hors = $images = $liees = 0;
$contenus = get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'type title', 'order' => 'ASC' ) );
foreach ( $contenus as $p ) {
	$tous = hg_aplatir( parse_blocks( $p->post_content ) );
	$h    = count( array_filter( $tous, fn( $b ) => 'core/html' === $b['blockName'] ) );
	$e    = count( array_filter( $tous, fn( $b ) => ! str_starts_with( $b['blockName'], 'core/' ) ) );
	$img  = array_filter( $tous, fn( $b ) => 'core/image' === $b['blockName'] );
	$l    = count( array_filter( $img, fn( $b ) => ! empty( $b['attrs']['id'] ) ) );
	printf( "%-5s %-36s blocs %4d | core/html %d | hors cœur %d | images liées %d/%d\n", $p->post_type, $p->post_name, count( $tous ), $h, $e, $l, count( $img ) );
	$html += $h; $hors += $e; $images += count( $img ); $liees += $l;
}
foreach ( array( 'header', 'footer' ) as $partie ) {
	$t    = get_block_template( get_stylesheet() . '//' . $partie, 'wp_template_part' );
	$tous = hg_aplatir( parse_blocks( $t->content ) );
	$html += count( array_filter( $tous, fn( $b ) => 'core/html' === $b['blockName'] ) );
	$hors += count( array_filter( $tous, fn( $b ) => ! str_starts_with( $b['blockName'], 'core/' ) ) );
}
$ok = 0 === $html && 0 === $hors && $images === $liees;
printf( "PORTE 1 : %s — %d contenus · core/html %d · hors cœur %d · images liées %d/%d\n", $ok ? 'franchie' : 'ÉCHEC', count( $contenus ), $html, $hors, $liees, $images );
