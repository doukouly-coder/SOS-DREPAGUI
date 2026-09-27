<?php
/**
 * SEO de base : titre et description de chaque page (repris de la maquette),
 * Open Graph, données structurées de l'organisation.
 *
 * S'efface si une extension SEO dédiée (Yoast, Rank Math, SEOPress) est active.
 *
 * @package hemato-gui
 */

defined( 'ABSPATH' ) || exit;

/** Une extension SEO complète est-elle active ? */
function hg_seo_externe() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

// Champs de page enregistrés (et modifiables dans l'éditeur, panneau « Champs personnalisés »).
add_action(
	'init',
	function () {
		foreach ( array( 'page', 'post' ) as $type ) {
			foreach ( array( 'hg_titre_seo', 'hg_description' ) as $cle ) {
				register_post_meta(
					$type,
					$cle,
					array(
						'type'          => 'string',
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => fn() => current_user_can( 'edit_posts' ),
					)
				);
			}
		}
	}
);

add_filter(
	'pre_get_document_title',
	function ( $titre ) {
		if ( hg_seo_externe() || ! is_singular( array( 'page', 'post' ) ) ) {
			return $titre;
		}
		$seo = get_post_meta( get_queried_object_id(), 'hg_titre_seo', true );
		return $seo ? $seo : $titre;
	}
);

add_action(
	'wp_head',
	function () {
		if ( hg_seo_externe() ) {
			return;
		}
		$titre       = wp_get_document_title();
		$description = is_singular() ? get_post_meta( get_queried_object_id(), 'hg_description', true ) : '';
		if ( ! $description ) {
			$description = get_bloginfo( 'description' );
		}
		$url = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:type" content="%s">' . "\n", is_singular( 'post' ) ? 'article' : 'website' );
		if ( is_singular() && has_post_thumbnail() ) {
			printf( '<meta property="og:image" content="%s">' . "\n", esc_url( get_the_post_thumbnail_url( null, 'full' ) ) );
		}
		printf( '<meta property="og:locale" content="fr_FR">' . "\n" );
		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $titre ) );
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
		printf( '<meta name="theme-color" content="#FFFFFF">' . "\n" );

		if ( is_front_page() ) {
			$organisation = array(
				'@context'    => 'https://schema.org',
				'@type'       => 'MedicalOrganization',
				'name'        => 'HEMATO GUI',
				'description' => 'Plateforme d’hématologie pour la Guinée et l’Afrique francophone : informer, former et traiter les maladies du sang.',
				'url'         => home_url( '/' ),
				'telephone'   => '+224628733143',
				'medicalSpecialty' => 'Hematologic',
				'address'     => array(
					'@type'           => 'PostalAddress',
					'addressLocality' => 'Conakry',
					'addressCountry'  => 'GN',
				),
				'contactPoint' => array(
					'@type'             => 'ContactPoint',
					'telephone'         => '+224628733143',
					'contactType'       => 'customer service',
					'availableLanguage' => 'French',
				),
			);
			echo '<script type="application/ld+json">' . wp_json_encode( $organisation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
		}
	},
	2
);
