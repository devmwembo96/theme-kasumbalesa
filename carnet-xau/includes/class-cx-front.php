<?php
/**
 * Interface : shortcode, protection de la page, scripts, manifest et service worker.
 *
 * La page du carnet est publique mais tenue hors du site : jamais dans un menu, une liste
 * de pages, la recherche, l'API des pages ou un plan du site, et toujours en noindex.
 * Le filtre « cx_can_use » permet de réserver l'accès plus tard (ex. current_user_can).
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
		add_filter( 'wpseo_robots', array( __CLASS__, 'yoast_robots' ) );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( __CLASS__, 'exclude' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'search_exclude' ) );
		add_filter( 'rest_page_query', array( __CLASS__, 'rest_exclude' ) );
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
	 * Accès au carnet. Ouvert pendant la conception ; à restreindre par le filtre « cx_can_use ».
	 *
	 * @return bool
	 */
	public static function can_use() {
		return (bool) apply_filters( 'cx_can_use', true );
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
	 * 404 si l'accès est restreint et refusé ; jamais de cache.
	 */
	public static function guard() {
		if ( ! self::is_app_page() ) {
			return;
		}
		if ( ! self::can_use() ) {
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
		if ( ! self::can_use() ) {
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
		if ( ! self::is_app_page() || ! self::can_use() ) {
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
					'ea'      => CX_URL . 'assets/mt5/CarnetXAU.mq5.txt',
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Manifest et métadonnées d'application installable.
	 */
	public static function head() {
		if ( ! self::is_app_page() || ! self::can_use() ) {
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
	 * Noindex aussi quand Yoast gère les balises robots.
	 *
	 * @param string $robots Directives.
	 * @return string
	 */
	public static function yoast_robots( $robots ) {
		return self::is_app_page() ? 'noindex, nofollow' : $robots;
	}

	/**
	 * Jamais dans les résultats de recherche du site.
	 *
	 * @param WP_Query $q Requête.
	 */
	public static function search_exclude( $q ) {
		if ( $q->is_search() && ! is_admin() && self::page_id() ) {
			$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), array( self::page_id() ) ) );
		}
	}

	/**
	 * Jamais dans la liste publique /wp/v2/pages.
	 *
	 * @param array $args Requête.
	 * @return array
	 */
	public static function rest_exclude( $args ) {
		if ( self::page_id() && ! current_user_can( 'edit_pages' ) ) {
			$args['post__not_in']   = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
			$args['post__not_in'][] = self::page_id();
		}
		return $args;
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
		if ( ! self::can_use() ) {
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
