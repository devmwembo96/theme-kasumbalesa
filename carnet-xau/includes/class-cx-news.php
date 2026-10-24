<?php
/**
 * Playbook des annonces : réaction de l'or à chaque annonce USD à fort impact, mesurée sur les
 * bougies du compte de référence (M1, sinon M5, sinon M15) puis gardée pour toujours (300 dernières).
 * Les plans de Londres et de New York lisent GET /news-playbook avant une annonce du jour.
 *
 * Mesures, en dollars depuis l'ouverture de la bougie de l'annonce :
 * - up15 / down15 : plus haut et plus bas atteints dans les 15 premières minutes ;
 * - c15 / c60     : clôture à +15 et +60 min ;
 * - first         : sens de la première impulsion (celui des deux extrêmes atteint en premier) ;
 * - reverse       : la clôture à +60 min est de l'autre côté de l'ouverture que la première impulsion.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_News {

	const OPT = 'cx_news_playbook';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( CX_Telegram::CRON, array( __CLASS__, 'run' ), 25 );
	}

	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/news-playbook',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
	}

	/** @return array[] Réactions mesurées, de la plus ancienne à la plus récente. */
	public static function entries() {
		$d = get_option( self::OPT, array() );
		return is_array( $d ) ? array_values( $d ) : array();
	}

	/**
	 * Mesure les annonces à fort impact passées depuis plus de 60 min et pas encore mesurées.
	 */
	public static function run() {
		$now  = time();
		$all  = self::entries();
		$seen = array_flip( array_map( fn( $e ) => $e['t'] . '|' . $e['title'], $all ) );
		$data = CX_Live::candle_data();
		$add  = 0;
		foreach ( CX_Calendar::data()['events'] as $e ) {
			if ( 'high' !== $e['impact'] || $e['t'] > $now - HOUR_IN_SECONDS - 5 * MINUTE_IN_SECONDS || $e['t'] < $now - 4 * DAY_IN_SECONDS ) {
				continue;
			}
			if ( isset( $seen[ $e['t'] . '|' . $e['title'] ] ) ) {
				continue;
			}
			$m = self::measure( $data, (int) $e['t'] );
			if ( ! $m ) {
				continue;
			}
			$all[] = array(
				't'        => (int) $e['t'],
				'title'    => $e['title'],
				'forecast' => $e['forecast'],
				'previous' => $e['previous'],
				'firm'     => (string) ( $data['firm'] ?? '' ),
			) + $m;
			$seen[ $e['t'] . '|' . $e['title'] ] = 1;
			++$add;
		}
		if ( $add ) {
			usort( $all, fn( $a, $b ) => $a['t'] <=> $b['t'] );
			update_option( self::OPT, array_slice( $all, -300 ), false );
		}
	}

	/**
	 * Réaction mesurée sur les bougies, null si elles ne couvrent pas l'heure de l'annonce et l'heure qui suit.
	 *
	 * @param array $data Bougies (CX_Live::candle_data()).
	 * @param int   $t    Heure de l'annonce (Unix).
	 * @return array|null
	 */
	public static function measure( $data, $t ) {
		foreach ( array( 'M1' => 60, 'M5' => 300, 'M15' => 900 ) as $tf => $len ) {
			$bars = array_values( array_filter( (array) ( $data['tf'][ $tf ] ?? array() ), fn( $b ) => $b[0] + $len > $t && $b[0] < $t + HOUR_IN_SECONDS ) );
			// Couverture : la bougie de l'annonce et au moins 55 minutes après.
			if ( ! $bars || $bars[0][0] > $t || end( $bars )[0] + $len < $t + 55 * MINUTE_IN_SECONDS ) {
				continue;
			}
			$open  = (float) $bars[0][1];
			$hi    = $open;
			$lo    = $open;
			$t_hi  = PHP_INT_MAX;
			$t_lo  = PHP_INT_MAX;
			$c15   = null;
			$c60   = (float) end( $bars )[4];
			foreach ( $bars as $b ) {
				if ( $b[0] < $t + 15 * MINUTE_IN_SECONDS ) {
					$hi = max( $hi, (float) $b[2] );
					$lo = min( $lo, (float) $b[3] );
					if ( $b[2] >= $open + 1 ) {
						$t_hi = min( $t_hi, $b[0] );
					}
					if ( $b[3] <= $open - 1 ) {
						$t_lo = min( $t_lo, $b[0] );
					}
					$c15 = (float) $b[4];
				}
			}
			$up    = round( $hi - $open, 2 );
			$down  = round( $open - $lo, 2 );
			// Première impulsion : le premier extrême à dépasser 1 $, sinon le plus grand des deux.
			$first = $t_hi < $t_lo ? 'h' : ( $t_lo < $t_hi ? 'b' : ( $up >= $down ? 'h' : 'b' ) );
			if ( max( $up, $down ) < 1 ) {
				$first = 'n';
			}
			$d60 = round( $c60 - $open, 2 );
			return array(
				'tf'      => $tf,
				'open'    => round( $open, 2 ),
				'up15'    => $up,
				'down15'  => $down,
				'c15'     => round( (float) $c15 - $open, 2 ),
				'c60'     => $d60,
				'first'   => $first,
				'reverse' => ( 'h' === $first && $d60 < 0 ) || ( 'b' === $first && $d60 > 0 ),
			);
		}
		return null;
	}

	/**
	 * Résumé par annonce : nombre, amplitude moyenne des 15 premières minutes, part des retournements à +60 min.
	 *
	 * @param array[] $rows Réactions.
	 * @return array[]
	 */
	public static function summary( $rows ) {
		$by = array();
		foreach ( $rows as $r ) {
			$by[ $r['title'] ][] = $r;
		}
		$out = array();
		foreach ( $by as $title => $list ) {
			$n     = count( $list );
			$out[] = array(
				'title'      => $title,
				'n'          => $n,
				'range15'    => round( array_sum( array_map( fn( $r ) => max( $r['up15'], $r['down15'] ), $list ) ) / $n, 2 ),
				'abs60'      => round( array_sum( array_map( fn( $r ) => abs( $r['c60'] ), $list ) ) / $n, 2 ),
				'reverse_pc' => (int) round( 100 * count( array_filter( $list, fn( $r ) => $r['reverse'] ) ) / $n ),
				'last'       => end( $list ),
			);
		}
		usort( $out, fn( $a, $b ) => $b['n'] <=> $a['n'] );
		return $out;
	}

	/**
	 * GET /news-playbook : résumé par annonce et réactions mesurées (?title= pour une seule annonce).
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response
	 */
	public static function rest( WP_REST_Request $req ) {
		$rows  = self::entries();
		$title = (string) $req->get_param( 'title' );
		if ( '' !== $title ) {
			$rows = array_values( array_filter( $rows, fn( $r ) => 0 === strcasecmp( $r['title'], $title ) ) );
		}
		$tz = CX_Repo::tz();
		foreach ( $rows as &$r ) {
			$r['at'] = ( new DateTimeImmutable( '@' . $r['t'] ) )->setTimezone( $tz )->format( 'Y-m-d H:i' );
		}
		unset( $r );
		return new WP_REST_Response(
			array(
				'note'    => 'Dollars depuis l’ouverture de la bougie de l’annonce ; first h/b/n ; reverse = clôture +60 min opposée à la première impulsion ; heure de Lubumbashi.',
				'summary' => self::summary( $rows ),
				'events'  => array_slice( $rows, -60 ),
			),
			200
		);
	}
}
