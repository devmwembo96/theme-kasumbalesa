<?php
/**
 * Points d'entrée machine (version 2) :
 * - POST /mt5         trades fermés et instantanés solde/équité envoyés par l'EA MT5,
 *                     protégé par une clé secrète par compte (en-tête X-CX-Key) ;
 * - POST /tradingview alertes de l'indicateur, protégé par un jeton (?token=… ou champ
 *                     « token » du corps), journalisées avec l'heure de réception.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Réception MT5 et TradingView.
 */
class CX_Ingest {

	/**
	 * Accroches.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Routes publiques : l'authentification se fait par jeton dans chaque méthode.
	 */
	public static function routes() {
		foreach ( array(
			'/mt5'         => 'mt5',
			'/tradingview' => 'tradingview',
		) as $path => $cb ) {
			register_rest_route(
				CX_Rest::NS,
				$path,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, $cb ),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/* ------------------------------------------------------------------
	 * MT5
	 * ------------------------------------------------------------------ */

	/**
	 * Compte correspondant à une clé MT5 (comparaison en temps constant).
	 *
	 * @param string $key Clé reçue.
	 * @return array|null
	 */
	public static function account_by_key( $key ) {
		global $wpdb;
		if ( ! is_string( $key ) || strlen( $key ) < 16 ) {
			return null;
		}
		$t     = CX_Install::table( 'accounts' );
		$found = null;
		foreach ( $wpdb->get_results( "SELECT id, mt5_cle FROM $t WHERE mt5_cle IS NOT NULL AND mt5_cle <> ''" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL
			if ( CX_Guard::matches( $key, $row->mt5_cle ) ) {
				$found = (int) $row->id;
			}
		}
		return $found ? CX_Repo::account( $found ) : null;
	}

	/**
	 * Réception d'un envoi de l'EA.
	 *
	 * Corps JSON : { "trades": [ {ticket, symbol, side, lots, open_price, close_price, sl, tp,
	 * profit, commission, swap, open_time, close_time} ], "snapshot": {balance, equity,
	 * daily_loss} }. Heures en secondes Unix UTC.
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function mt5( WP_REST_Request $req ) {
		$limit = CX_Guard::throttle( 'mt5', 120 );
		if ( $limit ) {
			return $limit;
		}
		$body = $req->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$key  = (string) $req->get_header( 'x_cx_key' );
		if ( '' === $key ) {
			$key = (string) ( $body['key'] ?? '' );
		}
		$acc = self::account_by_key( $key );
		if ( ! $acc ) {
			return CX_Guard::refuse( 'mt5', 'clé MT5 invalide' );
		}

		$out = array(
			'ok'         => true,
			'account'    => $acc['name'],
			'inserted'   => 0,
			'duplicates' => 0,
			'ignored'    => 0,
			'snapshot'   => false,
		);
		$trades = is_array( $body['trades'] ?? null ) ? array_slice( $body['trades'], 0, 300 ) : array();
		foreach ( $trades as $t ) {
			$r = is_array( $t ) ? self::import_trade( $acc, $t ) : 'ignored';
			++$out[ $r ];
		}
		if ( is_array( $body['snapshot'] ?? null ) ) {
			$out['snapshot'] = self::snapshot( $acc, $body['snapshot'] );
		}
		if ( $out['inserted'] ) {
			CX_Telegram::check_daily_from_trades( $acc['id'] );
		}
		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * Date et heure du site pour un horodatage Unix UTC.
	 *
	 * @param mixed $ts Secondes.
	 * @return DateTimeImmutable|null
	 */
	private static function site_time( $ts ) {
		$ts = CX_Repo::num( $ts, 946684800, 4102444800 ); // 2000 à 2100.
		return null === $ts ? null : ( new DateTimeImmutable( '@' . (int) $ts ) )->setTimezone( CX_Repo::tz() );
	}

	/**
	 * Ajoute un trade fermé, sauf s'il est déjà connu (ticket).
	 *
	 * @param array $acc Compte.
	 * @param array $t   Trade reçu.
	 * @return string inserted, duplicates ou ignored.
	 */
	private static function import_trade( $acc, $t ) {
		global $wpdb;
		$table  = CX_Install::table( 'trades' );
		$ticket = preg_replace( '/\D/', '', (string) ( $t['ticket'] ?? '' ) );
		$open   = self::site_time( $t['open_time'] ?? null );
		$lots   = CX_Repo::num( $t['lots'] ?? null, 0.001, 1000 );
		if ( '' === $ticket || ! $open || null === $lots ) {
			return 'ignored';
		}
		// Préfixe du compte : deux courtiers peuvent utiliser le même numéro de ticket.
		$ticket = mb_substr( $acc['id'] . ':' . $ticket, 0, 32 );
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE ticket_mt5 = %s", $ticket ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
			return 'duplicates';
		}

		$entry = CX_Repo::num( $t['open_price'] ?? null, 0, 1e6 );
		$sl    = CX_Repo::num( $t['sl'] ?? null, 0, 1e6 );
		$stop  = ( $entry && $sl ) ? round( abs( $entry - $sl ), 2 ) : null;
		$pnl   = round( ( CX_Repo::num( $t['profit'] ?? 0, -1e7, 1e7 ) ?? 0 ) + ( CX_Repo::num( $t['commission'] ?? 0, -1e6, 1e6 ) ?? 0 ) + ( CX_Repo::num( $t['swap'] ?? 0, -1e6, 1e6 ) ?? 0 ), 2 );
		$risk  = $stop ? round( $stop * $lots * (float) $acc['params']['contract'], 2 ) : null;
		$r     = $risk > 0 ? max( -100, min( 100, round( $pnl / $risk, 2 ) ) ) : 0;
		$side  = strtolower( (string) ( $t['side'] ?? '' ) );

		$notes = sprintf(
			'Importé de MT5 : %s, entrée %s, sortie %s%s%s.',
			CX_Repo::text( $t['symbol'] ?? 'XAUUSD', 30 ),
			null === $entry ? '?' : $entry,
			CX_Repo::num( $t['close_price'] ?? null, 0, 1e6 ) ?? '?',
			$sl ? ', SL ' . $sl : ', sans SL',
			CX_Repo::num( $t['tp'] ?? null, 0, 1e6 ) ? ', TP ' . CX_Repo::num( $t['tp'], 0, 1e6 ) : ''
		);

		$sens = in_array( $side, array( 'sell', 'vente', '1' ), true ) ? 'vente' : 'achat';
		// Setup, zone, niveau et biais repris du signal Claude et du plan (CX_Score::fill).
		list( $fill, $why ) = CX_Score::fill( $sens, $entry, $stop, $open );
		if ( $why ) {
			$notes .= ' Rempli par le carnet : ' . implode( ' ; ', $why ) . '.';
		}

		$ok = $wpdb->insert(
			$table,
			$fill + array(
				'account_id'        => (int) $acc['id'],
				'date'              => $open->format( 'Y-m-d' ),
				'heure'             => $open->format( 'H:i' ),
				'sens'              => $sens,
				'setup'             => 'hors',
				'niveau'            => 'Autre',
				'stop_usd'          => $stop,
				'lot'               => round( $lots, 2 ),
				'risque_usd'        => $risk,
				'resultat_r'        => $r,
				'resultat_usd'      => $pnl,
				'sur_niveau_cle'    => 0,
				'avec_biais'        => 0,
				'regles_respectees' => 0,
				'lien'              => '',
				'notes'             => $notes,
				'source'            => 'mt5',
				'ticket_mt5'        => $ticket,
				'a_completer'       => 1,
				'created_at'        => CX_Repo::clock(),
			)
		);
		return $ok ? 'inserted' : 'duplicates';
	}

	/**
	 * Enregistre un instantané (au plus un toutes les 30 secondes par compte).
	 *
	 * @param array $acc Compte.
	 * @param array $s   {balance, equity, daily_loss}.
	 * @return bool Enregistré ou non.
	 */
	private static function snapshot( $acc, $s ) {
		global $wpdb;
		$table   = CX_Install::table( 'snapshots' );
		$balance = CX_Repo::num( $s['balance'] ?? null, -1e8, 1e8 );
		$equity  = CX_Repo::num( $s['equity'] ?? null, -1e8, 1e8 );
		if ( null === $balance || null === $equity ) {
			return false;
		}
		$last = $wpdb->get_var( $wpdb->prepare( "SELECT horodatage FROM $table WHERE account_id = %d ORDER BY horodatage DESC LIMIT 1", $acc['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$now  = CX_Repo::clock();
		if ( $last && strtotime( $now ) - strtotime( $last ) < 30 ) {
			return false;
		}
		$loss = CX_Repo::num( $s['daily_loss'] ?? 0, -1e8, 1e8 ) ?? 0;
		$wpdb->insert(
			$table,
			array(
				'account_id' => (int) $acc['id'],
				'horodatage' => $now,
				'solde'      => $balance,
				'equite'     => $equity,
				'perte_jour' => $loss,
			)
		);
		CX_Telegram::check_daily( (int) $acc['id'], $loss );
		return true;
	}

	/**
	 * Dernier instantané de chaque compte.
	 *
	 * @return array account_id => {at, balance, equity, dailyLoss}
	 */
	public static function latest_snapshots() {
		global $wpdb;
		$t   = CX_Install::table( 'snapshots' );
		$out = array();
		$sql = "SELECT s.* FROM $t s JOIN (SELECT account_id, MAX(horodatage) h FROM $t GROUP BY account_id) m ON m.account_id = s.account_id AND m.h = s.horodatage";
		foreach ( $wpdb->get_results( $sql ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$out[ (int) $r->account_id ] = array(
				'at'        => $r->horodatage,
				'balance'   => (float) $r->solde,
				'equity'    => (float) $r->equite,
				'dailyLoss' => (float) $r->perte_jour,
			);
		}
		return (object) $out;
	}

	/* ------------------------------------------------------------------
	 * TradingView
	 * ------------------------------------------------------------------ */

	/**
	 * Date et heure du site pour une heure TradingView ({{time}}, {{timenow}} : ISO 8601 UTC,
	 * ou millisecondes Unix).
	 *
	 * @param mixed $v Valeur reçue.
	 * @return string|null Date MySQL.
	 */
	private static function tv_time( $v ) {
		if ( is_numeric( $v ) ) {
			$v = (float) $v;
			$d = self::site_time( $v > 1e11 ? $v / 1000 : $v );
			return $d ? $d->format( 'Y-m-d H:i:s' ) : null;
		}
		if ( ! is_string( $v ) || '' === trim( $v ) ) {
			return null;
		}
		try {
			return ( new DateTimeImmutable( trim( $v ), new DateTimeZone( 'UTC' ) ) )->setTimezone( CX_Repo::tz() )->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Réception d'une alerte TradingView (JSON ou texte libre).
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function tradingview( WP_REST_Request $req ) {
		global $wpdb;
		$limit = CX_Guard::throttle( 'tradingview', 60 );
		if ( $limit ) {
			return $limit;
		}
		$raw   = mb_substr( (string) $req->get_body(), 0, 4000 );
		$data  = json_decode( $raw, true );
		$data  = is_array( $data ) ? $data : array();
		$token = (string) $req->get_param( 'token' );
		if ( '' === $token ) {
			$token = (string) ( $data['token'] ?? '' );
		}
		$hash = (string) ( CX_Guard::settings()['tradingview'] ?? '' );
		if ( ! CX_Guard::matches( $token, $hash ) ) {
			return CX_Guard::refuse( 'tradingview', 'jeton TradingView invalide' );
		}

		// Le jeton n'est jamais conservé dans le journal.
		unset( $data['token'] );
		$payload = $data ? wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : str_replace( $token, '***', $raw );
		$type    = $data['signal'] ?? $data['type'] ?? $data['action'] ?? ( $data ? '' : $raw );

		$wpdb->insert(
			CX_Install::table( 'signals' ),
			array(
				'recu_le'      => CX_Repo::clock(),
				'symbole'      => CX_Repo::text( $data['symbol'] ?? $data['ticker'] ?? '', 30 ),
				'unite'        => CX_Repo::text( $data['tf'] ?? $data['interval'] ?? $data['timeframe'] ?? '', 10 ),
				'type_signal'  => CX_Repo::text( $type, 60 ),
				'prix'         => CX_Repo::num( $data['price'] ?? $data['close'] ?? null, 0, 1e7 ),
				'heure_bougie' => self::tv_time( $data['bar_time'] ?? $data['time'] ?? null ),
				'payload'      => (string) $payload,
			)
		);
		return new WP_REST_Response(
			array(
				'ok' => true,
				'id' => (int) $wpdb->insert_id,
			),
			200
		);
	}

	/**
	 * Derniers signaux reçus.
	 *
	 * @param int $limit Nombre maximal.
	 * @return array
	 */
	public static function signals( $limit = 100 ) {
		global $wpdb;
		$t = CX_Install::table( 'signals' );
		return array_map(
			static function ( $r ) {
				return array(
					'id'         => (int) $r->id,
					'receivedAt' => $r->recu_le,
					'symbol'     => $r->symbole,
					'tf'         => $r->unite,
					'type'       => $r->type_signal,
					'price'      => null === $r->prix ? null : (float) $r->prix,
					'barTime'    => $r->heure_bougie,
					'delay'      => $r->heure_bougie ? strtotime( $r->recu_le ) - strtotime( $r->heure_bougie ) : null,
					'payload'    => $r->payload,
				);
			},
			$wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY recu_le DESC, id DESC LIMIT %d", max( 1, min( 500, (int) $limit ) ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}
}
