<?php
/**
 * Polices, styles et scripts.
 *
 * @package RevNefty
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google Fonts : Montserrat (titres, comme le logo) et Inter (texte).
 *
 * @return string
 */
function rn_fonts_url() {
	return 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@300;500;700;800&display=swap';
}

/**
 * Version d'un fichier (date de modification) pour le cache navigateur.
 *
 * @param string $path Chemin relatif au thème.
 * @return string
 */
function rn_asset_version( $path ) {
	$file = RN_DIR . '/' . $path;
	return file_exists( $file ) ? RN_VERSION . '.' . filemtime( $file ) : RN_VERSION;
}

/**
 * Styles et scripts du site.
 */
function rn_enqueue_assets() {
	wp_enqueue_style( 'rn-fonts', rn_fonts_url(), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_enqueue_style( 'rn-main', RN_URI . '/assets/css/main.css', array(), rn_asset_version( 'assets/css/main.css' ) );
	wp_enqueue_script(
		'rn-main',
		RN_URI . '/assets/js/main.js',
		array(),
		rn_asset_version( 'assets/js/main.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'rn_enqueue_assets' );

/**
 * Préconnexion aux serveurs de polices.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Type de relation.
 * @return array
 */
function rn_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'rn_resource_hints', 10, 2 );

/**
 * Couleur de la barre du navigateur et classe JS.
 */
function rn_head_script() {
	?>
	<script>document.documentElement.className=document.documentElement.className.replace(/\bno-js\b/,'js');</script>
	<?php if ( ! rn_is_app() ) : ?>
	<meta name="theme-color" content="#070B09">
	<?php endif; ?>
	<meta name="color-scheme" content="dark">
	<?php
}
add_action( 'wp_head', 'rn_head_script', 0 );
