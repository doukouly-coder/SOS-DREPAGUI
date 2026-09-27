<?php
/**
 * Plugin Name:       HEMATO GUI — fonctions du site
 * Description:       Compagnon du thème HEMATO GUI : demandes de rendez-vous, contact et accès professionnel, comptes patients, calculateurs, rôles, SEO de base et durcissement de sécurité.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            HEMATO GUI
 * License:           GPL-2.0-or-later
 * Text Domain:       hemato-gui
 *
 * @package hemato-gui
 */

defined( 'ABSPATH' ) || exit;

define( 'HG_VERSION', '1.0.0' );
define( 'HG_DIR', plugin_dir_path( __FILE__ ) );
define( 'HG_URL', plugin_dir_url( __FILE__ ) );
define( 'HG_WHATSAPP', '224628733143' );

require HG_DIR . 'inc/gabarits.php';
require HG_DIR . 'inc/demandes.php';
require HG_DIR . 'inc/rendez-vous.php';
require HG_DIR . 'inc/formulaires.php';
require HG_DIR . 'inc/outils.php';
require HG_DIR . 'inc/seo.php';
require HG_DIR . 'inc/securite.php';
require HG_DIR . 'inc/tableau-de-bord.php';

/**
 * Rôles : patient (compte personnel), professionnel (vérifié à la main),
 * secrétariat (traite les demandes sans accès au reste de l'administration).
 */
function hg_activation() {
	add_role( 'hg_patient', 'Patient', array( 'read' => true ) );
	add_role( 'hg_pro', 'Professionnel de santé', array( 'read' => true, 'hg_espace_pro' => true ) );
	add_role( 'hg_secretariat', 'Secrétariat', array( 'read' => true, 'hg_gerer_demandes' => true ) );
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'hg_gerer_demandes' );
		$admin->add_cap( 'hg_espace_pro' );
	}
	hg_enregistrer_demandes();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'hg_activation' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
