<?php
/**
 * Coach de Claude : débrief des trades du jour (après la relecture du soir) et bilan de la semaine
 * (dimanche), envoyés en privé sur Telegram et gardés dans le carnet pour suivre les objectifs.
 *
 * - POST /claude/coach (jeton du plan, en-tête X-CX-Key) :
 *   {"kind": "day"|"week", "date": "AAAA-MM-JJ", "lines": ["…"], "focus": "…", "score": 0-10}
 * - GET  /coach : les 60 derniers débriefs (le coach relit l'objectif précédent avant d'écrire).
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_Coach {

	const OPT = 'cx_coach';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/coach',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_notes' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
		register_rest_route(
			CX_Rest::NS,
			'/claude/coach',
			array(
				'methods'             => 'POST,PUT',
				'callback'            => array( __CLASS__, 'publish' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** @return array[] Débriefs, du plus ancien au plus récent. */
	public static function notes() {
		$d = get_option( self::OPT, array() );
		return is_array( $d ) ? array_values( $d ) : array();
	}

	/** @return WP_REST_Response */
	public static function list_notes() {
		return new WP_REST_Response( array( 'notes' => self::notes() ), 200 );
	}

	/**
	 * Débrief de Claude : enregistré, puis envoyé en privé sur Telegram (pas sur la chaîne).
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function publish( WP_REST_Request $req ) {
		$limit = CX_Guard::throttle( 'plan', 30 );
		if ( $limit ) {
			return $limit;
		}
		if ( ! CX_Guard::matches( (string) $req->get_header( 'x_cx_key' ), (string) ( CX_Guard::settings()['plan'] ?? '' ) ) ) {
			return CX_Guard::refuse( 'plan', 'jeton du plan invalide' );
		}
		$in   = (array) $req->get_json_params();
		$kind = in_array( $in['kind'] ?? '', array( 'day', 'week' ), true ) ? $in['kind'] : '';
		$date = (string) ( $in['date'] ?? '' );
		if ( ! $kind || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return CX_Repo::invalid( '« kind » (day ou week) et « date » (AAAA-MM-JJ) obligatoires.' );
		}
		$lines = array_values( array_filter( array_map( fn( $l ) => CX_Repo::text( $l, 400 ), array_slice( (array) ( $in['lines'] ?? array() ), 0, 10 ) ) ) );
		$focus = CX_Repo::text( $in['focus'] ?? '', 300 );
		if ( ! $lines || '' === $focus ) {
			return CX_Repo::invalid( '« lines » (10 au plus) et « focus » (l’objectif) obligatoires.' );
		}
		$score = isset( $in['score'] ) && is_numeric( $in['score'] ) ? max( 0, min( 10, (int) $in['score'] ) ) : null;
		$note  = array(
			'kind'  => $kind,
			'date'  => $date,
			'lines' => $lines,
			'focus' => $focus,
			'score' => $score,
			'at'    => CX_Repo::clock(),
		);
		// Une note par jour et par type : la dernière remplace la précédente.
		$all   = array_values( array_filter( self::notes(), fn( $n ) => ! ( $n['kind'] === $kind && $n['date'] === $date ) ) );
		$all[] = $note;
		update_option( self::OPT, array_slice( $all, -60 ), false );

		$when = wp_date( 'd/m', strtotime( $date . ' 12:00' ), CX_Repo::tz() );
		$head = 'week' === $kind ? '🧭 <b>Coach · bilan de la semaine</b> (' . $when . ')' : '🎯 <b>Coach · débrief du ' . $when . '</b>';
		$text = $head . ( null !== $score ? ' · discipline ' . $score . '/10' : '' ) . "\n"
			. implode( "\n", array_map( fn( $l ) => '• ' . esc_html( $l ), $lines ) )
			. "\n\n<b>Objectif :</b> " . esc_html( $focus );
		$sent = CX_Telegram::send( $text, true );
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'telegram' => true === $sent,
			),
			200
		);
	}
}
