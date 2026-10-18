<?php
/**
 * Plan de semaine élargi, rédigé exclusivement par Claude (tâche planifiée).
 *
 * - PUT /claude/plans/{lundi} : publie ou complète le plan d'une semaine. Protégé par le jeton
 *   du plan (en-tête X-CX-Key), généré dans Comptes › Connexions. Les clés envoyées remplacent
 *   celles du plan existant (envoyer seulement « review » complète le bilan d'une semaine).
 * - L'interface lit le plan, sans pouvoir le modifier.
 *
 * Le format complet est décrit dans docs/PLAN-CLAUDE.md. Les champs du plan d'origine
 * (biais, niveaux, annonces, scénarios) sont dérivés automatiquement : résumé de l'onglet
 * Jour, alertes d'annonce et commande /plan continuent de fonctionner.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plan rédigé par Claude.
 */
class CX_Plan {

	const DAYS = array( 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi' );

	/**
	 * Accroches.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Route de publication (publique, protégée par le jeton du plan).
	 */
	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/claude/plans/(?P<week>\d{4}-\d{2}-\d{2})',
			array(
				'methods'             => 'PUT,POST',
				'callback'            => array( __CLASS__, 'publish' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Nettoyage selon le format
	 * ------------------------------------------------------------------ */

	/**
	 * Nettoie une valeur selon son type.
	 *
	 * Types : num, pct (0–100), int3 (1–3), score (0–10), text, long, day, time,
	 * trend (h|b|r), pd (premium|discount|equilibre), side (achat|vente), state (vierge|testee),
	 * effect (up|down|neutral), impact (high|medium|low), bias (h|b|n), setup (1|2),
	 * tf (W|D|H4|H1|M15), ou un tableau [ 'list', spec, max ] / spec associatif.
	 *
	 * @param mixed $v    Valeur reçue.
	 * @param mixed $type Type.
	 * @return mixed
	 */
	private static function clean( $v, $type ) {
		if ( is_array( $type ) ) {
			if ( isset( $type[0] ) && 'list' === $type[0] ) {
				$out = array();
				foreach ( array_slice( is_array( $v ) ? array_values( $v ) : array(), 0, $type[2] ) as $item ) {
					$c = self::clean( $item, $type[1] );
					if ( null !== $c && '' !== $c && array() !== $c ) {
						$out[] = $c;
					}
				}
				return $out;
			}
			$v   = is_array( $v ) ? $v : array();
			$out = array();
			foreach ( $type as $k => $t ) {
				$out[ $k ] = self::clean( $v[ $k ] ?? null, $t );
			}
			return $out;
		}
		$enums = array(
			'trend'  => array( 'h', 'b', 'r' ),
			'pd'     => array( 'premium', 'discount', 'equilibre' ),
			'side'   => array( 'achat', 'vente' ),
			'state'  => array( 'vierge', 'testee' ),
			'effect' => array( 'up', 'down', 'neutral' ),
			'impact' => array( 'high', 'medium', 'low' ),
			'bias'   => array( 'h', 'b', 'n' ),
			'setup'  => array( '1', '2' ),
			'tf'     => array( 'W', 'D', 'H4', 'H1', 'M15' ),
			'day'    => self::DAYS,
		);
		switch ( $type ) {
			case 'num':
				return CX_Repo::num( $v, 0, 1e6 );
			case 'pct':
				$n = CX_Repo::num( $v, 0, 100 );
				return null === $n ? null : (int) round( $n );
			case 'int3':
				$n = CX_Repo::num( $v, 1, 3 );
				return null === $n ? null : (int) round( $n );
			case 'score':
				$n = CX_Repo::num( $v, 0, 10 );
				return null === $n ? null : round( $n, 1 );
			case 'time':
				return (string) CX_Repo::time( $v );
			case 'long':
				return CX_Repo::textarea( $v, 4000 );
			case 'text':
				return CX_Repo::text( $v, 300 );
			default:
				return isset( $enums[ $type ] ) ? CX_Repo::pick( is_scalar( $v ) ? (string) $v : '', $enums[ $type ], '' ) : null;
		}
	}

	/**
	 * Format du plan (voir docs/PLAN-CLAUDE.md).
	 *
	 * @return array
	 */
	public static function spec() {
		return array(
			'prediction'   => array(
				'bull'         => 'pct',
				'bear'         => 'pct',
				'range'        => 'pct',
				'summary'      => 'long',
				'main'         => 'long',
				'alt'          => 'long',
				'invalidation' => 'num',
				'range_high'   => 'num',
				'range_low'    => 'num',
			),
			'refs'         => array(
				'price'           => 'num',
				'week_open'       => 'num',
				'prev_week_high'  => 'num',
				'prev_week_low'   => 'num',
				'prev_month_high' => 'num',
				'prev_month_low'  => 'num',
				'atr_d'           => 'num',
				'atr_w'           => 'num',
			),
			'structure'    => array(
				'list',
				array(
					'tf'    => 'tf',
					'trend' => 'trend',
					'last'  => 'text',
					'pd'    => 'pd',
					'note'  => 'text',
				),
				6,
			),
			'zones'        => array(
				'list',
				array(
					'type'     => 'text',
					'tf'       => 'tf',
					'low'      => 'num',
					'high'     => 'num',
					'side'     => 'side',
					'strength' => 'int3',
					'state'    => 'state',
					'note'     => 'text',
				),
				12,
			),
			'levels'       => array(
				'list',
				array(
					'price' => 'num',
					'type'  => 'text',
					'note'  => 'text',
				),
				15,
			),
			'fundamentals' => array(
				'list',
				array(
					'key'    => 'text',
					'label'  => 'text',
					'effect' => 'effect',
					'value'  => 'text',
					'note'   => 'text',
				),
				8,
			),
			'fund_notes'   => 'long',
			'calendar'     => array(
				'list',
				array(
					'day'      => 'day',
					'time'     => 'time',
					'event'    => 'text',
					'impact'   => 'impact',
					'forecast' => 'text',
					'previous' => 'text',
				),
				25,
			),
			'days'         => array(
				'list',
				array(
					'day'     => 'day',
					'bias'    => 'bias',
					'focus'   => 'long',
					'session' => 'text',
				),
				5,
			),
			'scenarios'    => array(
				'list',
				array(
					'if'           => 'long',
					'then'         => 'long',
					'setup'        => 'setup',
					'side'         => 'side',
					'entry'        => 'text',
					'sl'           => 'num',
					'tp'           => 'num',
					'prob'         => 'pct',
					'invalidation' => 'text',
				),
				8,
			),
			'rules'        => array( 'list', 'text', 10 ),
			'review'       => array(
				'text'  => 'long',
				'score' => 'score',
			),
			'sources'      => 'long',
		);
	}

	/* ------------------------------------------------------------------
	 * Publication
	 * ------------------------------------------------------------------ */

	/**
	 * Publication par la tâche Claude.
	 *
	 * @param WP_REST_Request $req Requête (JSON du plan, en-tête X-CX-Key).
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
		$week = CX_Repo::date( (string) $req['week'] );
		if ( ! $week || '1' !== gmdate( 'N', strtotime( $week . ' 00:00:00 UTC' ) ) ) {
			return CX_Repo::invalid( 'La semaine doit commencer un lundi.' );
		}
		$in = $req->get_json_params();
		if ( ! is_array( $in ) || ! $in ) {
			return CX_Repo::invalid( 'Corps JSON attendu (voir docs/PLAN-CLAUDE.md).' );
		}
		$plan = self::save( $week, $in );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		// Nouveau plan (pas un simple bilan) : résumé sur Telegram.
		if ( isset( $in['prediction'] ) && empty( $req->get_param( 'silent' ) ) ) {
			CX_Telegram::send( CX_Telegram::plan_summary( $week ) );
		}
		return new WP_REST_Response(
			array(
				'ok'   => true,
				'week' => $week,
				'url'  => get_permalink( CX_Front::page_id() ),
			),
			200
		);
	}

	/**
	 * Fusionne et enregistre le plan, puis met à jour les champs d'origine.
	 *
	 * @param string $week Lundi.
	 * @param array  $in   Clés du plan à remplacer.
	 * @return array|WP_Error Plan enregistré.
	 */
	public static function save( $week, $in ) {
		global $wpdb;
		$spec    = self::spec();
		$old     = CX_Repo::plan( $week );
		$content = is_array( $old['content'] ?? null ) ? $old['content'] : array();
		foreach ( $spec as $k => $type ) {
			if ( array_key_exists( $k, $in ) ) {
				$content[ $k ] = self::clean( $in[ $k ], $type );
			}
		}

		// Champs du plan d'origine, dérivés du plan élargi.
		$p      = $content['prediction'] ?? array();
		$probs  = array(
			'h' => (int) ( $p['bull'] ?? 0 ),
			'b' => (int) ( $p['bear'] ?? 0 ),
			'n' => (int) ( $p['range'] ?? 0 ),
		);
		$bias   = max( $probs ) > 0 ? array_search( max( $probs ), $probs, true ) : '';
		$fund   = array();
		foreach ( (array) ( $content['fundamentals'] ?? array() ) as $f ) {
			if ( in_array( $f['key'], array( 'dxy', 'rates', 'fed', 'geo' ), true ) ) {
				$fund[ $f['key'] ] = $f['effect'];
			}
		}
		$structure = implode(
			"\n",
			array_map(
				fn( $s ) => trim( $s['tf'] . ' : ' . $s['last'] . ( $s['note'] ? ' — ' . $s['note'] : '' ) ),
				(array) ( $content['structure'] ?? array() )
			)
		);
		$legacy    = CX_Repo::save_plan(
			$week,
			array(
				'bias'      => $bias,
				'wh'        => $content['refs']['prev_week_high'] ?? null,
				'wl'        => $content['refs']['prev_week_low'] ?? null,
				'struct'    => $structure,
				'fund'      => $fund,
				'fundnotes' => $content['fund_notes'] ?? '',
				'levels'    => array_map(
					fn( $l ) => array(
						'price' => $l['price'],
						'type'  => $l['type'],
						'note'  => $l['note'],
					),
					(array) ( $content['levels'] ?? array() )
				),
				'cal'       => array_map(
					fn( $e ) => array(
						'day'   => $e['day'],
						'time'  => $e['time'],
						'event' => $e['event'],
					),
					(array) ( $content['calendar'] ?? array() )
				),
				'sc'        => array_map(
					fn( $s ) => array( 'text' => trim( 'Si ' . $s['if'] . ', alors ' . $s['then'] ) ),
					(array) ( $content['scenarios'] ?? array() )
				),
				'review'    => $content['review']['text'] ?? '',
			)
		);
		if ( is_wp_error( $legacy ) ) {
			return $legacy;
		}
		$wpdb->update(
			CX_Install::table( 'plans' ),
			array(
				'contenu'   => wp_json_encode( $content, JSON_UNESCAPED_UNICODE ),
				'auteur'    => 'claude',
				'publie_le' => isset( $in['prediction'] ) || empty( $old['publishedAt'] ) ? CX_Repo::clock() : $old['publishedAt'],
			),
			array(
				'account_id' => 0,
				'semaine'    => $week,
			)
		);
		return CX_Repo::plan( $week );
	}
}
