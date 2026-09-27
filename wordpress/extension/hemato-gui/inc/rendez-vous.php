<?php
/**
 * [hg_rendez_vous] — le parcours en cinq étapes de la maquette, relié au serveur.
 *
 * Honnêteté du parcours : sans agenda synchronisé, le site enregistre une DEMANDE.
 * L'équipe la confirme ensuite par WhatsApp ou SMS ; la page le dit clairement.
 * Un module d'agenda (Amelia, Bookly…) pourra remplacer ce shortcode plus tard.
 *
 * @package hemato-gui
 */

defined( 'ABSPATH' ) || exit;

const HG_CONSULTATIONS = array(
	'hematologie'   => 'Consultation d’hématologie',
	'drepanocytose' => 'Drépanocytose',
	'hemophilie'    => 'Hémophilie',
	'hemostase'     => 'Hémostase',
	'cancer'        => 'Cancer du sang',
	'avis'          => 'Avis spécialisé',
);

const HG_CRENEAUX = array( '08:30', '09:00', '09:30', '10:30', '11:00', '11:30', '14:00', '14:30', '15:00', '15:30', '16:00' );

const HG_JOURS = array( 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche' );
const HG_MOIS  = array( 1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' );

add_shortcode(
	'hg_rendez_vous',
	function () {
		$html = hg_gabarit( 'rendez-vous' );
		if ( '' === $html ) {
			return '';
		}
		wp_enqueue_script( 'hg-rendez-vous', HG_URL . 'assets/js/rendez-vous.js', array( 'hg-app' ), HG_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		// Envoi réel : adresse admin-ajax et jeton sur le conteneur du parcours.
		$html = str_replace(
			'data-reservation>',
			'data-reservation data-envoi="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-jeton="' . esc_attr( wp_create_nonce( 'hg_rendez_vous' ) ) . '">',
			$html
		);

		// Calendrier des cinq prochaines semaines, jours ouvrés, capacité par jour.
		list( $titre, $grille, $pris ) = hg_calendrier();
		$html = preg_replace( '#<div class="calendrier-tete">.*?</div>#s', '<div class="calendrier-tete"><span class="cal-fleche" aria-hidden="true"></span><strong>' . esc_html( $titre ) . '</strong><span class="cal-fleche" aria-hidden="true"></span></div>', $html, 1 );
		$html = preg_replace( '#(<div class="calendrier-grille" data-groupe="date">).*?(</div>)#s', '$1' . $grille . '$2', $html, 1 );

		// Créneaux : tous proposés, ceux déjà demandés sont grisés selon la date (rendez-vous.js).
		$html = preg_replace_callback(
			'#<div class="creneaux" data-groupe="heure">(.*?)</div>#s',
			function ( $m ) use ( $pris ) {
				$interieur = preg_replace( '#<span class="est-pris">(\d\d:\d\d)</span>#', '<span data-valeur="$1">$1</span>', $m[1] );
				$interieur = preg_replace( '#data-valeur="(\d\d) h (\d\d)"#', 'data-valeur="$1:$2"', $interieur );
				return '<div class="creneaux" data-groupe="heure" data-pris="' . esc_attr( wp_json_encode( $pris ) ) . '">' . $interieur . '</div>';
			},
			$html,
			1
		);

		// Champ piège anti-robots dans le formulaire des coordonnées.
		$html = str_replace( '<form class="formulaire" onsubmit="return false">', '<form class="formulaire" onsubmit="return false">' . hg_champ_piege(), $html );
		return $html;
	}
);

/**
 * Grille du calendrier : du lundi de la semaine en cours, sur cinq semaines.
 *
 * @return array{0:string,1:string,2:array<string,string[]>} Titre, cellules, créneaux pris par date.
 */
function hg_calendrier() {
	$fuseau    = wp_timezone();
	$aujourdhui = new DateTimeImmutable( 'today', $fuseau );
	$debut     = $aujourdhui->modify( 'monday this week' );
	$fin       = $debut->modify( '+34 days' );
	$capacite  = max( 1, (int) get_option( 'hg_capacite_jour', 16 ) );

	// Demandes non annulées sur la période : comptes par jour et créneaux occupés.
	$compte = array();
	$pris   = array();
	$ids    = get_posts(
		array(
			'post_type'      => 'hg_demande',
			'post_status'    => 'private',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => '_hg_type', 'value' => 'rendez-vous' ),
				array( 'key' => '_hg_date', 'value' => array( $debut->format( 'Y-m-d' ), $fin->format( 'Y-m-d' ) ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
			),
		)
	);
	foreach ( $ids as $id ) {
		if ( 'annulee' === get_post_meta( $id, '_hg_statut', true ) ) {
			continue;
		}
		$jour            = get_post_meta( $id, '_hg_date', true );
		$compte[ $jour ] = ( $compte[ $jour ] ?? 0 ) + 1;
		$pris[ $jour ][] = get_post_meta( $id, '_hg_heure', true );
	}

	$cellules = '';
	for ( $jour = $debut; $jour <= $fin; $jour = $jour->modify( '+1 day' ) ) {
		$iso      = $jour->format( 'Y-m-d' );
		$numero   = (int) $jour->format( 'j' );
		$semaine  = (int) $jour->format( 'N' );
		$complet  = ( $compte[ $iso ] ?? 0 ) >= $capacite;
		if ( $jour <= $aujourdhui || $semaine >= 6 ) {
			$cellules .= '<span class="est-pris">' . $numero . '</span>';
		} elseif ( $complet ) {
			$cellules .= '<span class="est-pris complet">' . $numero . '</span>';
		} else {
			$libelle   = HG_JOURS[ $semaine - 1 ] . ' ' . ( 1 === $numero ? '1er' : $numero ) . ' ' . HG_MOIS[ (int) $jour->format( 'n' ) ] . ' ' . $jour->format( 'Y' );
			$cellules .= '<span data-valeur="' . esc_attr( $libelle ) . '" data-iso="' . esc_attr( $iso ) . '">' . $numero . '</span>';
		}
	}

	$m1    = (int) $debut->format( 'n' );
	$m2    = (int) $fin->format( 'n' );
	$titre = ucfirst( HG_MOIS[ $m1 ] ) . ( $m1 !== $m2 ? ' – ' . HG_MOIS[ $m2 ] : '' ) . ' ' . $fin->format( 'Y' );
	return array( $titre, $cellules, $pris );
}

/** Réception d'une demande (visiteurs connectés ou non). */
function hg_recevoir_rendez_vous() {
	if ( ! check_ajax_referer( 'hg_rendez_vous', 'jeton', false ) ) {
		wp_send_json_error( array( 'message' => 'La page a expiré. Rechargez-la puis recommencez.' ), 403 );
	}
	if ( ! empty( $_POST['site_web'] ) || hg_trop_de_demandes( 'rdv' ) ) {
		wp_send_json_error( array( 'message' => 'Trop de demandes depuis cette connexion. Écrivez-nous sur WhatsApp.' ), 429 );
	}
	$p = wp_unslash( $_POST );

	$type  = sanitize_key( $p['type'] ?? '' );
	$date  = sanitize_text_field( $p['date'] ?? '' );
	$heure = sanitize_text_field( $p['heure'] ?? '' );
	$tel   = preg_replace( '/\D/', '', (string) ( $p['tel'] ?? '' ) );

	$erreur = '';
	if ( ! isset( HG_CONSULTATIONS[ $type ] ) ) {
		$erreur = 'Choisissez un type de consultation.';
	} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $date <= wp_date( 'Y-m-d' ) || (int) wp_date( 'N', strtotime( $date ) ) >= 6 ) {
		$erreur = 'Choisissez une date disponible.';
	} elseif ( ! in_array( $heure, HG_CRENEAUX, true ) ) {
		$erreur = 'Choisissez un horaire.';
	} elseif ( '' === trim( $p['prenom'] ?? '' ) || '' === trim( $p['nom'] ?? '' ) ) {
		$erreur = 'Indiquez le prénom et le nom du patient.';
	} elseif ( strlen( $tel ) < 9 ) {
		$erreur = 'Indiquez un numéro de téléphone à 9 chiffres.';
	} elseif ( empty( $p['consentement'] ) ) {
		$erreur = 'Merci d’accepter l’utilisation de vos données pour ce rendez-vous.';
	}
	if ( $erreur ) {
		wp_send_json_error( array( 'message' => $erreur ), 400 );
	}

	list( , , $pris ) = hg_calendrier();
	if ( in_array( $heure, $pris[ $date ] ?? array(), true ) ) {
		wp_send_json_error( array( 'message' => 'Ce créneau vient d’être demandé. Choisissez-en un autre.' ), 409 );
	}

	$jour    = new DateTimeImmutable( $date, wp_timezone() );
	$numero  = (int) $jour->format( 'j' );
	$libelle = HG_JOURS[ (int) $jour->format( 'N' ) - 1 ] . ' ' . ( 1 === $numero ? '1er' : $numero ) . ' ' . HG_MOIS[ (int) $jour->format( 'n' ) ] . ' ' . $jour->format( 'Y' );

	$resultat = hg_creer_demande(
		'rendez-vous',
		array(
			'type_consult' => HG_CONSULTATIONS[ $type ],
			'date'         => $date,
			'date_libelle' => $libelle,
			'heure'        => $heure,
			'prenom'       => sanitize_text_field( $p['prenom'] ),
			'nom'          => sanitize_text_field( $p['nom'] ),
			'tel'          => '+224 ' . substr( $tel, -9 ),
			'mail'         => sanitize_email( $p['mail'] ?? '' ),
			'age'          => absint( $p['age'] ?? 0 ) ?: '',
			'premiere'     => 'oui' === ( $p['premiere'] ?? '' ) ? 'Oui' : 'Non',
			'motif'        => sanitize_textarea_field( mb_substr( (string) ( $p['motif'] ?? '' ), 0, 1000 ) ),
			'utilisateur'  => get_current_user_id(),
		)
	);
	if ( is_wp_error( $resultat ) ) {
		wp_send_json_error( array( 'message' => 'L’enregistrement a échoué. Écrivez-nous sur WhatsApp.' ), 500 );
	}
	wp_send_json_success( array( 'reference' => $resultat['reference'] ) );
}
add_action( 'wp_ajax_hg_rendez_vous', 'hg_recevoir_rendez_vous' );
add_action( 'wp_ajax_nopriv_hg_rendez_vous', 'hg_recevoir_rendez_vous' );

/**
 * Limite simple par adresse IP : 5 envois par heure et par formulaire.
 *
 * @param string $formulaire Nom court.
 * @return bool Vrai si la limite est dépassée.
 */
function hg_trop_de_demandes( $formulaire ) {
	$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$cle = 'hg_limite_' . $formulaire . '_' . md5( $ip );
	$n   = (int) get_transient( $cle );
	if ( $n >= 5 ) {
		return true;
	}
	set_transient( $cle, $n + 1, HOUR_IN_SECONDS );
	return false;
}
