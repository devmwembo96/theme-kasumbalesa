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
	 * Annonces de la semaine écrites par Claude dans son plan, à l'heure exacte (Unix).
	 *
	 * @param string $week Lundi (Y-m-d).
	 * @return array Liste de {t, impact, name, fp}.
	 */
	public static function plan_events( $week ) {
		$p = CX_Repo::plan( $week );
		if ( ! $p ) {
			return array();
		}
		$c      = is_array( $p['content'] ) ? $p['content'] : array();
		$monday = new DateTimeImmutable( $week . ' 00:00', CX_Repo::tz() );
		$out    = array();
		foreach ( (array) ( $c['calendar'] ?? $p['cal'] ?? array() ) as $e ) {
			$i = array_search( $e['day'] ?? '', CX_Plan::DAYS, true );
			if ( false === $i || empty( $e['time'] ) ) {
				continue;
			}
			$out[] = array(
				't'      => $monday->modify( '+' . $i . ' days ' . $e['time'] )->getTimestamp(),
				'impact' => 'medium' === ( $e['impact'] ?? '' ) ? 'medium' : 'high',
				'name'   => (string) ( $e['event'] ?? '' ),
				'fp'     => '',
			);
		}
		return $out;
	}

	/**
	 * Annonces entre deux instants : calendrier automatique, complété par celles du plan de
	 * Claude qui n'y sont pas (aucune annonce du calendrier à 10 minutes près).
	 *
	 * @param int  $from      Début (Unix).
	 * @param int  $to        Fin (Unix).
	 * @param bool $high_only Calendrier automatique : fort impact seulement (le plan de Claude
	 *                        compte toujours en entier).
	 * @return array Liste triée de {t, time, impact, name, fp}.
	 */
	public static function merged( $from, $to, $high_only = false ) {
		$out = array();
		foreach ( self::between( $from, $to ) as $e ) {
			if ( $high_only && 'high' !== $e['impact'] ) {
				continue;
			}
			$out[] = array(
				't'      => $e['t'],
				'impact' => $e['impact'],
				'name'   => $e['fr'] ? $e['fr'] : $e['title'],
				'fp'     => implode( ' · ', array_filter( array( $e['forecast'] ? 'prévu ' . $e['forecast'] : '', $e['previous'] ? 'précédent ' . $e['previous'] : '' ) ) ),
			);
		}
		$tz    = CX_Repo::tz();
		$weeks = array_unique( array( CX_Telegram::monday( ( new DateTimeImmutable( '@' . $from ) )->setTimezone( $tz ) ), CX_Telegram::monday( ( new DateTimeImmutable( '@' . max( $from, $to - 1 ) ) )->setTimezone( $tz ) ) ) );
		$auto  = $out;
		foreach ( $weeks as $week ) {
			foreach ( self::plan_events( $week ) as $e ) {
				if ( $e['t'] < $from || $e['t'] >= $to ) {
					continue;
				}
				foreach ( $auto as $a ) {
					if ( abs( $a['t'] - $e['t'] ) <= 10 * MINUTE_IN_SECONDS ) {
						continue 2;
					}
				}
				$out[] = $e;
			}
		}
		usort( $out, fn( $a, $b ) => $a['t'] <=> $b['t'] );
		foreach ( $out as &$e ) {
			$e['time'] = ( new DateTimeImmutable( '@' . $e['t'] ) )->setTimezone( $tz )->format( 'H:i' );
		}
		unset( $e );
		return $out;
	}

	/**
	 * Annonces d'une journée (heure de Lubumbashi).
	 *
	 * @param DateTimeImmutable $day Jour.
	 * @return array
	 */
	public static function day( DateTimeImmutable $day ) {
		$d0 = $day->setTimezone( CX_Repo::tz() )->setTime( 0, 0 );
		return self::merged( $d0->getTimestamp(), $d0->modify( '+1 day' )->getTimestamp() );
	}

	/**
	 * GET /calendar : aujourd'hui et les 7 jours suivants, ou ?week=lundi pour une semaine
	 * entière (rédaction du plan par Claude).
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response
	 */
	public static function rest( WP_REST_Request $req ) {
		if ( ! self::data()['tried'] ) {
			self::refresh( true );
		}
		$d     = self::data();
		$today = new DateTimeImmutable( 'today', CX_Repo::tz() );
		$week  = CX_Repo::date( $req->get_param( 'week' ) );
		$from  = $week ? new DateTimeImmutable( CX_Telegram::monday( new DateTimeImmutable( $week, CX_Repo::tz() ) ), CX_Repo::tz() ) : $today;
		$days  = $week ? 7 : 8;
		return new WP_REST_Response(
			array(
				'fetched' => $d['fetched'],
				'error'   => $d['error'],
				'now'     => time(),
				'today'   => $today->format( 'Y-m-d' ),
				'from'    => $from->format( 'Y-m-d' ),
				'events'  => self::between( $from->getTimestamp(), $from->modify( '+' . $days . ' days' )->getTimestamp() ),
			),
			200
		);
	}
}
