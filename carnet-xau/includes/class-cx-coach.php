<?php
/**
 * Coach de Claude : débrief des trades du jour (après la relecture du soir) et bilan de la semaine
 * (dimanche), envoyés en privé sur Telegram et gardés dans le carnet pour suivre les objectifs.
 *
 * Le coach évalue aussi Claude : là où ses plans, fiches et zones ont mal fait, et les améliorations
 * qu'il lui soumet. Les plans de Claude lisent GET /coach (`claude`) et doivent y répondre.
 *
 * - POST /claude/coach (jeton du plan, en-tête X-CX-Key) :
 *   {"kind": "day"|"week", "date": "AAAA-MM-JJ", "lines": ["…"], "focus": "…", "score": 0-10,
 *    "claude": {"score": 0-10, "lines": ["…"], "improve": ["…"]}}
 * - GET  /coach : les 60 derniers débriefs (le coach relit l'objectif précédent avant d'écrire)
 *   et `claude` : les 10 dernières évaluations de Claude.
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
		$notes  = self::notes();
		$claude = array_values( array_filter( array_map( fn( $n ) => empty( $n['claude'] ) ? null : array( 'kind' => $n['kind'], 'date' => $n['date'] ) + $n['claude'], $notes ) ) );
		return new WP_REST_Response(
			array(
				'notes'  => $notes,
				'claude' => array_slice( $claude, -10 ),
			),
			200
		);
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
		$list   = fn( $v, $n ) => array_values( array_filter( array_map( fn( $l ) => CX_Repo::text( $l, 400 ), array_slice( (array) $v, 0, $n ) ) ) );
		$lines  = $list( $in['lines'] ?? array(), 10 );
		$focus  = CX_Repo::text( $in['focus'] ?? '', 300 );
		$c      = (array) ( $in['claude'] ?? array() );
		$claude = array(
			'score'   => isset( $c['score'] ) && is_numeric( $c['score'] ) ? max( 0, min( 10, (int) $c['score'] ) ) : null,
			'lines'   => $list( $c['lines'] ?? array(), 5 ),
			'improve' => $list( $c['improve'] ?? array(), 5 ),
		);
		if ( ! $claude['lines'] && ! $claude['improve'] ) {
			$claude = null;
		}
		// Un jour sans trade : seule l'évaluation de Claude suffit.
		if ( ( ! $lines || '' === $focus ) && ! $claude ) {
			return CX_Repo::invalid( '« lines » (10 au plus) et « focus » (l’objectif) obligatoires, ou « claude ».' );
		}
		$score = isset( $in['score'] ) && is_numeric( $in['score'] ) ? max( 0, min( 10, (int) $in['score'] ) ) : null;
		$note  = array(
			'kind'  => $kind,
			'date'  => $date,
			'lines' => $lines,
			'focus' => $focus,
			'score'  => $score,
			'claude' => $claude,
			'at'     => CX_Repo::clock(),
		);
		// Une note par jour et par type : la dernière remplace la précédente.
		$all   = array_values( array_filter( self::notes(), fn( $n ) => ! ( $n['kind'] === $kind && $n['date'] === $date ) ) );
		$all[] = $note;
		update_option( self::OPT, array_slice( $all, -60 ), false );

		$when = wp_date( 'd/m', strtotime( $date . ' 12:00' ), CX_Repo::tz() );
		$head = 'week' === $kind ? '🧭 <b>Coach · bilan de la semaine</b> (' . $when . ')' : '🎯 <b>Coach · débrief du ' . $when . '</b>';
		$bul  = fn( $l ) => implode( "\n\n", array_map( fn( $x ) => '• ' . esc_html( $x ), $l ) );
		$text = $head;
		if ( $lines ) {
			$text .= ( null !== $score ? "\nDiscipline : " . $score . '/10' : '' ) . "\n\n" . $bul( $lines );
		}
		if ( '' !== $focus ) {
			$text .= "\n\n🎯 <b>Objectif</b>\n" . esc_html( $focus );
		}
		if ( $claude ) {
			$text .= "\n\n━━━━━━━━━━━━\n🤖 <b>Claude évalué</b>" . ( null !== $claude['score'] ? ' : ' . $claude['score'] . '/10' : '' );
			if ( $claude['lines'] ) {
				$text .= "\n\n<b>Là où il a mal fait</b>\n" . $bul( $claude['lines'] );
			}
			if ( $claude['improve'] ) {
				$text .= "\n\n<b>Améliorations soumises</b>\n" . $bul( $claude['improve'] );
			}
		}
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
