<?php
/**
 * Statisticien : où se trouve l'edge. Résultats en R des trades du journal et des signaux Claude
 * soldés, groupés par setup, séance et heure de Lubumbashi, sur tout l'historique et sur 30 jours.
 * Les plans de séance et le rejeu du soir lisent GET /edge pour mettre en tête ce qui gagne.
 *
 * Séances (heures locales, changements d'heure compris) : Asie avant 07:00 à Londres ; Londres de
 * 07:00 à Londres jusqu'à 08:15 à New York ; New York de 08:15 à 16:00 à New York ; hors séance après.
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
	 * @return string asia|london|ny|off
	 */
	public static function session( $ts ) {
		$at  = new DateTimeImmutable( '@' . $ts );
		$ldn = (int) $at->setTimezone( new DateTimeZone( 'Europe/London' ) )->format( 'Gi' );
		$ny  = (int) $at->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'Gi' );
		if ( $ny >= 815 && $ny < 1600 ) {
			return 'ny';
		}
		if ( $ldn >= 700 && $ny < 815 ) {
			return 'london';
		}
		return $ldn < 700 ? 'asia' : 'off';
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
			if ( ! $t['time'] ) {
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
		foreach ( $wpdb->get_results( "SELECT signale_le, setup, sens, resultat_r FROM $table WHERE resultat_r IS NOT NULL ORDER BY signale_le" ) as $s ) { // phpcs:ignore WordPress.DB.PreparedSQL
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
		$out   = array( 'note' => 'R par groupe : n trades, win % gagnants, avg R moyen, sum R total ; heures de Lubumbashi ; séances asia, london (07:00 Londres → 08:15 New York), ny (08:15 → 16:00 New York), off.' );
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
