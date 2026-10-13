<?php
/**
 * Configuration du thème : fonctionnalités, menus, tailles d'images, Gutenberg.
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $content_width ) ) {
	$content_width = 760;
}

/**
 * Déclare les fonctionnalités du thème.
 */
function kb_setup() {
	load_theme_textdomain( 'kasumbalesa', KB_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 240,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'customize-selective-refresh-widgets' );

	// Gutenberg.
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( array( kb_fonts_url(), 'assets/css/editor.css' ) );

	add_theme_support(
		'editor-color-palette',
		array(
			array( 'name' => __( 'Vert montagne', 'kasumbalesa' ), 'slug' => 'green', 'color' => '#0B2A1F' ),
			array( 'name' => __( 'Vert forêt', 'kasumbalesa' ), 'slug' => 'green-light', 'color' => '#1F5C43' ),
			array( 'name' => __( 'Cuivre du Katanga', 'kasumbalesa' ), 'slug' => 'copper', 'color' => '#B87333' ),
			array( 'name' => __( 'Cuivre foncé', 'kasumbalesa' ), 'slug' => 'copper-dark', 'color' => '#8E4F1E' ),
			array( 'name' => __( 'Or', 'kasumbalesa' ), 'slug' => 'gold', 'color' => '#D4A853' ),
			array( 'name' => __( 'Ivoire', 'kasumbalesa' ), 'slug' => 'ivory', 'color' => '#FAF7F0' ),
			array( 'name' => __( 'Blanc', 'kasumbalesa' ), 'slug' => 'white', 'color' => '#FFFFFF' ),
			array( 'name' => __( 'Noir profond', 'kasumbalesa' ), 'slug' => 'ink', 'color' => '#0A0F0C' ),
		)
	);
	add_theme_support(
		'editor-font-sizes',
		array(
			array( 'name' => __( 'Petit', 'kasumbalesa' ), 'slug' => 'small', 'size' => 15 ),
			array( 'name' => __( 'Normal', 'kasumbalesa' ), 'slug' => 'normal', 'size' => 18 ),
			array( 'name' => __( 'Grand', 'kasumbalesa' ), 'slug' => 'large', 'size' => 24 ),
			array( 'name' => __( 'Très grand', 'kasumbalesa' ), 'slug' => 'huge', 'size' => 40 ),
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Menu principal', 'kasumbalesa' ),
			'footer'  => __( 'Pied de page – navigation', 'kasumbalesa' ),
			'legal'   => __( 'Pied de page – mentions légales', 'kasumbalesa' ),
		)
	);

	add_image_size( 'kb-hero', 2400, 1350, true );
	add_image_size( 'kb-wide', 1600, 1000, true );
	add_image_size( 'kb-card', 800, 560, true );
	add_image_size( 'kb-portrait', 900, 1125, true );
	add_image_size( 'kb-thumb', 240, 240, true );

	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'after_setup_theme', 'kb_setup' );

/**
 * Classes du <body>.
 *
 * @param array $classes Classes.
 * @return array
 */
function kb_body_classes( $classes ) {
	// En-tête clair et opaque sur toutes les pages : le contenu commence sous lui.
	$classes[] = 'kb-header-solid';
	if ( kb_ticker_is_visible() ) {
		$classes[] = 'kb-has-ticker';
	}
	return $classes;
}
add_filter( 'body_class', 'kb_body_classes' );

/**
 * Longueur et suite des extraits.
 */
add_filter(
	'excerpt_length',
	static function () {
		return 28;
	}
);
add_filter(
	'excerpt_more',
	static function () {
		return '…';
	}
);

/**
 * Le menu affiche « Accueil » + les pages principales si aucun menu n'est encore assigné.
 *
 * @param array $args Arguments de wp_nav_menu.
 */
function kb_menu_fallback( $args ) {
	$pages = wp_list_pages(
		array(
			'depth'       => 1,
			'number'      => 6,
			'title_li'    => '',
			'echo'        => false,
			'sort_column' => 'menu_order,post_title',
		)
	);
	printf( '<ul class="%s">%s</ul>', esc_attr( $args['menu_class'] ), $pages ); // phpcs:ignore WordPress.Security.EscapeOutput -- sortie de wp_list_pages.
}

/**
 * Séparateur du titre du document.
 */
add_filter(
	'document_title_separator',
	static function () {
		return '–';
	}
);
