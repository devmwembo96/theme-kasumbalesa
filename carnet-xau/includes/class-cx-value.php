<?php
/**
 * Valorisation du système : un agent Claude note chaque semaine le système (signaux simulés de l'EA,
 * fiches et zones de Claude, logique, outils) sur sa propre grille de 0 à 100, inspirée des produits
 * du MQL5 Market. La note n'est pas le taux de réussite. Tant qu'elle reste sous 70, rien n'est envoyé :
 * le rapport part sur Telegram seulement quand la note atteint 70 (une fois par semaine au plus).
 *
 * - PUT /claude/valuation (jeton du plan, en-tête X-CX-Key) :
 *   {"date": "AAAA-MM-JJ", "score": 0-100, "grid_version": 1,
 *    "criteria": [{"key", "label", "weight", "score", "why"}], "value_usd": {"low", "high", "basis"},
 *    "strengths": ["…"], "gaps": ["…"], "actions": ["…"], "report": "…"}
 * - GET  /valuation : les 52 dernières notes (l'agent relit sa grille pour ne pas la changer en douce).
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_Value {

	const OPT  = 'cx_valuation';
	const GOAL = 70;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/valuation',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
		register_rest_route(
			CX_Rest::NS,
			'/claude/valuation',
			array(
				'methods'             => 'POST,PUT',
				'callback'            => array( __CLASS__, 'publish' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** @return array[] Notes, de la plus ancienne à la plus récente. */
	public static function notes() {
		$d = get_option( self::OPT, array() );
		return is_array( $d ) ? array_values( $d ) : array();
	}

	/** @return WP_REST_Response */
	public static function rest() {
		$notes = self::notes();
		return new WP_REST_Response(
			array(
				'goal'  => self::GOAL,
				'note'  => 'Note du système sur 100 (pas le taux de réussite) ; rapport sur Telegram à partir de 70.',
				'last'  => $notes ? end( $notes ) : null,
				'notes' => $notes,
			),
			200
		);
	}

	/**
	 * Note de l'agent : enregistrée ; envoyée sur Telegram seulement à partir de 70.
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
		$date = (string) ( $in['date'] ?? '' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! isset( $in['score'] ) || ! is_numeric( $in['score'] ) ) {
			return CX_Repo::invalid( '« date » (AAAA-MM-JJ) et « score » (0 à 100) obligatoires.' );
		}
		$list     = fn( $v, $n, $len = 400 ) => array_values( array_filter( array_map( fn( $l ) => CX_Repo::text( $l, $len ), array_slice( (array) $v, 0, $n ) ) ) );
		$criteria = array();
		foreach ( array_slice( (array) ( $in['criteria'] ?? array() ), 0, 12 ) as $c ) {
			$c = (array) $c;
			if ( empty( $c['label'] ) ) {
				continue;
			}
			$criteria[] = array(
				'key'    => sanitize_key( $c['key'] ?? '' ),
				'label'  => CX_Repo::text( $c['label'], 80 ),
				'weight' => max( 0, min( 100, (int) ( $c['weight'] ?? 0 ) ) ),
				'score'  => max( 0, min( 100, (int) ( $c['score'] ?? 0 ) ) ),
				'why'    => CX_Repo::text( $c['why'] ?? '', 300 ),
			);
		}
		if ( ! $criteria ) {
			return CX_Repo::invalid( '« criteria » obligatoire : la grille avec poids et note de chaque critère.' );
		}
		$v    = (array) ( $in['value_usd'] ?? array() );
		$note = array(
			'date'         => $date,
			'score'        => max( 0, min( 100, (int) round( (float) $in['score'] ) ) ),
			'grid_version' => max( 1, (int) ( $in['grid_version'] ?? 1 ) ),
			'criteria'     => $criteria,
			'value_usd'    => array(
				'low'   => max( 0, (int) ( $v['low'] ?? 0 ) ),
				'high'  => max( 0, (int) ( $v['high'] ?? 0 ) ),
				'basis' => CX_Repo::text( $v['basis'] ?? '', 300 ),
			),
			'strengths'    => $list( $in['strengths'] ?? array(), 6 ),
			'gaps'         => $list( $in['gaps'] ?? array(), 6 ),
			'actions'      => $list( $in['actions'] ?? array(), 5 ),
			'report'       => CX_Repo::text( $in['report'] ?? '', 2000 ),
			'at'           => CX_Repo::clock(),
		);
		// Une note par date : la dernière remplace la précédente.
		$all   = array_values( array_filter( self::notes(), fn( $n ) => $n['date'] !== $date ) );
		$all[] = $note;
		usort( $all, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );
		update_option( self::OPT, array_slice( $all, -52 ), false );

		$sent = false;
		if ( $note['score'] >= self::GOAL && CX_Guard::once( 'value|' . CX_Telegram::monday( new DateTimeImmutable( $date . ' 12:00', CX_Repo::tz() ) ) ) ) {
			$sent = true === CX_Telegram::send( self::message( $note ), true );
		}
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'goal'     => self::GOAL,
				'telegram' => $sent,
			),
			200
		);
	}

	/**
	 * Rapport Telegram (note ≥ 70).
	 *
	 * @param array $n Note.
	 * @return string
	 */
	private static function message( $n ) {
		$bul  = fn( $l ) => implode( "\n", array_map( fn( $x ) => '• ' . esc_html( $x ), $l ) );
		$when = wp_date( 'd/m', strtotime( $n['date'] . ' 12:00' ), CX_Repo::tz() );
		$text = '🏆 <b>Système Carnet XAU : ' . $n['score'] . '/100</b> (' . $when . ")\nObjectif 70 atteint : voici le rapport.\n\n<b>Grille</b>\n"
			. implode( "\n", array_map( fn( $c ) => '• ' . esc_html( $c['label'] ) . ' : ' . $c['score'] . '/100 (poids ' . $c['weight'] . ' %)', $n['criteria'] ) );
		if ( $n['value_usd']['high'] ) {
			$text .= "\n\n<b>Valeur estimée</b>\n• " . number_format( $n['value_usd']['low'], 0, ',', ' ' ) . ' à ' . number_format( $n['value_usd']['high'], 0, ',', ' ' ) . ' $'
				. ( $n['value_usd']['basis'] ? "\n• " . esc_html( $n['value_usd']['basis'] ) : '' );
		}
		foreach ( array( 'strengths' => 'Points forts', 'gaps' => 'Ce qui manque', 'actions' => 'Prochaines actions' ) as $k => $label ) {
			if ( $n[ $k ] ) {
				$text .= "\n\n<b>" . $label . "</b>\n" . $bul( $n[ $k ] );
			}
		}
		if ( $n['report'] ) {
			$text .= "\n\n" . esc_html( $n['report'] );
		}
		return mb_substr( $text, 0, 4000 );
	}
}
