<?php
/**
 * Menu « HEMATO GUI » de l'administration : tableau de bord, demandes, réglages.
 *
 * Le tableau de bord n'affiche que des comptes et des références : les identités
 * restent dans la fiche de chaque demande (règle du brief : aucune donnée médicale
 * personnelle exposée hors de son contexte).
 *
 * @package hemato-gui
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'HEMATO GUI', 'HEMATO GUI', 'hg_gerer_demandes', 'hemato-gui', 'hg_page_tableau', 'dashicons-heart', 3 );
		add_submenu_page( 'hemato-gui', 'Tableau de bord', 'Tableau de bord', 'hg_gerer_demandes', 'hemato-gui', 'hg_page_tableau' );
		add_submenu_page( 'hemato-gui', 'Réglages HEMATO GUI', 'Réglages', 'manage_options', 'hemato-gui-reglages', 'hg_page_reglages' );
	}
);

/**
 * Nombre de demandes d'un type sur une période.
 *
 * @param string      $type   Type.
 * @param int         $jours  Période en jours (0 = tout).
 * @param string|null $statut Statut exigé.
 * @param string|null $consult Type de consultation exigé.
 * @return int
 */
function hg_compter( $type, $jours = 0, $statut = null, $consult = null ) {
	$meta = array( array( 'key' => '_hg_type', 'value' => $type ) );
	if ( $statut ) {
		$meta[] = array( 'key' => '_hg_statut', 'value' => $statut );
	}
	if ( $consult ) {
		$meta[] = array( 'key' => '_hg_type_consult', 'value' => $consult );
	}
	$args = array(
		'post_type'      => 'hg_demande',
		'post_status'    => 'private',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
	);
	if ( $jours ) {
		$args['date_query'] = array( array( 'after' => $jours . ' days ago' ) );
	}
	return count( get_posts( $args ) );
}

/** Page « Tableau de bord ». */
function hg_page_tableau() {
	$indicateurs = array(
		array( 'Demandes de rendez-vous', hg_compter( 'rendez-vous', 30 ), '30 derniers jours' ),
		array( 'À confirmer', hg_compter( 'rendez-vous', 0, 'nouvelle' ), 'demandes en attente' ),
		array( 'Messages', hg_compter( 'contact', 30 ), '30 derniers jours' ),
		array( 'Accès pro à vérifier', hg_compter( 'acces-pro', 0, 'nouvelle' ), 'demandes en attente' ),
		array( 'Comptes patients', count( get_users( array( 'role' => 'hg_patient', 'fields' => 'ID' ) ) ), 'au total' ),
		array( 'Professionnels vérifiés', count( get_users( array( 'role' => 'hg_pro', 'fields' => 'ID' ) ) ), 'au total' ),
	);
	$par_type = array();
	foreach ( HG_CONSULTATIONS as $libelle ) {
		$par_type[ $libelle ] = hg_compter( 'rendez-vous', 30, null, $libelle );
	}
	arsort( $par_type );
	$max = max( 1, max( $par_type ) );

	echo '<div class="wrap hg-tableau"><h1>HEMATO GUI — tableau de bord</h1>';
	echo '<div class="hg-kpis">';
	foreach ( $indicateurs as list( $titre, $valeur, $note ) ) {
		printf( '<div class="hg-kpi"><p class="hg-kpi-titre">%s</p><p class="hg-kpi-valeur">%s</p><p class="hg-kpi-note">%s</p></div>', esc_html( $titre ), esc_html( number_format_i18n( $valeur ) ), esc_html( $note ) );
	}
	echo '</div><div class="hg-colonnes"><section class="hg-carte"><h2>Rendez-vous demandés par type <small>30 derniers jours</small></h2><table class="hg-barres"><tbody>';
	foreach ( $par_type as $libelle => $n ) {
		printf(
			'<tr><th scope="row">%s</th><td><span class="hg-barre" style="width:%s%%"></span></td><td class="hg-n">%s</td></tr>',
			esc_html( $libelle ),
			esc_attr( round( 100 * $n / $max ) ),
			esc_html( number_format_i18n( $n ) )
		);
	}
	echo '</tbody></table></section><section class="hg-carte"><h2>Dernières demandes</h2><table class="widefat striped"><thead><tr><th>Référence</th><th>Type</th><th>Statut</th><th>Reçue</th></tr></thead><tbody>';
	$recentes = get_posts( array( 'post_type' => 'hg_demande', 'post_status' => 'private', 'posts_per_page' => 10 ) );
	if ( ! $recentes ) {
		echo '<tr><td colspan="4">Aucune demande pour le moment.</td></tr>';
	}
	foreach ( $recentes as $d ) {
		$statut = get_post_meta( $d->ID, '_hg_statut', true ) ?: 'nouvelle';
		printf(
			'<tr><td><a href="%s">%s</a></td><td>%s</td><td><span class="hg-statut hg-statut-%s">%s</span></td><td>%s</td></tr>',
			esc_url( get_edit_post_link( $d->ID ) ),
			esc_html( get_post_meta( $d->ID, '_hg_reference', true ) ),
			esc_html( HG_TYPES_DEMANDE[ get_post_meta( $d->ID, '_hg_type', true ) ] ?? '—' ),
			esc_attr( $statut ),
			esc_html( HG_STATUTS[ $statut ] ?? $statut ),
			esc_html( human_time_diff( get_post_time( 'U', true, $d ) ) . ' ' . 'plus tôt' )
		);
	}
	echo '</tbody></table><p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=hg_demande' ) ) . '">Toutes les demandes</a></p></section></div>';
	echo '<p class="hg-note">Statistiques de visites : à brancher sur l’outil de mesure d’audience retenu (Site Kit, Matomo…). Ventes d’eBooks : disponibles une fois la boutique activée.</p></div>';
}

/** Page « Réglages ». */
function hg_page_reglages() {
	echo '<div class="wrap"><h1>Réglages HEMATO GUI</h1><form method="post" action="options.php">';
	settings_fields( 'hg_reglages' );
	do_settings_sections( 'hemato-gui-reglages' );
	submit_button();
	echo '</form></div>';
}

add_action(
	'admin_init',
	function () {
		register_setting( 'hg_reglages', 'hg_email_equipe', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => get_option( 'admin_email' ) ) );
		register_setting( 'hg_reglages', 'hg_capacite_jour', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 16 ) );
		register_setting( 'hg_reglages', 'hg_conservation_mois', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 24 ) );
		add_settings_section( 'hg_principal', '', '__return_false', 'hemato-gui-reglages' );
		$champs = array(
			'hg_email_equipe'      => array( 'E-mail de l’équipe', 'email', get_option( 'admin_email' ), 'Reçoit l’avis de chaque nouvelle demande (sans identité ni motif).' ),
			'hg_capacite_jour'     => array( 'Demandes de rendez-vous par jour', 'number', 16, 'Au-delà, le jour s’affiche « complet » dans le calendrier.' ),
			'hg_conservation_mois' => array( 'Conservation des demandes (mois)', 'number', 24, 'Les demandes plus anciennes sont supprimées automatiquement.' ),
		);
		foreach ( $champs as $cle => list( $libelle, $type, $defaut, $aide ) ) {
			add_settings_field(
				$cle,
				$libelle,
				function () use ( $cle, $type, $defaut, $aide ) {
					printf( '<input type="%s" name="%s" value="%s" class="regular-text"><p class="description">%s</p>', esc_attr( $type ), esc_attr( $cle ), esc_attr( get_option( $cle, $defaut ) ), esc_html( $aide ) );
				},
				'hemato-gui-reglages',
				'hg_principal'
			);
		}
	}
);

// Styles de l'administration (tableau de bord et statuts), aux couleurs du site.
add_action(
	'admin_head',
	function () {
		echo '<style>
.hg-tableau .hg-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0}
.hg-kpi{background:#fff;border:1px solid #E4E4E9;border-radius:14px;padding:16px 18px}
.hg-kpi p{margin:0}.hg-kpi-titre{font-weight:600;color:#1D1D1F}.hg-kpi-valeur{font-size:32px;font-weight:700;line-height:1.2;margin-top:6px!important;color:#1D1D1F}.hg-kpi-note{color:#6E6E73}
.hg-colonnes{display:grid;grid-template-columns:1fr;gap:16px}@media(min-width:1100px){.hg-colonnes{grid-template-columns:1fr 1fr}}
.hg-carte{background:#fff;border:1px solid #E4E4E9;border-radius:14px;padding:18px 20px}.hg-carte h2{margin-top:0}.hg-carte h2 small{font-weight:400;color:#6E6E73}
.hg-barres{width:100%;border-collapse:collapse}.hg-barres th{text-align:left;font-weight:500;padding:6px 10px 6px 0;width:40%}.hg-barres td{padding:6px 0}
.hg-barre{display:block;height:12px;min-width:2px;border-radius:0 4px 4px 0;background:#C8102E}.hg-n{width:40px;text-align:right;color:#1D1D1F}
.hg-statut{display:inline-block;padding:2px 10px;border-radius:999px;background:#F5F5F7;color:#1D1D1F;font-size:12px;font-weight:600}
.hg-statut-nouvelle{background:rgba(200,16,46,.1);color:#A00C25}.hg-statut-confirmee{background:#1D1D1F;color:#fff}.hg-statut-annulee{color:#6E6E73}
.hg-note{color:#6E6E73;max-width:80ch}
</style>';
	}
);
