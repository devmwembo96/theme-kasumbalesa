<?php
/**
 * Réglages de l'EA ajustés par Claude après le rejeu du soir (backtest de 23:20).
 *
 * - GET  /ea-params              réglages actuels, valeurs par défaut, bornes et historique.
 * - PUT  /claude/ea-params        {key, value, reason} ou {key, revert: true, reason}, protégé par le
 *   jeton du plan. Un changement par jour au plus, toujours dans les bornes ; le risque, les limites du
 *   jour et la plage de stop n'en font pas partie. Chaque changement est signalé sur Telegram.
 *
 * L'EA les reçoit avec le plan (ligne « S| ») et les applique sans réinstallation.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_Tuning {

	const OPT = 'cx_ea_params';

	/** Réglage => [ défaut, minimum, maximum, libellé ]. */
	const PARAMS = array(
		'touch'    => array( 30, 10, 60, 'Bougies M1 où chercher la touche de zone' ),
		'swing'    => array( 20, 10, 40, 'Bougies M1 avant l’extrême (pivot et FVG)' ),
		'fvg'      => array( 0.30, 0.10, 1.50, 'Taille minimale d’un FVG ($)' ),
		'slbuf'    => array( 0.30, 0.10, 1.00, 'Marge du stop derrière l’extrême ($)' ),
		'limitmin' => array( 30, 15, 120, 'Validité d’un ordre limite (min)' ),
		'cooldown' => array( 60, 15, 180, 'Minutes sans nouveau signal sur la même zone' ),
	);

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/ea-params',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
		register_rest_route(
			CX_Rest::NS,
			'/claude/ea-params',
			array(
				'methods'             => 'PUT,POST',
				'callback'            => array( __CLASS__, 'set' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @return array {values, history}
	 */
	private static function data() {
		$d = get_option( self::OPT, array() );
		$d = is_array( $d ) ? $d : array();
		$v = array();
		foreach ( self::PARAMS as $k => $p ) {
			$v[ $k ] = isset( $d['values'][ $k ] ) ? (float) $d['values'][ $k ] : (float) $p[0];
		}
		return array(
			'values'  => $v,
			'history' => array_values( (array) ( $d['history'] ?? array() ) ),
		);
	}

	/** @return array Réglages actuels. */
	public static function values() {
		return self::data()['values'];
	}

	/** @return WP_REST_Response */
	public static function get() {
		$d    = self::data();
		$defs = array();
		foreach ( self::PARAMS as $k => $p ) {
			$defs[ $k ] = array(
				'default' => $p[0],
				'min'     => $p[1],
				'max'     => $p[2],
				'label'   => $p[3],
			);
		}
		return new WP_REST_Response(
			array(
				'values'  => $d['values'],
				'params'  => $defs,
				'history' => array_reverse( array_slice( $d['history'], -30 ) ),
			),
			200
		);
	}

	/**
	 * Changement d'un réglage par Claude.
	 *
	 * @param WP_REST_Request $req Requête {key, value | revert, reason}.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function set( WP_REST_Request $req ) {
		$limit = CX_Guard::throttle( 'plan', 30 );
		if ( $limit ) {
			return $limit;
		}
		if ( ! CX_Guard::matches( (string) $req->get_header( 'x_cx_key' ), (string) ( CX_Guard::settings()['plan'] ?? '' ) ) ) {
			return CX_Guard::refuse( 'plan', 'jeton du plan invalide' );
		}
		$in     = (array) $req->get_json_params();
		$key    = (string) ( $in['key'] ?? '' );
		$reason = CX_Repo::textarea( $in['reason'] ?? '', 1500 );
		if ( ! isset( self::PARAMS[ $key ] ) ) {
			return CX_Repo::invalid( 'Réglage inconnu. Réglages : ' . implode( ', ', array_keys( self::PARAMS ) ) . '.' );
		}
		if ( '' === $reason ) {
			return CX_Repo::invalid( '« reason » obligatoire : les faits qui justifient le changement.' );
		}
		$d     = self::data();
		$today = CX_Repo::clock( 'Y-m-d' );
		foreach ( $d['history'] as $h ) {
			if ( substr( $h['at'], 0, 10 ) === $today ) {
				return CX_Repo::invalid( 'Un changement de réglage par jour au plus : déjà fait aujourd’hui (' . $h['key'] . ').' );
			}
		}
		$p   = self::PARAMS[ $key ];
		$old = $d['values'][ $key ];
		if ( ! empty( $in['revert'] ) ) {
			$prev = null;
			foreach ( array_reverse( $d['history'] ) as $h ) {
				if ( $h['key'] === $key ) {
					$prev = (float) $h['from'];
					break;
				}
			}
			if ( null === $prev ) {
				return CX_Repo::invalid( 'Aucun changement précédent à annuler pour ce réglage.' );
			}
			$new = $prev;
		} else {
			$new = CX_Repo::num( $in['value'] ?? null, $p[1], $p[2] );
			if ( null === $new ) {
				return CX_Repo::invalid( sprintf( 'Valeur hors bornes : %s doit être entre %s et %s.', $key, $p[1], $p[2] ) );
			}
		}
		$new = is_int( $p[0] ) ? (float) round( $new ) : round( $new, 2 );
		if ( abs( $new - $old ) < 1e-9 ) {
			return CX_Repo::invalid( 'Valeur identique à la valeur actuelle.' );
		}
		$d['values'][ $key ] = $new;
		$d['history'][]      = array(
			'at'     => CX_Repo::clock(),
			'key'    => $key,
			'from'   => $old,
			'to'     => $new,
			'revert' => ! empty( $in['revert'] ),
			'reason' => $reason,
		);
		$d['history'] = array_slice( $d['history'], -100 );
		update_option( self::OPT, $d, false );
		CX_Telegram::send(
			sprintf(
				"🛠 <b>EA : réglage ajusté par Claude</b>%s\n%s : %s → %s\n%s",
				empty( $in['revert'] ) ? '' : ' (retour à la valeur précédente)',
				esc_html( $p[3] ),
				esc_html( self::fmt( $old ) ),
				esc_html( self::fmt( $new ) ),
				esc_html( $reason )
			)
		);
		return self::get();
	}

	/**
	 * @param float $v Valeur.
	 * @return string
	 */
	private static function fmt( $v ) {
		return rtrim( rtrim( number_format( (float) $v, 2, ',', '' ), '0' ), ',' );
	}
}
