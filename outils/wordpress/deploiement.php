<?php
/**
 * Étapes de déploiement, communes au WordPress local (deployer.php, php-cli)
 * et au site en ligne (deployer-distant.py, via l'ability novamira/execute-php).
 *
 * Chaque fonction reçoit ses chemins en paramètre et renvoie un tableau de compte rendu :
 * rien ici ne suppose d'où viennent les fichiers ni qui l'appelle.
 * Idempotent : relancé, tout se met à jour au lieu de se dupliquer (repères en méta).
 *
 * @package hemato-gui
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Supprime un dossier et son contenu.
 *
 * @param string $dossier Chemin.
 */
function hg_dep_supprimer( $dossier ) {
	if ( ! is_dir( $dossier ) ) {
		return;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dossier, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		$f->isDir() ? rmdir( $f ) : unlink( $f );
	}
	rmdir( $dossier );
}

/**
 * Remplace entièrement $cible par une copie de $source : aucun ancien fichier
 * (gabarit, partie, motif) ne survit au déploiement.
 *
 * @param string $source Dossier source.
 * @param string $cible  Dossier cible.
 * @return string[] Fichiers qui n'ont pas pu être copiés.
 */
function hg_dep_copier( $source, $cible ) {
	hg_dep_supprimer( $cible );
	wp_mkdir_p( $cible );
	$echecs = array();
	$it     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $it as $f ) {
		$dest = $cible . '/' . $it->getSubPathName();
		if ( $f->isDir() ) {
			wp_mkdir_p( $dest );
		} elseif ( ! copy( $f, $dest ) ) {
			$echecs[] = $it->getSubPathName();
		}
	}
	return $echecs;
}

/**
 * Décompresse une archive (ZipArchive, sinon l'API de fichiers de WordPress).
 *
 * @param string $archive Fichier zip.
 * @param string $cible   Dossier de destination.
 * @return true|WP_Error
 */
function hg_dep_deballer( $archive, $cible ) {
	wp_mkdir_p( $cible );
	if ( class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $archive ) ) {
			return new WP_Error( 'hg_zip', 'archive illisible : ' . basename( $archive ) );
		}
		$ok = $zip->extractTo( $cible );
		$zip->close();
		return $ok ? true : new WP_Error( 'hg_zip', 'décompression impossible dans ' . $cible );
	}
	WP_Filesystem();
	return unzip_file( $archive, $cible );
}

/**
 * Diagnostic en lecture seule : ce qu'il faut savoir avant d'écrire quoi que ce soit.
 *
 * @return array
 */
function hg_dep_diagnostic() {
	global $wp_version;
	$actives = (array) get_option( 'active_plugins', array() );
	return array(
		'site'            => home_url( '/' ),
		'wordpress'       => $wp_version,
		'php'             => PHP_VERSION,
		'multisite'       => is_multisite(),
		'theme_actif'     => get_stylesheet(),
		'langue'          => get_locale(),
		'permaliens'      => get_option( 'permalink_structure' ),
		'accueil'         => get_option( 'show_on_front' ) . ' ' . get_option( 'page_on_front' ),
		'extensions'      => array_values( $actives ),
		'zip'             => class_exists( 'ZipArchive' ) ? 'ZipArchive' : 'unzip_file',
		'ecriture_themes' => wp_is_writable( get_theme_root() ),
		'ecriture_ext'    => wp_is_writable( WP_PLUGIN_DIR ),
		'ecriture_upload' => wp_is_writable( wp_upload_dir()['basedir'] ),
		'methode_fichier' => get_filesystem_method(),
		'nb_pages'        => (int) wp_count_posts( 'page' )->publish,
		'nb_articles'     => (int) wp_count_posts( 'post' )->publish,
		'nb_medias'       => (int) array_sum( (array) wp_count_attachments() ),
		'utilisateur'     => wp_get_current_user()->user_login,
		'peut_tout'       => current_user_can( 'manage_options' ) && current_user_can( 'install_themes' ) && current_user_can( 'activate_plugins' ),
	);
}

/**
 * Contenus du site existant (pas ceux d'un déploiement précédent) qui portent un slug
 * que le déploiement va écrire : ils seront réécrits. Lecture seule.
 *
 * @param string[] $slugs Slugs des pages et articles du site HEMATO GUI.
 * @return array[]
 */
function hg_dep_collisions( $slugs ) {
	$collisions = array();
	foreach ( get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'post_name__in' => $slugs, 'numberposts' => -1 ) ) as $c ) {
		if ( ! get_post_meta( $c->ID, '_hg_deploye', true ) ) {
			$collisions[] = array(
				'id'     => $c->ID,
				'type'   => $c->post_type,
				'slug'   => $c->post_name,
				'titre'  => $c->post_title,
				'statut' => $c->post_status,
				'taille' => strlen( $c->post_content ),
			);
		}
	}
	return $collisions;
}

/**
 * Étape 0 : sauvegarde de l'existant, avant toute écriture.
 *
 * La première sauvegarde est gardée en base (option non chargée automatiquement, jamais exposée
 * publiquement, contrairement à uploads/) ; les redéploiements ne l'écrasent pas.
 * Les révisions WordPress gardent de leur côté l'ancien contenu d'une page réécrite.
 *
 * @param string[] $slugs Slugs des pages et articles que le déploiement va écrire.
 * @return array Sauvegarde complète, plus les collisions de slug.
 */
function hg_dep_sauvegarde( $slugs ) {
	global $wpdb;
	$contenus   = $wpdb->get_results(
		"SELECT ID, post_name, post_title, post_status, post_type, post_date, post_content
		 FROM {$wpdb->posts} WHERE post_type IN ('page','post','wp_navigation','wp_template','wp_template_part')
		 AND post_status NOT IN ('auto-draft','revision','trash')",
		ARRAY_A
	);
	$collisions = hg_dep_collisions( $slugs );
	$sauvegarde = array(
		'date'           => current_time( 'mysql' ),
		'site'           => home_url( '/' ),
		'theme_avant'    => get_stylesheet(),
		'extensions'     => (array) get_option( 'active_plugins', array() ),
		'show_on_front'  => get_option( 'show_on_front' ),
		'page_on_front'  => get_option( 'page_on_front' ),
		'page_for_posts' => get_option( 'page_for_posts' ),
		'permaliens'     => get_option( 'permalink_structure' ),
		'titre_site'     => get_option( 'blogname' ),
		'slogan'         => get_option( 'blogdescription' ),
		'langue'         => get_option( 'WPLANG' ),
		'fuseau'         => get_option( 'timezone_string' ),
		'contenus'       => $contenus,
		'collisions'     => $collisions,
	);
	$deja = get_option( 'hg_sauvegarde_avant_theme' );
	if ( ! $deja ) {
		add_option( 'hg_sauvegarde_avant_theme', $sauvegarde, '', false );
	}
	$sauvegarde['en_base'] = $deja ? 'sauvegarde initiale du ' . $deja['date'] . ' conservée' : 'enregistrée (option hg_sauvegarde_avant_theme)';
	return $sauvegarde;
}

/**
 * Paquet de langue français, pour que l'administration soit en français.
 *
 * @return string
 */
function hg_dep_langue() {
	update_option( 'WPLANG', 'fr_FR' );
	if ( in_array( 'fr_FR', get_available_languages(), true ) ) {
		return 'fr_FR déjà installé';
	}
	require_once ABSPATH . 'wp-admin/includes/translation-install.php';
	ob_start();
	$r = wp_can_install_language_pack() ? wp_download_language_pack( 'fr_FR' ) : false;
	ob_end_clean();
	return $r ? 'fr_FR installé' : 'paquet fr_FR non téléchargé (le site public est en français quoi qu’il arrive)';
}

/**
 * Étapes 1-2 : thème et extension en place, activés, réglages du site.
 *
 * @param string $theme     Dossier source du thème (slug hemato-gui).
 * @param string $extension Dossier source de l'extension (slug hemato-gui).
 * @return array
 */
function hg_dep_installer( $theme, $extension ) {
	$echecs = array_merge(
		hg_dep_copier( $theme, get_theme_root() . '/hemato-gui' ),
		hg_dep_copier( $extension, WP_PLUGIN_DIR . '/hemato-gui' )
	);
	if ( $echecs ) {
		return array( 'erreur' => 'copie incomplète', 'fichiers' => array_slice( $echecs, 0, 20 ) );
	}
	wp_clean_themes_cache();
	switch_theme( 'hemato-gui' );
	$r = activate_plugin( 'hemato-gui/hemato-gui.php' );
	if ( is_wp_error( $r ) ) {
		return array( 'erreur' => 'extension : ' . $r->get_error_message() );
	}
	if ( function_exists( 'hg_activation' ) ) {
		hg_activation();
	}
	update_option( 'blogname', 'HEMATO GUI' );
	update_option( 'blogdescription', 'Informer, former et traiter les maladies hématologiques' );
	update_option( 'timezone_string', 'Africa/Conakry' );
	update_option( 'date_format', 'j F Y' );
	update_option( 'users_can_register', 0 );
	update_option( 'default_comment_status', 'closed' );
	$langue = hg_dep_langue();
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '/%postname%/' );
	$wp_rewrite->flush_rules( false );
	return array(
		'theme'     => get_stylesheet(),
		'version'   => wp_get_theme( 'hemato-gui' )->get( 'Version' ),
		'extension' => is_plugin_active( 'hemato-gui/hemato-gui.php' ) ? 'active' : 'inactive',
		'langue'    => $langue,
	);
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

/**
 * Étape 3 : visuels en médiathèque, repérés par leur chemin dans la maquette (méta _hg_source).
 * Par lots : $limite nouveaux imports au plus par appel, pour tenir dans le délai d'exécution
 * d'un hébergement mutualisé. Relancer tant que « restant » n'est pas nul.
 *
 * @param string $dossier Dossier contenant les sous-dossiers d'images (équivalent de site/assets/img).
 * @param int    $limite  Nombre maximal de nouveaux imports (0 : sans limite).
 * @return array { carte: { "assets/img/…": { id, url } }, importees, restant, erreurs }
 */
function hg_dep_medias( $dossier, $limite = 0 ) {
	$carte    = array();
	$erreurs  = array();
	$nouveaux = 0;
	$restant  = 0;
	$images   = array_filter( (array) glob( rtrim( $dossier, '/' ) . '/*/*' ), fn( $f ) => preg_match( '/\.(webp|jpe?g|png)$/i', $f ) );
	sort( $images );
	foreach ( $images as $chemin ) {
		$relatif = 'assets/img/' . basename( dirname( $chemin ) ) . '/' . basename( $chemin );
		$existe  = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'meta_key' => '_hg_source', 'meta_value' => $relatif ) );
		if ( $existe ) {
			$id = $existe[0];
		} elseif ( $limite && $nouveaux >= $limite ) {
			$restant++;
			continue;
		} else {
			$tmp = wp_tempnam( basename( $chemin ) );
			copy( $chemin, $tmp );
			$id = media_handle_sideload( array( 'name' => basename( $chemin ), 'tmp_name' => $tmp ), 0, hg_titre_image( $relatif ), array( 'post_name' => 'visuel-' . sanitize_title( pathinfo( $chemin, PATHINFO_FILENAME ) ) ) );
			if ( is_wp_error( $id ) ) {
				$erreurs[ $relatif ] = $id->get_error_message();
				continue;
			}
			update_post_meta( $id, '_hg_source', $relatif );
			$nouveaux++;
		}
		$carte[ $relatif ] = array( 'id' => $id, 'url' => wp_get_attachment_url( $id ) );
	}
	return array( 'carte' => $carte, 'importees' => $nouveaux, 'restant' => $restant, 'erreurs' => $erreurs );
}

/**
 * Contenu existant d'un type donné, par slug. get_page_by_path() ne convient pas : il cherche
 * aussi parmi les pièces jointes, et renverrait le visuel « drepanocytose » pour la page du même
 * nom — que wp_insert_post() transformerait alors en page.
 *
 * @param string $slug Slug.
 * @param string $type Type de contenu.
 * @return int 0 si absent.
 */
function hg_dep_existant( $slug, $type ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND post_status NOT IN ('trash','auto-draft','inherit') ORDER BY ID LIMIT 1", $slug, $type ) );
}

/**
 * Une page et une pièce jointe sans parent ne peuvent pas partager un slug : WordPress donnerait
 * « drepanocytose-2 » à la page. La pièce jointe est renommée, la page garde son adresse.
 *
 * @param string $slug Slug voulu pour la page.
 */
function hg_dep_liberer_slug( $slug ) {
	global $wpdb;
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'attachment'", $slug ) ) as $id ) {
		wp_update_post( array( 'ID' => (int) $id, 'post_name' => 'visuel-' . $slug ) );
	}
}

/**
 * Étapes 5-6 : pages, articles, page d'accueil statique, menu principal, contenu d'exemple retiré.
 *
 * @param string        $dossier     Dossier produit par contenu.py (<slug>.html + <slug>.json, et articles/).
 * @param callable|null $transformer Appliqué à chaque contenu avant écriture (contenu modèle à remplir).
 * @return array
 */
function hg_dep_pages( $dossier, $transformer = null ) {
	$dossier = rtrim( $dossier, '/' );
	$ids     = array();
	$erreurs = array();
	foreach ( array_filter( (array) glob( $dossier . '/*.json' ), fn( $f ) => 'modele.json' !== basename( $f ) ) as $meta_fichier ) {
		$meta    = json_decode( file_get_contents( $meta_fichier ), true );
		$contenu = file_get_contents( substr( $meta_fichier, 0, -5 ) . '.html' );
		$contenu = $transformer ? $transformer( $contenu ) : $contenu;
		$existe  = hg_dep_existant( $meta['slug'], 'page' );
		hg_dep_liberer_slug( $meta['slug'] );
		$donnees = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $meta['titre'],
			'post_name'    => $meta['slug'],
			'post_content' => wp_slash( $contenu ),
		);
		if ( $existe ) {
			$donnees['ID'] = $existe;
		}
		$id = wp_insert_post( $donnees, true );
		if ( is_wp_error( $id ) ) {
			$erreurs[ $meta['slug'] ] = $id->get_error_message();
			continue;
		}
		update_post_meta( $id, 'hg_titre_seo', $meta['titre_seo'] );
		update_post_meta( $id, 'hg_description', $meta['description'] );
		update_post_meta( $id, '_hg_deploye', 1 );
		// Page issue d'une pièce jointe convertie par une version antérieure de ce script : réparée.
		foreach ( array( '_wp_attached_file', '_wp_attachment_metadata', '_hg_source' ) as $meta_image ) {
			delete_post_meta( $id, $meta_image );
		}
		$ids[ $meta['slug'] ] = $id;
	}
	// Articles d'actualité : type « post », catégorie, date de publication, image mise en avant.
	$articles = 0;
	$art_ids  = array();
	foreach ( glob( $dossier . '/articles/*.json' ) as $meta_fichier ) {
		$meta    = json_decode( file_get_contents( $meta_fichier ), true );
		$contenu = file_get_contents( substr( $meta_fichier, 0, -5 ) . '.html' );
		$contenu = $transformer ? $transformer( $contenu ) : $contenu;
		$cat     = term_exists( $meta['categorie'], 'category' ) ?: wp_insert_term( $meta['categorie'], 'category' );
		$existe  = hg_dep_existant( $meta['slug'], 'post' );
		$donnees = array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => $meta['titre'],
			'post_name'     => $meta['slug'],
			'post_content'  => wp_slash( $contenu ),
			'post_excerpt'  => $meta['extrait'],
			'post_date'     => $meta['date'] . ' 09:00:00',
			'post_category' => array( (int) ( is_array( $cat ) ? $cat['term_id'] : $cat ) ),
		);
		if ( $existe ) {
			$donnees['ID'] = $existe;
		}
		$id = wp_insert_post( $donnees, true );
		if ( is_wp_error( $id ) ) {
			$erreurs[ $meta['slug'] ] = $id->get_error_message();
			continue;
		}
		update_post_meta( $id, 'hg_titre_seo', $meta['titre_seo'] );
		update_post_meta( $id, 'hg_description', $meta['description'] );
		update_post_meta( $id, '_hg_deploye', 1 );
		$image = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'meta_key' => '_hg_source', 'meta_value' => $meta['image'] ) );
		if ( $image ) {
			set_post_thumbnail( $id, $image[0] );
		}
		$art_ids[ $meta['slug'] ] = $id;
		$articles++;
	}
	// Le contenu lie les articles en /<slug>/. Si l'hébergeur a gardé une autre structure de
	// permaliens (adresses datées, par exemple), ces liens sont réécrits vers la vraie adresse.
	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		$vers = array();
		foreach ( $art_ids as $slug => $id ) {
			$vers[ home_url( '/' . $slug . '/' ) ] = get_permalink( $id );
		}
		foreach ( array_merge( array_values( $ids ), array_values( $art_ids ) ) as $id ) {
			$avant = get_post_field( 'post_content', $id );
			$apres = strtr( $avant, $vers );
			if ( $apres !== $avant ) {
				wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $apres ) ) );
			}
		}
	}
	foreach ( array( 'accueil', 'hematologie', 'rendez-vous' ) as $requise ) {
		if ( empty( $ids[ $requise ] ) ) {
			return array( 'erreur' => "page « $requise » absente : accueil et menu non réglés", 'erreurs' => $erreurs );
		}
	}
	$non_classe = get_term_by( 'slug', 'uncategorized', 'category' ) ?: get_term_by( 'slug', 'non-classe', 'category' );
	if ( $non_classe && 0 === (int) $non_classe->count ) {
		wp_update_term( $non_classe->term_id, 'category', array( 'name' => 'Actualités', 'slug' => 'actualites-hemato-gui' ) );
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ids['accueil'] );
	// Aucune page ne sert de liste d'articles : la page « Actualités » a son propre contenu.
	// (Un réglage resté d'un ancien site ferait afficher les articles à la place d'une page.)
	update_option( 'page_for_posts', 0 );

	// Menu principal (wp_navigation) : les liens pointent vers les pages par identifiant,
	// d'où l'état « page courante » ; le bloc de l'en-tête le reprend par défaut.
	$lien  = fn( $libelle, $slug, $desc = '' ) => empty( $ids[ $slug ] ) ? '' : sprintf(
		'<!-- wp:navigation-link %s /-->',
		wp_json_encode( array_filter( array( 'label' => $libelle, 'type' => 'page', 'id' => $ids[ $slug ], 'url' => get_permalink( $ids[ $slug ] ), 'kind' => 'post-type', 'description' => $desc ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
	);
	$menu  = $lien( 'Accueil', 'accueil' ) . "\n";
	$menu .= '<!-- wp:navigation-submenu ' . wp_json_encode( array( 'label' => 'Hématologie', 'type' => 'page', 'id' => $ids['hematologie'], 'url' => get_permalink( $ids['hematologie'] ), 'kind' => 'post-type' ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . " -->\n";
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
	// Retrouvé même en brouillon (après un retour arrière) : jamais de second menu.
	$nav_existante = get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => array( 'publish', 'draft' ), 'title' => 'Menu principal', 'fields' => 'ids' ) );
	$nav           = wp_insert_post(
		array(
			'ID'           => $nav_existante[0] ?? 0,
			'post_type'    => 'wp_navigation',
			'post_status'  => 'publish',
			'post_title'   => 'Menu principal',
			'post_content' => wp_slash( $menu ),
		)
	);
	update_post_meta( $nav, '_hg_deploye', 1 );

	// Contenu d'exemple de l'installation : retiré (seulement s'il n'a pas été modifié).
	$retires = array();
	foreach ( array( 'hello-world' => 'post', 'bonjour-tout-le-monde' => 'post', 'sample-page' => 'page', 'page-d-exemple' => 'page', 'exemple-de-page' => 'page' ) as $slug => $type ) {
		$p = get_post( hg_dep_existant( $slug, $type ) );
		if ( $p && $p->post_modified === $p->post_date ) {
			wp_delete_post( $p->ID, true );
			$retires[] = $slug;
		}
	}
	flush_rewrite_rules( false );
	return array(
		'pages'    => count( $ids ),
		'articles' => $articles,
		'accueil'  => $ids['accueil'],
		'menu'     => 'Menu principal à jour',
		'retires'  => $retires,
		'erreurs'  => $erreurs,
		'liens'    => array_map( 'get_permalink', $ids ),
	);
}

/**
 * Étape 7 : purge des caches de WordPress et des extensions de cache courantes.
 *
 * @return string[] Caches purgés.
 */
function hg_dep_purger() {
	wp_clean_themes_cache( true );
	wp_cache_flush();
	if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
		WP_Theme_JSON_Resolver::clean_cached_data();
	}
	$faits = array( 'WordPress (thèmes, objets, theme.json)' );
	if ( defined( 'LSCWP_V' ) || has_action( 'litespeed_purge_all' ) ) {
		do_action( 'litespeed_purge_all' );
		$faits[] = 'LiteSpeed';
	}
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
		$faits[] = 'WP Rocket';
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
		$faits[] = 'W3 Total Cache';
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
		$faits[] = 'WP Super Cache';
	}
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache();
		$faits[] = 'SiteGround';
	}
	if ( function_exists( 'wpfc_clear_all_cache' ) ) {
		wpfc_clear_all_cache( true );
		$faits[] = 'WP Fastest Cache';
	}
	if ( class_exists( 'autoptimizeCache' ) ) {
		autoptimizeCache::clearall();
		$faits[] = 'Autoptimize';
	}
	return $faits;
}

/**
 * Remplit un contenu « modèle » (contenu.py --modele) : identifiants et adresses des visuels,
 * adresse du site. Le résultat est identique à ce que contenu.py produit pour ce site.
 *
 * @param string $contenu Contenu modèle.
 * @param array  $modele  { "assets/img/…": jeton numérique } (modele.json).
 * @param array  $carte   { "assets/img/…": { id, url } } (hg_dep_medias).
 * @return string
 */
function hg_dep_remplir( $contenu, $modele, $carte ) {
	$ids = array();
	foreach ( $modele as $relatif => $jeton ) {
		$ids[ (string) $jeton ] = $carte[ $relatif ]['id'] ?? $jeton;
	}
	$contenu = preg_replace_callback( '#https://hg-modele\.invalid/hg-media/([\w./-]+)#', fn( $m ) => $carte[ $m[1] ]['url'] ?? $m[0], $contenu );
	$contenu = preg_replace_callback( '/("id":|wp-image-)(98765\d{4})\b/', fn( $m ) => $m[1] . ( $ids[ $m[2] ] ?? $m[2] ), $contenu );
	return str_replace( 'https://hg-modele.invalid/', home_url( '/' ), $contenu );
}

/**
 * Retour à l'état d'avant le premier déploiement : thème, réglages de lecture, titre du site,
 * contenus réécrits restaurés ; les pages et articles HEMATO GUI passent en brouillon
 * (rien n'est supprimé), l'extension est désactivée.
 *
 * @return array
 */
function hg_dep_retour() {
	$s = get_option( 'hg_sauvegarde_avant_theme' );
	if ( ! $s ) {
		return array( 'erreur' => 'aucune sauvegarde en base (hg_sauvegarde_avant_theme)' );
	}
	switch_theme( $s['theme_avant'] );
	$options = array( 'show_on_front' => 'show_on_front', 'page_on_front' => 'page_on_front', 'page_for_posts' => 'page_for_posts', 'permalink_structure' => 'permaliens', 'blogname' => 'titre_site', 'blogdescription' => 'slogan', 'WPLANG' => 'langue', 'timezone_string' => 'fuseau' );
	foreach ( $options as $option => $cle ) {
		update_option( $option, $s[ $cle ] );
	}
	$anciens = array();
	foreach ( $s['contenus'] as $c ) {
		$anciens[ (int) $c['ID'] ] = $c;
	}
	$restaures = 0;
	foreach ( $s['collisions'] as $c ) {
		$a = $anciens[ $c['id'] ];
		wp_update_post( wp_slash( array( 'ID' => $c['id'], 'post_title' => $a['post_title'], 'post_content' => $a['post_content'], 'post_status' => $a['post_status'] ) ) );
		delete_post_meta( $c['id'], '_hg_deploye' );
		$restaures++;
	}
	$brouillons = 0;
	foreach ( get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_hg_deploye', 'meta_value' => '1' ) ) as $p ) {
		wp_update_post( array( 'ID' => $p->ID, 'post_status' => 'draft' ) );
		$brouillons++;
	}
	// Le menu HEMATO GUI aussi : sinon le bloc de navigation de l'ancien thème le reprendrait par défaut.
	foreach ( get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_hg_deploye', 'meta_value' => '1' ) ) as $p ) {
		wp_update_post( array( 'ID' => $p->ID, 'post_status' => 'draft' ) );
	}
	deactivate_plugins( 'hemato-gui/hemato-gui.php' );
	flush_rewrite_rules( false );
	hg_dep_purger();
	return array( 'theme' => get_stylesheet(), 'restaures' => $restaures, 'brouillons' => $brouillons );
}

/**
 * Blocs à plat (blocs imbriqués compris).
 *
 * @param array $blocs Résultat de parse_blocks().
 * @return array
 */
function hg_dep_aplatir( $blocs ) {
	$r = array();
	foreach ( $blocs as $b ) {
		if ( $b['blockName'] ) {
			$r[] = $b;
			$r   = array_merge( $r, hg_dep_aplatir( $b['innerBlocks'] ) );
		}
	}
	return $r;
}

/**
 * Porte 1 de la recette, côté serveur : aucun bloc core/html, aucun bloc hors cœur,
 * toutes les images liées à la médiathèque (pages, articles, en-tête et pied de page).
 *
 * @return array { ok, contenus, html, hors, images, liees, jetons, detail[] }
 */
function hg_dep_porte1() {
	$t = array( 'html' => 0, 'hors' => 0, 'images' => 0, 'liees' => 0, 'detail' => array() );
	$contenus = get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'type title', 'order' => 'ASC' ) );
	$compter  = function ( $tous ) {
		$img = array_filter( $tous, fn( $b ) => 'core/image' === $b['blockName'] );
		return array(
			'blocs'  => count( $tous ),
			'html'   => count( array_filter( $tous, fn( $b ) => 'core/html' === $b['blockName'] ) ),
			'hors'   => count( array_filter( $tous, fn( $b ) => ! str_starts_with( $b['blockName'], 'core/' ) ) ),
			'images' => count( $img ),
			'liees'  => count( array_filter( $img, fn( $b ) => ! empty( $b['attrs']['id'] ) && 'attachment' === get_post_type( $b['attrs']['id'] ) ) ),
		);
	};
	$sources = array();
	foreach ( $contenus as $p ) {
		$sources[] = array( $p->post_type, $p->post_name, $p->post_content );
	}
	foreach ( array( 'header', 'footer' ) as $partie ) {
		$gabarit = get_block_template( get_stylesheet() . '//' . $partie, 'wp_template_part' );
		if ( $gabarit ) {
			$sources[] = array( 'partie', $partie, $gabarit->content );
		}
	}
	foreach ( $sources as list( $type, $nom, $contenu ) ) {
		$c = $compter( hg_dep_aplatir( parse_blocks( $contenu ) ) );
		$t['detail'][] = array( 'type' => $type, 'nom' => $nom ) + $c;
		foreach ( array( 'html', 'hors', 'images', 'liees' ) as $cle ) {
			$t[ $cle ] += $c[ $cle ];
		}
	}
	// Contenu modèle mal rempli : un jeton restant serait un lien ou une image cassés.
	$t['jetons']   = count( array_filter( $contenus, fn( $p ) => str_contains( $p->post_content, 'hg-modele.invalid' ) || preg_match( '/("id":|wp-image-)98765\d{4}\b/', $p->post_content ) ) );
	$t['contenus'] = count( $contenus );
	$t['ok']       = 0 === $t['html'] && 0 === $t['hors'] && $t['images'] === $t['liees'] && 0 === $t['jetons'];
	return $t;
}

