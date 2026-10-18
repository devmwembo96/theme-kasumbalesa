<?php
/**
 * API REST /wp-json/carnet-xau/v1/ : réservée aux utilisateurs qui ont la capacité du carnet.
 *
 * L'interface envoie le nonce « wp_rest » dans l'en-tête X-WP-Nonce ; sans lui, WordPress
 * ignore le cookie de connexion et la permission est refusée.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Routes du carnet.
 */
class CX_Rest {

	const NS = 'carnet-xau/v1';

	/**
	 * Accroches.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_ajax_cx_nonce', array( __CLASS__, 'refresh_nonce' ) );
	}

	/**
	 * Contrôle d'accès commun à toutes les routes.
	 *
	 * @return bool|WP_Error
	 */
	public static function can() {
		if ( current_user_can( CX_CAP ) ) {
			return true;
		}
		return new WP_Error( 'cx_forbidden', 'Accès réservé.', array( 'status' => is_user_logged_in() ? 403 : 401 ) );
	}

	/**
	 * Nouveau nonce REST pour une interface restée ouverte longtemps (admin-ajax, cookie seul).
	 */
	public static function refresh_nonce() {
		if ( ! current_user_can( CX_CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		nocache_headers();
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
	}

	/**
	 * Déclare une route.
	 *
	 * @param string $path    Chemin.
	 * @param string $methods Méthodes HTTP.
	 * @param string $cb      Méthode de cette classe.
	 */
	private static function route( $path, $methods, $cb ) {
		register_rest_route(
			self::NS,
			$path,
			array(
				'methods'             => $methods,
				'callback'            => array( __CLASS__, $cb ),
				'permission_callback' => array( __CLASS__, 'can' ),
			)
		);
	}

	/**
	 * Déclaration des routes.
	 */
	public static function routes() {
		self::route( '/bootstrap', 'GET', 'bootstrap' );

		self::route( '/accounts', 'GET', 'list_accounts' );
		self::route( '/accounts', 'POST', 'create_account' );
		self::route( '/accounts/(?P<id>\d+)', 'PUT,PATCH', 'update_account' );
		self::route( '/accounts/(?P<id>\d+)', 'DELETE', 'delete_account' );
		self::route( '/accounts/(?P<id>\d+)/activate', 'POST', 'activate_account' );

		self::route( '/trades', 'GET', 'list_trades' );
		self::route( '/trades', 'POST', 'create_trade' );
		self::route( '/trades/(?P<id>\d+)', 'PUT,PATCH', 'update_trade' );
		self::route( '/trades/(?P<id>\d+)', 'DELETE', 'delete_trade' );

		self::route( '/plans/(?P<week>\d{4}-\d{2}-\d{2})', 'GET', 'get_plan' );
		self::route( '/plans/(?P<week>\d{4}-\d{2}-\d{2})', 'PUT', 'save_plan' );

		self::route( '/payouts', 'GET', 'list_payouts' );
		self::route( '/payouts', 'POST', 'create_payout' );
		self::route( '/payouts/(?P<id>\d+)', 'PUT,PATCH', 'update_payout' );
		self::route( '/payouts/(?P<id>\d+)', 'DELETE', 'delete_payout' );

		self::route( '/captures', 'POST', 'upload_capture' );

		self::route( '/export/trades.csv', 'GET', 'export_csv' );
		self::route( '/export/backup.json', 'GET', 'export_json' );
	}

	/**
	 * Réponse : données ou erreur.
	 *
	 * @param mixed $v      Résultat.
	 * @param int   $status Code HTTP en cas de succès.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function out( $v, $status = 200 ) {
		if ( is_wp_error( $v ) ) {
			return $v;
		}
		$res = new WP_REST_Response( $v, $status );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/**
	 * 404 générique.
	 *
	 * @return WP_Error
	 */
	private static function not_found() {
		return new WP_Error( 'cx_not_found', 'Élément introuvable.', array( 'status' => 404 ) );
	}

	/**
	 * Tout ce qu'il faut à l'interface au démarrage, en une requête.
	 *
	 * @return WP_REST_Response
	 */
	public static function bootstrap() {
		return self::out(
			array(
				'accounts' => CX_Repo::accounts(),
				'trades'   => CX_Repo::trades(),
				'payouts'  => CX_Repo::payouts(),
			)
		);
	}

	/* Comptes ----------------------------------------------------------- */

	/** @return WP_REST_Response */
	public static function list_accounts() {
		return self::out( CX_Repo::accounts() );
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_account( WP_REST_Request $req ) {
		return self::out( CX_Repo::save_account( (array) $req->get_json_params() ), 201 );
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_account( WP_REST_Request $req ) {
		return self::out( CX_Repo::save_account( (array) $req->get_json_params(), (int) $req['id'] ) );
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_account( WP_REST_Request $req ) {
		$id     = (int) $req['id'];
		$counts = CX_Repo::account_counts( $id );
		return CX_Repo::delete_account( $id ) ? self::out( array( 'deleted' => true ) + $counts ) : self::not_found();
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function activate_account( WP_REST_Request $req ) {
		return CX_Repo::activate_account( (int) $req['id'] ) ? self::out( CX_Repo::accounts() ) : self::not_found();
	}

	/* Trades ------------------------------------------------------------ */

	/**
	 * @param WP_REST_Request $req Requête (?account=).
	 * @return WP_REST_Response
	 */
	public static function list_trades( WP_REST_Request $req ) {
		return self::out( CX_Repo::trades( absint( $req->get_param( 'account' ) ) ) );
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_trade( WP_REST_Request $req ) {
		return self::out( CX_Repo::save_trade( (array) $req->get_json_params() ), 201 );
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_trade( WP_REST_Request $req ) {
		return self::out( CX_Repo::save_trade( (array) $req->get_json_params(), (int) $req['id'] ) );
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_trade( WP_REST_Request $req ) {
		return CX_Repo::delete_trade( (int) $req['id'] ) ? self::out( array( 'deleted' => true ) ) : self::not_found();
	}

	/* Plans ------------------------------------------------------------- */

	/**
	 * Semaine valide (un lundi), ou erreur.
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return string|WP_Error
	 */
	private static function week( WP_REST_Request $req ) {
		$w = CX_Repo::date( (string) $req['week'] );
		if ( ! $w || '1' !== gmdate( 'N', strtotime( $w . ' 00:00:00 UTC' ) ) ) {
			return CX_Repo::invalid( 'La semaine doit commencer un lundi.' );
		}
		return $w;
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_plan( WP_REST_Request $req ) {
		$w = self::week( $req );
		return is_wp_error( $w ) ? $w : self::out( CX_Repo::plan( $w ) );
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_plan( WP_REST_Request $req ) {
		$w = self::week( $req );
		return is_wp_error( $w ) ? $w : self::out( CX_Repo::save_plan( $w, (array) $req->get_json_params() ) );
	}

	/* Retraits ---------------------------------------------------------- */

	/** @return WP_REST_Response */
	public static function list_payouts() {
		return self::out( CX_Repo::payouts() );
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_payout( WP_REST_Request $req ) {
		return self::out( CX_Repo::save_payout( (array) $req->get_json_params() ), 201 );
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_payout( WP_REST_Request $req ) {
		return self::out( CX_Repo::save_payout( (array) $req->get_json_params(), (int) $req['id'] ) );
	}

	/**
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_payout( WP_REST_Request $req ) {
		return CX_Repo::delete_payout( (int) $req['id'] ) ? self::out( array( 'deleted' => true ) ) : self::not_found();
	}

	/* Captures ---------------------------------------------------------- */

	/**
	 * Envoi d'une capture (image) dans la médiathèque, en pièce jointe privée au nom aléatoire.
	 *
	 * @param WP_REST_Request $req Requête multipart, champ « file ».
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload_capture( WP_REST_Request $req ) {
		$files = $req->get_file_params();
		if ( empty( $files['file'] ) || ! empty( $files['file']['error'] ) ) {
			return CX_Repo::invalid( 'Aucun fichier reçu.' );
		}
		if ( (int) $files['file']['size'] > 8 * MB_IN_BYTES ) {
			return CX_Repo::invalid( 'Image trop lourde (8 Mo maximum).' );
		}
		$mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'gif'          => 'image/gif',
		);
		$check = wp_check_filetype_and_ext( $files['file']['tmp_name'], $files['file']['name'], $mimes );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return CX_Repo::invalid( 'Format accepté : JPG, PNG, WebP ou GIF.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Nom de fichier imprévisible : l'adresse de l'image ne se devine pas.
		$_FILES['file']         = $files['file'];
		$_FILES['file']['name'] = 'cx-' . wp_generate_password( 20, false ) . '.' . $check['ext'];

		$id = media_handle_upload(
			'file',
			0,
			array(
				'post_title'  => 'Capture Carnet XAU',
				// Statut privé : hors des listes publiques (REST, pages de fichier, plans du site).
				'post_status' => 'private',
			),
			array(
				'test_form' => false,
				'mimes'     => $mimes,
			)
		);
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'cx_upload', $id->get_error_message(), array( 'status' => 400 ) );
		}
		update_post_meta( $id, '_cx_capture', 1 );

		return self::out(
			array(
				'id'  => (int) $id,
				'url' => (string) wp_get_attachment_url( $id ),
			),
			201
		);
	}

	/* Exports ----------------------------------------------------------- */

	/**
	 * @param WP_REST_Request $req Requête (?account=).
	 */
	public static function export_csv( WP_REST_Request $req ) {
		CX_Export::csv( absint( $req->get_param( 'account' ) ) );
	}

	/** Sauvegarde JSON complète. */
	public static function export_json() {
		CX_Export::json();
	}
}
