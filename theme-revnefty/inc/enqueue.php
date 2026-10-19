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
 * Contenu d'un fichier du thème, pour l'intégrer à la page : le cache de l'hébergeur ignore le
 * paramètre de version des fichiers statiques et servirait l'ancienne feuille après une mise à jour.
 *
 * @param string $path Chemin relatif au thème.
 * @return string
 */
function rn_asset_inline( $path ) {
	$file = RN_DIR . '/' . $path;
	$code = file_exists( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	return str_replace( 'url("../', 'url("' . RN_URI . '/assets/', $code );
}

/**
 * Styles et scripts du site.
 */
function rn_enqueue_assets() {
	wp_enqueue_style( 'rn-fonts', rn_fonts_url(), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_register_style( 'rn-main', false, array(), RN_VERSION );
	wp_enqueue_style( 'rn-main' );
	wp_add_inline_style( 'rn-main', rn_asset_inline( 'assets/css/main.css' ) );
	wp_register_script( 'rn-main', false, array(), RN_VERSION, true );
	wp_enqueue_script( 'rn-main' );
	wp_add_inline_script( 'rn-main', rn_asset_inline( 'assets/js/main.js' ) );
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
