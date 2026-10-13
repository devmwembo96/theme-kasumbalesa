<?php
/**
 * Réglages initiaux du site de la Ville, appliqués une seule fois à l'activation du thème.
 *
 * Ne remplace jamais une valeur déjà saisie dans le Customizer, et ignore les médias
 * ou pages absents : sans effet sur un autre site.
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applique les réglages initiaux (logo, héros, coordonnées, chiffres, menus…).
 */
function kb_site_setup() {
	if ( get_option( 'kb_site_setup_done' ) ) {
		return;
	}

	$set = static function ( $key, $value ) {
		if ( '' === (string) get_theme_mod( $key, '' ) && '' !== (string) $value ) {
			set_theme_mod( $key, $value );
		}
	};
	$media = static function ( $id ) {
		return ( $id && 'attachment' === get_post_type( $id ) ) ? $id : 0;
	};

	// Identité et héros (photos de la médiathèque de la Ville).
	$set( 'custom_logo', $media( 6043 ) );
	foreach ( array( 7307, 7236, 7241 ) as $i => $id ) {
		$set( 'hero_slide_' . ( $i + 1 ), $media( $id ) );
	}

	// Coordonnées et réseaux.
	$set( 'contact_phone', '+243 808 828 262' );
	$set( 'contact_email', 'contact@ville-de-kasumbalesa.cd' );
	$set( 'contact_address', "Avenue de l'Administration, n° 01\nKasumbalesa, Haut-Katanga\nRépublique Démocratique du Congo" );
	$set( 'social_facebook', 'https://www.facebook.com/Ville-de-Kasumbalesa-101543848014261/' );

	// Toponymie.
	$set( 'topo_image', $media( 7237 ) );
	$set( 'topo_text', "La ville de Kasumbalesa tire son nom de la langue locale lamba : « kasumba ka lesa », la montagne de Dieu.\n\nUne évocation de la tentaculaire montagne qui la surplombe et attise la curiosité des visiteurs." );
	$set( 'topo_link_url', home_url( '/historique/' ) );

	// Chiffres clés.
	$figures = array(
		1 => array( '1240', ' m', 'd’altitude' ),
		2 => array( '866', ' km²', 'de superficie' ),
		3 => array( '1000000', '+', 'habitants' ),
		4 => array( '2018', '', 'création de la ville' ),
	);
	foreach ( $figures as $i => $figure ) {
		$set( "fig_{$i}_number", $figure[0] );
		$set( "fig_{$i}_suffix", $figure[1] );
		$set( "fig_{$i}_label", $figure[2] );
	}

	// Page « Mot du Maire » (si elle existe et est publiée).
	$mayor = get_page_by_path( 'mot-du-maire' );
	if ( $mayor && 'publish' === $mayor->post_status ) {
		$set( 'mayor_page', $mayor->ID );
	}

	// Contenu de la page d'accueil affiché sous les sections.
	$set( 'front_show_content', 1 );

	// Menu existant « Main Menu » sur les emplacements libres du thème.
	$menu      = wp_get_nav_menu_object( 'Main Menu' );
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( $menu ) {
		foreach ( array( 'primary', 'footer' ) as $location ) {
			if ( empty( $locations[ $location ] ) ) {
				$locations[ $location ] = (int) $menu->term_id;
			}
		}
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	update_option( 'kb_site_setup_done', gmdate( 'c' ), false );
}
add_action( 'after_switch_theme', 'kb_site_setup' );
