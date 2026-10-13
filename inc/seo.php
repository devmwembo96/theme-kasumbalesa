<?php
/**
 * Balises SEO : description, Open Graph, Twitter Card, données structurées.
 * Désactivé automatiquement si une extension SEO (Yoast, Rank Math, AIOSEO, SEOPress) est active.
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Une extension SEO gère-t-elle déjà les balises ?
 *
 * @return bool
 */
function kb_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

/**
 * Nettoie un texte pour une balise meta.
 *
 * @param string $text Texte.
 * @return string
 */
function kb_seo_clean( $text ) {
	$text = wp_strip_all_tags( strip_shortcodes( (string) $text ), true );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 160 ) {
		$text = rtrim( mb_substr( $text, 0, 157 ), " ,.;:" ) . '…';
	}
	return $text;
}

/**
 * Description de la page courante.
 *
 * @return string
 */
function kb_seo_description() {
	$default = kb_mod( 'seo_description' );
	$default = $default ? $default : get_bloginfo( 'description' );

	if ( is_singular() && ! is_front_page() ) {
		$post = get_queried_object();
		if ( has_excerpt( $post ) ) {
			return kb_seo_clean( $post->post_excerpt );
		}
		$text = kb_seo_clean( excerpt_remove_blocks( $post->post_content ) );
		return $text ? $text : kb_seo_clean( $default );
	}
	if ( ( is_category() || is_tag() || is_tax() ) && term_description() ) {
		return kb_seo_clean( term_description() );
	}
	return kb_seo_clean( $default );
}

/**
 * Grande image de partage : image mise en avant > image par défaut > 1re image du héros > logo.
 *
 * @return array|null [url, largeur, hauteur, alt]
 */
function kb_seo_image() {
	$id = 0;
	if ( is_singular() && has_post_thumbnail( get_queried_object_id() ) ) {
		$id = get_post_thumbnail_id( get_queried_object_id() );
	}
	if ( ! $id ) {
		$id = absint( get_theme_mod( 'seo_image', 0 ) );
	}
	if ( ! $id ) {
		$slides = kb_hero_slide_ids();
		$id     = $slides ? $slides[0] : 0;
	}
	if ( ! $id ) {
		$id = absint( get_theme_mod( 'custom_logo', 0 ) );
	}
	if ( ! $id ) {
		return null;
	}
	$src = wp_get_attachment_image_src( $id, 'kb-wide' );
	if ( ! $src ) {
		return null;
	}
	return array( $src[0], $src[1], $src[2], (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
}

/**
 * Affiche les balises.
 */
function kb_seo_meta() {
	if ( kb_seo_plugin_active() ) {
		return;
	}

	$title = wp_get_document_title();
	$desc  = kb_seo_description();
	$image = kb_seo_image();
	$type  = is_singular( 'post' ) ? 'article' : 'website';

	if ( is_singular() ) {
		$url = get_permalink( get_queried_object_id() );
	} elseif ( is_front_page() ) {
		$url = home_url( '/' );
	} else {
		global $wp;
		$url = home_url( add_query_arg( array(), $wp->request ) );
	}

	$tags = array(
		array( 'name', 'description', $desc ),
		array( 'property', 'og:locale', str_replace( '-', '_', get_bloginfo( 'language' ) ) ),
		array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
		array( 'property', 'og:type', $type ),
		array( 'property', 'og:title', $title ),
		array( 'property', 'og:description', $desc ),
		array( 'property', 'og:url', $url ),
		array( 'name', 'twitter:card', $image ? 'summary_large_image' : 'summary' ),
		array( 'name', 'twitter:title', $title ),
		array( 'name', 'twitter:description', $desc ),
	);

	if ( $image ) {
		$tags[] = array( 'property', 'og:image', $image[0] );
		$tags[] = array( 'property', 'og:image:width', $image[1] );
		$tags[] = array( 'property', 'og:image:height', $image[2] );
		if ( $image[3] ) {
			$tags[] = array( 'property', 'og:image:alt', $image[3] );
		}
		$tags[] = array( 'name', 'twitter:image', $image[0] );
	}

	if ( 'article' === $type ) {
		$tags[] = array( 'property', 'article:published_time', get_the_date( DATE_W3C, get_queried_object_id() ) );
		$tags[] = array( 'property', 'article:modified_time', get_the_modified_date( DATE_W3C, get_queried_object_id() ) );
	}

	echo "\n";
	foreach ( $tags as $tag ) {
		if ( '' === (string) $tag[2] ) {
			continue;
		}
		printf( '<meta %s="%s" content="%s">' . "\n", esc_attr( $tag[0] ), esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
	}

	if ( ! is_singular() && ! is_front_page() && ( is_home() || is_archive() || is_search() ) ) {
		// Les pages de liste paginées gardent leur URL propre.
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
	}

	if ( is_front_page() ) {
		kb_seo_schema();
	}
}
add_action( 'wp_head', 'kb_seo_meta', 2 );

/**
 * Données structurées schema.org (administration municipale) sur l'accueil.
 */
function kb_seo_schema() {
	$same_as = array();
	foreach ( array_keys( kb_social_networks() ) as $key ) {
		$url = kb_mod( 'social_' . $key );
		if ( $url ) {
			$same_as[] = $url;
		}
	}

	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'GovernmentOrganization',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
		'areaServed' => array(
			'@type'          => 'City',
			'name'           => 'Kasumbalesa',
			'containedInPlace' => array(
				'@type' => 'AdministrativeArea',
				'name'  => 'Haut-Katanga, République Démocratique du Congo',
			),
		),
	);

	$logo = get_theme_mod( 'custom_logo' );
	if ( $logo ) {
		$data['logo'] = wp_get_attachment_image_url( $logo, 'full' );
	}
	if ( kb_mod( 'contact_email' ) ) {
		$data['email'] = kb_mod( 'contact_email' );
	}
	if ( kb_mod( 'contact_phone' ) ) {
		$data['telephone'] = preg_replace( '/[^0-9+]/', '', kb_mod( 'contact_phone' ) );
	}
	if ( kb_mod( 'contact_address' ) ) {
		$data['address'] = preg_replace( '/\s*\n\s*/', ', ', trim( kb_mod( 'contact_address' ) ) );
	}
	if ( $same_as ) {
		$data['sameAs'] = $same_as;
	}

	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
