<?php
/**
 * Échanges en direct avec l'EA MT5 (clé MT5 du compte, en-tête X-CX-Key) :
 * - GET  /mt5/plan    plan de Claude au format texte (une ligne par élément), règles de risque
 *                     du compte et état du jour, pour le tracé et le calcul des signaux ;
 * - POST /mt5/event   signal (BUY NOW / SELL NOW), invalidation touchée, zone cassée :
 *                     enregistrés, visibles dans le carnet et envoyés sur Telegram ;
 * - POST /mt5/candles bougies du courtier (D1, H4, H1) pour l'analyse de Claude ;
 * - GET  /candles     ces bougies, en lecture (tâche Claude, interface).
 *
 * Format texte (séparateur « | », prix avec un point décimal, heures en secondes Unix UTC) :
 *   V|empreinte|lundi|publié|mis à jour
 *   B|haussier|baissier|range
 *   I|invalidation|range bas|range haut|prix de référence|invalidée (0/1)
 *   Z|clé|type|ut|bas|haut|achat/vente|force|état (vierge/testee/cassee)
 *   L|prix|type
 *   N|heure unix|impact|annonce
 *   R|risque|risque réduit|seuil|contrat|trades max|pertes max|spread max %|stop min|stop max|limite jour $|taille|plancher
 *   D|trades du jour|pertes du jour|résultat du jour $
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plan dynamique et signaux d'exécution.
 */
class CX_Live {

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
		foreach ( (array) ( $c['levels'] ?? array() ) as $l ) {
			if ( null !== $l['price'] ) {
				$out[] = 'L|' . self::n( $l['price'] ) . '|' . self::t( $l['type'] );
			}
		}
		// Annonces : heure exacte (UTC) calculée ici, l'EA n'a pas à lire de date. Celles du plan
		// de Claude et les annonces USD à fort impact du calendrier automatique.
		$monday = new DateTimeImmutable( $week . ' 00:00', CX_Repo::tz() );
		foreach ( CX_Calendar::merged( $monday->getTimestamp(), $monday->modify( '+7 days' )->getTimestamp(), true ) as $e ) {
			$out[] = 'N|' . $e['t'] . '|' . $e['impact'] . '|' . self::t( $e['name'] );
		}
		$pa    = $acc['params'];
		$out[] = 'R|' . self::n( $pa['risk'] ) . '|' . self::n( $pa['riskred'] ) . '|' . self::n( $pa['redat'] ) . '|' . self::n( $pa['contract'] ) . '|' . (int) $pa['maxtrades'] . '|' . (int) $pa['maxloss'] . '|' . self::n( $pa['spreadmax'] ) . '|1.5|4|' . self::n( $acc['size'] * $pa['daily'] / 100 ) . '|' . self::n( $acc['size'] ) . '|' . self::n( $acc['size'] * ( 1 - $pa['max'] / 100 ) );
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
			$kind = 'limit' === ( $in['kind'] ?? '' ) ? 'limit' : 'now';
			$data = array(
				'kind'  => $kind,
				'side'  => $side,
				'setup' => CX_Repo::pick( (string) ( $in['setup'] ?? '' ), array( '1', '2' ), '' ),
				'zone'  => CX_Repo::text( $in['zone'] ?? '', 80 ),
				'entry' => $num( 'entry' ),
				'sl'    => $num( 'sl' ),
				'tp'    => $num( 'tp' ),
				'lot'   => CX_Repo::num( $in['lot'] ?? null, 0, 1000 ),
				'rr'    => CX_Repo::num( $in['rr'] ?? null, 0, 100 ),
				'stop'  => CX_Repo::num( $in['stop'] ?? null, 0, 1000 ),
			);
			$label = ( 'sell' === $side ? 'SELL ' : 'BUY ' ) . ( 'limit' === $kind ? 'LIMIT' : 'NOW' ) . ( $data['setup'] ? ' · setup ' . $data['setup'] : '' );
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
					'statut'     => 'limit' === $kind ? 'attente' : 'ouvert',
					'ouvert_le'  => 'limit' === $kind ? null : $at->format( 'Y-m-d H:i:s' ),
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
			if ( $send ) {
				CX_Telegram::send(
					sprintf(
						"%s <b>%s</b> — %s\nZone : %s\nEntrée %s · stop %s · objectif %s (%sR)\nLot : %s (stop %s $)\n" . ( 'limit' === $kind ? 'Ordre limite : valable 30 min, en attente d’un repli.' : 'Sortie partielle à 2R, puis stop au prix d’entrée.' ),
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
			$key   = preg_replace( '/[^0-9.\-bs]/', '', (string) ( $in['key'] ?? '' ) );
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
		if ( $send && in_array( $status, array( 'gagne', 'perdu', 'be' ), true ) ) {
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
			if ( 6 !== count( $f ) || ! in_array( $f[0], array( 'D1', 'H4', 'H1' ), true ) ) {
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
			$out[ $tf ] = array_slice( $rows, -600 );
		}
		update_option(
			'cx_candles',
			array(
				'at'      => CX_Repo::clock(),
				'account' => $acc['name'],
				'tf'      => $out,
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

	/**
	 * Dernières bougies reçues du courtier.
	 *
	 * @return WP_REST_Response
	 */
	public static function candles() {
		return new WP_REST_Response( get_option( 'cx_candles', array() ), 200 );
	}
}
