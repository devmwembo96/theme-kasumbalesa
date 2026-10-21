<?php
/**
 * Graphique d'une analyse de Claude, au style TradingView clair, et sa capture d'écran.
 *
 * - ?cx_chart=1&date=AAAA-MM-JJ&kind=asia|premarket|us|week : page du graphique, en paysage comme un
 *   écran de PC (1600 × 900, capture à 2560 × 1440) : bougies du courtier,
 *   zones, niveaux, outil position de chaque scénario, trajet simulé). La tâche Claude la capture
 *   avec un navigateur sans écran (window.CX_READY = true quand le dessin est fini).
 * - POST /claude/chart?date=…&kind=… (jeton du plan, champ « file » en PNG) : capture enregistrée dans
 *   les fichiers du site, liée à l'analyse (onglet Suivi) et envoyée sur Telegram.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_Chart {

	const KINDS = array( 'asia', 'premarket', 'us', 'week' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'serve' ), 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/claude/chart',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'upload' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @param string $date Date AAAA-MM-JJ.
	 * @return string Lundi de la semaine.
	 */
	private static function week_of( $date ) {
		return CX_Telegram::monday( new DateTimeImmutable( $date . ' 12:00', CX_Repo::tz() ) );
	}

	/**
	 * Risque par trade du compte actif, pour le lot affiché sur l'outil position.
	 *
	 * @return float
	 */
	private static function active_risk() {
		foreach ( CX_Repo::accounts() as $a ) {
			if ( ! $a['active'] ) {
				continue;
			}
			$pa = $a['params'];
			if ( 'propre' === ( $pa['kind'] ?? 'propfirm' ) ) {
				$snap = ( (array) CX_Ingest::latest_snapshots() )[ $a['id'] ] ?? null;
				return CX_Repo::own_risk( $snap ? (float) $snap['balance'] : (float) $a['size'], $pa )['risk'];
			}
			return (float) $pa['risk'];
		}
		return 0.0;
	}

	/**
	 * Données du graphique d'une analyse.
	 *
	 * @param string $date Date.
	 * @param string $kind Séance ou « week ».
	 * @return array|null
	 */
	public static function data( $date, $kind ) {
		$p = CX_Repo::plan( self::week_of( $date ) );
		$c = is_array( $p['content'] ?? null ) ? $p['content'] : array();
		$s = null;
		if ( 'week' !== $kind ) {
			foreach ( (array) ( $c['sessions'] ?? array() ) as $x ) {
				if ( $x['date'] === $date && $x['kind'] === $kind ) {
					$s = $x;
				}
			}
			if ( ! $s ) {
				return null;
			}
		}

		// Bougies : M5 pour une séance (M15 si absent), H1 pour la semaine.
		$tf    = (array) ( get_option( 'cx_candles', array() )['tf'] ?? array() );
		$order = 'week' === $kind ? array( 'H1' => 150, 'H4' => 110 ) : array( 'M5' => 156, 'M15' => 120, 'H1' => 90 );
		$name  = '';
		$bars  = array();
		foreach ( $order as $k => $n ) {
			if ( count( (array) ( $tf[ $k ] ?? array() ) ) >= 30 ) {
				$name = $k;
				$bars = array_slice( $tf[ $k ], -$n );
				break;
			}
		}
		$lo = $bars ? min( array_column( $bars, 3 ) ) : 0;
		$hi = $bars ? max( array_column( $bars, 2 ) ) : 0;
		$sp = max( 1, $hi - $lo );
		$near = fn( $a, $b ) => $b >= $lo - $sp * 0.6 && $a <= $hi + $sp * 0.6;

		// Zones : celles de l'analyse, puis celles du plan de la semaine encore valides et proches du prix.
		$zones = array();
		foreach ( (array) ( $s['zones'] ?? array() ) as $z ) {
			$zones[] = array(
				'low'   => (float) $z['low'],
				'high'  => (float) $z['high'],
				'side'  => $z['side'],
				'label' => trim( (string) $z['type'] ),
			);
		}
		$live = (array) ( $c['live']['zones'] ?? array() );
		foreach ( (array) ( $c['zones'] ?? array() ) as $z ) {
			$state = $live[ CX_Live::zone_key( $z ) ]['state'] ?? ( $z['state'] ?? 'vierge' );
			if ( 'cassee' === $state || ! $near( (float) $z['low'], (float) $z['high'] ) ) {
				continue;
			}
			$zones[] = array(
				'low'   => (float) $z['low'],
				'high'  => (float) $z['high'],
				'side'  => $z['side'],
				'label' => trim( $z['type'] . ' ' . $z['tf'] ),
			);
		}

		// Niveaux de liquidité : ceux de l'analyse et du plan, proches du prix, sans doublon.
		$levels = array();
		$seen   = array();
		$lv     = array_merge( (array) ( $s['levels'] ?? array() ), (array) ( $c['levels'] ?? array() ) );
		foreach ( $lv as $l ) {
			$v = (float) ( $l['price'] ?? 0 );
			// Deux niveaux à moins de 1 $ : un seul trait (le premier, celui de l'analyse).
			if ( ! $v || ! $near( $v, $v ) || array_filter( array_keys( $seen ), fn( $x ) => abs( (float) $x - $v ) < 1.0 ) ) {
				continue;
			}
			$seen[ (string) $v ] = 1;
			$t          = trim( (string) ( $l['type'] ?? '' ) );
			$levels[]   = array(
				'price' => $v,
				'label' => '' === $t ? 'Niveau' : ( preg_match( '/^EQ[HL]$/i', $t ) ? $t . ' liquidity' : $t ),
				// Couleurs de TradingView : PDH, PDL, PWH, PWL en bleu ; EQH, EQL en gris foncé ; le reste en gris.
				'color' => preg_match( '/^P[DWM][HL]/i', $t ) ? '#2962ff' : ( preg_match( '/^EQ[HL]/i', $t ) ? '#434651' : '#787b86' ),
			);
		}
		// Invalidation : premier prix de la phrase « annule » (séance) ou invalidation du plan (semaine).
		$inv = 0.0;
		if ( $s && preg_match_all( '/\d[\d \x{202F}\x{00A0}]*(?:[.,]\d+)?/u', (string) ( $s['invalid'] ?? '' ), $m ) ) {
			foreach ( $m[0] as $x ) {
				$v = (float) str_replace( array( ' ', "\u{202F}", "\u{00A0}", ',' ), array( '', '', '', '.' ), trim( $x ) );
				if ( $v > 100 ) { // le premier prix : « M15 », « H1 »… ne comptent pas
					$inv = $v;
					break;
				}
			}
		} elseif ( 'week' === $kind ) {
			$inv = (float) ( $c['prediction']['invalidation'] ?? 0 );
		}
		if ( $inv > 100 && $near( $inv, $inv ) ) {
			$levels[] = array(
				'price' => $inv,
				'label' => 'Invalidation',
				'color' => '#f23645',
			);
		}

		// Scénarios d'exécution : outil position côte à côte, à droite de la dernière bougie.
		$raw = array();
		if ( 'week' === $kind ) {
			foreach ( (array) ( $c['scenarios'] ?? array() ) as $sc ) {
				preg_match_all( '/\d[\d\s]*[.,]?\d*/', str_replace( array( "\u{202F}", "\u{00A0}" ), ' ', (string) $sc['entry'] ), $m );
				$e = array_filter( array_map( fn( $x ) => (float) str_replace( array( ' ', ',' ), array( '', '.' ), $x ), $m[0] ) );
				if ( $e && $sc['sl'] && $sc['tp'] ) {
					$raw[] = array( 'entry' => array_sum( $e ) / count( $e ), 'sl' => (float) $sc['sl'], 'tp' => (float) $sc['tp'] );
				}
			}
		} else {
			foreach ( (array) ( $s['positions'] ?? array() ) as $x ) {
				$raw[] = array( 'entry' => (float) $x['entry'], 'sl' => (float) $x['sl'], 'tp' => (float) $x['tp'] );
			}
		}
		$risk = self::active_risk();
		$pos  = array();
		foreach ( array_slice( $raw, 0, 3 ) as $i => $x ) {
			$stop = abs( $x['entry'] - $x['sl'] );
			if ( $stop <= 0 || ! $x['tp'] ) {
				continue;
			}
			$pos[] = array(
				'entry' => round( $x['entry'], 2 ),
				'sl'    => $x['sl'],
				'tp'    => $x['tp'],
				'stop'  => round( $stop, 2 ),
				'rr'    => round( abs( $x['tp'] - $x['entry'] ) / $stop, 1 ),
				'lot'   => $risk > 0 ? floor( $risk / ( $stop * 100 ) * 100 + 1e-9 ) / 100 : null,
				'at'    => 3 + 30 * $i,
				'len'   => 26,
			);
		}

		$titles = array(
			'asia'      => 'Session Asie',
			'premarket' => 'Pré-marché US',
			'us'        => 'Plan de la session US',
			'week'      => 'Plan de la semaine',
		);
		$when   = wp_date( 'l j F', strtotime( $date . ' 12:00' ), CX_Repo::tz() );
		$bias   = $s ? ( $s['bias'] ?? '' ) : '';
		$pr     = (array) ( $c['prediction'] ?? array() );
		if ( 'week' === $kind ) {
			$bias = ( $pr['bull'] ?? 0 ) > ( $pr['bear'] ?? 0 ) ? 'h' : ( ( $pr['bear'] ?? 0 ) > ( $pr['bull'] ?? 0 ) ? 'b' : 'n' );
		}
		$broker = '';
		foreach ( CX_Repo::accounts() as $a ) {
			if ( $a['active'] ) {
				$broker = $a['firm'];
			}
		}
		return array(
			'title'       => $titles[ $kind ] . ' · ' . $when,
			'sub'         => trim( ( $bias ? 'Biais ' . array( 'h' => 'haussier', 'b' => 'baissier', 'n' => 'neutre' )[ $bias ] : '' ) . ( ( $s['invalid'] ?? ( $pr['invalidation'] ?? '' ) ) ? ' · annule : ' . ( $s['invalid'] ?? number_format( (float) $pr['invalidation'], 2, ',', ' ' ) ) : '' ) ),
			'tf'          => $name,
			'tfLabel'     => array( 'M5' => '5', 'M15' => '15', 'H1' => '1h', 'H4' => '4h' )[ $name ] ?? $name,
			'broker'      => $broker,
			'bars'        => $bars,
			'zones'       => array_slice( $zones, 0, 8 ),
			'levels'      => array_slice( $levels, 0, 8 ),
			'positions'   => $pos,
			'visible'     => count( $bars ),
			'rightOffset' => max( 12, 6 + 30 * count( $pos ) ),
			'barSpacing'  => 8,
		);
	}

	/** Page du graphique (capture d'écran). */
	public static function serve() {
		if ( ! isset( $_GET['cx_chart'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$date = CX_Repo::date( wp_unslash( $_GET['date'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$kind = sanitize_key( wp_unslash( $_GET['kind'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		nocache_headers();
		if ( ! $date || ! in_array( $kind, self::KINDS, true ) || ! CX_Front::can_use() ) {
			status_header( 404 );
			exit;
		}
		$d = self::data( $date, $kind );
		if ( ! $d ) {
			status_header( 404 );
			echo 'Analyse introuvable.';
			exit;
		}
		header( 'Content-Type: text/html; charset=utf-8' );
		$lwc = (string) file_get_contents( CX_DIR . 'assets/vendor/lightweight-charts-4.2.3.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$js  = (string) file_get_contents( CX_DIR . 'assets/js/chart-shot.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		// phpcs:disable WordPress.Security.EscapeOutput -- page autonome : bibliothèque et données JSON.
		echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=1600"><title>Carnet XAU</title><style>'
			. 'html,body{margin:0;background:#fff;color:#131722;font-family:-apple-system,BlinkMacSystemFont,"Trebuchet MS",Roboto,Ubuntu,sans-serif}'
			. '#shot{width:1600px;height:900px;position:relative;background:#fff;overflow:hidden}'
			. '#chart{position:absolute;left:0;top:0;right:0;bottom:0}'
			. '#legend{position:absolute;left:12px;top:6px;z-index:3;background:rgba(255,255,255,.88);padding:2px 6px;border-radius:4px;font-size:13px;line-height:18px;pointer-events:none}'
			. '#legend b{font-weight:500}#sym{font-weight:600}#ohlc{font-size:12px;color:#787b86}'
			. '#head{position:absolute;right:86px;top:6px;z-index:3;text-align:right;max-width:720px;background:rgba(255,255,255,.88);padding:2px 6px;border-radius:4px}'
			. '#title{font-size:13px;font-weight:600}#sub{font-size:11px;color:#787b86;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
			. '#foot{position:absolute;left:64px;bottom:34px;z-index:3;font-size:10px;color:#b2b5be}'
			. '#msg{position:absolute;left:0;right:0;top:45%;text-align:center;color:#787b86;font-size:14px}'
			. '</style></head><body><div id="shot"><div id="chart"></div>'
			. '<div id="legend"><div id="sym"></div><div id="ohlc"></div></div>'
			. '<div id="head"><div id="title"></div><div id="sub"></div></div>'
			. '<div id="foot">Carnet XAU · scénario de Claude, simulation · heures de Lubumbashi</div><div id="msg"></div></div>'
			. '<script>' . $lwc . '</script><script>window.CX_DATA=' . wp_json_encode( $d ) . ';</script><script>' . $js . '</script></body></html>';
		// phpcs:enable
		exit;
	}

	/**
	 * Capture envoyée par la tâche Claude.
	 *
	 * @param WP_REST_Request $req Requête (fichier « file », date, kind).
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload( WP_REST_Request $req ) {
		global $wpdb;
		$limit = CX_Guard::throttle( 'plan', 30 );
		if ( $limit ) {
			return $limit;
		}
		if ( ! CX_Guard::matches( (string) $req->get_header( 'x_cx_key' ), (string) ( CX_Guard::settings()['plan'] ?? '' ) ) ) {
			return CX_Guard::refuse( 'plan', 'jeton du plan invalide' );
		}
		$date  = CX_Repo::date( (string) $req->get_param( 'date' ) );
		$kind  = (string) $req->get_param( 'kind' );
		$files = $req->get_file_params();
		$f     = $files['file'] ?? null;
		if ( ! $date || ! in_array( $kind, self::KINDS, true ) ) {
			return CX_Repo::invalid( '« date » (AAAA-MM-JJ) et « kind » (asia, premarket, us ou week) obligatoires.' );
		}
		if ( ! $f || ! empty( $f['error'] ) || ! is_uploaded_file( $f['tmp_name'] ) || $f['size'] > 4 * MB_IN_BYTES ) {
			return CX_Repo::invalid( 'Image absente ou trop lourde (4 Mo au plus), champ « file ».' );
		}
		$info = @getimagesize( $f['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $info || IMAGETYPE_PNG !== $info[2] ) {
			return CX_Repo::invalid( 'Le fichier doit être une image PNG.' );
		}
		$week = self::week_of( $date );
		$p    = CX_Repo::plan( $week );
		if ( ! $p || ! is_array( $p['content'] ) ) {
			return CX_Repo::invalid( 'Aucun plan pour la semaine du ' . $week . '.' );
		}
		$c   = $p['content'];
		$idx = null;
		if ( 'week' !== $kind ) {
			foreach ( (array) ( $c['sessions'] ?? array() ) as $i => $x ) {
				if ( $x['date'] === $date && $x['kind'] === $kind ) {
					$idx = $i;
				}
			}
			if ( null === $idx ) {
				return CX_Repo::invalid( 'Publiez d’abord l’analyse de cette séance.' );
			}
		}

		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'carnet-xau/graphiques';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'cx_fs', 'Dossier des graphiques impossible à créer.', array( 'status' => 500 ) );
		}
		$name = $date . '-' . $kind . '.png';
		if ( ! move_uploaded_file( $f['tmp_name'], $dir . '/' . $name ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
			return new WP_Error( 'cx_fs', 'Enregistrement de l’image impossible.', array( 'status' => 500 ) );
		}
		$url = trailingslashit( $up['baseurl'] ) . 'carnet-xau/graphiques/' . $name . '?v=' . time();
		if ( 'week' === $kind ) {
			$c['chart'] = $url;
		} else {
			$c['sessions'][ $idx ]['chart'] = $url;
		}
		$wpdb->update(
			CX_Install::table( 'plans' ),
			array( 'contenu' => wp_json_encode( $c, JSON_UNESCAPED_UNICODE ) ),
			array(
				'account_id' => 0,
				'semaine'    => $week,
			)
		);
		$s   = 'week' === $kind ? null : $c['sessions'][ $idx ];
		$cap = 'week' === $kind ? '📈 <b>Plan de la semaine</b> : zones et scénarios.' : CX_Telegram::chart_caption( $s );
		$tg  = empty( $req->get_param( 'silent' ) ) ? CX_Telegram::photo( $dir . '/' . $name, $cap ) : true;
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'url'      => $url,
				'telegram' => is_wp_error( $tg ) ? $tg->get_error_message() : true,
			),
			200
		);
	}
}
