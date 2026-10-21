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
		add_action( 'init', array( __CLASS__, 'maybe_purge' ), 30 );
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
		// Jamais de copie en cache : constante lue par WP Super Cache, W3TC, LiteSpeed… et appel
		// propre à WP Fastest Cache (installé sur le site), qui ignore la constante.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( function_exists( 'wpfc_exclude_current_page' ) ) {
			wpfc_exclude_current_page();
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
	 * Après chaque mise à jour du plugin : vide la copie en cache de la page du carnet.
	 */
	public static function maybe_purge() {
		if ( get_option( 'cx_version' ) === CX_VERSION ) {
			return;
		}
		update_option( 'cx_version', CX_VERSION, false );
		self::purge_cache();
	}

	/**
	 * Vide le cache de la page du carnet dans les extensions de cache courantes.
	 */
	public static function purge_cache() {
		$id = self::page_id();
		if ( ! $id ) {
			return;
		}
		clean_post_cache( $id );
		do_action( 'wpfc_clear_post_cache_by_id', false, $id ); // WP Fastest Cache.
		do_action( 'litespeed_purge_post', $id ); // LiteSpeed Cache.
		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $id ); // WP Super Cache.
		}
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $id ); // W3 Total Cache.
		}
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $id ); // WP Rocket.
		}
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
		// CSS et JS insérés dans la page (jamais en cache) : le cache du serveur LWS sert d'anciennes
		// copies compressées des fichiers statiques, même quand le paramètre ?ver change.
		wp_register_style( 'cx-app', false, array(), CX_VERSION );
		wp_enqueue_style( 'cx-app' );
		wp_add_inline_style( 'cx-app', (string) file_get_contents( CX_DIR . 'assets/css/app.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		// Graphiques de TradingView (Lightweight Charts, Apache 2.0) : la version est dans le nom du
		// fichier, le cache du serveur ne peut donc pas en servir une autre.
		wp_register_script( 'cx-lwc', CX_URL . 'assets/vendor/lightweight-charts-4.2.3.js', array(), null, array( 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_script( 'cx-app', false, array( 'cx-lwc' ), CX_VERSION, array( 'in_footer' => true ) );
		wp_enqueue_script( 'cx-app' );
		wp_add_inline_script( 'cx-app', (string) file_get_contents( CX_DIR . 'assets/js/app.js' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions

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
					'readOnly' => self::read_only(),
					'login'   => wp_login_url( $page ),
					'ea'      => add_query_arg(
						array(
							'cx_pwa' => 'ea',
							'v'      => CX_VERSION,
						),
						home_url( '/' )
					),
					'calc'    => add_query_arg(
						array(
							'cx_pwa' => 'calc',
							'v'      => CX_VERSION,
						),
						home_url( '/' )
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Page publique ? Par défaut non : tenue hors du site (menus, recherche, plans du site,
	 * noindex). Un thème ou un mu-plugin peut la rendre visible : filtre « cx_page_visible ».
	 *
	 * @return bool
	 */
	public static function visible() {
		return (bool) apply_filters( 'cx_page_visible', false );
	}

	/**
	 * Lecture seule pour ce visiteur ? Par défaut, dès que la page est visible : les visiteurs
	 * consultent, seul un utilisateur connecté ayant la capacité du carnet modifie.
	 * Filtre « cx_public_read_only » pour changer ce comportement.
	 *
	 * @return bool
	 */
	public static function read_only() {
		return (bool) apply_filters( 'cx_public_read_only', self::visible() ) && ! current_user_can( CX_CAP );
	}

	/**
	 * Identité de l'application installée : nom, couleurs, icônes. Un thème peut la changer
	 * avec le filtre « cx_app_identity » (par exemple pour reprendre le logo du site).
	 *
	 * @return array
	 */
	public static function identity() {
		$defaults = array(
			'name'             => 'Carnet XAU',
			'short_name'       => 'Carnet XAU',
			'description'      => 'Carnet de trading XAUUSD',
			'theme_color'      => '#A87A1E',
			'background_color' => '#E9EDF1',
			'icon_192'         => CX_URL . 'assets/icons/icon-192.png',
			'icon_512'         => CX_URL . 'assets/icons/icon-512.png',
			'icon_maskable'    => CX_URL . 'assets/icons/icon-512.png',
			'icon_apple'       => CX_URL . 'assets/icons/icon-192.png',
		);
		$id = apply_filters( 'cx_app_identity', $defaults );
		return is_array( $id ) ? array_merge( $defaults, $id ) : $defaults;
	}

	/**
	 * Manifest et métadonnées d'application installable.
	 */
	public static function head() {
		if ( ! self::is_app_page() || ! self::can_use() ) {
			return;
		}
		$id = self::identity();
		printf(
			"<link rel=\"manifest\" href=\"%s\" crossorigin=\"use-credentials\">\n<meta name=\"theme-color\" content=\"%s\">\n<meta name=\"apple-mobile-web-app-capable\" content=\"yes\">\n<meta name=\"mobile-web-app-capable\" content=\"yes\">\n<meta name=\"apple-mobile-web-app-title\" content=\"%s\">\n<link rel=\"apple-touch-icon\" href=\"%s\">\n",
			esc_url( add_query_arg( 'cx_pwa', 'manifest', home_url( '/' ) ) ),
			esc_attr( $id['theme_color'] ),
			esc_attr( $id['short_name'] ),
			esc_url( $id['icon_apple'] )
		);
	}

	/**
	 * Jamais indexée.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( self::is_app_page() && ! self::visible() ) {
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
		return self::is_app_page() && ! self::visible() ? 'noindex, nofollow' : $robots;
	}

	/**
	 * Jamais dans les résultats de recherche du site.
	 *
	 * @param WP_Query $q Requête.
	 */
	public static function search_exclude( $q ) {
		if ( $q->is_search() && ! is_admin() && self::page_id() && ! self::visible() ) {
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
		if ( self::page_id() && ! current_user_can( 'edit_pages' ) && ! self::visible() ) {
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
		if ( self::page_id() && ! self::visible() ) {
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
		if ( self::page_id() && ! self::visible() ) {
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
		if ( ! in_array( $what, array( 'manifest', 'sw', 'ea', 'calc' ), true ) ) {
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

		// EA MetaTrader 5, servi par PHP pour échapper au cache des fichiers statiques.
		if ( 'ea' === $what ) {
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="CarnetXAU.mq5"' );
			// L'adresse par défaut de l'EA devient celle de ce site, quel que soit son domaine.
			$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			echo str_replace( // phpcs:ignore WordPress.Security.EscapeOutput -- fichier source de l'EA.
				array( 'https://revnefty.store/wp-json/carnet-xau/v1/mt5', 'https://revnefty.store       ' ),
				array( rest_url( CX_Rest::NS . '/mt5' ), str_pad( 'https://' . $host, 29 ) ),
				(string) file_get_contents( CX_DIR . 'assets/mt5/CarnetXAU.mq5.txt' ) // phpcs:ignore WordPress.WP.AlternativeFunctions
			);
			exit;
		}

		// EA de déclenchement des positions (calculateur de lot du modèle fonds propres).
		if ( 'calc' === $what ) {
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="CalculateurXAU.mq5"' );
			echo (string) file_get_contents( CX_DIR . 'assets/mt5/CalculateurXAU.mq5.txt' ); // phpcs:ignore WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions -- fichier source de l'EA.
			exit;
		}

		if ( 'manifest' === $what ) {
			nocache_headers();
			header( 'Content-Type: application/manifest+json; charset=utf-8' );
			$id = self::identity();
			echo wp_json_encode(
				array(
					'name'             => $id['name'],
					'short_name'       => $id['short_name'],
					'description'      => $id['description'],
					'lang'             => 'fr',
					'start_url'        => $page,
					'scope'            => wp_parse_url( $page, PHP_URL_PATH ),
					'display'          => 'standalone',
					'background_color' => $id['background_color'],
					'theme_color'      => $id['theme_color'],
					'icons'            => array(
						array(
							'src'     => $id['icon_192'],
							'sizes'   => '192x192',
							'type'    => 'image/png',
							'purpose' => 'any',
						),
						array(
							'src'     => $id['icon_512'],
							'sizes'   => '512x512',
							'type'    => 'image/png',
							'purpose' => 'any',
						),
						array(
							'src'     => $id['icon_maskable'],
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
						self::identity()['icon_192'],
					),
					JSON_UNESCAPED_SLASHES
				),
			),
			$js
		);
		exit;
	}
}
