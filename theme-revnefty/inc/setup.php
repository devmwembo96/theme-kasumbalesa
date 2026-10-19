<?php
/**
 * Configuration du thème : fonctionnalités, menus, Gutenberg, icônes du site.
 *
 * @package RevNefty
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $content_width ) ) {
	$content_width = 760;
}

/**
 * Déclare les fonctionnalités du thème.
 */
function rn_setup() {
	load_theme_textdomain( 'revnefty', RN_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 120,
			'width'       => 480,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'customize-selective-refresh-widgets' );

	// Gutenberg (éditeur sombre, comme le site).
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'dark-editor-style' );
	add_editor_style( array( rn_fonts_url(), 'assets/css/editor.css' ) );

	add_theme_support(
		'editor-color-palette',
		array(
			array( 'name' => __( 'Vert RevNefty', 'revnefty' ), 'slug' => 'green', 'color' => '#3DBE4B' ),
			array( 'name' => __( 'Vert profond', 'revnefty' ), 'slug' => 'green-deep', 'color' => '#1E7F33' ),
			array( 'name' => __( 'Menthe', 'revnefty' ), 'slug' => 'mint', 'color' => '#9BE7A6' ),
			array( 'name' => __( 'Noir', 'revnefty' ), 'slug' => 'ink', 'color' => '#070B09' ),
			array( 'name' => __( 'Carbone', 'revnefty' ), 'slug' => 'carbon', 'color' => '#111915' ),
			array( 'name' => __( 'Ardoise', 'revnefty' ), 'slug' => 'slate', 'color' => '#1B2620' ),
			array( 'name' => __( 'Gris clair', 'revnefty' ), 'slug' => 'mist', 'color' => '#A9B8AF' ),
			array( 'name' => __( 'Blanc', 'revnefty' ), 'slug' => 'white', 'color' => '#FFFFFF' ),
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Menu principal', 'revnefty' ),
			'footer'  => __( 'Pied de page – navigation', 'revnefty' ),
			'legal'   => __( 'Pied de page – mentions légales', 'revnefty' ),
		)
	);

	add_image_size( 'rn-card', 800, 500, true );
	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'after_setup_theme', 'rn_setup' );

/**
 * Classes du <body>.
 *
 * @param array $classes Classes.
 * @return array
 */
function rn_body_classes( $classes ) {
	if ( rn_is_app() ) {
		$classes[] = 'rn-app';
	}
	if ( rn_mod( 'ticker_enable' ) && is_front_page() ) {
		$classes[] = 'rn-has-ticker';
	}
	return $classes;
}
add_filter( 'body_class', 'rn_body_classes' );

/**
 * Extraits courts.
 */
add_filter(
	'excerpt_length',
	static function () {
		return 24;
	}
);
add_filter(
	'excerpt_more',
	static function () {
		return '…';
	}
);

/**
 * Séparateur du titre du document.
 */
add_filter(
	'document_title_separator',
	static function () {
		return '–';
	}
);

/**
 * Menu par défaut si aucun menu n'est assigné : ancres de la page d'accueil.
 *
 * @param array $args Arguments de wp_nav_menu.
 */
function rn_menu_fallback( $args ) {
	$home  = home_url( '/' );
	$items = array(
		'#methode' => __( 'Méthode', 'revnefty' ),
		'#outils'  => __( 'Outils', 'revnefty' ),
		'#cadre'   => __( 'Règles', 'revnefty' ),
		'#faq'     => __( 'FAQ', 'revnefty' ),
		'#contact' => __( 'Contact', 'revnefty' ),
	);
	$html  = '';
	foreach ( $items as $anchor => $label ) {
		$html .= '<li class="menu-item"><a href="' . esc_url( $home . $anchor ) . '">' . esc_html( $label ) . '</a></li>';
	}
	printf( '<ul class="%s">%s</ul>', esc_attr( $args['menu_class'] ), $html ); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé ci-dessus.
}

/**
 * Icônes du site (onglet, écran d'accueil) quand aucune « icône du site » n'est choisie.
 */
function rn_site_icons() {
	if ( has_site_icon() ) {
		return;
	}
	printf( '<link rel="icon" href="%s" sizes="32x32">' . "\n", esc_url( RN_URI . '/assets/img/icon-32.png' ) );
	printf( '<link rel="icon" href="%s" sizes="192x192">' . "\n", esc_url( RN_URI . '/assets/img/icon-192.png' ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( RN_URI . '/assets/img/icon-180.png' ) );
}
add_action( 'wp_head', 'rn_site_icons', 2 );
