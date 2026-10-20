<?php
/**
 * Fiabilité de Claude et remplissage automatique des trades MT5.
 *
 * - Chaque analyse de séance est notée par le carnet sur les bougies du courtier, sans avis de
 *   Claude : mouvement entre la publication et la fin de la séance visée (Asie : jusqu'à 08:30 à
 *   New York ; pré-marché et plan US : jusqu'à 16:00 à New York). Au-delà de ± 0,2 × ATR
 *   journalier le mouvement est haussier ou baissier, sinon neutre ; le biais est juste s'il
 *   correspond. GET /claude-score en fait le bilan.
 * - Un trade importé de MT5 reçoit le setup du signal Claude correspondant, la zone et le niveau
 *   du plan, et le biais du moment ; l'utilisateur ne complète que les règles et l'émotion.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_Score {

	/** Seuil du mouvement neutre, en part de l'ATR journalier. */
	const NEUTRAL = 0.2;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( CX_Telegram::CRON, array( __CLASS__, 'run' ), 15 );
	}

	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/claude-score',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'summary' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
	}

	/** Tâche du carnet : notes des séances, puis remplissage des anciens trades MT5 (une fois). */
	public static function run() {
		$now = new DateTimeImmutable( 'now', CX_Repo::tz() );
		foreach ( array( CX_Live::week(), CX_Telegram::monday( $now->modify( '-7 days' ) ) ) as $week ) {
			self::score_week( $week );
		}
		if ( ! get_option( 'cx_autofill_v1' ) ) {
			update_option( 'cx_autofill_v1', 1, false );
			self::backfill();
		}
	}

	/* ------------------------------------------------------------------
	 * Notes des séances
	 * ------------------------------------------------------------------ */

	/**
	 * Fin de la séance visée par une analyse, en secondes Unix.
	 *
	 * @param array $s Séance.
	 * @return int|null
	 */
	private static function end_ts( $s ) {
		$h = 'asia' === $s['kind'] ? '08:30' : '16:00';
		try {
			return ( new DateTimeImmutable( $s['date'] . ' ' . $h, new DateTimeZone( 'America/New_York' ) ) )->getTimestamp();
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Bougies les plus fines qui couvrent [début, fin].
	 *
	 * @param int $from Début.
	 * @param int $to   Fin.
	 * @return array|null Bougies de la fenêtre.
	 */
	private static function window( $from, $to ) {
		$tf = (array) ( get_option( 'cx_candles', array() )['tf'] ?? array() );
		foreach ( array( 'M5' => 300, 'M15' => 900, 'H1' => 3600 ) as $k => $dur ) {
			$rows = (array) ( $tf[ $k ] ?? array() );
			if ( count( $rows ) < 2 || $rows[0][0] > $from || end( $rows )[0] + $dur < $to ) {
				continue;
			}
			$in = array_values( array_filter( $rows, fn( $r ) => $r[0] + $dur > $from && $r[0] < $to ) );
			if ( $in ) {
				return $in;
			}
		}
		return null;
	}

	/**
	 * Note les séances terminées d'une semaine.
	 *
	 * @param string $week Lundi AAAA-MM-JJ.
	 */
	private static function score_week( $week ) {
		global $wpdb;
		$p = CX_Repo::plan( $week );
		if ( ! $p || ! is_array( $p['content'] ) || empty( $p['content']['sessions'] ) ) {
			return;
		}
		$c       = $p['content'];
		$atr     = (float) ( $c['refs']['atr_d'] ?? 0 );
		$thr     = round( self::NEUTRAL * ( $atr > 0 ? $atr : 40 ), 2 );
		$changed = false;
		foreach ( $c['sessions'] as $i => $s ) {
			if ( isset( $s['score'] ) || empty( $s['bias'] ) || empty( $s['at'] ) ) {
				continue;
			}
			$from = ( new DateTimeImmutable( $s['at'], CX_Repo::tz() ) )->getTimestamp();
			$to   = self::end_ts( $s );
			if ( ! $to || time() < $to + 600 ) {
				continue;
			}
			$late = $from >= $to;
			$rows = $late ? null : self::window( $from, $to );
			if ( ! $rows ) {
				// Publiée après la séance, ou bougies manquantes (PC éteint) : pas de note après 2 jours.
				if ( $late || time() > $to + 2 * DAY_IN_SECONDS ) {
					$c['sessions'][ $i ]['score'] = array( 'ok' => null, 'why' => $late ? 'publiée après la séance' : 'bougies manquantes' );
					$changed                      = true;
				}
				continue;
			}
			$open = round( (float) $rows[0][1], 2 );
			$last = round( (float) end( $rows )[4], 2 );
			$move = round( $last - $open, 2 );
			$real = $move > $thr ? 'h' : ( $move < -$thr ? 'b' : 'n' );
			$c['sessions'][ $i ]['score'] = array(
				'ok'    => $real === $s['bias'],
				'real'  => $real,
				'move'  => $move,
				'from'  => $open,
				'to'    => $last,
				'hi'    => round( max( array_column( $rows, 2 ) ), 2 ),
				'lo'    => round( min( array_column( $rows, 3 ) ), 2 ),
				'thr'   => $thr,
				'until' => ( new DateTimeImmutable( '@' . $to ) )->setTimezone( CX_Repo::tz() )->format( 'H:i' ),
			);
			$changed = true;
		}
		if ( $changed ) {
			$wpdb->update(
				CX_Install::table( 'plans' ),
				array( 'contenu' => wp_json_encode( $c, JSON_UNESCAPED_UNICODE ) ),
				array(
					'account_id' => 0,
					'semaine'    => $week,
				)
			);
		}
	}

	/**
	 * Bilan des notes sur les 12 dernières semaines.
	 *
	 * @return WP_REST_Response
	 */
	public static function summary() {
		$now   = new DateTimeImmutable( 'now', CX_Repo::tz() );
		$kinds = array();
		$last  = array();
		for ( $w = 0; $w < 12; $w++ ) {
			$p = CX_Repo::plan( CX_Telegram::monday( $now->modify( '-' . ( 7 * $w ) . ' days' ) ) );
			foreach ( (array) ( $p['content']['sessions'] ?? array() ) as $s ) {
				if ( ! isset( $s['score']['ok'] ) || null === $s['score']['ok'] ) {
					continue;
				}
				$k = $s['kind'];
				$kinds[ $k ]['n']  = ( $kinds[ $k ]['n'] ?? 0 ) + 1;
				$kinds[ $k ]['ok'] = ( $kinds[ $k ]['ok'] ?? 0 ) + ( $s['score']['ok'] ? 1 : 0 );
				$last[]            = array(
					'date' => $s['date'],
					'kind' => $k,
					'bias' => $s['bias'],
				) + $s['score'];
			}
		}
		usort( $last, fn( $a, $b ) => strcmp( $b['date'] . $b['kind'], $a['date'] . $a['kind'] ) );
		return new WP_REST_Response(
			array(
				'kinds'   => (object) $kinds,
				'last'    => array_slice( $last, 0, 15 ),
				'neutral' => self::NEUTRAL,
			),
			200
		);
	}

	/* ------------------------------------------------------------------
	 * Remplissage des trades MT5
	 * ------------------------------------------------------------------ */

	/**
	 * Setup, zone, niveau et biais d'un trade d'après les signaux et le plan.
	 *
	 * @param string            $side  achat ou vente.
	 * @param float|null        $entry Prix d'entrée.
	 * @param float|null        $stop  Écart entrée → stop ($).
	 * @param DateTimeImmutable $open  Ouverture (heure du carnet).
	 * @return array Champs du trade à remplir et note.
	 */
	public static function fill( $side, $entry, $stop, DateTimeImmutable $open ) {
		global $wpdb;
		$out  = array();
		$note = array();
		$ts   = $open->getTimestamp();

		// Signal Claude : même sens, émis entre 45 min avant et 5 min après l'ouverture, prix proche.
		if ( null !== $entry ) {
			$t    = CX_Install::table( 'csignals' );
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $t WHERE sens = %s AND signale_le BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL
					'vente' === $side ? 'sell' : 'buy',
					$open->modify( '-45 minutes' )->format( 'Y-m-d H:i:s' ),
					$open->modify( '+5 minutes' )->format( 'Y-m-d H:i:s' )
				)
			);
			$tol  = max( 2.0, 0.5 * (float) $stop );
			$best = null;
			foreach ( $rows as $r ) {
				if ( null === $r->entree || abs( (float) $r->entree - $entry ) > $tol ) {
					continue;
				}
				if ( ! $best || abs( strtotime( $r->signale_le ) - $ts ) < abs( strtotime( $best->signale_le ) - $ts ) ) {
					$best = $r;
				}
			}
			if ( $best && in_array( $best->setup, array( '1', '2', '3' ), true ) ) {
				$out['setup']          = $best->setup;
				$out['sur_niveau_cle'] = 1;
				$note[]                = sprintf( 'signal Claude de %s (setup %s%s)', substr( $best->signale_le, 11, 5 ), $best->setup, $best->zone ? ', ' . $best->zone : '' );
			}
		}

		$p = CX_Repo::plan( CX_Telegram::monday( $open ) );
		$c = is_array( $p['content'] ?? null ) ? $p['content'] : array();
		if ( ! $c ) {
			return array( $out, $note );
		}
		$date = $open->format( 'Y-m-d' );
		$ses  = array_values( array_filter( (array) ( $c['sessions'] ?? array() ), fn( $s ) => $s['date'] === $date && ! empty( $s['at'] ) && $s['at'] <= $open->format( 'Y-m-d H:i:s' ) ) );

		// Zone du plan de la semaine ou du plan US du jour, dans le sens du trade.
		if ( null !== $entry ) {
			$zones = (array) ( $c['zones'] ?? array() );
			foreach ( $ses as $s ) {
				$zones = array_merge( $zones, (array) ( $s['zones'] ?? array() ) );
			}
			foreach ( $zones as $z ) {
				if ( ( $z['side'] ?? '' ) === $side && $entry >= (float) $z['low'] - 1 && $entry <= (float) $z['high'] + 1 ) {
					$out['sur_niveau_cle'] = 1;
					$note[]                = sprintf( 'zone %s %s–%s', trim( ( $z['type'] ?? 'Zone' ) . ' ' . ( $z['tf'] ?? '' ) ), $z['low'], $z['high'] );
					break;
				}
			}
			// Niveau de liquidité du plan le plus proche (3 $ au plus).
			$near = null;
			foreach ( (array) ( $c['levels'] ?? array() ) as $l ) {
				$d = abs( (float) $l['price'] - $entry );
				if ( $d <= 3 && in_array( $l['type'] ?? '', CX_Repo::LEVELS, true ) && ( ! $near || $d < $near[0] ) ) {
					$near = array( $d, $l['type'] );
				}
			}
			if ( $near ) {
				$out['niveau'] = $near[1];
			}
		}

		// Biais du moment : dernière analyse de séance publiée, sinon le jour, sinon la semaine.
		$bias = '';
		$from = '';
		if ( $ses ) {
			$bias = (string) end( $ses )['bias'];
			$from = array( 'asia' => 'session Asie', 'premarket' => 'pré-marché', 'us' => 'plan US' )[ end( $ses )['kind'] ] ?? 'séance';
		}
		if ( '' === $bias ) {
			$days = array( 1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi' );
			foreach ( (array) ( $c['days'] ?? array() ) as $d ) {
				if ( ( $d['day'] ?? '' ) === ( $days[ (int) $open->format( 'N' ) ] ?? '' ) ) {
					$bias = (string) $d['bias'];
					$from = 'plan du jour';
				}
			}
		}
		if ( '' === $bias ) {
			$pr   = (array) ( $c['prediction'] ?? array() );
			$bull = (int) ( $pr['bull'] ?? 0 );
			$bear = (int) ( $pr['bear'] ?? 0 );
			$bias = $bull > $bear ? 'h' : ( $bear > $bull ? 'b' : 'n' );
			$from = 'plan de la semaine';
		}
		if ( in_array( $bias, array( 'h', 'b' ), true ) ) {
			$out['avec_biais'] = ( 'h' === $bias ) === ( 'achat' === $side ) ? 1 : 0;
			$note[]            = sprintf( 'biais %s (%s)', 'h' === $bias ? 'haussier' : 'baissier', $from );
		}
		return array( $out, $note );
	}

	/** Remplit les trades MT5 encore à compléter et sans setup (une seule fois, à l'installation). */
	private static function backfill() {
		global $wpdb;
		$t    = CX_Install::table( 'trades' );
		$rows = $wpdb->get_results( "SELECT * FROM $t WHERE source = 'mt5' AND a_completer = 1 AND setup = 'hors' ORDER BY id DESC LIMIT 300" ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $rows as $r ) {
			if ( ! preg_match( '/entrée ([\d.]+)/', (string) $r->notes, $m ) ) {
				continue;
			}
			$open = new DateTimeImmutable( $r->date . ' ' . $r->heure, CX_Repo::tz() );
			list( $set, $note ) = self::fill( $r->sens, (float) $m[1], null === $r->stop_usd ? null : (float) $r->stop_usd, $open );
			if ( $set ) {
				$set['notes'] = rtrim( (string) $r->notes ) . ' Rempli par le carnet : ' . implode( ' ; ', $note ) . '.';
				$wpdb->update( $t, $set, array( 'id' => (int) $r->id ) );
			}
		}
	}
}
