<?php
/**
 * Échanges en direct avec l'EA MT5 (clé MT5 du compte, en-tête X-CX-Key) :
 * - GET  /mt5/plan    plan de Claude au format texte (une ligne par élément), règles de risque
 *                     du compte et état du jour, pour le tracé et le calcul des signaux ;
 * - POST /mt5/event   signal (BUY NOW / SELL NOW), invalidation touchée, zone cassée :
 *                     enregistrés, visibles dans le carnet et envoyés sur Telegram ;
 * - POST /mt5/candles bougies du courtier, rangées par compte (chaque courtier a ses propres prix
 *   et ses propres bougies journalières et H4, selon l'heure de son serveur) ;
 * - GET  /candles     les bougies du compte de référence de l'analyse (tâches Claude, interface),
 *   ou celles d'un compte précis avec ?account=ID.
 *
 * Format texte (séparateur « | », prix avec un point décimal, heures en secondes Unix UTC ; « origine » :
 * bougie de départ du tracé, « jj/mm hh:mm » en heure du serveur MT5 du compte, « jj/mm » sans heure,
 * vide si le plan n'en donne pas) :
 *   V|empreinte|lundi|publié|mis à jour
 *   B|haussier|baissier|range
 *   I|invalidation|range bas|range haut|prix de référence|invalidée (0/1)
 *   Z|clé|type|ut|bas|haut|achat/vente|force|état (vierge/testee/cassee)|origine
 *   L|prix|type|origine
 *   N|heure unix|impact|annonce
 *   R|risque|risque réduit|seuil|contrat|trades max|pertes max|spread max %|stop min|stop max|limite jour $|taille|plancher
 *   X|invalidation de la dernière analyse de séance du jour
 *   P|achat ou vente|entrée|stop|objectif|nom (scénarios d'exécution de cette analyse)
 *   D|trades du jour|pertes du jour|résultat du jour $
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plan dynamique et signaux d'exécution.
 */
class CX_Live {

	/** Plage du stop (écart entrée → stop, en $) envoyée à l'EA : jusqu'à 8 $, le lot baisse et le risque reste le même. */
	const STOP_MIN = 1.5;
	const STOP_MAX = 8.0;

	/** Unités de temps reçues de l'EA et nombre de bougies gardées (M1 à M15 : analyses de séance et rejeu du soir). */
	const CANDLE_MAX = array(
		'D1'  => 150,
		'H4'  => 300,
		'H1'  => 500,
		'M15' => 400,
		'M5'  => 600,
		'M1'  => 1500,
	);

	/**
	 * Accroches.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( CX_Telegram::CRON, array( __CLASS__, 'repair_foreign' ), 5 );
	}

	/**
	 * État du plan faussé par un EA posé sur un autre graphique (BTC…) : invalidation à un prix qui
	 * n'est pas celui de l'or levée, avec les zones « cassées » au même instant ; zone marquée à un
	 * prix qui n'est pas celui de l'or remise à son état d'avant.
	 */
	public static function repair_foreign() {
		global $wpdb;
		$week = self::week();
		$p    = CX_Repo::plan( $week );
		if ( ! $p || ! is_array( $p['content'] ) || empty( $p['content']['live'] ) ) {
			return;
		}
		$c    = $p['content'];
		$live = (array) $c['live'];
		$bad  = array();
		if ( ! empty( $live['invalidated'] ) && null !== ( $live['invalidated']['price'] ?? null ) && ! self::gold_ok( (float) $live['invalidated']['price'] ) ) {
			$bad[] = $live['invalidated']['at'];
			unset( $live['invalidated'] );
		}
		$fix = (bool) $bad;
		foreach ( (array) ( $live['zones'] ?? array() ) as $k => $z ) {
			if ( in_array( $z['at'] ?? '', $bad, true ) || ( isset( $z['price'] ) && ! self::gold_ok( (float) $z['price'] ) ) ) {
				unset( $live['zones'][ $k ] );
				$fix = true;
			}
		}
		if ( ! $fix ) {
			return;
		}
		$c['live'] = $live;
		$wpdb->update(
			CX_Install::table( 'plans' ),
			array( 'contenu' => wp_json_encode( $c, JSON_UNESCAPED_UNICODE ) ),
			array(
				'account_id' => 0,
				'semaine'    => $week,
			)
		);
	}

	/**
	 * Routes (authentification par la clé MT5 dans chaque méthode, sauf /candles).
	 */
	public static function routes() {
		$r = array(
			'/mt5/plan'    => array( 'GET', 'plan' ),
			'/mt5/event'   => array( 'POST', 'event' ),
			'/mt5/candles' => array( 'POST', 'store_candles' ),
		);
		foreach ( $r as $path => $def ) {
			register_rest_route(
				CX_Rest::NS,
				$path,
				array(
					'methods'             => $def[0],
					'callback'            => array( __CLASS__, $def[1] ),
					'permission_callback' => '__return_true',
				)
			);
		}
		register_rest_route(
			CX_Rest::NS,
			'/claude-signals',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_signals' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
		register_rest_route(
			CX_Rest::NS,
			'/candles',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'candles' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
	}

	/**
	 * Compte authentifié par la clé MT5, ou erreur.
	 *
	 * @param WP_REST_Request $req    Requête.
	 * @param string          $bucket Point d'entrée (limite de débit).
	 * @return array|WP_Error
	 */
	private static function auth( WP_REST_Request $req, $bucket ) {
		$limit = CX_Guard::throttle( $bucket, 120 );
		if ( $limit ) {
			return $limit;
		}
		$acc = CX_Ingest::account_by_key( (string) $req->get_header( 'x_cx_key' ) );
		return $acc ? $acc : CX_Guard::refuse( $bucket, 'clé MT5 invalide' );
	}

	/**
	 * Semaine utile : la semaine en cours, ou la suivante le samedi et le dimanche.
	 *
	 * @return string Lundi.
	 */
	public static function week() {
		$now = new DateTimeImmutable( 'now', CX_Repo::tz() );
		return (int) $now->format( 'N' ) >= 6 ? $now->modify( 'next monday' )->format( 'Y-m-d' ) : CX_Telegram::monday( $now );
	}

	/**
	 * Clé stable d'une zone (bornes et sens), pour suivre son état d'une version à l'autre.
	 *
	 * @param array $z Zone.
	 * @return string
	 */
	public static function zone_key( $z ) {
		return number_format( (float) $z['low'], 2, '.', '' ) . '-' . number_format( (float) $z['high'], 2, '.', '' ) . '-' . ( 'vente' === $z['side'] ? 's' : 'b' );
	}

	/**
	 * Nettoie un champ texte pour le format à barres verticales.
	 *
	 * @param mixed $s Texte.
	 * @return string
	 */
	private static function t( $s ) {
		return str_replace( array( '|', "\n", "\r" ), array( '/', ' ', ' ' ), (string) $s );
	}

	/**
	 * Nombre au format texte (point décimal), vide si absent.
	 *
	 * @param mixed $v Nombre.
	 * @return string
	 */
	private static function n( $v ) {
		return null === $v || '' === $v ? '' : rtrim( rtrim( number_format( (float) $v, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Bougie d'origine d'une zone ou d'un niveau (« origin » du plan, écrit en heure de Lubumbashi) :
	 * le premier « jj/mm » du texte et l'heure « hh:mm » (ou « hhhmm ») qui le suit, avant la date
	 * suivante. « Bas D1 du 07/10 et bas H1 du 14/10 18:00 » donne le 07/10 sans heure.
	 *
	 * @param mixed  $text Texte « origin ».
	 * @param string $week Lundi du plan (année de référence).
	 * @return array|null {t : heure Unix UTC, hour : heure donnée}
	 */
	public static function origin_time( $text, $week ) {
		$s = (string) $text;
		if ( ! preg_match( '/(?<!\d)(\d{1,2})\/(\d{1,2})(?!\d)/', $s, $d, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		$day  = (int) $d[1][0];
		$mon  = (int) $d[2][0];
		$rest = substr( $s, $d[0][1] + strlen( $d[0][0] ) );
		if ( preg_match( '/\d{1,2}\/\d{1,2}/', $rest, $nx, PREG_OFFSET_CAPTURE ) ) {
			$rest = substr( $rest, 0, $nx[0][1] );
		}
		$hour = preg_match( '/(?<!\d)(\d{1,2})[:h](\d{2})(?!\d)/', $rest, $h ) && (int) $h[1] < 24 && (int) $h[2] < 60;
		$ref  = new DateTimeImmutable( $week . ' 12:00', CX_Repo::tz() );
		$year = (int) $ref->format( 'Y' ) - ( $mon > (int) $ref->format( 'n' ) + 1 ? 1 : 0 ); // plan de janvier, bougie de décembre
		if ( ! checkdate( $mon, $day, $year ) ) {
			return null;
		}
		$t = new DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d', $year, $mon, $day, $hour ? (int) $h[1] : 0, $hour ? (int) $h[2] : 0 ), CX_Repo::tz() );
		return array(
			't'    => $t->getTimestamp(),
			'hour' => $hour,
		);
	}

	/**
	 * Décalage de l'heure du serveur MT5 d'un compte sur UTC, en secondes : la bougie D1 s'ouvre à
	 * minuit, heure du serveur. Sans bougies : heure d'Europe de l'Est (UTC+2, UTC+3 l'été), celle de la
	 * plupart des serveurs MT5.
	 *
	 * @param int $id Compte.
	 * @return int
	 */
	public static function server_offset( $id ) {
		$d1 = $id ? ( self::candle_data( (int) $id )['tf']['D1'] ?? array() ) : array();
		if ( $d1 ) {
			$off = ( 86400 - (int) $d1[ count( $d1 ) - 1 ][0] % 86400 ) % 86400;
			return $off > 43200 ? $off - 86400 : $off;
		}
		return ( new DateTimeImmutable( 'now', new DateTimeZone( 'Europe/Athens' ) ) )->getOffset();
	}

	/**
	 * « origin » pour l'EA : « jj/mm hh:mm » en heure du serveur du compte, « jj/mm » sans heure, vide sinon.
	 *
	 * @param mixed  $text Texte « origin » du plan.
	 * @param string $week Lundi du plan.
	 * @param int    $off  Décalage du serveur sur UTC (secondes).
	 * @return string
	 */
	private static function origin_field( $text, $week, $off ) {
		$o = self::origin_time( $text, $week );
		if ( ! $o ) {
			return '';
		}
		return $o['hour'] ? gmdate( 'd/m H:i', $o['t'] + $off ) : wp_date( 'd/m', $o['t'], CX_Repo::tz() );
	}

	/**
	 * Plan au format texte pour l'EA.
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function plan( WP_REST_Request $req ) {
		$acc = self::auth( $req, 'mt5' );
		if ( is_wp_error( $acc ) ) {
			return $acc;
		}
		$week = self::week();
		$p    = CX_Repo::plan( $week );
		$c    = is_array( $p['content'] ?? null ) ? $p['content'] : array();
		$live = (array) ( $c['live'] ?? array() );
		$out  = array();
		$off  = self::server_offset( (int) $acc['id'] );

		$pr    = (array) ( $c['prediction'] ?? array() );
		$refs  = (array) ( $c['refs'] ?? array() );
		$out[] = 'B|' . (int) ( $pr['bull'] ?? 0 ) . '|' . (int) ( $pr['bear'] ?? 0 ) . '|' . (int) ( $pr['range'] ?? 0 );
		$out[] = 'I|' . self::n( $pr['invalidation'] ?? null ) . '|' . self::n( $pr['range_low'] ?? null ) . '|' . self::n( $pr['range_high'] ?? null ) . '|' . self::n( $refs['price'] ?? null ) . '|' . ( empty( $live['invalidated'] ) ? 0 : 1 );
		foreach ( (array) ( $c['zones'] ?? array() ) as $z ) {
			if ( null === $z['low'] || null === $z['high'] ) {
				continue;
			}
			$key   = self::zone_key( $z );
			$state = $live['zones'][ $key ]['state'] ?? ( $z['state'] ? $z['state'] : 'vierge' );
			$out[] = 'Z|' . $key . '|' . self::t( $z['type'] ) . '|' . self::t( $z['tf'] ) . '|' . self::n( min( $z['low'], $z['high'] ) ) . '|' . self::n( max( $z['low'], $z['high'] ) ) . '|' . ( 'vente' === $z['side'] ? 'vente' : 'achat' ) . '|' . (int) $z['strength'] . '|' . $state . '|' . self::origin_field( $z['origin'] ?? '', $week, $off );
		}
		// Zones du dernier plan de séance du jour (Londres, puis New York qui le remplace), surveillées
		// par l'EA comme les zones du plan.
		$today = CX_Repo::clock( 'Y-m-d' );
		$feed  = array(
			'london' => array( 'ld-', 'LDN' ),
			'us'     => array( 'us-', 'US' ),
		);
		$plan  = null;
		foreach ( (array) ( $c['sessions'] ?? array() ) as $se ) {
			if ( $se['date'] === $today && isset( $feed[ $se['kind'] ] ) && ( ! $plan || 'us' === $se['kind'] ) ) {
				$plan = $se;
			}
		}
		foreach ( $plan ? (array) $plan['zones'] : array() as $z ) {
			if ( null === $z['low'] || null === $z['high'] ) {
				continue;
			}
			list( $pre, $tf ) = $feed[ $plan['kind'] ];
			$key              = $pre . self::zone_key( $z );
			$state            = $live['zones'][ $key ]['state'] ?? 'vierge';
			$out[]            = 'Z|' . $key . '|' . self::t( $z['type'] ? $z['type'] : 'Zone' ) . '|' . $tf . '|' . self::n( min( $z['low'], $z['high'] ) ) . '|' . self::n( max( $z['low'], $z['high'] ) ) . '|' . ( 'vente' === $z['side'] ? 'vente' : 'achat' ) . '|2|' . $state . '|' . self::origin_field( $z['origin'] ?? '', $week, $off );
		}
		$t     = CX_Tuning::values();
		$out[] = 'S|' . (int) $t['touch'] . '|' . (int) $t['swing'] . '|' . self::n( $t['fvg'] ) . '|' . self::n( $t['slbuf'] ) . '|' . (int) $t['limitmin'] . '|' . (int) $t['cooldown'];
		// Niveaux : deux niveaux à moins de 1 $ n'en font qu'un (un seul trait sur le graphique).
		$seen = array();
		// Dernière analyse de séance du jour : ses niveaux (L), son invalidation (X) et ses scénarios
		// d'exécution (P), tracés par l'EA comme sur le graphique envoyé sur Telegram.
		$order = array(
			'asia'      => 0,
			'london'    => 1,
			'premarket' => 2,
			'us'        => 3,
		);
		$last  = null;
		foreach ( (array) ( $c['sessions'] ?? array() ) as $se ) {
			if ( $se['date'] === $today && ( ! $last || $order[ $se['kind'] ] >= $order[ $last['kind'] ] ) ) {
				$last = $se;
			}
		}
		// Ceux de l'analyse de séance d'abord, puis ceux du plan de la semaine. Le niveau gardé prend
		// l'origine d'un niveau fusionné s'il n'en a pas (PDH de la séance, origine dans le plan).
		$all  = array_merge( $last ? (array) ( $last['levels'] ?? array() ) : array(), (array) ( $c['levels'] ?? array() ) );
		$keep = array();
		foreach ( $all as $l ) {
			if ( null === $l['price'] ) {
				continue;
			}
			$v = (float) $l['price'];
			$k = null;
			foreach ( $seen as $i => $x ) {
				$k = null === $k && abs( $x - $v ) < 1.0 ? $i : $k;
			}
			if ( null === $k ) {
				$keep[] = $l;
				$seen[] = $v;
			} elseif ( '' === trim( (string) ( $keep[ $k ]['origin'] ?? '' ) ) ) {
				$keep[ $k ]['origin'] = $l['origin'] ?? '';
			}
		}
		foreach ( $keep as $l ) {
			$out[] = 'L|' . self::n( $l['price'] ) . '|' . self::t( $l['type'] ) . '|' . self::origin_field( $l['origin'] ?? '', $week, $off );
		}
		if ( $last ) {
			if ( preg_match_all( '/\d[\d \x{202F}\x{00A0}]*(?:[.,]\d+)?/u', (string) ( $last['invalid'] ?? '' ), $m ) ) {
				foreach ( $m[0] as $x ) {
					$v = (float) str_replace( array( ' ', "\u{202F}", "\u{00A0}", ',' ), array( '', '', '', '.' ), trim( $x ) );
					if ( $v > 100 ) {
						$out[] = 'X|' . self::n( $v );
						break;
					}
				}
			}
			foreach ( array_slice( (array) ( $last['positions'] ?? array() ), 0, 3 ) as $po ) {
				if ( $po['entry'] && $po['sl'] && $po['tp'] ) {
					$out[] = 'P|' . ( 'vente' === $po['side'] ? 'vente' : 'achat' ) . '|' . self::n( $po['entry'] ) . '|' . self::n( $po['sl'] ) . '|' . self::n( $po['tp'] ) . '|' . self::t( $po['label'] ?? '' );
				}
			}
		}
		// Annonces : heure exacte (UTC) calculée ici, l'EA n'a pas à lire de date. Celles du plan
		// de Claude et les annonces USD à fort impact du calendrier automatique.
		$monday = new DateTimeImmutable( $week . ' 00:00', CX_Repo::tz() );
		foreach ( CX_Calendar::merged( $monday->getTimestamp(), $monday->modify( '+7 days' )->getTimestamp(), true ) as $e ) {
			$out[] = 'N|' . $e['t'] . '|' . $e['impact'] . '|' . self::t( $e['name'] );
		}
		$pa    = $acc['params'];
		if ( 'propre' === ( $pa['kind'] ?? 'propfirm' ) ) {
			// Fonds propres : risque du palier sur le solde MT5 (sinon capital + résultats), limite du jour
			// en R, plancher fixe ; « taille » = solde, pour que l'EA ne réduise pas le risque.
			$snaps = (array) CX_Ingest::latest_snapshots();
			$cap   = isset( $snaps[ $acc['id'] ] ) ? (float) $snaps[ $acc['id'] ]['balance'] : (float) $acc['size'] + array_sum( array_map( fn( $t ) => (float) $t['pnl'], CX_Repo::trades( $acc['id'] ) ) );
			$own   = CX_Repo::own_risk( $cap, $pa );
			$out[] = 'R|' . self::n( $own['risk'] ) . '|' . self::n( $own['risk'] ) . '|' . self::n( 1e7 ) . '|' . self::n( $pa['contract'] ) . '|' . (int) $pa['maxtrades'] . '|' . (int) $pa['maxloss'] . '|' . self::n( $pa['spreadmax'] ) . '|' . self::n( self::STOP_MIN ) . '|' . self::n( self::STOP_MAX ) . '|' . self::n( $pa['daystopr'] * $own['risk'] ) . '|' . self::n( $cap ) . '|' . self::n( $pa['floor'] );
		} else {
			// Prop firm : limite du jour = la plus basse entre la perte journalière de la firme et l'arrêt personnel en R.
			$lim   = CX_Repo::prop_limits( $acc, ( (array) CX_Ingest::latest_snapshots() )[ $acc['id'] ] ?? null );
			$out[] = 'R|' . self::n( $pa['risk'] ) . '|' . self::n( $pa['riskred'] ) . '|' . self::n( $pa['redat'] ) . '|' . self::n( $pa['contract'] ) . '|' . (int) $pa['maxtrades'] . '|' . (int) $pa['maxloss'] . '|' . self::n( $pa['spreadmax'] ) . '|' . self::n( self::STOP_MIN ) . '|' . self::n( self::STOP_MAX ) . '|' . self::n( min( $lim['firm_daily'], $lim['day_stop'] ) ) . '|' . self::n( $acc['size'] ) . '|' . self::n( $lim['floor'] );
		}
		$today = CX_Repo::clock( 'Y-m-d' );
		$tt    = array_filter( CX_Repo::trades( $acc['id'] ), fn( $t ) => $t['date'] === $today );
		$out[] = 'D|' . count( $tt ) . '|' . count( array_filter( $tt, fn( $t ) => $t['r'] < 0 ) ) . '|' . self::n( array_sum( array_map( fn( $t ) => (float) $t['pnl'], $tt ) ) );
		// Étape du compte, comme l'onglet Jour : objectif en $ (prop firm seulement), avancement, jours
		// tradés et limites de la semaine. Le Calculateur XAU arrête les trades normaux à l'objectif.
		$st    = (string) $acc['status'];
		$own   = 'propre' === ( $pa['kind'] ?? 'propfirm' );
		$pct   = $own ? 0.0 : (float) ( array( 'phase1' => $pa['t1'], 'phase2' => $pa['t2'], 'finance' => $pa['tf'] )[ $st ] ?? 0 );
		$since = $acc['phaseStart'] ? $acc['phaseStart'] : '0000-00-00';
		$phase = array_filter( CX_Repo::trades( $acc['id'] ), fn( $t ) => $t['date'] >= $since );
		$snap  = ( (array) CX_Ingest::latest_snapshots() )[ $acc['id'] ] ?? null;
		$gain  = $snap ? (float) $snap['balance'] - (float) $acc['size'] : array_sum( array_map( fn( $t ) => (float) $t['pnl'], $phase ) );
		$out[] = 'G|' . $st . '|' . self::n( (float) $acc['size'] * $pct / 100 ) . '|' . self::n( $gain ) . '|' . count( array_unique( array_column( $phase, 'date' ) ) ) . '|' . (int) $pa['mindays'] . '|' . (int) $pa['weekloss'] . '|' . self::n( $pa['weekstopr'] ) . '|' . ( $own ? 'propre' : 'propfirm' );

		// Signaux du compte de référence (EA du VPS) des dernières 24 h, pour un autre EA (PC rallumé) :
		// il les dessine comme s'il avait tourné. Heure du signal en heure du serveur de ce compte.
		$ref_id = self::ref_account_id();
		if ( $ref_id && (int) $acc['id'] !== $ref_id ) {
			global $wpdb;
			$t    = CX_Install::table( 'csignals' );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE account_id = %d AND genre <> 'retest' AND signale_le >= %s ORDER BY signale_le", $ref_id, gmdate( 'Y-m-d H:i:s', strtotime( CX_Repo::clock() ) - DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
			foreach ( $rows as $r ) {
				$ts    = ( new DateTimeImmutable( $r->signale_le, CX_Repo::tz() ) )->getTimestamp() + $off;
				$out[] = 'K|' . preg_replace( '/[^0-9A-Za-z_-]/', '', (string) $r->ref ) . '|' . $ts . '|' . ( 'sell' === $r->sens ? 'sell' : 'buy' ) . '|' . $r->genre . '|' . self::n( (float) $r->entree ) . '|' . self::n( (float) $r->stop_prix ) . '|' . self::n( (float) $r->objectif ) . '|' . $r->statut . '|' . ( null === $r->resultat_r ? '' : self::n( (float) $r->resultat_r ) ) . '|' . $r->setup;
			}
		}

		// Empreinte : l'EA ne redessine que si le plan ou son état a changé.
		$body = implode( "\n", $out );
		$head = 'V|' . substr( md5( $body ), 0, 12 ) . '|' . $week . '|' . ( $p ? ( $p['publishedAt'] ?? '' ) : '' ) . '|' . ( $p['updatedAt'] ?? '' );
		$res  = new WP_REST_Response( null, 200 );
		add_filter(
			'rest_pre_serve_request',
			static function ( $served ) use ( $head, $body ) {
				header( 'Content-Type: text/plain; charset=utf-8' );
				header( 'Cache-Control: no-store' );
				echo $head . "\n" . $body . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- texte brut pour l'EA.
				return true;
			}
		);
		return $res;
	}

	/**
	 * Événement envoyé par l'EA.
	 *
	 * Corps JSON : {"type":"signal","side":"buy|sell","setup":"1|2","zone":"…","entry":…,"sl":…,
	 * "tp":…,"lot":…,"rr":…,"time":unix} ; {"type":"invalidation","price":…,"time":…} ;
	 * {"type":"zone","key":"…","state":"cassee|testee","price":…,"time":…}.
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function event( WP_REST_Request $req ) {
		global $wpdb;
		$acc = self::auth( $req, 'mt5' );
		if ( is_wp_error( $acc ) ) {
			return $acc;
		}
		$in   = (array) $req->get_json_params();
		$type = CX_Repo::pick( $in['type'] ?? '', array( 'signal', 'signal_update', 'invalidation', 'zone' ), '' );
		$ts   = CX_Repo::num( $in['time'] ?? null, 946684800, 4102444800 );
		$at   = ( new DateTimeImmutable( '@' . (int) ( $ts ?? time() ) ) )->setTimezone( CX_Repo::tz() );
		$num  = fn( $k ) => CX_Repo::num( $in[ $k ] ?? null, 0, 1e6 );
		$usd  = fn( $v ) => null === $v ? '?' : number_format( (float) $v, 2, ',', ' ' );
		$tg   = CX_Telegram::cfg();
		$send = ! empty( $tg['chat'] ) && ! empty( $tg['alerts']['signals'] );

		// Prix d'un autre symbole (EA posé sur BTC…) : ni signal, ni invalidation, ni zone cassée.
		$px = $num( 'signal' === $type ? 'entry' : 'price' );
		if ( 'signal_update' !== $type && ! self::gold_ok( $px ) ) {
			return self::not_gold( $px );
		}
		// Invalidation et zones : seul l'EA du compte de référence fait foi tant qu'il envoie ses bougies.
		if ( in_array( $type, array( 'invalidation', 'zone' ), true ) ) {
			$ref_id = self::ref_account_id();
			if ( $ref_id && (int) $acc['id'] !== $ref_id ) {
				$rd = self::candle_data( $ref_id );
				if ( ! empty( $rd['received'] ) && strtotime( CX_Repo::clock() ) - strtotime( $rd['received'] ) < 30 * MINUTE_IN_SECONDS ) {
					return new WP_REST_Response(
						array(
							'ok'      => true,
							'ignored' => 'état du plan suivi par le compte de référence',
						),
						200
					);
				}
			}
		}

		if ( 'signal' === $type ) {
			// Signaux : un seul EA fait foi, celui du compte de référence (VPS allumé en continu). Un autre
			// EA (PC personnel) est ignoré tant que la référence envoie ses bougies : pas de doublon.
			$ref_id = self::ref_account_id();
			if ( $ref_id && (int) $acc['id'] !== $ref_id ) {
				$rd = self::candle_data( $ref_id );
				if ( ! empty( $rd['received'] ) && strtotime( CX_Repo::clock() ) - strtotime( $rd['received'] ) < 30 * MINUTE_IN_SECONDS ) {
					return new WP_REST_Response(
						array(
							'ok'      => true,
							'ignored' => 'signal du compte de référence',
						),
						200
					);
				}
			}
			$side = 'sell' === ( $in['side'] ?? '' ) ? 'sell' : 'buy';
			$kind = CX_Repo::pick( (string) ( $in['kind'] ?? '' ), array( 'now', 'limit', 'retest' ), 'now' );
			// Variante « retour FVG » : suivie pour comparer les entrées, sans message.
			$send = $send && 'retest' !== $kind;
			$data = array(
				'kind'  => $kind,
				'side'  => $side,
				'setup' => CX_Repo::pick( (string) ( $in['setup'] ?? '' ), array( '1', '2', '3' ), '' ),
				'zone'  => CX_Repo::text( $in['zone'] ?? '', 80 ),
				'entry' => $num( 'entry' ),
				'sl'    => $num( 'sl' ),
				'tp'    => $num( 'tp' ),
				'lot'   => CX_Repo::num( $in['lot'] ?? null, 0, 1000 ),
				'rr'    => CX_Repo::num( $in['rr'] ?? null, 0, 100 ),
				'stop'  => CX_Repo::num( $in['stop'] ?? null, 0, 1000 ),
			);
			$retest = $num( 'retest' );
			$label  = ( 'sell' === $side ? 'SELL ' : 'BUY ' ) . array( 'now' => 'NOW', 'limit' => 'LIMIT', 'retest' => 'RETOUR' )[ $kind ] . ( $data['setup'] ? ' · setup ' . $data['setup'] . ( '3' === $data['setup'] ? ' (iFVG)' : '' ) : '' );
			// Trade virtuel « Signal Claude » : suivi jusqu'à sa clôture (signal_update), qu'il soit pris ou non.
			$ref = preg_replace( '/[^0-9A-Za-z_-]/', '', (string) ( $in['ref'] ?? '' ) );
			$ref = '' !== $ref ? $ref : (string) $at->getTimestamp();
			$t   = CX_Install::table( 'csignals' );
			$dup = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE account_id = %d AND ref = %s", $acc['id'], $ref ) ); // phpcs:ignore WordPress.DB.PreparedSQL
			if ( $dup ) {
				// Signal déjà reçu (renvoi de l'EA) : ni doublon, ni second message.
				return new WP_REST_Response( array( 'ok' => true, 'already' => true ), 200 );
			}
			$wpdb->insert(
				$t,
				array(
					'account_id' => (int) $acc['id'],
					'ref'        => $ref,
					'semaine'    => self::week(),
					'signale_le' => $at->format( 'Y-m-d H:i:s' ),
					'sens'       => $side,
					'genre'      => $kind,
					'setup'      => $data['setup'],
					'zone'       => $data['zone'],
					'entree'     => $data['entry'],
					'stop_prix'  => $data['sl'],
					'objectif'   => $data['tp'],
					'stop_usd'   => $data['stop'],
					'lot'        => $data['lot'],
					'rr'         => $data['rr'],
					'statut'     => 'now' === $kind ? 'ouvert' : 'attente',
					'ouvert_le'  => 'now' === $kind ? $at->format( 'Y-m-d H:i:s' ) : null,
				)
			);
			$wpdb->insert(
				CX_Install::table( 'signals' ),
				array(
					'recu_le'      => CX_Repo::clock(),
					'symbole'      => 'XAUUSD',
					'unite'        => 'EA',
					'type_signal'  => $label,
					'prix'         => $data['entry'],
					'heure_bougie' => $at->format( 'Y-m-d H:i:s' ),
					'payload'      => (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
				)
			);
			// Calculateur XAU à l'arrêt (pertes, plancher) : le signal reste suivi, mais ne se prend pas.
			$note = CX_Repo::text( $in['note'] ?? '', 200 );
			// Lot du compte actif (celui que l'utilisateur trade), calculé comme l'onglet Jour.
			$rk = CX_Telegram::exec_risk();
			if ( null !== $data['stop'] && $data['stop'] > 0 && $rk['risk'] > 0 ) {
				$data['lot'] = floor( $rk['risk'] / ( $data['stop'] * $rk['contract'] + $rk['commission'] ) * 100 + 1e-9 ) / 100;
			}
			$active = CX_Telegram::active();
			if ( $send ) {
				CX_Telegram::send(
					sprintf(
						"%s <b>%s</b>\n%s\n\nZone : %s\n\n• Entrée : %s\n• Stop : %s\n• Objectif : %s (%sR)\n• Lot : %s (stop %s $)" . ( 'limit' === $kind ? "\n\nOrdre limite : valable 30 min, en attente d’un repli." : '' ) . ( null === $retest ? '' : "\n\nEntrée de retour possible : ordre limite à " . $usd( $retest ) . ' (bord du FVG), même stop.' ) . ( '' === $note ? '' : "\n\n⛔ " . esc_html( $note ) ),
						'sell' === $side ? '🔴' : '🟢',
						esc_html( $label ),
						esc_html( $active ? $active['name'] : $acc['name'] ),
						esc_html( $data['zone'] ),
						$usd( $data['entry'] ),
						$usd( $data['sl'] ),
						$usd( $data['tp'] ),
						null === $data['rr'] ? '?' : number_format( (float) $data['rr'], 1, ',', '' ),
						null === $data['lot'] ? '?' : number_format( (float) $data['lot'], 2, ',', '' ),
						null === $data['stop'] ? '?' : number_format( (float) $data['stop'], 2, ',', '' )
					)
				);
			}
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		if ( 'signal_update' === $type ) {
			return self::signal_update( $acc, $in, $at, $send );
		}

		// Invalidation et zones : état « live » du plan, lu par l'interface, l'EA et la relecture de Claude.
		$week = self::week();
		$p    = CX_Repo::plan( $week );
		if ( ! $p || ! is_array( $p['content'] ) ) {
			return CX_Repo::invalid( 'Aucun plan publié pour la semaine.' );
		}
		$c    = $p['content'];
		$live = (array) ( $c['live'] ?? array() );
		$msg  = '';
		if ( 'invalidation' === $type && empty( $live['invalidated'] ) ) {
			$live['invalidated'] = array(
				'at'    => $at->format( 'Y-m-d H:i:s' ),
				'price' => $num( 'price' ),
			);
			$msg = sprintf( "⚠️ <b>Invalidation touchée</b> à %s (%s)\nLe plan de Claude ne tient plus dans ce sens : pas de signal tant que Claude n’a pas relu le plan (17 h à New York).", $at->format( 'H:i' ), $usd( $num( 'price' ) ) );
		} elseif ( 'zone' === $type ) {
			$key   = preg_replace( '/[^0-9.\-a-z]/', '', (string) ( $in['key'] ?? '' ) );
			$state = CX_Repo::pick( $in['state'] ?? '', array( 'cassee', 'testee' ), '' );
			if ( $key && $state && ( $live['zones'][ $key ]['state'] ?? '' ) !== $state ) {
				$live['zones'][ $key ] = array(
					'state' => $state,
					'at'    => $at->format( 'Y-m-d H:i:s' ),
					'price' => $px,
				);
				if ( 'cassee' === $state ) {
					// « ld-4100.00-4110.00-b » → « 4100.00-4110.00 (achat, plan de Londres) ».
					$src  = array(
						'ld' => ', plan de Londres',
						'us' => ', plan de New York',
					);
					$name = preg_match( '/^(?:([a-z]+)-)?(.+)-([bs])$/', $key, $mm ) ? $mm[2] . ' (' . ( 's' === $mm[3] ? 'vente' : 'achat' ) . ( $src[ $mm[1] ] ?? '' ) . ')' : $key;
					$msg  = sprintf( '🧱 Zone %s cassée à %s : plus de signal sur cette zone.', esc_html( $name ), $at->format( 'H:i' ) );
				}
			}
		}
		$c['live'] = $live;
		$wpdb->update(
			CX_Install::table( 'plans' ),
			array( 'contenu' => wp_json_encode( $c, JSON_UNESCAPED_UNICODE ) ),
			array(
				'account_id' => 0,
				'semaine'    => $week,
			)
		);
		if ( $msg && $send ) {
			CX_Telegram::send( $msg );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Suivi d'un trade virtuel « Signal Claude » : limite déclenchée ou annulée, clôture.
	 *
	 * Corps : {"type":"signal_update","ref":"…","status":"ouvert|annule|gagne|perdu|be",
	 * "result_r":…,"reached_2r":true,"mfe":…,"mae":…,"time":unix}.
	 *
	 * @param array             $acc  Compte.
	 * @param array             $in   Corps.
	 * @param DateTimeImmutable $at   Heure de l'événement.
	 * @param bool              $send Envoyer sur Telegram.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function signal_update( $acc, $in, $at, $send ) {
		global $wpdb;
		$t      = CX_Install::table( 'csignals' );
		$ref    = preg_replace( '/[^0-9A-Za-z_-]/', '', (string) ( $in['ref'] ?? '' ) );
		$status = CX_Repo::pick( $in['status'] ?? '', array( 'ouvert', 'annule', 'gagne', 'perdu', 'be' ), '' );
		$row    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE account_id = %d AND ref = %s", $acc['id'], $ref ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! $row || ! $status ) {
			return CX_Repo::invalid( 'Signal inconnu ou statut invalide.' );
		}
		if ( in_array( $row->statut, array( 'annule', 'gagne', 'perdu', 'be' ), true ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'already' => true ), 200 ); // déjà clôturé
		}
		$data = array( 'statut' => $status );
		if ( 'ouvert' === $status ) {
			$data['ouvert_le'] = $at->format( 'Y-m-d H:i:s' );
		} else {
			$data['ferme_le']   = $at->format( 'Y-m-d H:i:s' );
			$data['resultat_r'] = 'annule' === $status ? null : CX_Repo::num( $in['result_r'] ?? null, -5, 50 );
			$data['atteint_2r'] = empty( $in['reached_2r'] ) ? 0 : 1;
			$data['mfe_r']      = CX_Repo::num( $in['mfe'] ?? null, -50, 50 );
			$data['mae_r']      = CX_Repo::num( $in['mae'] ?? null, -50, 50 );
		}
		$wpdb->update( $t, $data, array( 'id' => (int) $row->id ) );
		if ( $send && 'retest' !== $row->genre && in_array( $status, array( 'gagne', 'perdu', 'be' ), true ) ) {
			CX_Telegram::send( self::close_message( $row, $data ) );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Message de clôture d'un signal : objectif ou stop, résultat en R et temps mis pour l'atteindre
	 * (depuis l'entrée, et depuis le signal quand c'était un ordre limite).
	 *
	 * @param object $row  Signal.
	 * @param array  $data Colonnes de clôture (ferme_le, resultat_r, ouvert_le éventuel).
	 * @return string
	 */
	public static function close_message( $row, $data ) {
		$tz     = CX_Repo::tz();
		$r      = (float) ( $data['resultat_r'] ?? 0 );
		$rr     = null === $row->rr ? null : (float) $row->rr;
		$sig    = ( new DateTimeImmutable( $row->signale_le, $tz ) )->getTimestamp();
		$open   = $data['ouvert_le'] ?? $row->ouvert_le;
		$opened = $open ? ( new DateTimeImmutable( $open, $tz ) )->getTimestamp() : $sig;
		$closed = ( new DateTimeImmutable( $data['ferme_le'], $tz ) )->getTimestamp();
		$what   = $r <= -0.95 ? '❌ <b>Stop touché</b>' : ( null !== $rr && $r >= $rr - 0.05 ? '✅ <b>Objectif atteint</b>' : '⏱ <b>Clôturé au bout de 24 h</b>' );
		$lines  = array(
			$what,
			'Signal Claude ' . ( 'sell' === $row->sens ? 'VENTE' : 'ACHAT' ) . ' de ' . substr( $row->signale_le, 11, 5 ),
			'',
			'• Résultat : ' . ( $r > 0 ? '+' : ( $r < 0 ? '−' : '' ) ) . number_format( abs( $r ), 1, ',', '' ) . 'R',
			'• Durée depuis l’entrée : ' . self::duration( $closed - $opened ),
		);
		if ( $opened - $sig >= 60 ) {
			$lines[] = '• Entrée déclenchée ' . self::duration( $opened - $sig ) . ' après le signal';
		}
		if ( $r < 0 && ! empty( $data['atteint_2r'] ) ) {
			$lines[] = '• 2R avait été atteint avant le stop';
		}
		return implode( "\n", $lines );
	}

	/**
	 * Durée lisible : « 45 min », « 1 h 12 », « 1 j 3 h ».
	 *
	 * @param int $sec Secondes.
	 * @return string
	 */
	public static function duration( $sec ) {
		$m = max( 1, (int) round( $sec / 60 ) );
		if ( $m < 60 ) {
			return $m . ' min';
		}
		if ( $m < 1440 ) {
			return intdiv( $m, 60 ) . ' h ' . str_pad( (string) ( $m % 60 ), 2, '0', STR_PAD_LEFT );
		}
		return intdiv( $m, 1440 ) . ' j ' . intdiv( $m % 1440, 60 ) . ' h';
	}

	/** Durée de suivi d'un trade virtuel par l'EA (TRACK_MAX_HOURS), en heures. */
	const TRACK_HOURS = 24;

	/**
	 * Signaux que l'EA n'a pas soldés (EA arrêté, changement de compte sur le même terminal, fichier de
	 * suivi perdu). Passé 25 h, l'EA les aurait clôturés : le carnet les rejoue sur les bougies de leur
	 * compte, sinon du compte de référence, avec la gestion de l'EA. Colonne cloture_carnet = unité rejouée.
	 *
	 * @param DateTimeImmutable|null $now Instant (par défaut maintenant).
	 * @return int Signaux clôturés.
	 */
	public static function settle_orphans( $now = null ) {
		global $wpdb;
		$t    = CX_Install::table( 'csignals' );
		$tz   = CX_Repo::tz();
		$now  = $now ?? new DateTimeImmutable( 'now', $tz );
		$cut  = $now->modify( '-' . ( self::TRACK_HOURS + 1 ) . ' hours' )->format( 'Y-m-d H:i:s' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE statut IN ('ouvert','attente') AND signale_le < %s ORDER BY signale_le LIMIT 20", $cut ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$lim  = (int) CX_Tuning::values()['limitmin'];
		$done = 0;
		$old  = $now->modify( '-8 days' )->format( 'Y-m-d H:i:s' );
		foreach ( $rows as $r ) {
			$out = self::replay_signal( $r, $lim, $tz );
			if ( ! $out && $r->signale_le < $old ) {
				// Plus aucune bougie pour le rejouer (l'EA rattrape 7 jours au plus) : issue inconnue.
				$out = array(
					'statut'         => 'inconnu',
					'ferme_le'       => $r->signale_le,
					'cloture_carnet' => '—',
				);
			}
			if ( $out ) {
				$wpdb->update( $t, $out, array( 'id' => (int) $r->id ) );
				++$done;
			}
		}
		return $done;
	}

	/**
	 * Rejeu d'un signal : bougies de son compte puis du compte de référence, de la plus fine (M1) à la
	 * plus large (H1) qui couvre son heure.
	 *
	 * @param object       $r   Ligne du signal.
	 * @param int          $lim Validité d'un ordre limite (min).
	 * @param DateTimeZone $tz  Fuseau du carnet.
	 * @return array|null Colonnes à mettre à jour, null tant que les bougies ne donnent pas d'issue.
	 */
	private static function replay_signal( $r, $lim, $tz ) {
		$e  = (float) $r->entree;
		$sl = (float) $r->stop_prix;
		$tp = (float) $r->objectif;
		if ( $e <= 0 || $sl <= 0 || $tp <= 0 || abs( $e - $sl ) <= 0 ) {
			// Signal incomplet : impossible à rejouer, retiré des signaux en cours.
			return array(
				'statut'         => 'inconnu',
				'ferme_le'       => $r->signale_le,
				'cloture_carnet' => '—',
			);
		}
		$start = ( new DateTimeImmutable( $r->signale_le, $tz ) )->getTimestamp();
		$ids   = array_unique( array_filter( array( (int) $r->account_id, self::ref_account_id() ) ) );
		foreach ( $ids as $id ) {
			$tf = (array) ( self::candle_data( $id )['tf'] ?? array() );
			foreach ( array( 'M1' => 60, 'M5' => 300, 'M15' => 900, 'H1' => 3600 ) as $k => $sec ) {
				$bars = (array) ( $tf[ $k ] ?? array() );
				if ( ! $bars || (int) $bars[0][0] > $start ) {
					continue;
				}
				$out = self::replay_bars( $r, $bars, $sec, $start, $lim, $tz );
				if ( $out ) {
					return $out + array( 'cloture_carnet' => $k );
				}
			}
		}
		return null;
	}

	/**
	 * Suivi de l'EA (ProcessBar), bougie par bougie : ordre limite déclenché ou annulé au bout de
	 * $lim minutes, puis stop (vérifié en premier) ou objectif, sans gestion ; clôture au dernier
	 * cours au bout de 24 h.
	 *
	 * @param object       $r     Ligne du signal.
	 * @param array        $bars  Bougies [unix UTC, ouverture, haut, bas, clôture].
	 * @param int          $sec   Durée d'une bougie (s).
	 * @param int          $start Heure du signal (unix).
	 * @param int          $lim   Validité d'un ordre limite (min).
	 * @param DateTimeZone $tz    Fuseau du carnet.
	 * @return array|null
	 */
	private static function replay_bars( $r, $bars, $sec, $start, $lim, $tz ) {
		$at      = fn( $ts ) => ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz )->format( 'Y-m-d H:i:s' );
		$buy     = 'buy' === $r->sens;
		$e       = (float) $r->entree;
		$tp      = (float) $r->objectif;
		$st      = $r->stop_usd > 0 ? (float) $r->stop_usd : abs( $e - (float) $r->stop_prix );
		$rr_tp   = abs( $tp - $e ) / $st;
		$pending = 'attente' === $r->statut;
		// Entrée : heure du signal (au marché) ou clôture de la bougie qui a déclenché l'ordre limite.
		$opened  = $pending ? 0 : ( $r->ouvert_le ? ( new DateTimeImmutable( $r->ouvert_le, $tz ) )->getTimestamp() : $start );
		$from    = $pending ? $start : $opened;
		$fill    = null;
		$hit     = false;
		$mfe     = 0.0;
		$mae     = 0.0;
		foreach ( $bars as $b ) {
			$o = (int) $b[0];
			if ( $o < $from ) {
				continue;
			}
			$t  = $o + $sec;
			$hi = (float) $b[2];
			$lo = (float) $b[3];
			if ( $pending ) {
				if ( $o >= $start + $lim * 60 ) {
					return array(
						'statut'   => 'annule',
						'ferme_le' => $at( $start + $lim * 60 ),
					);
				}
				if ( $buy ? $lo <= $e : $hi >= $e ) {
					$pending = false;
					$opened  = $o;
					$fill    = $at( $o );
				}
				continue;
			}
			$up    = $buy ? ( $hi - $e ) / $st : ( $e - $lo ) / $st;
			$down  = $buy ? ( $lo - $e ) / $st : ( $e - $hi ) / $st;
			$mfe   = max( $mfe, $up );
			$mae   = min( $mae, $down );
			$close = null;
			$hit   = $hit || $up >= 2.0;
			if ( $down <= -1.0 ) {
				$close = -1.0;
			} elseif ( $up >= $rr_tp ) {
				$close = $rr_tp;
			}
			if ( null === $close && $t - $opened > self::TRACK_HOURS * 3600 ) {
				$close = $buy ? ( (float) $b[4] - $e ) / $st : ( $e - (float) $b[4] ) / $st;
			}
			if ( null !== $close ) {
				return array(
					'statut'     => $close > 0.05 ? 'gagne' : ( $close < -0.05 ? 'perdu' : 'be' ),
					'ferme_le'   => $at( $t ),
					'resultat_r' => round( $close, 2 ),
					'atteint_2r' => $hit ? 1 : 0,
					'mfe_r'      => round( $mfe, 2 ),
					'mae_r'      => round( $mae, 2 ),
				) + ( $fill ? array( 'ouvert_le' => $fill ) : array() );
			}
		}
		return null;
	}

	/**
	 * Signaux Claude (trades virtuels), du plus récent au plus ancien, avec les trades du
	 * journal pris dans les 15 minutes, dans le même sens (« pris par vous »).
	 *
	 * @return WP_REST_Response
	 */
	public static function list_signals() {
		global $wpdb;
		$t      = CX_Install::table( 'csignals' );
		$rows   = $wpdb->get_results( "SELECT * FROM $t ORDER BY signale_le DESC, id DESC LIMIT 500" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$trades = CX_Repo::trades();
		$out    = array();
		foreach ( $rows as $r ) {
			$ts    = strtotime( $r->signale_le );
			$taken = false;
			foreach ( $trades as $tr ) {
				if ( (int) $tr['accountId'] === (int) $r->account_id && $tr['time'] && ( 'buy' === $r->sens ? 'achat' : 'vente' ) === $tr['dir']
					&& abs( strtotime( $tr['date'] . ' ' . $tr['time'] ) - $ts ) <= 15 * 60 ) {
					$taken = true;
					break;
				}
			}
			$out[] = array(
				'id'       => (int) $r->id,
				'at'       => $r->signale_le,
				'week'     => $r->semaine,
				'side'     => $r->sens,
				'kind'     => $r->genre,
				'setup'    => $r->setup,
				'zone'     => $r->zone,
				'entry'    => null === $r->entree ? null : (float) $r->entree,
				'sl'       => null === $r->stop_prix ? null : (float) $r->stop_prix,
				'tp'       => null === $r->objectif ? null : (float) $r->objectif,
				'stop'     => null === $r->stop_usd ? null : (float) $r->stop_usd,
				'rr'       => null === $r->rr ? null : (float) $r->rr,
				'status'   => $r->statut,
				'closedAt' => $r->ferme_le,
				'r'        => null === $r->resultat_r ? null : (float) $r->resultat_r,
				'hit2r'    => (bool) $r->atteint_2r,
				'mfe'      => null === $r->mfe_r ? null : (float) $r->mfe_r,
				'mae'      => null === $r->mae_r ? null : (float) $r->mae_r,
				'taken'    => $taken,
				'account'  => (int) $r->account_id,
				// Unité rejouée par le carnet quand l'EA n'a pas soldé le signal, vide sinon.
				'settled'  => (string) ( $r->cloture_carnet ?? '' ),
			);
		}
		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * Bougies du courtier, texte « ut|heure unix|ouverture|haut|bas|clôture » par ligne.
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function store_candles( WP_REST_Request $req ) {
		$acc = self::auth( $req, 'mt5' );
		if ( is_wp_error( $acc ) ) {
			return $acc;
		}
		$out = array();
		foreach ( preg_split( '/\R/', mb_substr( (string) $req->get_body(), 0, 400000 ) ) as $line ) {
			$f = explode( '|', trim( $line ) );
			if ( 6 !== count( $f ) || ! isset( self::CANDLE_MAX[ $f[0] ] ) ) {
				continue;
			}
			$v = array_map( fn( $x ) => CX_Repo::num( $x, 0, 1e6 ), array_slice( $f, 2 ) );
			$t = CX_Repo::num( $f[1], 946684800, 4102444800 );
			if ( null === $t || in_array( null, $v, true ) ) {
				continue;
			}
			$out[ $f[0] ][] = array( (int) $t, $v[0], $v[1], $v[2], $v[3] );
		}
		foreach ( $out as $tf => $rows ) {
			usort( $rows, fn( $a, $b ) => $a[0] <=> $b[0] );
			$out[ $tf ] = array_slice( $rows, -self::CANDLE_MAX[ $tf ] );
		}
		// Graphique d'un autre symbole (BTC, argent…) : rien n'est gardé. Référence : les bougies déjà
		// reçues de ce compte, sinon le cours du compte de référence.
		$own  = self::candle_data( (int) $acc['id'] );
		$mine = null;
		foreach ( array( 'M1', 'M5', 'M15', 'H1' ) as $k ) {
			if ( null === $mine && ! empty( $own['tf'][ $k ] ) ) {
				$mine = (float) end( $own['tf'][ $k ] )[4];
			}
		}
		foreach ( $out as $rows ) {
			$close = (float) end( $rows )[4];
			if ( ! self::gold_ok( $close, $mine ?? self::gold_ref() ) ) {
				return self::not_gold( $close );
			}
		}
		// « at » = heure des données : clôture de la dernière bougie M1 si elle est plus ancienne que la
		// réception (terminal déconnecté du courtier : l'EA renvoie de vieilles bougies). « received » = réception.
		$now = CX_Repo::clock();
		$at  = $now;
		if ( ! empty( $out['M1'] ) ) {
			$at = min( $now, ( new DateTimeImmutable( '@' . ( $out['M1'][ count( $out['M1'] ) - 1 ][0] + 60 ) ) )->setTimezone( CX_Repo::tz() )->format( 'Y-m-d H:i:s' ) );
		}
		update_option(
			self::OPT . (int) $acc['id'],
			array(
				'at'         => $at,
				'received'   => $now,
				'account'    => $acc['name'],
				'account_id' => (int) $acc['id'],
				'firm'       => $acc['firm'],
				'tf'         => $out,
			),
			false
		);
		return new WP_REST_Response(
			array(
				'ok'   => true,
				'bars' => array_map( 'count', $out ),
			),
			200
		);
	}

	const OPT = 'cx_candles_';

	/**
	 * Compte de référence de l'analyse : celui choisi dans Comptes › Connexions, sinon le compte actif.
	 * Claude et les graphiques n'utilisent que ses bougies : deux courtiers ne sont jamais mélangés.
	 *
	 * @return int Identifiant du compte, 0 s'il n'y a aucun compte.
	 */
	public static function ref_account_id() {
		$ref = (int) get_option( 'cx_candles_ref', 0 );
		if ( $ref && CX_Repo::account( $ref ) ) {
			return $ref;
		}
		foreach ( CX_Repo::accounts() as $a ) {
			if ( $a['active'] ) {
				return (int) $a['id'];
			}
		}
		return 0;
	}

	/**
	 * Bougies d'un compte (par défaut le compte de référence).
	 *
	 * @param int $id Compte, 0 pour le compte de référence.
	 * @return array {at, account, account_id, firm, tf}
	 */
	public static function candle_data( $id = 0 ) {
		self::migrate_candles();
		$id = $id ? (int) $id : self::ref_account_id();
		$d  = $id ? get_option( self::OPT . $id, array() ) : array();
		return is_array( $d ) ? $d : array();
	}

	/**
	 * Cours de l'or qui fait foi : dernière clôture des bougies du compte de référence, sinon prix du plan.
	 *
	 * @return float|null
	 */
	public static function gold_ref() {
		$d = self::candle_data();
		foreach ( array( 'M1', 'M5', 'M15', 'H1' ) as $tf ) {
			if ( ! empty( $d['tf'][ $tf ] ) ) {
				return (float) end( $d['tf'][ $tf ] )[4];
			}
		}
		$p = CX_Repo::plan( self::week() );
		$v = $p['content']['refs']['price'] ?? null;
		return is_numeric( $v ) && $v > 0 ? (float) $v : null;
	}

	/**
	 * Prix plausible pour l'or : à moins de 15 % du cours de référence (500 à 20 000 $ s'il n'y en a pas).
	 * Un EA posé par erreur sur un autre graphique (BTC, argent…) envoie des prix sans rapport : refusés.
	 *
	 * @param float|null $price Prix reçu.
	 * @param float|null $ref   Cours de référence (null : gold_ref()).
	 * @return bool
	 */
	public static function gold_ok( $price, $ref = null ) {
		if ( null === $price || $price <= 0 ) {
			return false;
		}
		$ref = $ref ?? self::gold_ref();
		return $ref ? abs( $price / $ref - 1 ) <= 0.15 : ( $price >= 500 && $price <= 20000 );
	}

	/** @return WP_Error Refus d'un prix qui n'est pas celui de l'or. */
	private static function not_gold( $price ) {
		return new WP_Error( 'cx_symbol', 'Prix ' . $price . ' : ce n’est pas l’or (XAUUSD). L’EA Carnet XAU ne fonctionne que sur le graphique de l’or.', array( 'status' => 422 ) );
	}

	/**
	 * Ancien stockage unique (le dernier EA à envoyer écrasait l'autre) : rangé sous son compte, puis supprimé.
	 */
	private static function migrate_candles() {
		$old = get_option( 'cx_candles', null );
		if ( null === $old ) {
			return;
		}
		if ( is_array( $old ) && ! empty( $old['tf'] ) ) {
			foreach ( CX_Repo::accounts() as $a ) {
				if ( $a['name'] === ( $old['account'] ?? null ) && ! get_option( self::OPT . $a['id'] ) ) {
					update_option( self::OPT . $a['id'], $old + array( 'account_id' => (int) $a['id'], 'firm' => $a['firm'] ), false );
					break;
				}
			}
		}
		delete_option( 'cx_candles' );
	}

	/**
	 * Comptes dont l'EA a envoyé des bougies, avec la référence de l'analyse.
	 *
	 * @return array[] {id, name, firm, at, ref}
	 */
	public static function candle_sources() {
		self::migrate_candles();
		$ref = self::ref_account_id();
		$out = array();
		foreach ( CX_Repo::accounts() as $a ) {
			$d = get_option( self::OPT . $a['id'], array() );
			if ( is_array( $d ) && ! empty( $d['tf'] ) ) {
				$out[] = array(
					'id'   => (int) $a['id'],
					'name' => $a['name'],
					'firm' => $a['firm'],
					'at'   => $d['at'] ?? null,
					'ref'  => (int) $a['id'] === $ref,
				);
			}
		}
		return $out;
	}

	/**
	 * Bougies du compte de référence, ou d'un compte précis (?account=ID).
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response
	 */
	public static function candles( WP_REST_Request $req ) {
		$d            = self::candle_data( (int) $req->get_param( 'account' ) );
		$d['ref']     = self::ref_account_id();
		$d['sources'] = self::candle_sources();
		return new WP_REST_Response( $d, 200 );
	}
}
