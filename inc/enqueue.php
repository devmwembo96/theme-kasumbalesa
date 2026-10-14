<?php
/**
 * Chargement des polices, styles et scripts.
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL Google Fonts : Roboto, la police de l'ancien site (titres en Roboto Light).
 *
 * @return string
 */
function kb_fonts_url() {
	return 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,300;1,400&display=swap';
}

/**
 * Version d'un fichier (date de modification) pour le cache navigateur.
 *
 * @param string $path Chemin relatif au thème.
 * @return string
 */
function kb_asset_version( $path ) {
	$file = KB_DIR . '/' . $path;
	return file_exists( $file ) ? KB_VERSION . '.' . filemtime( $file ) : KB_VERSION;
}

/**
 * Styles et scripts du site.
 */
function kb_enqueue_assets() {
	wp_enqueue_style( 'kb-fonts', kb_fonts_url(), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_enqueue_style( 'kb-main', KB_URI . '/assets/css/main.css', array(), kb_asset_version( 'assets/css/main.css' ) );

	wp_enqueue_script(
		'kb-main',
		KB_URI . '/assets/js/main.js',
		array(),
		kb_asset_version( 'assets/js/main.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	wp_add_inline_script(
		'kb-main',
		'window.kbConfig = ' . wp_json_encode(
			array(
				'heroInterval' => max( 4, absint( kb_mod( 'hero_interval' ) ) ) * 1000,
				'i18n'         => array(
					'pause'    => __( 'Mettre en pause', 'kasumbalesa' ),
					'play'     => __( 'Relancer le défilement', 'kasumbalesa' ),
					'copied'   => __( 'Lien copié', 'kasumbalesa' ),
					'readMore' => kb_mod( 'mayor_readmore' ),
					'readLess' => kb_mod( 'mayor_readless' ),
				),
			)
		) . ';',
		'before'
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'kb_enqueue_assets' );

/**
 * Préconnexion aux serveurs de polices.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Type de relation.
 * @return array
 */
function kb_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'kb_resource_hints', 10, 2 );

/**
 * Script minimal dans le <head> : active les styles JS, mémorise l'intro déjà vue
 * (évite tout flash de l'animation) et détecte prefers-reduced-motion.
 */
function kb_head_script() {
	?>
	<script>(function(h){h.className=h.className.replace(/\bno-js\b/,'js');try{if(sessionStorage.getItem('kbIntroSeen')){h.className+=' kb-intro-seen';}}catch(e){}if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches){h.className+=' kb-reduced-motion';}})(document.documentElement);</script>
	<meta name="theme-color" content="#0B2A1F">
	<?php
}
add_action( 'wp_head', 'kb_head_script', 0 );
