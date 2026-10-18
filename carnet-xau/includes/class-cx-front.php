<?php
/**
 * Interface : shortcode, protection de la page, scripts, manifest et service worker.
 *
 * La page du carnet est privée, hors menus, hors recherche, hors plans du site et en
 * noindex. Toute personne sans la capacité du carnet reçoit une 404.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Affichage.
 */
class CX_Front {

	/**
	 * Accroches.
	 */
	public static function init() {
		add_shortcode( 'carnet_xau', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'serve_pwa' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'guard' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'private_title_format', array( __CLASS__, 'title_format' ), 10, 2 );
		add_filter( 'wp_list_pages_excludes', array( __CLASS__, 'exclude' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_exclude' ) );
	}

	/**
	 * Identifiant de la page du carnet.
	 *
	 * @return int
	 */
	public static function page_id() {
		return (int) get_option( 'cx_page_id' );
	}

	/**
	 * La requête en cours affiche-t-elle le carnet ?
	 *
	 * @return bool
	 */
	public static function is_app_page() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		return $post instanceof WP_Post && ( (int) $post->ID === self::page_id() || has_shortcode( $post->post_content, 'carnet_xau' ) );
	}

	/**
	 * 404 pour toute personne non autorisée, et jamais de cache.
	 */
	public static function guard() {
		if ( ! self::is_app_page() ) {
			return;
		}
		if ( ! current_user_can( CX_CAP ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}
		nocache_headers();
	}

	/**
	 * Le shortcode : l'application, ou rien.
	 *
	 * @return string
	 */
	public static function shortcode() {
		if ( ! current_user_can( CX_CAP ) ) {
			return '';
		}
		ob_start();
		include CX_DIR . 'templates/app.php';
		return ob_get_clean();
	}

	/**
	 * Feuilles de style et script, sur la page du carnet uniquement.
	 */
	public static function enqueue() {
		if ( ! self::is_app_page() || ! current_user_can( CX_CAP ) ) {
			return;
		}
		wp_enqueue_style( 'cx-fonts', 'https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=Instrument+Sans:wght@400;500;600;700&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'cx-app', CX_URL . 'assets/css/app.css', array(), CX_VERSION );
		wp_enqueue_script( 'cx-app', CX_URL . 'assets/js/app.js', array(), CX_VERSION, array( 'in_footer' => true ) );

		$page = get_permalink( get_queried_object_id() );
		wp_add_inline_script(
			'cx-app',
			'window.CX_CONFIG=' . wp_json_encode(
				array(
					'root'    => esc_url_raw( rest_url( CX_Rest::NS . '/' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'ajax'    => admin_url( 'admin-ajax.php' ),
					'sw'      => add_query_arg( 'cx_pwa', 'sw', home_url( '/' ) ),
					'scope'   => wp_parse_url( $page, PHP_URL_PATH ),
					'version' => CX_VERSION,
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Manifest et métadonnées d'application installable.
	 */
	public static function head() {
		if ( ! self::is_app_page() || ! current_user_can( CX_CAP ) ) {
			return;
		}
		printf(
			"<link rel=\"manifest\" href=\"%s\" crossorigin=\"use-credentials\">\n<meta name=\"theme-color\" content=\"#A87A1E\">\n<meta name=\"apple-mobile-web-app-capable\" content=\"yes\">\n<meta name=\"mobile-web-app-capable\" content=\"yes\">\n<meta name=\"apple-mobile-web-app-title\" content=\"Carnet XAU\">\n<link rel=\"apple-touch-icon\" href=\"%s\">\n",
			esc_url( add_query_arg( 'cx_pwa', 'manifest', home_url( '/' ) ) ),
			esc_url( CX_URL . 'assets/icons/icon-192.png' )
		);
	}

	/**
	 * Jamais indexée.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( self::is_app_page() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * Pas de préfixe « Privé : » devant le titre du carnet.
	 *
	 * @param string  $format Format.
	 * @param WP_Post $post   Article.
	 * @return string
	 */
	public static function title_format( $format, $post = null ) {
		return ( $post && (int) $post->ID === self::page_id() ) ? '%s' : $format;
	}

	/**
	 * Jamais dans une liste de pages (menu de secours du thème compris).
	 *
	 * @param array $ids Pages exclues.
	 * @return array
	 */
	public static function exclude( $ids ) {
		if ( self::page_id() ) {
			$ids[] = self::page_id();
		}
		return $ids;
	}

	/**
	 * Jamais dans le plan du site de WordPress.
	 *
	 * @param array $args Requête.
	 * @return array
	 */
	public static function sitemap_exclude( $args ) {
		if ( self::page_id() ) {
			$args['post__not_in']   = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
			$args['post__not_in'][] = self::page_id();
		}
		return $args;
	}

	/**
	 * Manifest (?cx_pwa=manifest) et service worker (?cx_pwa=sw), servis depuis la racine
	 * pour que le service worker puisse couvrir la page du carnet.
	 */
	public static function serve_pwa() {
		$what = isset( $_GET['cx_pwa'] ) ? sanitize_key( wp_unslash( $_GET['cx_pwa'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $what, array( 'manifest', 'sw' ), true ) ) {
			return;
		}
		if ( ! current_user_can( CX_CAP ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$page = get_permalink( self::page_id() );
		if ( ! $page ) {
			status_header( 404 );
			exit;
		}

		if ( 'manifest' === $what ) {
			nocache_headers();
			header( 'Content-Type: application/manifest+json; charset=utf-8' );
			echo wp_json_encode(
				array(
					'name'             => 'Carnet XAU',
					'short_name'       => 'Carnet XAU',
					'description'      => 'Carnet de trading XAUUSD',
					'lang'             => 'fr',
					'start_url'        => $page,
					'scope'            => wp_parse_url( $page, PHP_URL_PATH ),
					'display'          => 'standalone',
					'background_color' => '#E9EDF1',
					'theme_color'      => '#A87A1E',
					'icons'            => array(
						array(
							'src'   => CX_URL . 'assets/icons/icon-192.png',
							'sizes' => '192x192',
							'type'  => 'image/png',
						),
						array(
							'src'   => CX_URL . 'assets/icons/icon-512.png',
							'sizes' => '512x512',
							'type'  => 'image/png',
						),
						array(
							'src'     => CX_URL . 'assets/icons/icon-512.png',
							'sizes'   => '512x512',
							'type'    => 'image/png',
							'purpose' => 'maskable',
						),
					),
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
			exit;
		}

		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		$js = (string) file_get_contents( CX_DIR . 'assets/js/sw.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		echo str_replace( // phpcs:ignore WordPress.Security.EscapeOutput -- script du plugin, valeurs encodées en JSON.
			array( '__CX_VERSION__', '__CX_PRECACHE__' ),
			array(
				wp_json_encode( CX_VERSION ),
				wp_json_encode(
					array(
						CX_URL . 'assets/css/app.css?ver=' . CX_VERSION,
						CX_URL . 'assets/js/app.js?ver=' . CX_VERSION,
						CX_URL . 'assets/icons/icon-192.png',
					),
					JSON_UNESCAPED_SLASHES
				),
			),
			$js
		);
		exit;
	}
}
