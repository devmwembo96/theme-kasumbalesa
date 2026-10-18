<?php
/**
 * Calendrier économique automatique : annonces USD à fort et moyen impact de la semaine,
 * lues sur le flux public de ForexFactory (faireconomy.media) toutes les 6 heures par WP-Cron.
 *
 * - GET /calendar  annonces d'aujourd'hui (heure de Lubumbashi) et des 7 jours suivants.
 *
 * Les annonces des jours passés de la semaine restent en mémoire quand le flux change de
 * semaine (le dimanche) ; celles de plus de 21 jours sont effacées.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calendrier économique.
 */
class CX_Calendar {

	const OPT  = 'cx_calendar';
	const FEED = 'https://nfs.faireconomy.media/ff_calendar_thisweek.json';

	/**
	 * Noms français des annonces les plus suivies (le titre anglais reste affiché).
	 */
	const FR = array(
		'Non-Farm Employment Change'         => 'Emplois non agricoles (NFP)',
		'Unemployment Rate'                  => 'Taux de chômage',
		'Average Hourly Earnings m/m'        => 'Salaire horaire moyen',
		'CPI m/m'                            => 'Inflation (IPC) sur un mois',
		'CPI y/y'                            => 'Inflation (IPC) sur un an',
		'Core CPI m/m'                       => 'Inflation sous-jacente (IPC)',
		'PPI m/m'                            => 'Prix à la production',
		'Core PPI m/m'                       => 'Prix à la production sous-jacents',
		'Core PCE Price Index m/m'           => 'Inflation PCE sous-jacente',
		'Federal Funds Rate'                 => 'Taux directeur de la Fed',
		'FOMC Statement'                     => 'Communiqué de la Fed (FOMC)',
		'FOMC Press Conference'              => 'Conférence de presse de la Fed',
		'FOMC Meeting Minutes'               => 'Compte rendu de la Fed (FOMC)',
		'Fed Chair Powell Speaks'            => 'Discours du président de la Fed',
		'Advance GDP q/q'                    => 'PIB (première estimation)',
		'Prelim GDP q/q'                     => 'PIB (estimation préliminaire)',
		'Final GDP q/q'                      => 'PIB (estimation finale)',
		'Retail Sales m/m'                   => 'Ventes au détail',
		'Core Retail Sales m/m'              => 'Ventes au détail hors automobile',
		'ISM Manufacturing PMI'              => 'Indice ISM manufacturier',
		'ISM Services PMI'                   => 'Indice ISM des services',
		'Flash Manufacturing PMI'            => 'PMI manufacturier (flash)',
		'Flash Services PMI'                 => 'PMI des services (flash)',
		'JOLTS Job Openings'                 => 'Offres d’emploi (JOLTS)',
		'ADP Non-Farm Employment Change'     => 'Emplois privés ADP',
		'Unemployment Claims'                => 'Inscriptions au chômage',
		'Prelim UoM Consumer Sentiment'      => 'Confiance des consommateurs (Michigan)',
		'Revised UoM Consumer Sentiment'     => 'Confiance des consommateurs (Michigan, révisée)',
		'Prelim UoM Inflation Expectations'  => 'Anticipations d’inflation (Michigan)',
		'Revised UoM Inflation Expectations' => 'Anticipations d’inflation (Michigan, révisées)',
		'CB Consumer Confidence'             => 'Confiance des consommateurs (Conference Board)',
		'Durable Goods Orders m/m'           => 'Commandes de biens durables',
		'Empire State Manufacturing Index'   => 'Indice manufacturier de New York',
		'Philly Fed Manufacturing Index'     => 'Indice manufacturier de Philadelphie',
	);

	/**
	 * Accroches.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( CX_Telegram::CRON, array( __CLASS__, 'refresh' ) );
	}

	/**
	 * Route de lecture.
	 */
	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/calendar',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
	}

	/**
	 * Données enregistrées.
	 *
	 * @return array{fetched:int,tried:int,error:string,events:array}
	 */
	public static function data() {
		$d = get_option( self::OPT );
		return array(
			'fetched' => (int) ( $d['fetched'] ?? 0 ),
			'tried'   => (int) ( $d['tried'] ?? 0 ),
			'error'   => (string) ( $d['error'] ?? '' ),
			'events'  => is_array( $d['events'] ?? null ) ? $d['events'] : array(),
		);
	}

	/**
	 * Relit le flux : toutes les 6 heures, ou 1 heure après un échec.
	 *
	 * @param bool $force Relire sans attendre.
	 * @return bool Vrai si le flux a été lu.
	 */
	public static function refresh( $force = false ) {
		$d    = self::data();
		$wait = '' === $d['error'] ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS;
		if ( ! $force && time() - $d['tried'] < $wait ) {
			return false;
		}
		$d['tried'] = time();
		$res        = wp_remote_get(
			self::FEED,
			array(
				'timeout'    => 15,
				'user-agent' => 'CarnetXAU/' . CX_VERSION . '; ' . home_url( '/' ),
			)
		);
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$feed = 200 === $code ? json_decode( (string) wp_remote_retrieve_body( $res ), true ) : null;
		if ( ! is_array( $feed ) || ! $feed ) {
			$d['error'] = is_wp_error( $res ) ? $res->get_error_message() : 'Réponse ' . $code . ' du calendrier.';
			update_option( self::OPT, $d, false );
			return false;
		}

		$start  = PHP_INT_MAX;
		$events = array();
		foreach ( $feed as $e ) {
			$t = is_array( $e ) ? strtotime( (string) ( $e['date'] ?? '' ) ) : false;
			if ( ! $t ) {
				continue;
			}
			$start  = min( $start, $t );
			$impact = strtolower( (string) ( $e['impact'] ?? '' ) );
			if ( 'USD' !== ( $e['country'] ?? '' ) || ! in_array( $impact, array( 'high', 'medium' ), true ) ) {
				continue;
			}
			$events[] = array(
				't'        => $t,
				'impact'   => $impact,
				'title'    => CX_Repo::text( $e['title'] ?? '', 120 ),
				'forecast' => CX_Repo::text( $e['forecast'] ?? '', 20 ),
				'previous' => CX_Repo::text( $e['previous'] ?? '', 20 ),
			);
		}
		// Semaine du flux remplacée en entier ; les jours d'avant (semaine précédente) sont gardés 21 jours.
		$start = strtotime( gmdate( 'Y-m-d', $start ) . ' 00:00:00 UTC' );
		foreach ( $d['events'] as $e ) {
			if ( $e['t'] < $start && $e['t'] > time() - 21 * DAY_IN_SECONDS ) {
				$events[] = $e;
			}
		}
		usort( $events, fn( $a, $b ) => $a['t'] <=> $b['t'] );
		$d['events']  = $events;
		$d['fetched'] = time();
		$d['error']   = '';
		update_option( self::OPT, $d, false );
		return true;
	}

	/**
	 * Annonces entre deux instants, avec date et heure de Lubumbashi.
	 *
	 * @param int $from Début (Unix).
	 * @param int $to   Fin (Unix).
	 * @return array
	 */
	public static function between( $from, $to ) {
		$tz  = CX_Repo::tz();
		$out = array();
		foreach ( self::data()['events'] as $e ) {
			if ( $e['t'] < $from || $e['t'] >= $to ) {
				continue;
			}
			$at    = ( new DateTimeImmutable( '@' . $e['t'] ) )->setTimezone( $tz );
			$out[] = $e + array(
				'date' => $at->format( 'Y-m-d' ),
				'time' => $at->format( 'H:i' ),
				'fr'   => self::FR[ $e['title'] ] ?? '',
			);
		}
		return $out;
	}

	/**
	 * GET /calendar : aujourd'hui et les 7 jours suivants.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest() {
		if ( ! self::data()['tried'] ) {
			self::refresh( true );
		}
		$d     = self::data();
		$today = new DateTimeImmutable( 'today', CX_Repo::tz() );
		return new WP_REST_Response(
			array(
				'fetched' => $d['fetched'],
				'error'   => $d['error'],
				'now'     => time(),
				'today'   => $today->format( 'Y-m-d' ),
				'events'  => self::between( $today->getTimestamp(), $today->modify( '+8 days' )->getTimestamp() ),
			),
			200
		);
	}
}
