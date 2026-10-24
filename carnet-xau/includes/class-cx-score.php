<?php
/**
 * Fiabilité de Claude et remplissage automatique des trades MT5.
 *
 * - Chaque analyse de séance est notée par le carnet sur les bougies du courtier, sans avis de
 *   Claude : mouvement entre la publication et la fin de la séance visée (plan de Londres : jusqu'à
 *   08:15 à New York, quand le plan de New York prend le relais ; plan de New York : jusqu'à 16:00
 *   à New York ; anciennes analyses Asie : 08:30, pré-marché : 16:00). Au-delà de ± 0,2 × ATR
 *   journalier le mouvement est haussier ou baissier, sinon neutre ; le biais est juste s'il
 *   correspond. GET /claude-score en fait le bilan.
 * - Chaque fiche d'exécution d'un plan de séance, et chaque scénario du plan de la semaine, est
 *   rejoué sur les bougies, sans gestion : entrée touchée ou non (annulée si sa condition « Annulé
 *   si » est clôturée avant), puis stop (vérifié en premier) ou objectif, avec le temps mis.
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
		$h = array(
			'asia'   => '08:30',
			'london' => '08:15',
		)[ $s['kind'] ] ?? '16:00';
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
		$tf = (array) ( CX_Live::candle_data()['tf'] ?? array() );
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
		if ( ! $p || ! is_array( $p['content'] ) ) {
			return;
		}
		$c       = $p['content'];
		$changed = self::score_cards( $c ) | self::score_scenarios( $c, $week, (string) ( $p['publishedAt'] ?? '' ) );
		$atr     = (float) ( $c['refs']['atr_d'] ?? 0 );
		$thr     = round( self::NEUTRAL * ( $atr > 0 ? $atr : 40 ), 2 );
		foreach ( (array) ( $c['sessions'] ?? array() ) as $i => $s ) {
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

	/* ------------------------------------------------------------------
	 * Rejeu des fiches et des scénarios
	 * ------------------------------------------------------------------ */

	/** Unités pour le rejeu, de la plus fine à la plus large. */
	const REPLAY_TF = array(
		'M1'  => 60,
		'M5'  => 300,
		'M15' => 900,
		'H1'  => 3600,
	);

	/**
	 * Bougies les plus fines du compte de référence qui commencent avant $from.
	 *
	 * @param int $from Début (Unix).
	 * @return array|null [unité, durée (s), bougies]
	 */
	private static function bars_from( $from ) {
		$tf = (array) ( CX_Live::candle_data()['tf'] ?? array() );
		foreach ( self::REPLAY_TF as $k => $sec ) {
			$rows = (array) ( $tf[ $k ] ?? array() );
			if ( count( $rows ) > 1 && (int) $rows[0][0] <= $from ) {
				return array( $k, $sec, $rows );
			}
		}
		return null;
	}

	/**
	 * Condition « Clôture M15 au-dessus de 4 165,80 » lue dans un texte : [durée de l'unité, au-dessus ?, prix].
	 *
	 * @param string $text Texte de la fiche (cancel) ou du scénario (invalidation).
	 * @return array|null
	 */
	public static function close_rule( $text ) {
		$u = array(
			'M1'  => 60,
			'M5'  => 300,
			'M15' => 900,
			'M30' => 1800,
			'H1'  => 3600,
		);
		if ( ! preg_match( '/cl[ôo]ture\s+(M1|M5|M15|M30|H1)\b[^0-9]*?(au-dessus|au dessus|sous|en dessous|en-dessous)\s+(?:de\s+|du\s+)?([0-9][0-9\s\x{00A0}\x{202F}]*[.,]?[0-9]*)/iu', (string) $text, $m ) ) {
			return null;
		}
		$price = (float) str_replace( ',', '.', preg_replace( '/[\s\x{00A0}\x{202F}]/u', '', $m[3] ) );
		return $price > 0 ? array( $u[ strtoupper( $m[1] ) ], 0 === stripos( $m[2], 'au' ), $price ) : null;
	}

	/**
	 * Prix d'entrée d'un scénario : bord de la zone touché en premier (« 4 140–4 148 » : 4 148 pour un
	 * achat, 4 140 pour une vente), ou le prix seul.
	 *
	 * @param mixed $v    Texte ou nombre.
	 * @param bool  $buy  Achat.
	 * @return float|null
	 */
	private static function entry_price( $v, $buy ) {
		if ( is_numeric( $v ) ) {
			return (float) $v;
		}
		preg_match_all( '/\d{1,3}(?:[\s\x{00A0}\x{202F}]\d{3})+(?:[.,]\d+)?|\d+(?:[.,]\d+)?/u', (string) $v, $m );
		$n = array_values( array_filter( array_map( fn( $x ) => (float) str_replace( ',', '.', preg_replace( '/[\s\x{00A0}\x{202F}]/u', '', $x ) ), $m[0] ), fn( $x ) => $x >= 100 ) );
		if ( ! $n ) {
			return null;
		}
		$n = array_slice( $n, 0, 2 );
		return $buy ? max( $n ) : min( $n );
	}

	/**
	 * Rejeu d'un ordre sans gestion : en attente jusqu'à ce que le prix passe par l'entrée (dans un
	 * sens ou dans l'autre : ordre limite ou cassure ; annulé si la
	 * règle d'annulation est clôturée avant, ou à $until), puis stop (en premier) ou objectif ; clôture
	 * au dernier cours 24 h après l'entrée.
	 *
	 * @param array      $o     {side, entry, sl, tp}.
	 * @param array|null $rule  close_rule() de l'annulation.
	 * @param int        $from  Publication (Unix).
	 * @param int        $until Fin de validité de l'ordre en attente (Unix).
	 * @return array|null Résultat, null si les bougies ne permettent pas encore de conclure.
	 */
	public static function replay_order( $o, $rule, $from, $until ) {
		$e  = (float) $o['entry'];
		$sl = (float) $o['sl'];
		$tp = (float) $o['tp'];
		$st = abs( $e - $sl );
		if ( $e <= 0 || $sl <= 0 || $tp <= 0 || $st <= 0 ) {
			return array( 'status' => 'incomplete' );
		}
		$src = self::bars_from( $from );
		if ( ! $src ) {
			return time() > $until + 2 * DAY_IN_SECONDS ? array( 'status' => 'no_data' ) : null;
		}
		list( $tf, $sec, $bars ) = $src;
		$tz     = CX_Repo::tz();
		$hm     = fn( $ts ) => wp_date( 'd/m H:i', $ts, $tz );
		$buy    = 'vente' !== ( $o['side'] ?? '' );
		$rr     = round( abs( $tp - $e ) / $st, 2 );
		$fill   = null;
		$mfe    = 0.0;
		$last   = 0;
		$prev   = null;
		$base   = array(
			'tf' => $tf,
			'rr' => $rr,
		);
		foreach ( $bars as $b ) {
			$o0 = (int) $b[0];
			$t  = $o0 + $sec;
			if ( $t <= $from ) {
				continue;
			}
			$last = $t;
			$hi   = (float) $b[2];
			$lo   = (float) $b[3];
			if ( null === $fill ) {
				if ( $o0 >= $until ) {
					return $base + array( 'status' => 'not_filled' );
				}
				// Le prix passe par l'entrée : la bougie la contient, ou un écart la saute.
				$gap  = null !== $prev && ( $prev - $e ) * ( (float) $b[1] - $e ) < 0;
				$prev = (float) $b[4];
				if ( ( $lo <= $e && $hi >= $e ) || $gap ) {
					$fill = $o0;
				} elseif ( $rule && $sec <= $rule[0] && 0 === $t % $rule[0] && ( $rule[1] ? (float) $b[4] > $rule[2] : (float) $b[4] < $rule[2] ) ) {
					return $base + array(
						'status' => 'cancelled',
						'close'  => $hm( $t ),
					);
				} else {
					continue;
				}
			}
			$up   = $buy ? ( $hi - $e ) / $st : ( $e - $lo ) / $st;
			$down = $buy ? ( $lo - $e ) / $st : ( $e - $hi ) / $st;
			$mfe  = max( $mfe, $up );
			$r    = null;
			$what = '';
			if ( $down <= -1.0 ) {
				$r    = -1.0;
				$what = 'sl';
			} elseif ( $up >= $rr ) {
				$r    = $rr;
				$what = 'tp';
			} elseif ( $t - $fill > 24 * HOUR_IN_SECONDS ) {
				$r    = round( $buy ? ( (float) $b[4] - $e ) / $st : ( $e - (float) $b[4] ) / $st, 2 );
				$what = '24h';
			}
			if ( null !== $r ) {
				return $base + array(
					'status'  => $what,
					'r'       => $r,
					'fill'    => $hm( $fill ),
					'close'   => $hm( $t ),
					'to_fill' => (int) round( max( 0, $fill - $from ) / 60 ),
					'to_out'  => (int) round( ( $t - $fill ) / 60 ),
					'mfe'     => round( $mfe, 2 ),
				);
			}
		}
		// Pas encore d'issue : on attend de nouvelles bougies, 2 jours au plus. Ordre en attente : jamais
		// touché si les bougies vont jusqu'à sa fin de validité (à 2 h près : clôture du marché le vendredi).
		$end = (int) end( $bars )[0] + $sec;
		if ( null === $fill && ( $last >= $until || ( time() > $until && $end >= $until - 2 * HOUR_IN_SECONDS ) ) ) {
			return $base + array( 'status' => 'not_filled' );
		}
		return time() > max( $until, $fill ?? 0 ) + 2 * DAY_IN_SECONDS ? $base + array( 'status' => 'no_data' ) : null;
	}

	/**
	 * Fiches des plans de séance terminés : rejouées une fois, résultat dans session.cards.
	 *
	 * @param array $c Contenu du plan (modifié).
	 * @return bool Vrai si quelque chose a changé.
	 */
	private static function score_cards( &$c ) {
		$changed = false;
		foreach ( (array) ( $c['sessions'] ?? array() ) as $i => $s ) {
			if ( isset( $s['cards'] ) || empty( $s['positions'] ) || empty( $s['at'] ) ) {
				continue;
			}
			$to = self::end_ts( $s );
			if ( ! $to || time() < $to + 600 ) {
				continue;
			}
			$from  = ( new DateTimeImmutable( $s['at'], CX_Repo::tz() ) )->getTimestamp();
			$cards = array();
			foreach ( array_values( $s['positions'] ) as $n => $p ) {
				$res = self::replay_order( $p, self::close_rule( $p['cancel'] ?? '' ), $from, $to );
				if ( null === $res ) {
					continue 2;
				}
				$cards[] = array(
					'n'     => $n + 1,
					'side'  => $p['side'] ?? '',
					'setup' => $p['setup'] ?? '',
				) + $res;
			}
			$c['sessions'][ $i ]['cards'] = $cards;
			$changed                      = true;
		}
		return $changed;
	}

	/**
	 * Scénarios du plan de la semaine, rejoués de leur publication (ou de la dernière relecture qui les
	 * a refaits) au vendredi 23:59 (Lubumbashi), une fois la semaine terminée ; résultat dans scenario_score.
	 *
	 * @param array  $c    Contenu du plan (modifié).
	 * @param string $week      Lundi.
	 * @param string $published Publication du plan (heure du carnet).
	 * @return bool
	 */
	private static function score_scenarios( &$c, $week, $published = '' ) {
		if ( isset( $c['scenario_score'] ) || empty( $c['scenarios'] ) ) {
			return false;
		}
		$mon = new DateTimeImmutable( $week . ' 00:00', CX_Repo::tz() );
		$to  = $mon->modify( '+5 days' )->getTimestamp();
		if ( time() < $to ) {
			return false;
		}
		// Versions des scénarios : celle du dimanche (`initial`, gardée avant la première mise à jour) de la
		// publication à la première relecture « mis_a_jour », puis la dernière depuis la dernière (17:00 New York).
		$ny    = new DateTimeZone( 'America/New_York' );
		$start = $mon->getTimestamp();
		if ( $published ) {
			$start = max( $start, ( new DateTimeImmutable( $published, CX_Repo::tz() ) )->getTimestamp() );
		}
		$ups = array();
		foreach ( (array) ( $c['updates'] ?? array() ) as $u ) {
			if ( 'mis_a_jour' === ( $u['status'] ?? '' ) && ! empty( $u['date'] ) ) {
				$ups[] = ( new DateTimeImmutable( $u['date'] . ' 17:00', $ny ) )->getTimestamp();
			}
		}
		sort( $ups );
		$runs = array();
		if ( $ups && ! empty( $c['initial']['scenarios'] ) ) {
			$runs[] = array( 'initial', $c['initial']['scenarios'], $start, min( $to, $ups[0] ) );
		}
		$runs[] = array( 'final', $c['scenarios'], $ups ? max( $start, end( $ups ) ) : $start, $to );
		$out = array();
		foreach ( $runs as list( $version, $list, $from, $until ) ) {
			if ( $from >= $until ) {
				continue;
			}
			foreach ( array_values( (array) $list ) as $n => $x ) {
				$o   = array(
					'side'  => $x['side'] ?? '',
					'entry' => self::entry_price( $x['entry'] ?? '', 'vente' !== ( $x['side'] ?? '' ) ),
					'sl'    => $x['sl'] ?? null,
					'tp'    => $x['tp'] ?? null,
				);
				$res = self::replay_order( $o, self::close_rule( $x['invalidation'] ?? '' ), $from, $until );
				if ( null === $res ) {
					return false;
				}
				$out[] = array(
					'n'       => $n + 1,
					'version' => $version,
					'side'    => $o['side'],
					'setup'   => $x['setup'] ?? '',
				) + $res;
			}
		}
		$c['scenario_score'] = $out;
		return true;
	}

	/**
	 * Bilan des fiches : publiées, déclenchées, objectif, stop, R moyen, temps moyen.
	 *
	 * @param array[] $cards Fiches rejouées.
	 * @return array
	 */
	public static function card_stats( $cards ) {
		$done = array_values( array_filter( $cards, fn( $x ) => isset( $x['r'] ) ) );
		$tp   = array_values( array_filter( $done, fn( $x ) => 'tp' === $x['status'] ) );
		$n    = count( $done );
		return array(
			'published'  => count( array_filter( $cards, fn( $x ) => ! in_array( $x['status'], array( 'incomplete', 'no_data' ), true ) ) ),
			'triggered'  => $n,
			'tp'         => count( $tp ),
			'sl'         => count( array_filter( $done, fn( $x ) => 'sl' === $x['status'] ) ),
			'not_filled' => count( array_filter( $cards, fn( $x ) => 'not_filled' === $x['status'] ) ),
			'cancelled'  => count( array_filter( $cards, fn( $x ) => 'cancelled' === $x['status'] ) ),
			'win'        => $n ? (int) round( 100 * count( array_filter( $done, fn( $x ) => $x['r'] > 0 ) ) / $n ) : 0,
			'sum_r'      => round( array_sum( array_column( $done, 'r' ) ), 2 ),
			'avg_r'      => $n ? round( array_sum( array_column( $done, 'r' ) ) / $n, 2 ) : 0,
			'tp_minutes' => $tp ? (int) round( array_sum( array_column( $tp, 'to_out' ) ) / count( $tp ) ) : null,
		);
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
		$cards = array();
		$recent = array();
		$scen  = array();
		for ( $w = 0; $w < 12; $w++ ) {
			$p = CX_Repo::plan( CX_Telegram::monday( $now->modify( '-' . ( 7 * $w ) . ' days' ) ) );
			$scen = array_merge( $scen, (array) ( $p['content']['scenario_score'] ?? array() ) );
			foreach ( (array) ( $p['content']['sessions'] ?? array() ) as $s ) {
				foreach ( (array) ( $s['cards'] ?? array() ) as $x ) {
					$cards[ $s['kind'] ][] = $x;
					$recent[]              = array(
						'date' => $s['date'],
						'kind' => $s['kind'],
					) + $x;
				}
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
				// Fiches rejouées sur les bougies, sans gestion (objectif ou stop) : par séance et les 20 dernières.
				'cards'   => array(
					'all'    => self::card_stats( array_merge( array(), ...array_values( $cards ) ) ),
					'kinds'  => (object) array_map( array( __CLASS__, 'card_stats' ), $cards ),
					'recent' => array_slice( array_reverse( $recent ), 0, 20 ),
				),
				'scenarios' => self::card_stats( $scen ),
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
	 * @param string            $ref   Référence du signal chargé dans le Calculateur XAU (commentaire de l'ordre).
	 * @return array Champs du trade à remplir et note.
	 */
	public static function fill( $side, $entry, $stop, DateTimeImmutable $open, $ref = '' ) {
		global $wpdb;
		$out  = array();
		$note = array();
		$ts   = $open->getTimestamp();

		// Signal chargé dans le Calculateur XAU : référence exacte.
		$best = null;
		if ( '' !== $ref ) {
			$t    = CX_Install::table( 'csignals' );
			$best = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE ref = %s AND sens = %s ORDER BY id DESC LIMIT 1", $ref, 'vente' === $side ? 'sell' : 'buy' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		// Sinon, signal Claude : même sens, émis entre 45 min avant et 5 min après l'ouverture, prix proche.
		if ( ! $best && null !== $entry ) {
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
			foreach ( $rows as $r ) {
				if ( null === $r->entree || abs( (float) $r->entree - $entry ) > $tol ) {
					continue;
				}
				if ( ! $best || abs( strtotime( $r->signale_le ) - $ts ) < abs( strtotime( $best->signale_le ) - $ts ) ) {
					$best = $r;
				}
			}
		}
		if ( $best && in_array( $best->setup, array( '1', '2', '3' ), true ) ) {
			$out['setup']          = $best->setup;
			$out['sur_niveau_cle'] = 1;
			$note[]                = sprintf( 'signal Claude de %s (setup %s%s)', substr( $best->signale_le, 11, 5 ), $best->setup, $best->zone ? ', ' . $best->zone : '' );
		}

		$p = CX_Repo::plan( CX_Telegram::monday( $open ) );
		$c = is_array( $p['content'] ?? null ) ? $p['content'] : array();
		if ( ! $c ) {
			return array( $out, $note );
		}
		$date = $open->format( 'Y-m-d' );
		$ses  = array_values( array_filter( (array) ( $c['sessions'] ?? array() ), fn( $s ) => $s['date'] === $date && ! empty( $s['at'] ) && $s['at'] <= $open->format( 'Y-m-d H:i:s' ) ) );

		// Zone du plan de la semaine ou d'un plan de séance du jour, dans le sens du trade.
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
			$from = array( 'asia' => 'session Asie', 'london' => 'plan de Londres', 'premarket' => 'pré-marché', 'us' => 'plan de New York' )[ end( $ses )['kind'] ] ?? 'séance';
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
