<?php
/**
 * Plugin Name:       HEMATO GUI — installation du site
 * Description:       Installe en une fois le site HEMATO GUI (thème, extension, visuels en médiathèque, pages, articles et menu), après sauvegarde du site existant, avec retour arrière. Outils › Installer HEMATO GUI. À supprimer une fois le site vérifié.
 * Version:           1.0.1
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            HEMATO GUI
 * License:           GPL-2.0-or-later
 * Text Domain:       hemato-gui-installation
 *
 * Le paquet (thème, extension, visuels, contenu modèle) et deploiement.php sont ajoutés
 * par outils/wordpress/construire.py : ce sont les mêmes étapes que le déploiement local
 * et le déploiement par Novamira, déclenchées ici depuis l'administration.
 *
 * @package hemato-gui-installation
 */

defined( 'ABSPATH' ) || exit;

define( 'HGI_PAQUET', __DIR__ . '/paquet' );
define( 'HGI_LOT', 6 );

/**
 * Slugs des pages et articles du site HEMATO GUI.
 *
 * @return string[]
 */
function hgi_slugs() {
	$slugs = array();
	foreach ( array_merge( (array) glob( HGI_PAQUET . '/contenu/*.json' ), (array) glob( HGI_PAQUET . '/contenu/articles/*.json' ) ) as $f ) {
		$meta = json_decode( file_get_contents( $f ), true );
		if ( isset( $meta['slug'] ) ) {
			$slugs[] = $meta['slug'];
		}
	}
	return $slugs;
}

register_activation_hook(
	__FILE__,
	function () {
		set_transient( 'hgi_redirection', 1, MINUTE_IN_SECONDS );
	}
);

// Après activation : directement sur la page d'installation.
add_action(
	'admin_init',
	function () {
		if ( get_transient( 'hgi_redirection' ) && ! wp_doing_ajax() && ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			delete_transient( 'hgi_redirection' );
			wp_safe_redirect( admin_url( 'tools.php?page=hemato-gui-installation' ) );
			exit;
		}
	}
);

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Installer HEMATO GUI', 'Installer HEMATO GUI', 'manage_options', 'hemato-gui-installation', 'hgi_page' );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $liens ) {
		array_unshift( $liens, '<a href="' . esc_url( admin_url( 'tools.php?page=hemato-gui-installation' ) ) . '">Installer le site</a>' );
		return $liens;
	}
);

/**
 * Page Outils › Installer HEMATO GUI.
 */
function hgi_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès réservé aux administrateurs.' );
	}
	require_once __DIR__ . '/deploiement.php';
	global $wp_version;
	$d          = hg_dep_diagnostic();
	$collisions = hg_dep_collisions( hgi_slugs() );
	$fait       = get_option( 'hgi_installation' );
	$sauvegarde = get_option( 'hg_sauvegarde_avant_theme' );
	$nb_images  = count( array_filter( (array) glob( HGI_PAQUET . '/img/*/*' ), 'is_file' ) );
	$nb_pages   = count( (array) glob( HGI_PAQUET . '/contenu/*.html' ) );
	$nb_art     = count( (array) glob( HGI_PAQUET . '/contenu/articles/*.html' ) );
	$verifs     = array(
		array( 'WordPress 6.6 ou plus', version_compare( $wp_version, '6.6-alpha', '>=' ), $wp_version ),
		array( 'PHP 8.0 ou plus', version_compare( PHP_VERSION, '8.0', '>=' ), PHP_VERSION ),
		array( 'Compte administrateur', $d['peut_tout'], $d['utilisateur'] ),
		array( 'Écriture dans les dossiers des thèmes et des extensions', $d['ecriture_themes'] && $d['ecriture_ext'], 'direct' === $d['methode_fichier'] ? 'accès direct' : 'accès « ' . $d['methode_fichier'] . ' »' ),
		array( 'Écriture dans la médiathèque', $d['ecriture_upload'], '' ),
		array( 'Site unique (pas de réseau multisite)', ! $d['multisite'], '' ),
	);
	$bloque = in_array( false, array_map( 'boolval', array_column( $verifs, 1 ) ), true );
	?>
	<div class="wrap hgi">
		<h1>Installer le site HEMATO GUI</h1>
		<p class="hgi-intro">Cette extension installe le site complet en une fois : thème, extension, visuels, pages, articles et menu. Tout reste modifiable ensuite dans l’éditeur de WordPress.</p>

		<?php if ( $fait ) : ?>
			<div class="notice notice-success inline"><p>
				<strong>Site installé le <?php echo esc_html( mysql2date( 'j F Y à H:i', $fait['date'] ) ); ?>.</strong>
				<?php echo esc_html( sprintf( '%d contenus HEMATO GUI éditables, aucun bloc HTML brut, %d/%d images liées à la médiathèque.', $fait['porte1']['contenus'], $fait['porte1']['liees'], $fait['porte1']['images'] ) ); ?>
			</p><p>
				<a class="button button-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">Voir le site</a>
				<a class="button" href="<?php echo esc_url( (string) get_edit_post_link( (int) get_option( 'page_on_front' ), 'url' ) ); ?>">Modifier la page d’accueil</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>">Toutes les pages</a>
			</p><p>Une fois le site vérifié, désactivez puis supprimez l’extension « HEMATO GUI — installation du site » : elle n’est plus utile, et le retour arrière ne sera plus proposé.</p></div>
		<?php endif; ?>

		<div class="card">
			<h2>Vérifications</h2>
			<table class="hgi-verifs">
				<?php foreach ( $verifs as list( $libelle, $ok, $detail ) ) : ?>
					<tr><td class="<?php echo $ok ? 'ok' : 'ko'; ?>"><?php echo $ok ? '✓' : '✗'; ?></td><td><?php echo esc_html( $libelle ); ?></td><td class="hgi-detail"><?php echo esc_html( $detail ); ?></td></tr>
				<?php endforeach; ?>
			</table>
			<?php if ( $bloque ) : ?>
				<p class="hgi-bloque">L’installation n’est pas possible sur ce site tant qu’un point ci-dessus est en rouge. Votre hébergeur peut le régler.</p>
			<?php endif; ?>
		</div>

		<div class="card">
			<h2><?php echo $fait ? 'Réinstaller' : 'Ce qui va se passer'; ?></h2>
			<?php if ( $fait ) : ?>
				<div class="notice notice-warning inline"><p><strong>Réinstaller remet les pages et articles HEMATO GUI dans leur version d’origine</strong> : les modifications faites depuis dans l’éditeur sur ces contenus seront remplacées (elles restent dans leurs révisions). Les pages et articles que vous avez ajoutés ne sont pas touchés.</p></div>
			<?php endif; ?>
			<ol>
				<li><strong>Sauvegarde du site actuel</strong> (réglages et contenus), qui permet de revenir en arrière.</li>
				<?php if ( 'hemato-gui' === $d['theme_actif'] ) : ?>
					<li><strong>Thème et extension HEMATO GUI</strong> remis à jour.</li>
				<?php else : ?>
					<li><strong>Thème et extension HEMATO GUI</strong> installés et activés. Votre thème actuel, « <?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?> », reste installé.</li>
				<?php endif; ?>
				<li><strong><?php echo (int) $nb_images; ?> visuels</strong> ajoutés à la médiathèque.</li>
				<li><strong><?php echo (int) $nb_pages; ?> pages, <?php echo (int) $nb_art; ?> articles</strong> et le menu principal ; la page « Accueil » devient la page d’accueil.</li>
				<li><strong>Vérification</strong> : chaque page est éditable, chaque image est liée à la médiathèque.</li>
			</ol>
			<?php if ( $collisions ) : ?>
				<div class="notice notice-warning inline"><p>Ces contenus de votre site portent la même adresse qu’une page HEMATO GUI et seront <strong>remplacés</strong>. Leur contenu actuel reste dans la sauvegarde et dans leurs révisions :</p><ul>
					<?php foreach ( $collisions as $c ) : ?>
						<li><a href="<?php echo esc_url( (string) get_edit_post_link( $c['id'], 'url' ) ); ?>"><?php echo esc_html( $c['titre'] ?: '(sans titre)' ); ?></a> — /<?php echo esc_html( $c['slug'] ); ?>/</li>
					<?php endforeach; ?>
				</ul></div>
			<?php endif; ?>
			<p><button type="button" class="button button-primary button-hero" id="hgi-installer" data-confirmation="<?php echo esc_attr( $fait ? 'Réinstaller le site HEMATO GUI ? Les pages et articles HEMATO GUI reviendront à leur version d’origine.' : 'Installer le site HEMATO GUI ? Le site actuel est sauvegardé avant toute modification.' ); ?>" <?php disabled( $bloque ); ?>><?php echo $fait ? 'Réinstaller le site' : 'Installer le site'; ?></button></p>
			<ol id="hgi-journal" aria-live="polite"></ol>
		</div>

		<?php if ( $sauvegarde ) : ?>
			<div class="card">
				<h2>Revenir en arrière</h2>
				<p>Rétablit le site du <?php echo esc_html( mysql2date( 'j F Y à H:i', $sauvegarde['date'] ) ); ?> : thème « <?php echo esc_html( $sauvegarde['theme_avant'] ); ?> », page d’accueil, titre du site et contenus remplacés. Les pages et articles HEMATO GUI passent en brouillon : rien n’est supprimé.</p>
				<p><button type="button" class="button" id="hgi-retour">Revenir au site d’avant</button></p>
			</div>
		<?php endif; ?>
	</div>
	<style>
		.hgi .card, .hgi > .notice { max-width: 820px; box-sizing: border-box; }
		.hgi .card { padding: 8px 24px 16px; }
		.hgi-intro { font-size: 14px; max-width: 820px; }
		.hgi-verifs td { padding: 4px 12px 4px 0; vertical-align: top; }
		.hgi-verifs .ok { color: #008a20; font-weight: 700; }
		.hgi-verifs .ko, .hgi-bloque { color: #d63638; font-weight: 700; }
		.hgi-detail { color: #646970; }
		#hgi-journal li { margin: 6px 0; }
		#hgi-journal li.fait::marker, #hgi-journal li.fin::marker { color: #008a20; }
		#hgi-journal li.en-cours { color: #646970; }
		#hgi-journal li.erreur { color: #d63638; font-weight: 600; }
		#hgi-journal li.fin { font-weight: 600; }
	</style>
	<script>
	( function () {
		const adresse = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const jeton = <?php echo wp_json_encode( wp_create_nonce( 'hgi_etape' ) ); ?>;
		const journal = document.getElementById( 'hgi-journal' );
		const ligne = ( texte, etat ) => {
			const li = document.createElement( 'li' );
			li.textContent = texte;
			li.className = etat || '';
			journal.appendChild( li );
			return li;
		};
		async function etape( nom ) {
			const corps = new FormData();
			corps.append( 'action', 'hgi_etape' );
			corps.append( 'etape', nom );
			corps.append( 'jeton', jeton );
			const r = await fetch( adresse, { method: 'POST', body: corps, credentials: 'same-origin' } );
			let d;
			try {
				d = await r.json();
			} catch ( e ) {
				throw new Error( 'réponse inattendue du serveur (HTTP ' + r.status + '), délai d’exécution dépassé ?' );
			}
			if ( ! d.success ) {
				throw new Error( typeof d.data === 'string' ? d.data : 'erreur (HTTP ' + r.status + ')' );
			}
			return d.data;
		}
		async function derouler( bouton, etapes, fin ) {
			bouton.disabled = true;
			journal.innerHTML = '';
			try {
				for ( const [ nom, titre ] of etapes ) {
					const li = ligne( titre + '…', 'en-cours' );
					let d = await etape( nom );
					while ( d.restant ) {
						li.textContent = titre + ' : ' + d.message + '…';
						d = await etape( nom );
					}
					li.textContent = titre + ' : ' + d.message;
					li.className = 'fait';
				}
				ligne( fin, 'fin' );
				setTimeout( () => location.reload(), 2500 );
			} catch ( e ) {
				ligne( 'Arrêt : ' + e.message + '. Rien n’est perdu : relancez, ou revenez en arrière.', 'erreur' );
				bouton.disabled = false;
			}
		}
		const installer = document.getElementById( 'hgi-installer' );
		installer && installer.addEventListener( 'click', () => {
			if ( ! confirm( installer.dataset.confirmation ) ) {
				return;
			}
			derouler( installer, [
				[ 'sauvegarde', 'Sauvegarde du site actuel' ],
				[ 'installer', 'Thème et extension' ],
				[ 'medias', 'Visuels en médiathèque' ],
				[ 'pages', 'Pages, articles et menu' ],
				[ 'verifier', 'Vérification' ],
			], 'Installation terminée.' );
		} );
		const retour = document.getElementById( 'hgi-retour' );
		retour && retour.addEventListener( 'click', () => {
			if ( ! confirm( 'Revenir au site d’avant ? Les pages et articles HEMATO GUI passeront en brouillon.' ) ) {
				return;
			}
			derouler( retour, [ [ 'retour', 'Retour au site d’avant' ] ], 'Site d’avant rétabli.' );
		} );
	} )();
	</script>
	<?php
}

add_action( 'wp_ajax_hgi_etape', 'hgi_etape' );

/**
 * Une étape de l'installation (appel AJAX de la page d'installation).
 */
function hgi_etape() {
	check_ajax_referer( 'hgi_etape', 'jeton' );
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'install_themes' ) || ! current_user_can( 'activate_plugins' ) ) {
		wp_send_json_error( 'Droits insuffisants : il faut un compte administrateur.', 403 );
	}
	require_once __DIR__ . '/deploiement.php';
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	try {
		$r = hgi_executer( sanitize_key( wp_unslash( $_POST['etape'] ?? '' ) ) );
	} catch ( Throwable $e ) {
		wp_send_json_error( $e->getMessage() );
	}
	if ( isset( $r['erreur'] ) ) {
		wp_send_json_error( $r['erreur'] );
	}
	wp_send_json_success( $r );
}

/**
 * Exécute une étape.
 *
 * @param string $etape Nom de l'étape.
 * @return array { message, restant? } ou { erreur }
 */
function hgi_executer( $etape ) {
	switch ( $etape ) {
		case 'sauvegarde':
			$s = hg_dep_sauvegarde( hgi_slugs() );
			return array( 'message' => sprintf( '%d contenus sauvegardés', count( $s['contenus'] ) ) . ( $s['collisions'] ? sprintf( ', dont %d à remplacer', count( $s['collisions'] ) ) : '' ) );

		case 'installer':
			$r = hg_dep_installer( HGI_PAQUET . '/theme/hemato-gui', HGI_PAQUET . '/extension/hemato-gui' );
			if ( isset( $r['erreur'] ) ) {
				return array( 'erreur' => $r['erreur'] . ( isset( $r['fichiers'] ) ? ' (' . implode( ', ', $r['fichiers'] ) . ')' : '' ) );
			}
			return array( 'message' => sprintf( 'thème %s %s et extension activés', $r['theme'], $r['version'] ) );

		case 'medias':
			$m = hg_dep_medias( HGI_PAQUET . '/img', HGI_LOT );
			if ( $m['erreurs'] ) {
				return array( 'erreur' => 'visuels non importés : ' . implode( ' ; ', array_map( fn( $k, $v ) => "$k ($v)", array_keys( $m['erreurs'] ), $m['erreurs'] ) ) );
			}
			return array(
				'message' => sprintf( '%d visuels en médiathèque', count( $m['carte'] ) ) . ( $m['restant'] ? sprintf( ', %d à venir', $m['restant'] ) : '' ),
				'restant' => $m['restant'],
			);

		case 'pages':
			$m = hg_dep_medias( HGI_PAQUET . '/img', HGI_LOT );
			if ( $m['restant'] || $m['erreurs'] ) {
				return array( 'erreur' => 'visuels incomplets : relancez l’installation' );
			}
			$modele = json_decode( file_get_contents( HGI_PAQUET . '/contenu/modele.json' ), true );
			$p      = hg_dep_pages( HGI_PAQUET . '/contenu', fn( $c ) => hg_dep_remplir( $c, $modele, $m['carte'] ) );
			if ( isset( $p['erreur'] ) ) {
				return $p;
			}
			if ( $p['erreurs'] ) {
				return array( 'erreur' => 'contenus non écrits : ' . implode( ', ', array_keys( $p['erreurs'] ) ) );
			}
			return array( 'message' => sprintf( '%d pages, %d articles et le menu principal ; « Accueil » est la page d’accueil', $p['pages'], $p['articles'] ) );

		case 'verifier':
			$caches = hg_dep_purger();
			$p1     = hg_dep_porte1();
			if ( ! $p1['ok'] ) {
				return array( 'erreur' => sprintf( 'contrôle échoué : %d blocs HTML brut, %d blocs hors cœur, %d/%d images liées, %d contenus mal remplis', $p1['html'], $p1['hors'], $p1['liees'], $p1['images'], $p1['jetons'] ) );
			}
			unset( $p1['detail'] );
			update_option( 'hgi_installation', array( 'date' => current_time( 'mysql' ), 'porte1' => $p1 ), false );
			return array( 'message' => sprintf( '%d contenus HEMATO GUI éditables, aucun bloc HTML brut, %d/%d images liées ; caches vidés (%s)', $p1['contenus'], $p1['liees'], $p1['images'], implode( ', ', $caches ) ) . ( $p1['autres'] ? sprintf( ' ; %d contenus déjà présents sur le site laissés tels quels', $p1['autres'] ) : '' ) );

		case 'retour':
			$r = hg_dep_retour();
			if ( isset( $r['erreur'] ) ) {
				return $r;
			}
			delete_option( 'hgi_installation' );
			return array( 'message' => sprintf( 'thème « %s » rétabli, %d contenus restaurés, %d pages et articles HEMATO GUI en brouillon', $r['theme'], $r['restaures'], $r['brouillons'] ) );
	}
	return array( 'erreur' => 'étape inconnue' );
}
