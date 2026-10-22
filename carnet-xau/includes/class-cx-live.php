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
 * Format texte (séparateur « | », prix avec un point décimal, heures en secondes Unix UTC) :
 *   V|empreinte|lundi|publié|mis à jour
 *   B|haussier|baissier|range
 *   I|invalidation|range bas|range haut|prix de référence|invalidée (0/1)
 *   Z|clé|type|ut|bas|haut|achat/vente|force|état (vierge/testee/cassee)
 *   L|prix|type
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
			$out[] = 'Z|' . $key . '|' . self::t( $z['type'] ) . '|' . self::t( $z['tf'] ) . '|' . self::n( min( $z['low'], $z['high'] ) ) . '|' . self::n( max( $z['low'], $z['high'] ) ) . '|' . ( 'vente' === $z['side'] ? 'vente' : 'achat' ) . '|' . (int) $z['strength'] . '|' . $state;
		}
		// Zones du plan de séance US du jour (15:15), surveillées par l'EA comme les zones du plan.
		$today = CX_Repo::clock( 'Y-m-d' );
		foreach ( (array) ( $c['sessions'] ?? array() ) as $se ) {
			if ( 'us' !== $se['kind'] || $se['date'] !== $today ) {
				continue;
			}
			foreach ( (array) $se['zones'] as $z ) {
				if ( null === $z['low'] || null === $z['high'] ) {
					continue;
				}
				$key   = 'us-' . self::zone_key( $z );
				$state = $live['zones'][ $key ]['state'] ?? 'vierge';
				$out[] = 'Z|' . $key . '|' . self::t( $z['type'] ? $z['type'] : 'Zone' ) . '|US|' . self::n( min( $z['low'], $z['high'] ) ) . '|' . self::n( max( $z['low'], $z['high'] ) ) . '|' . ( 'vente' === $z['side'] ? 'vente' : 'achat' ) . '|2|' . $state;
			}
		}
		$t     = CX_Tuning::values();
		$out[] = 'S|' . (int) $t['touch'] . '|' . (int) $t['swing'] . '|' . self::n( $t['fvg'] ) . '|' . self::n( $t['slbuf'] ) . '|' . (int) $t['limitmin'] . '|' . (int) $t['cooldown'];
		// Niveaux : deux niveaux à moins de 1 $ n'en font qu'un (un seul trait sur le graphique).
		$seen = array();
		// Dernière analyse de séance du jour : ses niveaux (L), son invalidation (X) et ses scénarios
		// d'exécution (P), tracés par l'EA comme sur le graphique envoyé sur Telegram.
		$order = array(
			'asia'      => 0,
			'premarket' => 1,
			'us'        => 2,
		);
		$last  = null;
		foreach ( (array) ( $c['sessions'] ?? array() ) as $se ) {
			if ( $se['date'] === $today && ( ! $last || $order[ $se['kind'] ] >= $order[ $last['kind'] ] ) ) {
				$last = $se;
			}
		}
		// Ceux de l'analyse de séance d'abord, puis ceux du plan de la semaine.
		$all = array_merge( $last ? (array) ( $last['levels'] ?? array() ) : array(), (array) ( $c['levels'] ?? array() ) );
		foreach ( $all as $l ) {
			$v    = (float) $l['price'];
			$near = false;
			foreach ( $seen as $x ) {
				$near = $near || abs( $x - $v ) < 1.0;
			}
			if ( null !== $l['price'] && ! $near ) {
				$out[]  = 'L|' . self::n( $l['price'] ) . '|' . self::t( $l['type'] );
				$seen[] = (float) $l['price'];
			}
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

		if ( 'signal' === $type ) {
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
			if ( $send ) {
				CX_Telegram::send(
					sprintf(
						"%s <b>%s</b> — %s\nZone : %s\nEntrée %s · stop %s · objectif %s (%sR)\nLot : %s (stop %s $)\n" . ( 'limit' === $kind ? 'Ordre limite : valable 30 min, en attente d’un repli.' : 'Sortie partielle à 2R, puis stop au prix d’entrée.' ) . ( null === $retest ? '' : "\nEntrée de retour possible : ordre limite à " . $usd( $retest ) . ' (bord du FVG), même stop.' ) . ( '' === $note ? '' : "\n⛔ " . esc_html( $note ) ),
						'sell' === $side ? '🔴' : '🟢',
						esc_html( $label ),
						esc_html( $acc['name'] ),
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
			$key   = preg_replace( '/[^0-9.\-bsu]/', '', (string) ( $in['key'] ?? '' ) );
			$state = CX_Repo::pick( $in['state'] ?? '', array( 'cassee', 'testee' ), '' );
			if ( $key && $state && ( $live['zones'][ $key ]['state'] ?? '' ) !== $state ) {
				$live['zones'][ $key ] = array(
					'state' => $state,
					'at'    => $at->format( 'Y-m-d H:i:s' ),
				);
				if ( 'cassee' === $state ) {
					$msg = sprintf( '🧱 Zone %s cassée à %s : plus de signal sur cette zone.', esc_html( str_replace( array( '-b', '-s' ), array( ' (achat)', ' (vente)' ), $key ) ), $at->format( 'H:i' ) );
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
			$r = (float) ( $data['resultat_r'] ?? 0 );
			CX_Telegram::send(
				sprintf(
					"%s Signal Claude %s du %s : <b>%s%sR</b>%s",
					$r > 0 ? '✅' : ( $r < 0 ? '❌' : '➖' ),
					'sell' === $row->sens ? 'SELL' : 'BUY',
					substr( $row->signale_le, 11, 5 ),
					$r > 0 ? '+' : ( $r < 0 ? '−' : '' ),
					number_format( abs( $r ), 1, ',', '' ),
					$data['atteint_2r'] ? ' (2R atteint, sortie partielle)' : ''
				)
			);
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
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
	 * Gestion de l'EA (ProcessBar), bougie par bougie : ordre limite déclenché ou annulé au bout de
	 * $lim minutes, stop vérifié en premier, 50 % à 2R puis stop à l'entrée, objectif, clôture au
	 * dernier cours au bout de 24 h.
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
			if ( ! $hit ) {
				if ( $down <= -1.0 ) {
					$close = -1.0;
				} elseif ( $up >= 2.0 ) {
					$hit = true;
					if ( $up >= $rr_tp ) {
						$close = 1.0 + 0.5 * $rr_tp;
					}
				}
			} elseif ( $up >= $rr_tp ) {
				$close = 1.0 + 0.5 * $rr_tp;
			} elseif ( $down <= 0 ) {
				$close = 1.0;
			}
			if ( null === $close && $t - $opened > self::TRACK_HOURS * 3600 ) {
				$now   = $buy ? ( (float) $b[4] - $e ) / $st : ( $e - (float) $b[4] ) / $st;
				$close = $hit ? 1.0 + 0.5 * $now : $now;
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
		update_option(
			self::OPT . (int) $acc['id'],
			array(
				'at'         => CX_Repo::clock(),
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
