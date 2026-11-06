<?php
/**
 * Statisticien : où se trouve l'edge. Résultats en R des trades du journal et des signaux Claude
 * soldés, groupés par setup, séance et heure de Lubumbashi, sur tout l'historique et sur 30 jours.
 * Les plans de séance et le rejeu du soir lisent GET /edge pour mettre en tête ce qui gagne.
 *
 * Séances du Nasdaq 100 (heure de New York, changements d'heure compris) : ny = séance de New York
 * 09:30–16:00 ; apres = 16:00–18:00 ; nuit = séance de nuit 18:00–09:30. Ère NAS100 seulement.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_Edge {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/edge',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
	}

	/**
	 * Séance d'un instant.
	 *
	 * @param int $ts Unix.
	 * @return string ny|apres|nuit
	 */
	public static function session( $ts ) {
		$ny = (int) ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'Gi' );
		if ( $ny >= 930 && $ny < 1600 ) {
			return 'ny';
		}
		return ( $ny >= 1600 && $ny < 1800 ) ? 'apres' : 'nuit';
	}

	/**
	 * Bilan d'un groupe de résultats.
	 *
	 * @param float[] $rs Résultats en R.
	 * @return array {n, win, avg, sum}
	 */
	private static function stat( $rs ) {
		$n = count( $rs );
		return array(
			'n'   => $n,
			'win' => $n ? (int) round( 100 * count( array_filter( $rs, fn( $r ) => $r > 0 ) ) / $n ) : 0,
			'avg' => $n ? round( array_sum( $rs ) / $n, 2 ) : 0,
			'sum' => round( array_sum( $rs ), 2 ),
		);
	}

	/**
	 * Groupes par setup, séance, heure et sens.
	 *
	 * @param array[] $rows {ts, setup, side, r}.
	 * @return array
	 */
	public static function breakdown( $rows ) {
		$tz  = CX_Repo::tz();
		$grp = array(
			'setup'   => array(),
			'session' => array(),
			'hour'    => array(),
			'side'    => array(),
		);
		foreach ( $rows as $x ) {
			$grp['setup'][ (string) $x['setup'] ][]                   = $x['r'];
			$grp['session'][ self::session( $x['ts'] ) ][]             = $x['r'];
			$grp['hour'][ wp_date( 'H', $x['ts'], $tz ) . 'h' ][]      = $x['r'];
			$grp['side'][ $x['side'] ][]                               = $x['r'];
		}
		$out = array( 'all' => self::stat( array_column( $rows, 'r' ) ) );
		foreach ( $grp as $k => $g ) {
			ksort( $g );
			$out[ $k ] = array_map( array( __CLASS__, 'stat' ), $g );
		}
		return $out;
	}

	/**
	 * Trades du journal (tous les comptes) et signaux Claude soldés.
	 *
	 * @return array {journal: array[], claude: array[]}
	 */
	public static function rows() {
		global $wpdb;
		$tz      = CX_Repo::tz();
		$journal = array();
		foreach ( CX_Repo::trades() as $t ) {
			// Ère NAS100 seulement : les trades de l'or restent au journal, hors statistiques.
			if ( ! $t['time'] || ! CX_Market::in_era( $t['date'] . ' ' . $t['time'] ) ) {
				continue;
			}
			$journal[] = array(
				'ts'    => ( new DateTimeImmutable( $t['date'] . ' ' . $t['time'], $tz ) )->getTimestamp(),
				'setup' => (string) $t['setup'],
				'side'  => $t['dir'],
				'r'     => (float) $t['r'],
				'rules' => (bool) $t['rules'],
				'done'  => ! $t['toComplete'],
			);
		}
		$table  = CX_Install::table( 'csignals' );
		$claude = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT signale_le, setup, sens, resultat_r FROM $table WHERE resultat_r IS NOT NULL AND signale_le >= %s ORDER BY signale_le", CX_Market::epoch() ) ) as $s ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$claude[] = array(
				'ts'    => ( new DateTimeImmutable( $s->signale_le, $tz ) )->getTimestamp(),
				'setup' => (string) $s->setup,
				'side'  => 'buy' === $s->sens ? 'achat' : 'vente',
				'r'     => (float) $s->resultat_r,
			);
		}
		return array(
			'journal' => $journal,
			'claude'  => $claude,
		);
	}

	/**
	 * GET /edge : bilan en R par setup, séance, heure et sens, tout l'historique et 30 derniers jours ;
	 * journal : aussi selon le respect des règles (trades complétés).
	 *
	 * @return WP_REST_Response
	 */
	public static function rest() {
		$rows  = self::rows();
		$since = time() - 30 * DAY_IN_SECONDS;
		$out   = array( 'note' => 'R par groupe : n trades, win % gagnants, avg R moyen, sum R total ; heures de Lubumbashi ; séances ny (09:30 → 16:00 New York), apres (16:00 → 18:00), nuit (18:00 → 09:30) ; Nasdaq 100 (NAS100), ère NAS100 seulement.' );
		foreach ( $rows as $src => $list ) {
			$recent       = array_values( array_filter( $list, fn( $x ) => $x['ts'] >= $since ) );
			$out[ $src ] = array(
				'total' => self::breakdown( $list ),
				'30d'   => self::breakdown( $recent ),
			);
		}
		// Règles : seulement les trades complétés (un trade MT5 importé n'a pas encore ses règles).
		$j                       = array_filter( $rows['journal'], fn( $x ) => $x['done'] );
		$out['journal']['rules'] = array(
			'respected' => self::stat( array_column( array_filter( $j, fn( $x ) => $x['rules'] ), 'r' ) ),
			'broken'    => self::stat( array_column( array_filter( $j, fn( $x ) => ! $x['rules'] ), 'r' ) ),
		);
		return new WP_REST_Response( $out, 200 );
	}
}
