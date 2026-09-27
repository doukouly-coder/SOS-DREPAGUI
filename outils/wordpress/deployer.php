<?php
/**
 * Déploiement sur un WordPress accessible en local (php-cli + wp-load.php).
 *
 *   php deployer.php <racine-wp> <url-du-site> installer
 *   php deployer.php <racine-wp> <url-du-site> medias <sortie.json>
 *   php deployer.php <racine-wp> <url-du-site> pages  <dossier-contenu>
 *
 * Idempotent : relancé, il met à jour au lieu de dupliquer (repères en méta).
 * Pour le site en ligne (phase 5), les mêmes étapes passent par l'API de l'hébergement.
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
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_set_current_user( 1 );

$depot = dirname( __DIR__, 2 );

/** Copie récursive. */
function hg_copier( $source, $cible ) {
	if ( is_dir( $cible ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cible, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? rmdir( $f ) : unlink( $f );
		}
		rmdir( $cible );
	}
	mkdir( $cible, 0775, true );
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $it as $f ) {
		$dest = $cible . '/' . $it->getSubPathName();
		$f->isDir() ? @mkdir( $dest, 0775, true ) : copy( $f, $dest );
	}
}

switch ( $etape ) {
	case 'installer':
		hg_copier( $depot . '/wordpress/theme/hemato-gui', get_theme_root() . '/hemato-gui' );
		hg_copier( $depot . '/wordpress/extension/hemato-gui', WP_PLUGIN_DIR . '/hemato-gui' );
		wp_clean_themes_cache();
		switch_theme( 'hemato-gui' );
		$r = activate_plugin( 'hemato-gui/hemato-gui.php' );
		if ( is_wp_error( $r ) ) {
			fwrite( STDERR, $r->get_error_message() . "\n" );
			exit( 1 );
		}
		hg_activation();
		update_option( 'blogname', 'HEMATO GUI' );
		update_option( 'blogdescription', 'Informer, former et traiter les maladies hématologiques' );
		update_option( 'WPLANG', 'fr_FR' );
		update_option( 'timezone_string', 'Africa/Conakry' );
		update_option( 'date_format', 'j F Y' );
		update_option( 'users_can_register', 0 );
		update_option( 'default_comment_status', 'closed' );
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules( false );
		echo 'thème actif : ', get_stylesheet(), ' · extension : ', is_plugin_active( 'hemato-gui/hemato-gui.php' ) ? 'active' : 'inactive', "\n";
		break;

	case 'medias':
		$sortie = $argv[4] ?? 'medias.json';
		$carte  = array();
		$images = glob( $depot . '/site/assets/img/*/*.{webp,jpg,png}', GLOB_BRACE );
		foreach ( $images as $chemin ) {
			$relatif = substr( $chemin, strlen( $depot . '/site/' ) );
			$existe  = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'meta_key' => '_hg_source', 'meta_value' => $relatif ) );
			if ( $existe ) {
				$id = $existe[0];
			} else {
				$tmp = wp_tempnam( basename( $chemin ) );
				copy( $chemin, $tmp );
				$id = media_handle_sideload( array( 'name' => basename( $chemin ), 'tmp_name' => $tmp ), 0, hg_titre_image( $relatif ) );
				if ( is_wp_error( $id ) ) {
					fwrite( STDERR, $relatif . ' : ' . $id->get_error_message() . "\n" );
					continue;
				}
				update_post_meta( $id, '_hg_source', $relatif );
			}
			$carte[ $relatif ] = array( 'id' => $id, 'url' => wp_get_attachment_url( $id ) );
		}
		file_put_contents( $sortie, json_encode( $carte, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		echo count( $carte ), " images en médiathèque -> $sortie\n";
		break;

	case 'pages':
		$dossier = rtrim( $argv[4] ?? '', '/' );
		$ids     = array();
		foreach ( glob( $dossier . '/*.json' ) as $meta_fichier ) {
			$meta    = json_decode( file_get_contents( $meta_fichier ), true );
			$contenu = file_get_contents( substr( $meta_fichier, 0, -5 ) . '.html' );
			$existe  = get_page_by_path( $meta['slug'], OBJECT, 'page' );
			$donnees = array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $meta['titre'],
				'post_name'    => $meta['slug'],
				'post_content' => wp_slash( $contenu ),
			);
			if ( $existe ) {
				$donnees['ID'] = $existe->ID;
			}
			$id = wp_insert_post( $donnees, true );
			if ( is_wp_error( $id ) ) {
				fwrite( STDERR, $meta['slug'] . ' : ' . $id->get_error_message() . "\n" );
				continue;
			}
			update_post_meta( $id, 'hg_titre_seo', $meta['titre_seo'] );
			update_post_meta( $id, 'hg_description', $meta['description'] );
			$ids[ $meta['slug'] ] = $id;
		}
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['accueil'] );

		// Menu principal (wp_navigation) : les liens pointent vers les pages par identifiant,
		// d'où l'état « page courante » ; le bloc de l'en-tête le reprend par défaut.
		$lien  = fn( $libelle, $slug, $desc = '' ) => sprintf(
			'<!-- wp:navigation-link %s /-->',
			wp_json_encode( array_filter( array( 'label' => $libelle, 'type' => 'page', 'id' => $ids[ $slug ], 'url' => get_permalink( $ids[ $slug ] ), 'kind' => 'post-type', 'description' => $desc ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
		$menu  = $lien( 'Accueil', 'accueil' ) . "\n";
		$menu .= '<!-- wp:navigation-submenu ' . wp_json_encode( array( 'label' => 'Hématologie', 'type' => 'custom', 'url' => home_url( '/#domaines' ), 'kind' => 'custom' ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . " -->\n";
		foreach ( array(
			array( 'Drépanocytose', 'drepanocytose', 'Crises, prévention, traitements' ),
			array( 'Hémophilie', 'hemophilie', 'Et maladies hémorragiques' ),
			array( 'Cancers du sang', 'cancers-du-sang', 'Leucémies, lymphomes, myélome' ),
			array( 'Hémostase et thrombose', 'hemostase', 'Comprendre son bilan' ),
			array( 'Diagnostic', 'diagnostic', 'Vos examens expliqués' ),
			array( 'Outils hématologiques', 'outils', 'Calculateurs pour les soignants' ),
		) as $l ) {
			$menu .= $lien( ...$l ) . "\n";
		}
		$menu .= "<!-- /wp:navigation-submenu -->\n";
		foreach ( array( array( 'Patients', 'espace-patient' ), array( 'Professionnels', 'espace-pro' ), array( 'eBooks', 'ebooks' ), array( 'Actualités', 'actualites' ), array( 'À propos', 'a-propos' ), array( 'Contact', 'contact' ) ) as $l ) {
			$menu .= $lien( ...$l ) . "\n";
		}
		$wa    = 'https://wa.me/224628733143?text=Bonjour%20HEMATO%20GUI%2C%20je%20souhaite%20obtenir%20des%20informations%20concernant%20une%20consultation%20d%E2%80%99h%C3%A9matologie.';
		$menu .= '<!-- wp:buttons {"className":"menu-boutons"} -->' . "\n" . '<div class="wp-block-buttons menu-boutons">'
			. '<!-- wp:button {"className":"btn btn-rouge btn-grand b-ico ico-calendrier"} -->' . "\n"
			. '<div class="wp-block-button btn btn-rouge btn-grand b-ico ico-calendrier"><a class="wp-block-button__link wp-element-button" href="' . esc_url( get_permalink( $ids['rendez-vous'] ) ) . '">Prendre rendez-vous</a></div>' . "\n<!-- /wp:button -->\n\n"
			. '<!-- wp:button {"className":"btn btn-wa btn-grand b-ico ico-whatsapp","linkTarget":"_blank","rel":"noopener"} -->' . "\n"
			. '<div class="wp-block-button btn btn-wa btn-grand b-ico ico-whatsapp"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">WhatsApp</a></div>' . "\n<!-- /wp:button -->"
			. "</div>\n<!-- /wp:buttons -->";
		$nav_existante = get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'title' => 'Menu principal', 'fields' => 'ids' ) );
		wp_insert_post(
			array(
				'ID'           => $nav_existante[0] ?? 0,
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Menu principal',
				'post_content' => wp_slash( $menu ),
			)
		);

		// Contenu d'exemple de l'installation : retiré.
		foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page', 'exemple-de-page' => 'page' ) as $slug => $type ) {
			$p = get_page_by_path( $slug, OBJECT, $type );
			if ( $p ) {
				wp_delete_post( $p->ID, true );
			}
		}
		flush_rewrite_rules( false );
		echo count( $ids ), " pages publiées · accueil = page ", $ids['accueil'], " · menu principal à jour\n";
		break;
}

/**
 * Titre d'image lisible pour la médiathèque (« rendus/globule-seul.webp » → « Globule seul »).
 *
 * @param string $relatif Chemin.
 * @return string
 */
function hg_titre_image( $relatif ) {
	return ucfirst( str_replace( '-', ' ', pathinfo( $relatif, PATHINFO_FILENAME ) ) );
}
