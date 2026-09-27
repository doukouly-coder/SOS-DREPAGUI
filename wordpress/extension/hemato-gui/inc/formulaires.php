<?php
/**
 * [hg_contact], [hg_connexion] (espace patient), [hg_acces_pro] (espace professionnel).
 *
 * Les gabarits sont ceux de la maquette ; l'extension les branche sur admin-post.php
 * (nonce, champ piège, limite d'envois) et sur la connexion WordPress.
 *
 * @package hemato-gui
 */

defined( 'ABSPATH' ) || exit;

/** Paramètre de retour ?hg=… après un envoi. */
function hg_retour() {
	return sanitize_key( wp_unslash( $_GET['hg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * Redirige vers la page d'origine avec un code de retour.
 *
 * @param string $code   Code.
 * @param string $ancre  Ancre de la page.
 */
function hg_revenir( $code, $ancre = '' ) {
	$origine = wp_get_referer() ?: home_url( '/' );
	wp_safe_redirect( add_query_arg( 'hg', $code, remove_query_arg( 'hg', $origine ) ) . ( $ancre ? '#' . $ancre : '' ) );
	exit;
}

/**
 * Champs cachés communs aux formulaires envoyés à admin-post.php.
 *
 * @param string $action Action.
 * @return string
 */
function hg_champs_caches( $action ) {
	return '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">'
		. wp_nonce_field( $action, 'hg_jeton', true, false )
		. hg_champ_piege();
}

/* ---------- Contact ---------- */

add_shortcode(
	'hg_contact',
	function () {
		$html = hg_gabarit( 'contact' );
		$html = hg_brancher_formulaire( $html, 'contact-formulaire', admin_url( 'admin-post.php' ), hg_champs_caches( 'hg_contact' ) );
		$retour = hg_retour();
		if ( 'contact-ok' === $retour ) {
			$html = hg_message_formulaire( $html, 'contact-formulaire', 'Merci, votre message est bien arrivé. Nous vous répondons rapidement, en priorité sur WhatsApp ou par téléphone.' );
		} elseif ( 'contact-erreur' === $retour ) {
			$html = hg_message_formulaire( $html, 'contact-formulaire', 'Le message n’a pas pu être envoyé. Vérifiez les champs, ou écrivez-nous sur WhatsApp.', true );
		}
		return $html;
	}
);

/** Réception du formulaire de contact. */
function hg_recevoir_contact() {
	$p = wp_unslash( $_POST );
	if ( ! wp_verify_nonce( sanitize_key( $p['hg_jeton'] ?? '' ), 'hg_contact' ) || ! empty( $p['site_web'] ) || hg_trop_de_demandes( 'contact' ) ) {
		hg_revenir( 'contact-erreur' );
	}
	$nom     = sanitize_text_field( $p['nom'] ?? '' );
	$message = sanitize_textarea_field( mb_substr( (string) ( $p['message'] ?? '' ), 0, 3000 ) );
	if ( '' === $nom || '' === $message || empty( $p['consentement'] ) ) {
		hg_revenir( 'contact-erreur' );
	}
	$objets = array( 'Consultation', 'eBooks', 'Espace professionnel', 'Partenariat', 'Presse', 'Autre' );
	$objet  = sanitize_text_field( $p['objet'] ?? '' );
	hg_creer_demande(
		'contact',
		array(
			'nom'     => $nom,
			'tel'     => ( $t = preg_replace( '/\D/', '', (string) ( $p['tel'] ?? '' ) ) ) ? '+224 ' . substr( $t, -9 ) : '',
			'mail'    => sanitize_email( $p['mail'] ?? '' ),
			'objet'   => in_array( $objet, $objets, true ) ? $objet : 'Autre',
			'message' => $message,
		)
	);
	hg_revenir( 'contact-ok' );
}
add_action( 'admin_post_hg_contact', 'hg_recevoir_contact' );
add_action( 'admin_post_nopriv_hg_contact', 'hg_recevoir_contact' );

/* ---------- Espace patient : connexion et création de compte ---------- */

add_shortcode(
	'hg_connexion',
	function () {
		if ( is_user_logged_in() ) {
			return hg_carte_compte( 'patient' );
		}
		$html = hg_gabarit( 'connexion' );
		$html = hg_brancher_formulaire(
			$html,
			'variante est-actif formulaire-carte',
			wp_login_url(),
			'<input type="hidden" name="redirect_to" value="' . esc_url( get_permalink() . '#connexion' ) . '"><input type="hidden" name="rememberme" value="forever">'
		);
		$html = hg_brancher_formulaire( $html, 'variante formulaire-carte', admin_url( 'admin-post.php' ), hg_champs_caches( 'hg_inscription' ) );
		$html = str_replace( '<a class="lien-discret" href="#">', '<a class="lien-discret" href="' . esc_url( wp_lostpassword_url( get_permalink() ) ) . '">', $html );
		if ( 'connexion-echec' === hg_retour() ) {
			$html = hg_message_formulaire( $html, 'variante est-actif formulaire-carte', 'Identifiant ou mot de passe incorrect.', true );
		}
		$messages = array(
			'inscription-erreur' => array( 'variante formulaire-carte', 'Le compte n’a pas pu être créé : vérifiez les champs (mot de passe de 8 caractères au moins).', true ),
			'inscription-existe' => array( 'variante formulaire-carte', 'Un compte existe déjà avec ce numéro ou cet e-mail. Connectez-vous, ou utilisez « Mot de passe oublié ».', true ),
		);
		$retour = hg_retour();
		if ( isset( $messages[ $retour ] ) ) {
			list( $classe, $texte, $erreur ) = $messages[ $retour ];
			$html = hg_message_formulaire( $html, $classe, $texte, $erreur );
			// Rouvrir l'onglet « Créer un compte » pour montrer le message.
			$html = str_replace(
				array( '<p class="choix-item est-actif">Se connecter</p>', '<p class="choix-item">Créer un compte</p>', 'class="variante est-actif formulaire-carte"', 'class="variante formulaire-carte"' ),
				array( '<p class="choix-item">Se connecter</p>', '<p class="choix-item est-actif">Créer un compte</p>', 'class="variante formulaire-carte hg-a"', 'class="variante est-actif formulaire-carte"' ),
				$html
			);
			$html = str_replace( 'class="variante formulaire-carte hg-a"', 'class="variante formulaire-carte"', $html );
		}
		return $html;
	}
);

/** Création d'un compte patient (rôle hg_patient) puis connexion. */
function hg_recevoir_inscription() {
	$p = wp_unslash( $_POST );
	if ( ! wp_verify_nonce( sanitize_key( $p['hg_jeton'] ?? '' ), 'hg_inscription' ) || ! empty( $p['site_web'] ) || hg_trop_de_demandes( 'inscription' ) ) {
		hg_revenir( 'inscription-erreur', 'connexion' );
	}
	$prenom = sanitize_text_field( $p['prenom'] ?? '' );
	$nom    = sanitize_text_field( $p['nom'] ?? '' );
	$tel    = substr( preg_replace( '/\D/', '', (string) ( $p['tel'] ?? '' ) ), -9 );
	$mail   = sanitize_email( $p['mail'] ?? '' );
	$mdp    = (string) ( $p['mdp'] ?? '' );
	if ( '' === $prenom || '' === $nom || strlen( $tel ) !== 9 || strlen( $mdp ) < 8 || empty( $p['consentement'] ) || ( $mail && ! is_email( $mail ) ) ) {
		hg_revenir( 'inscription-erreur', 'connexion' );
	}
	$identifiant = '224' . $tel;
	if ( username_exists( $identifiant ) || ( $mail && email_exists( $mail ) ) ) {
		hg_revenir( 'inscription-existe', 'connexion' );
	}
	$uid = wp_insert_user(
		array(
			'user_login'   => $identifiant,
			'user_pass'    => $mdp,
			'user_email'   => $mail,
			'first_name'   => $prenom,
			'last_name'    => $nom,
			'display_name' => $prenom,
			'role'         => 'hg_patient',
		)
	);
	if ( is_wp_error( $uid ) ) {
		hg_revenir( 'inscription-erreur', 'connexion' );
	}
	update_user_meta( $uid, 'hg_telephone', '+224 ' . $tel );
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true );
	hg_revenir( 'bienvenue', 'connexion' );
}
add_action( 'admin_post_nopriv_hg_inscription', 'hg_recevoir_inscription' );
add_action( 'admin_post_hg_inscription', 'hg_recevoir_inscription' );

// Connexion avec le numéro de téléphone tel qu'on le tape (« 622 00 00 00 », « +224… »).
add_filter(
	'authenticate',
	function ( $utilisateur, $identifiant, $mdp ) {
		$chiffres = preg_replace( '/\D/', '', (string) $identifiant );
		if ( $utilisateur instanceof WP_User || strlen( $chiffres ) < 9 || str_contains( (string) $identifiant, '@' ) ) {
			return $utilisateur;
		}
		$login = '224' . substr( $chiffres, -9 );
		return username_exists( $login ) ? wp_authenticate_username_password( null, $login, $mdp ) : $utilisateur;
	},
	25,
	3
);

// Les patients et les professionnels ne voient pas l'administration.
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}
		$u = wp_get_current_user();
		if ( array_intersect( array( 'hg_patient', 'hg_pro' ), $u->roles ) && ! current_user_can( 'edit_posts' ) && ! current_user_can( 'hg_gerer_demandes' ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
	}
);
add_filter(
	'show_admin_bar',
	function ( $montrer ) {
		return current_user_can( 'edit_posts' ) || current_user_can( 'hg_gerer_demandes' ) ? $montrer : false;
	}
);

/**
 * Carte affichée à la place des formulaires quand on est connecté.
 *
 * @param string $espace patient|pro.
 * @return string
 */
function hg_carte_compte( $espace ) {
	$u      = wp_get_current_user();
	$prenom = $u->first_name ?: $u->display_name;
	$ancre  = 'pro' === $espace ? 'acces' : 'connexion';
	$html   = '<div class="connexion-carte connexion-compte" id="' . esc_attr( $ancre ) . '">';
	$html  .= '<p class="surtitre">' . ( 'pro' === $espace ? 'Espace professionnel' : 'Mon espace patient' ) . '</p>';
	$html  .= '<p class="connexion-bonjour">Bonjour ' . esc_html( $prenom ) . '</p>';

	if ( 'pro' === $espace && ! current_user_can( 'hg_espace_pro' ) ) {
		$html .= '<p class="connexion-texte">Votre compte n’a pas encore l’accès professionnel. Envoyez une demande d’accès : elle est vérifiée avant ouverture.</p>';
	} elseif ( 'pro' === $espace ) {
		$html .= '<p class="connexion-texte">Votre accès professionnel est actif. La bibliothèque « Hématologie pratique » s’enrichit au fil des publications.</p>';
	} else {
		$html .= '<p class="connexion-texte">Vos demandes de rendez-vous envoyées depuis ce compte :</p>' . hg_mes_demandes( $u->ID );
	}
	$html .= '<div class="boutons boutons-gauche">';
	if ( 'patient' === $espace ) {
		$html .= '<a class="btn btn-rouge btn-grand" href="' . esc_url( home_url( '/rendez-vous/' ) ) . '">Prendre rendez-vous</a>';
	}
	$html .= '<a class="btn btn-contour btn-grand" href="' . esc_url( wp_logout_url( get_permalink() ) ) . '">Se déconnecter</a></div></div>';
	return $html;
}

/**
 * Demandes de rendez-vous du patient connecté — visibles par lui seul.
 *
 * @param int $uid Utilisateur.
 * @return string
 */
function hg_mes_demandes( $uid ) {
	$ids = get_posts(
		array(
			'post_type'      => 'hg_demande',
			'post_status'    => 'private',
			'fields'         => 'ids',
			'posts_per_page' => 5,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => '_hg_utilisateur', 'value' => (int) $uid ),
				array( 'key' => '_hg_type', 'value' => 'rendez-vous' ),
			),
		)
	);
	if ( ! $ids ) {
		return '<p class="connexion-note">Aucune demande pour le moment.</p>';
	}
	$html = '<ul class="liste-ligne mes-demandes">';
	foreach ( $ids as $id ) {
		$statut = get_post_meta( $id, '_hg_statut', true ) ?: 'nouvelle';
		$html  .= '<li><span><strong>' . esc_html( get_post_meta( $id, '_hg_type_consult', true ) ) . '</strong><small>'
			. esc_html( get_post_meta( $id, '_hg_date_libelle', true ) . ' · ' . get_post_meta( $id, '_hg_heure', true ) ) . '</small></span>'
			. '<em class="etat' . ( 'confirmee' === $statut ? ' etat-ok' : '' ) . '">' . esc_html( 'nouvelle' === $statut ? 'En attente' : ( HG_STATUTS[ $statut ] ?? $statut ) ) . '</em></li>';
	}
	return $html . '</ul>';
}

/* ---------- Espace professionnel : connexion et demande d'accès ---------- */

add_shortcode(
	'hg_acces_pro',
	function () {
		if ( is_user_logged_in() ) {
			return hg_carte_compte( 'pro' );
		}
		$html = hg_gabarit( 'acces-pro' );
		$html = hg_brancher_formulaire(
			$html,
			'variante est-actif formulaire-carte',
			wp_login_url(),
			'<input type="hidden" name="redirect_to" value="' . esc_url( get_permalink() . '#acces' ) . '">'
		);
		$html   = hg_brancher_formulaire( $html, 'variante formulaire-carte', admin_url( 'admin-post.php' ), hg_champs_caches( 'hg_acces_pro' ) );
		$retour = hg_retour();
		if ( 'connexion-echec' === $retour ) {
			$html = hg_message_formulaire( $html, 'variante est-actif formulaire-carte', 'Identifiant ou mot de passe incorrect.', true );
		}
		if ( in_array( $retour, array( 'acces-ok', 'acces-erreur' ), true ) ) {
			$ok   = 'acces-ok' === $retour;
			$html = hg_message_formulaire( $html, 'variante formulaire-carte', $ok ? 'Demande reçue. Nous vérifions votre inscription à l’Ordre, puis vous recevez un lien de connexion par e-mail.' : 'La demande n’a pas pu être envoyée : vérifiez les champs.', ! $ok );
			$html = str_replace(
				array( '<p class="choix-item est-actif">Connexion</p>', '<p class="choix-item">Demander un accès</p>', 'class="variante est-actif formulaire-carte"', 'class="variante formulaire-carte"' ),
				array( '<p class="choix-item">Connexion</p>', '<p class="choix-item est-actif">Demander un accès</p>', 'class="variante formulaire-carte hg-a"', 'class="variante est-actif formulaire-carte"' ),
				$html
			);
			$html = str_replace( 'class="variante formulaire-carte hg-a"', 'class="variante formulaire-carte"', $html );
		}
		return $html;
	}
);

/** Réception d'une demande d'accès professionnel (vérifiée à la main dans l'administration). */
function hg_recevoir_acces_pro() {
	$p = wp_unslash( $_POST );
	if ( ! wp_verify_nonce( sanitize_key( $p['hg_jeton'] ?? '' ), 'hg_acces_pro' ) || ! empty( $p['site_web'] ) || hg_trop_de_demandes( 'acces' ) ) {
		hg_revenir( 'acces-erreur', 'acces' );
	}
	$professions = array( 'Médecin', 'Pharmacien·ne', 'Biologiste', 'Infirmier·e', 'Sage-femme', 'Étudiant·e en santé' );
	$profession  = sanitize_text_field( $p['profession'] ?? '' );
	$mail        = sanitize_email( $p['mail'] ?? '' );
	$ordre       = sanitize_text_field( $p['ordre'] ?? '' );
	$etab        = sanitize_text_field( $p['etablissement'] ?? '' );
	if ( ! in_array( $profession, $professions, true ) || ! is_email( $mail ) || '' === $ordre || '' === $etab ) {
		hg_revenir( 'acces-erreur', 'acces' );
	}
	hg_creer_demande(
		'acces-pro',
		array(
			'profession'    => $profession,
			'ordre'         => $ordre,
			'etablissement' => $etab,
			'mail'          => $mail,
		)
	);
	hg_revenir( 'acces-ok', 'acces' );
}
add_action( 'admin_post_nopriv_hg_acces_pro', 'hg_recevoir_acces_pro' );
add_action( 'admin_post_hg_acces_pro', 'hg_recevoir_acces_pro' );

// Échec de connexion depuis les cartes du site : revenir sur la page, pas sur wp-login.php.
add_action(
	'wp_login_failed',
	function () {
		$origine = wp_get_referer();
		if ( $origine && ! str_contains( $origine, 'wp-login.php' ) && ! str_contains( $origine, 'wp-admin' ) ) {
			$ancre = str_contains( $origine, 'espace-pro' ) ? '#acces' : '#connexion';
			wp_safe_redirect( add_query_arg( 'hg', 'connexion-echec', remove_query_arg( 'hg', $origine ) ) . $ancre );
			exit;
		}
	}
);
