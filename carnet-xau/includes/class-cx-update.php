<?php
/**
 * Mise à jour du plugin sans passer par l'écran Extensions (choix de l'utilisateur).
 *
 * Garde-fous : seul un administrateur (droit « update_plugins ») annonce une version par
 * POST /update {version, url, sha256} ; le zip doit être dans la médiathèque de ce site ; son
 * empreinte SHA-256 est vérifiée avant l'installation ; trois essais au plus ; chaque mise à jour
 * (ou échec) est signalée sur Telegram. La tâche du carnet (toutes les 5 minutes) l'installe avec
 * l'outil de mise à jour de WordPress.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_Update {

	const OPT = 'cx_update';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( CX_Telegram::CRON, array( __CLASS__, 'maybe_run' ), 20 );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject' ) );
	}

	public static function routes() {
		$admin = static fn() => current_user_can( 'update_plugins' );
		register_rest_route(
			CX_Rest::NS,
			'/update',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'status' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'announce' ),
					'permission_callback' => $admin,
				),
			)
		);
	}

	/** @return string Chemin du plugin pour WordPress (carnet-xau/carnet-xau.php). */
	private static function basename() {
		return plugin_basename( CX_FILE );
	}

	/** @return WP_REST_Response */
	public static function status() {
		return new WP_REST_Response(
			array(
				'version' => CX_VERSION,
				'pending' => get_option( self::OPT, null ),
			),
			200
		);
	}

	/**
	 * Annonce d'une version.
	 *
	 * @param WP_REST_Request $req Requête {version, url, sha256}.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function announce( WP_REST_Request $req ) {
		$in      = (array) $req->get_json_params();
		$version = (string) ( $in['version'] ?? '' );
		$url     = esc_url_raw( (string) ( $in['url'] ?? '' ) );
		$sha     = strtolower( (string) ( $in['sha256'] ?? '' ) );
		$uploads = wp_get_upload_dir();
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) || version_compare( $version, CX_VERSION, '<=' ) ) {
			return new WP_Error( 'cx_invalid', 'Version absente ou pas plus récente que ' . CX_VERSION . '.', array( 'status' => 400 ) );
		}
		if ( 0 !== strpos( $url, trailingslashit( $uploads['baseurl'] ) ) || ! preg_match( '/\/carnet-xau-[\d.]+\.zip$/', $url ) ) {
			return new WP_Error( 'cx_invalid', 'Le zip doit être un carnet-xau-x.y.z.zip de la médiathèque de ce site.', array( 'status' => 400 ) );
		}
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $sha ) ) {
			return new WP_Error( 'cx_invalid', 'Empreinte SHA-256 invalide.', array( 'status' => 400 ) );
		}
		update_option(
			self::OPT,
			array(
				'version' => $version,
				'url'     => $url,
				'sha256'  => $sha,
				'state'   => 'en attente',
				'tries'   => 0,
				'at'      => CX_Repo::clock(),
			),
			false
		);
		return self::status();
	}

	/**
	 * Fait connaître la version annoncée à l'outil de mise à jour de WordPress.
	 *
	 * @param mixed $t Transient « update_plugins ».
	 * @return mixed
	 */
	public static function inject( $t ) {
		$u = get_option( self::OPT );
		// Liste vidée par WordPress juste après une mise à jour : on la recrée.
		if ( false === $t || null === $t ) {
			$t = new stdClass();
		}
		if ( ! is_object( $t ) || ! is_array( $u ) || version_compare( $u['version'], CX_VERSION, '<=' ) ) {
			return $t;
		}
		if ( ! isset( $t->response ) || ! is_array( $t->response ) ) {
			$t->response = array();
		}
		$t->response[ self::basename() ] = (object) array(
			'slug'        => 'carnet-xau',
			'plugin'      => self::basename(),
			'new_version' => $u['version'],
			'package'     => $u['url'],
			'url'         => home_url( '/' ),
		);
		return $t;
	}

	/**
	 * Installe la version annoncée (tâche du carnet).
	 */
	public static function maybe_run() {
		$u = get_option( self::OPT );
		if ( ! is_array( $u ) ) {
			return;
		}
		if ( version_compare( $u['version'], CX_VERSION, '<=' ) ) {
			if ( 'installée' !== $u['state'] ) {
				$u['state'] = 'installée';
				$u['done']  = CX_Repo::clock();
				update_option( self::OPT, $u, false );
				CX_Telegram::send( '🔄 Carnet XAU mis à jour automatiquement en version ' . esc_html( CX_VERSION ) . '.', true );
			}
			return;
		}
		if ( (int) ( $u['tries'] ?? 0 ) >= 3 || get_transient( 'cx_update_lock' ) ) {
			return;
		}
		set_transient( 'cx_update_lock', 1, 10 * MINUTE_IN_SECONDS );
		$u['tries'] = (int) ( $u['tries'] ?? 0 ) + 1;
		update_option( self::OPT, $u, false );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$tmp = download_url( $u['url'], 120 );
		if ( is_wp_error( $tmp ) ) {
			self::fail( $u, 'téléchargement impossible : ' . $tmp->get_error_message() );
			return;
		}
		if ( ! hash_equals( $u['sha256'], (string) hash_file( 'sha256', $tmp ) ) ) {
			wp_delete_file( $tmp );
			self::fail( $u, 'empreinte du zip différente de celle annoncée' );
			return;
		}
		// Le zip déjà vérifié est donné tel quel à l'outil de mise à jour.
		$url = $u['url'];
		$pre = static function ( $reply, $package ) use ( $url, $tmp ) {
			return $package === $url ? $tmp : $reply;
		};
		add_filter( 'upgrader_pre_download', $pre, 10, 2 );
		$skin = new Automatic_Upgrader_Skin();
		$up   = new Plugin_Upgrader( $skin );
		$res  = $up->upgrade( self::basename() );
		remove_filter( 'upgrader_pre_download', $pre, 10 );
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		if ( true !== $res ) {
			$msgs = implode( ' ', array_map( 'wp_strip_all_tags', (array) $skin->get_upgrade_messages() ) );
			self::fail( $u, 'installation refusée' . ( is_wp_error( $res ) ? ' : ' . $res->get_error_message() : '' ) . ( $msgs ? ' (' . $msgs . ')' : '' ) );
			return;
		}
		$u['state'] = 'copiée, en attente de chargement';
		update_option( self::OPT, $u, false );
		if ( ! is_plugin_active( self::basename() ) ) {
			activate_plugin( self::basename() );
		}
	}

	/**
	 * @param array  $u   Annonce.
	 * @param string $why Raison.
	 */
	private static function fail( $u, $why ) {
		$u['state'] = 'échec : ' . $why;
		update_option( self::OPT, $u, false );
		if ( (int) $u['tries'] >= 3 ) {
			CX_Telegram::send( '⚠️ Mise à jour automatique du Carnet XAU en ' . esc_html( $u['version'] ) . ' impossible : ' . esc_html( $why ) . '. À installer à la main.', true );
		}
	}
}
