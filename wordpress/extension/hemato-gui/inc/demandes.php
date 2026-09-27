<?php
/**
 * Demandes reçues : rendez-vous, messages de contact, accès professionnels.
 *
 * Un type de contenu privé : jamais public, jamais exposé à l'API REST, visible
 * seulement par les rôles qui ont hg_gerer_demandes (administrateur, secrétariat).
 * Aucune information médicale n'est affichée publiquement ni envoyée par e-mail.
 *
 * @package hemato-gui
 */

defined( 'ABSPATH' ) || exit;

const HG_TYPES_DEMANDE = array(
	'rendez-vous' => 'Rendez-vous',
	'contact'     => 'Message',
	'acces-pro'   => 'Accès professionnel',
);

const HG_STATUTS = array(
	'nouvelle'  => 'Nouvelle',
	'confirmee' => 'Confirmée',
	'traitee'   => 'Traitée',
	'annulee'   => 'Annulée',
);

/** Type de contenu « hg_demande ». */
function hg_enregistrer_demandes() {
	register_post_type(
		'hg_demande',
		array(
			'labels'              => array(
				'name'               => 'Demandes',
				'singular_name'      => 'Demande',
				'menu_name'          => 'Demandes',
				'all_items'          => 'Toutes les demandes',
				'edit_item'          => 'Demande',
				'search_items'       => 'Rechercher une demande',
				'not_found'          => 'Aucune demande.',
				'not_found_in_trash' => 'Aucune demande dans la corbeille.',
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // entrée ajoutée sous « HEMATO GUI », après le tableau de bord
			'show_in_rest'        => false,
			'show_in_nav_menus'   => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'capabilities'        => array(
				'create_posts'           => 'do_not_allow',
				'edit_post'              => 'hg_gerer_demandes',
				'read_post'              => 'hg_gerer_demandes',
				'delete_post'            => 'hg_gerer_demandes',
				'edit_posts'             => 'hg_gerer_demandes',
				'edit_others_posts'      => 'hg_gerer_demandes',
				'delete_posts'           => 'hg_gerer_demandes',
				'delete_others_posts'    => 'hg_gerer_demandes',
				'publish_posts'          => 'hg_gerer_demandes',
				'read_private_posts'     => 'hg_gerer_demandes',
				'edit_private_posts'     => 'hg_gerer_demandes',
				'delete_private_posts'   => 'hg_gerer_demandes',
				'edit_published_posts'   => 'hg_gerer_demandes',
				'delete_published_posts' => 'hg_gerer_demandes',
			),
			'map_meta_cap'        => false,
		)
	);
}
add_action( 'init', 'hg_enregistrer_demandes' );

/**
 * Crée une demande. Le titre ne contient que le type et une référence : pas de nom,
 * pas de motif, pour que les listes et les journaux restent anonymes.
 *
 * @param string $type    Clé de HG_TYPES_DEMANDE.
 * @param array  $donnees Champs déjà nettoyés.
 * @return array{id:int,reference:string}|WP_Error
 */
function hg_creer_demande( $type, array $donnees ) {
	$reference = hg_nouvelle_reference( $type );
	$id        = wp_insert_post(
		array(
			'post_type'   => 'hg_demande',
			'post_status' => 'private',
			'post_title'  => HG_TYPES_DEMANDE[ $type ] . ' · ' . $reference,
			'post_author' => get_current_user_id(),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, '_hg_type', $type );
	update_post_meta( $id, '_hg_statut', 'nouvelle' );
	update_post_meta( $id, '_hg_reference', $reference );
	foreach ( $donnees as $cle => $valeur ) {
		update_post_meta( $id, '_hg_' . $cle, $valeur );
	}
	hg_notifier_equipe( $type, $reference, $id );
	return array(
		'id'        => $id,
		'reference' => $reference,
	);
}

/**
 * Référence lisible : HG-AAAA-MM-NNNN (numéro du mois).
 *
 * @param string $type Type de demande.
 * @return string
 */
function hg_nouvelle_reference( $type ) {
	$prefixe = array(
		'rendez-vous' => 'HG',
		'contact'     => 'HGM',
		'acces-pro'   => 'HGP',
	)[ $type ] ?? 'HG';
	$mois    = wp_date( 'Y-m' );
	$cle     = 'hg_compteur_' . $prefixe . '_' . str_replace( '-', '', $mois );
	$numero  = (int) get_option( $cle, 0 ) + 1;
	update_option( $cle, $numero, false );
	return sprintf( '%s-%s-%04d', $prefixe, $mois, $numero );
}

/**
 * Prévient l'équipe par e-mail — sans aucune donnée de santé ni identité :
 * seulement le type, la référence et le lien vers l'administration.
 *
 * @param string $type      Type.
 * @param string $reference Référence.
 * @param int    $id        Demande.
 */
function hg_notifier_equipe( $type, $reference, $id ) {
	$destinataire = get_option( 'hg_email_equipe', get_option( 'admin_email' ) );
	$sujet        = sprintf( '[HEMATO GUI] %s — %s', HG_TYPES_DEMANDE[ $type ], $reference );
	$message      = "Une nouvelle demande est arrivée sur le site.\n\n"
		. 'Type : ' . HG_TYPES_DEMANDE[ $type ] . "\n"
		. 'Référence : ' . $reference . "\n\n"
		. "Consulter la demande (connexion requise) :\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n\n"
		. "Par confidentialité, cet e-mail ne contient ni l'identité ni le motif.";
	wp_mail( $destinataire, $sujet, $message );
}

/* ---------- Liste d'administration ---------- */

add_filter(
	'manage_hg_demande_posts_columns',
	function () {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => 'Référence',
			'hg_type'   => 'Type',
			'hg_qui'    => 'Personne',
			'hg_quand'  => 'Créneau demandé',
			'hg_statut' => 'Statut',
			'date'      => 'Reçue le',
		);
	}
);

add_action(
	'manage_hg_demande_posts_custom_column',
	function ( $colonne, $id ) {
		$m = fn( $cle ) => (string) get_post_meta( $id, '_hg_' . $cle, true );
		switch ( $colonne ) {
			case 'hg_type':
				echo esc_html( HG_TYPES_DEMANDE[ $m( 'type' ) ] ?? '—' );
				break;
			case 'hg_qui':
				echo esc_html( trim( $m( 'prenom' ) . ' ' . $m( 'nom' ) ) ?: '—' );
				if ( $m( 'tel' ) ) {
					echo '<br><a href="' . esc_url( 'https://wa.me/' . preg_replace( '/\D/', '', $m( 'tel' ) ) ) . '" target="_blank" rel="noopener">' . esc_html( $m( 'tel' ) ) . '</a>';
				}
				break;
			case 'hg_quand':
				echo esc_html( $m( 'date_libelle' ) ? $m( 'date_libelle' ) . ' · ' . $m( 'heure' ) : '—' );
				break;
			case 'hg_statut':
				$statut = $m( 'statut' ) ?: 'nouvelle';
				echo '<span class="hg-statut hg-statut-' . esc_attr( $statut ) . '">' . esc_html( HG_STATUTS[ $statut ] ?? $statut ) . '</span>';
				break;
		}
	},
	10,
	2
);

// Filtre par type dans la liste.
add_action(
	'restrict_manage_posts',
	function ( $type_contenu ) {
		if ( 'hg_demande' !== $type_contenu ) {
			return;
		}
		$courant = sanitize_key( $_GET['hg_type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="hg_type"><option value="">Tous les types</option>';
		foreach ( HG_TYPES_DEMANDE as $cle => $libelle ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $cle ), selected( $courant, $cle, false ), esc_html( $libelle ) );
		}
		echo '</select>';
	}
);
add_action(
	'pre_get_posts',
	function ( $requete ) {
		if ( ! is_admin() || ! $requete->is_main_query() || 'hg_demande' !== $requete->get( 'post_type' ) ) {
			return;
		}
		$type = sanitize_key( $_GET['hg_type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $type && isset( HG_TYPES_DEMANDE[ $type ] ) ) {
			$requete->set( 'meta_key', '_hg_type' );
			$requete->set( 'meta_value', $type );
		}
	}
);

/* ---------- Fiche d'une demande : lecture + changement de statut ---------- */

add_action(
	'add_meta_boxes_hg_demande',
	function () {
		add_meta_box( 'hg_detail', 'Détail de la demande', 'hg_boite_detail', 'hg_demande', 'normal', 'high' );
		add_meta_box( 'hg_statut', 'Statut', 'hg_boite_statut', 'hg_demande', 'side', 'high' );
		remove_meta_box( 'submitdiv', 'hg_demande', 'side' );
	}
);

/**
 * Affiche les champs de la demande (lecture seule).
 *
 * @param WP_Post $demande Demande.
 */
function hg_boite_detail( $demande ) {
	$libelles = array(
		'reference'     => 'Référence',
		'type_consult'  => 'Consultation',
		'date_libelle'  => 'Date souhaitée',
		'heure'         => 'Heure souhaitée',
		'prenom'        => 'Prénom',
		'nom'           => 'Nom',
		'tel'           => 'Téléphone',
		'mail'          => 'E-mail',
		'age'           => 'Âge du patient',
		'premiere'      => 'Première consultation',
		'motif'         => 'Motif',
		'objet'         => 'Objet',
		'message'       => 'Message',
		'profession'    => 'Profession',
		'ordre'         => 'N° d’Ordre',
		'etablissement' => 'Établissement',
	);
	echo '<table class="widefat striped"><tbody>';
	foreach ( $libelles as $cle => $libelle ) {
		$valeur = get_post_meta( $demande->ID, '_hg_' . $cle, true );
		if ( '' === $valeur || null === $valeur ) {
			continue;
		}
		printf( '<tr><th style="width:200px">%s</th><td>%s</td></tr>', esc_html( $libelle ), nl2br( esc_html( $valeur ) ) );
	}
	echo '</tbody></table>';
	$tel = preg_replace( '/\D/', '', (string) get_post_meta( $demande->ID, '_hg_tel', true ) );
	if ( $tel ) {
		printf( '<p><a class="button" href="%s" target="_blank" rel="noopener">Répondre sur WhatsApp</a></p>', esc_url( 'https://wa.me/' . $tel ) );
	}
}

/**
 * Statut + actions.
 *
 * @param WP_Post $demande Demande.
 */
function hg_boite_statut( $demande ) {
	$statut = get_post_meta( $demande->ID, '_hg_statut', true ) ?: 'nouvelle';
	wp_nonce_field( 'hg_statut_' . $demande->ID, 'hg_jeton_statut' );
	echo '<p><select name="hg_statut" style="width:100%">';
	foreach ( HG_STATUTS as $cle => $libelle ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $cle ), selected( $statut, $cle, false ), esc_html( $libelle ) );
	}
	echo '</select></p>';
	if ( 'acces-pro' === get_post_meta( $demande->ID, '_hg_type', true ) && ! get_post_meta( $demande->ID, '_hg_compte', true ) ) {
		echo '<p><label><input type="checkbox" name="hg_creer_compte" value="1"> Vérification faite : créer le compte professionnel et envoyer le lien de connexion</label></p>';
	}
	submit_button( 'Enregistrer', 'primary', 'publish', false );
}

add_action(
	'save_post_hg_demande',
	function ( $id ) {
		if ( ! isset( $_POST['hg_jeton_statut'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hg_jeton_statut'] ), 'hg_statut_' . $id ) ) {
			return;
		}
		if ( ! current_user_can( 'hg_gerer_demandes' ) ) {
			return;
		}
		$statut = sanitize_key( $_POST['hg_statut'] ?? '' );
		if ( isset( HG_STATUTS[ $statut ] ) ) {
			update_post_meta( $id, '_hg_statut', $statut );
		}
		if ( ! empty( $_POST['hg_creer_compte'] ) && current_user_can( 'create_users' ) ) {
			hg_creer_compte_pro( $id );
		}
	}
);

/**
 * Accès professionnel validé : compte « hg_pro » + e-mail de création de mot de passe.
 *
 * @param int $id Demande d'accès.
 */
function hg_creer_compte_pro( $id ) {
	$mail = sanitize_email( get_post_meta( $id, '_hg_mail', true ) );
	if ( ! is_email( $mail ) ) {
		return;
	}
	$utilisateur = get_user_by( 'email', $mail );
	if ( $utilisateur ) {
		$utilisateur->add_role( 'hg_pro' );
		$uid = $utilisateur->ID;
	} else {
		$uid = wp_insert_user(
			array(
				'user_login' => sanitize_user( strtok( $mail, '@' ) . '-' . wp_generate_password( 4, false ), true ),
				'user_email' => $mail,
				'user_pass'  => wp_generate_password( 24 ),
				'role'       => 'hg_pro',
			)
		);
		if ( is_wp_error( $uid ) ) {
			return;
		}
		wp_new_user_notification( $uid, null, 'user' );
	}
	update_post_meta( $id, '_hg_compte', $uid );
	update_post_meta( $id, '_hg_statut', 'traitee' );
}

/* ---------- Données personnelles : export, effacement, durée de conservation ---------- */

add_filter(
	'wp_privacy_personal_data_exporters',
	function ( $exporteurs ) {
		$exporteurs['hemato-gui'] = array(
			'exporter_friendly_name' => 'Demandes HEMATO GUI',
			'callback'               => function ( $mail ) {
				$items = array();
				foreach ( hg_demandes_par_mail( $mail ) as $id ) {
					$donnees = array();
					foreach ( get_post_meta( $id ) as $cle => $valeurs ) {
						if ( str_starts_with( $cle, '_hg_' ) ) {
							$donnees[] = array( 'name' => substr( $cle, 4 ), 'value' => (string) $valeurs[0] );
						}
					}
					$items[] = array(
						'group_id'    => 'hg_demandes',
						'group_label' => 'Demandes HEMATO GUI',
						'item_id'     => 'hg-demande-' . $id,
						'data'        => $donnees,
					);
				}
				return array( 'data' => $items, 'done' => true );
			},
		);
		return $exporteurs;
	}
);
add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $effaceurs ) {
		$effaceurs['hemato-gui'] = array(
			'eraser_friendly_name' => 'Demandes HEMATO GUI',
			'callback'             => function ( $mail ) {
				$ids = hg_demandes_par_mail( $mail );
				foreach ( $ids as $id ) {
					wp_delete_post( $id, true );
				}
				return array( 'items_removed' => count( $ids ), 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $effaceurs;
	}
);

/**
 * Demandes liées à une adresse e-mail.
 *
 * @param string $mail Adresse.
 * @return int[]
 */
function hg_demandes_par_mail( $mail ) {
	return get_posts(
		array(
			'post_type'      => 'hg_demande',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_hg_mail', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => sanitize_email( $mail ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
}

// Conservation limitée : suppression automatique des demandes de plus de 24 mois (réglable).
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'hg_purge_demandes' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hg_purge_demandes' );
		}
	}
);
add_action(
	'hg_purge_demandes',
	function () {
		$mois = max( 1, (int) get_option( 'hg_conservation_mois', 24 ) );
		$ids  = get_posts(
			array(
				'post_type'      => 'hg_demande',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'date_query'     => array( array( 'before' => $mois . ' months ago' ) ),
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}
);
