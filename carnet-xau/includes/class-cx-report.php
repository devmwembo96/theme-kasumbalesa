<?php
/**
 * Rapport hebdomadaire : chiffres de la semaine d'un compte, discipline, plan et signaux de
 * Claude, annonces, analyse du journal rédigée par Claude (clé « coaching » du plan).
 *
 * - GET /report?week=lundi&account=id  données du rapport (interface, tâche Claude) ;
 * - /?cx_report=lundi&account=id       page imprimable (« Enregistrer en PDF » du navigateur) ;
 * - Telegram : résumé le samedi à partir de 9 h (Lubumbashi), avec le lien du rapport.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rapport de la semaine.
 */
class CX_Report {

	const DAYS = array( 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche' );
	const EMO  = array(
		'calme'    => 'Calme',
		'presse'   => 'Pressé',
		'revanche' => 'Revanche',
		'fatigue'  => 'Fatigue',
	);

	/**
	 * Accroches.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'page' ), 0 );
		add_action( CX_Telegram::CRON, array( __CLASS__, 'weekly' ) );
	}

	/**
	 * Route de lecture.
	 */
	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/report',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
	}

	/**
	 * Lundi demandé (n'importe quel jour de la semaine accepté), sinon semaine en cours.
	 *
	 * @param mixed $v Date AAAA-MM-JJ.
	 * @return string Lundi.
	 */
	public static function week( $v = null ) {
		$d = CX_Repo::date( $v );
		return CX_Telegram::monday( new DateTimeImmutable( $d ? $d : 'now', CX_Repo::tz() ) );
	}

	/**
	 * Compte demandé, sinon compte actif.
	 *
	 * @param mixed $id Identifiant.
	 * @return array|null
	 */
	public static function account( $id = null ) {
		$a = $id ? CX_Repo::account( (int) $id ) : null;
		return $a && ! is_wp_error( $a ) ? $a : CX_Telegram::active();
	}

	/**
	 * Agrégats d'une liste de trades (mêmes règles que l'onglet Stats).
	 *
	 * @param array $list Trades.
	 * @return array
	 */
	private static function agg( $list ) {
		$rs  = array_map( fn( $t ) => (float) $t['r'], $list );
		$pos = array_sum( array_filter( $rs, fn( $r ) => $r > 0 ) );
		$neg = -array_sum( array_filter( $rs, fn( $r ) => $r < 0 ) );
		$n   = count( $list );
		return array(
			'n'      => $n,
			'wins'   => count( array_filter( $rs, fn( $r ) => $r > 0 ) ),
			'losses' => count( array_filter( $rs, fn( $r ) => $r < 0 ) ),
			'wr'     => $n ? count( array_filter( $rs, fn( $r ) => $r > 0 ) ) / $n : null,
			'sumR'   => round( array_sum( $rs ), 2 ),
			'avgR'   => $n ? round( array_sum( $rs ) / $n, 2 ) : null,
			'pf'     => $neg ? round( $pos / $neg, 2 ) : ( $pos ? null : 0 ),
			'pnl'    => round( array_sum( array_map( fn( $t ) => (float) $t['pnl'], $list ) ), 2 ),
		);
	}

	/**
	 * Données du rapport.
	 *
	 * @param array  $acc  Compte.
	 * @param string $week Lundi.
	 * @return array
	 */
	public static function data( $acc, $week ) {
		$tz     = CX_Repo::tz();
		$monday = new DateTimeImmutable( $week . ' 00:00', $tz );
		$sunday = $monday->modify( '+6 days' )->format( 'Y-m-d' );
		$all    = CX_Repo::trades( $acc['id'] );
		$trades = array_values( array_filter( $all, fn( $t ) => $t['date'] >= $week && $t['date'] <= $sunday ) );
		usort( $trades, fn( $a, $b ) => strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] ) );
		$pa = $acc['params'];

		// Par jour, et jours où la limite de trades ou de pertes a été dépassée.
		$days = array();
		$over = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$date = $monday->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
			$list = array_values( array_filter( $trades, fn( $t ) => $t['date'] === $date ) );
			if ( ! $list && $i >= 5 ) {
				continue;
			}
			$days[] = array(
				'day'  => self::DAYS[ $i ],
				'date' => $date,
			) + self::agg( $list );
			$lost = count( array_filter( $list, fn( $t ) => $t['r'] < 0 ) );
			if ( count( $list ) > $pa['maxtrades'] || $lost > $pa['maxloss'] ) {
				$over[] = self::DAYS[ $i ];
			}
		}

		// Plus longue série de pertes.
		$streak = 0;
		$run    = 0;
		foreach ( $trades as $t ) {
			$run    = $t['r'] < 0 ? $run + 1 : 0;
			$streak = max( $streak, $run );
		}

		$emotions = array();
		foreach ( self::EMO as $k => $label ) {
			$list = array_values( array_filter( $trades, fn( $t ) => $t['emotion'] === $k ) );
			if ( $list ) {
				$emotions[] = array( 'label' => $label ) + self::agg( $list );
			}
		}
		$setups = array();
		foreach ( array(
			'1'    => 'Setup 1',
			'2'    => 'Setup 2',
			'3'    => 'Setup 3 (iFVG)',
			'hors' => 'Hors setup',
		) as $k => $label ) {
			$list = array_values( array_filter( $trades, fn( $t ) => $t['setup'] === $k ) );
			if ( $list ) {
				$setups[] = array( 'label' => $label ) + self::agg( $list );
			}
		}

		// Avancement de l'étape : solde MT5 s'il existe, sinon trades depuis le début de l'étape.
		$pct    = 'phase2' === $acc['status'] ? $pa['t2'] : ( 'finance' === $acc['status'] ? $pa['tf'] : $pa['t1'] );
		$target = $acc['size'] * $pct / 100;
		$snap   = CX_Ingest::latest_snapshots()->{$acc['id']} ?? null;
		$since  = $acc['phaseStart'] ? $acc['phaseStart'] : '0000-00-00';
		$profit = $snap ? $snap['balance'] - $acc['size'] : array_sum( array_map( fn( $t ) => (float) $t['pnl'], array_filter( $all, fn( $t ) => $t['date'] >= $since ) ) );

		// Plan et verdict de Claude.
		$plan  = CX_Repo::plan( $week );
		$c     = is_array( $plan['content'] ?? null ) ? $plan['content'] : array();
		$claude = array(
			'published'  => ! empty( $c['prediction'] ),
			'prediction' => $c['prediction'] ?? null,
			'review'     => $c['review'] ?? null,
			'updates'    => count( (array) ( $c['updates'] ?? array() ) ),
			'coaching'   => $c['coaching'] ?? null,
		);

		// Signaux Claude (trades virtuels de l'EA) de la semaine.
		$sig    = array_values( array_filter( CX_Live::list_signals()->get_data(), fn( $s ) => $s['week'] === $week ) );
		$closed = array_values( array_filter( $sig, fn( $s ) => in_array( $s['status'], array( 'gagne', 'perdu', 'be' ), true ) ) );
		$won    = count( array_filter( $closed, fn( $s ) => (float) $s['r'] > 0 ) );

		return array(
			'week'     => $week,
			'end'      => $monday->modify( '+4 days' )->format( 'Y-m-d' ),
			'account'  => array(
				'id'     => $acc['id'],
				'name'   => $acc['name'],
				'status' => $acc['status'],
				'size'   => $acc['size'],
			),
			'totals'   => self::agg( $trades ) + array(
				'best'  => $trades ? max( array_map( fn( $t ) => (float) $t['r'], $trades ) ) : null,
				'worst' => $trades ? min( array_map( fn( $t ) => (float) $t['r'], $trades ) ) : null,
			),
			'progress' => array(
				'pct'    => (float) $pct,
				'target' => round( $target, 2 ),
				'profit' => round( $profit, 2 ),
				'left'   => round( max( 0, $target - $profit ), 2 ),
				'margin' => round( $profit + $acc['size'] * $pa['max'] / 100, 2 ),
				'source' => $snap ? 'mt5' : 'journal',
			),
			'days'     => $days,
			'setups'   => $setups,
			'emotions' => $emotions,
			'discipline' => array(
				'rules'    => count( array_filter( $trades, fn( $t ) => $t['rules'] ) ),
				'onLevel'  => count( array_filter( $trades, fn( $t ) => $t['onLevel'] ) ),
				'withBias' => count( array_filter( $trades, fn( $t ) => $t['withBias'] ) ),
				'streak'   => $streak,
				'overDays' => $over,
				'toFill'   => count( array_filter( $trades, fn( $t ) => $t['toComplete'] ) ),
			),
			'trades'   => array_map(
				fn( $t ) => array_intersect_key( $t, array_flip( array( 'date', 'time', 'dir', 'setup', 'level', 'stop', 'lot', 'r', 'pnl', 'rules', 'onLevel', 'withBias', 'emotion', 'notes' ) ) ),
				$trades
			),
			'claude'   => $claude,
			'signals'  => array(
				'n'      => count( $sig ),
				'closed' => count( $closed ),
				'wr'     => $closed ? $won / count( $closed ) : null,
				'sumR'   => round( array_sum( array_map( fn( $s ) => (float) $s['r'], $closed ) ), 2 ),
				'taken'  => count( array_filter( $sig, fn( $s ) => $s['taken'] ) ),
			),
			'news'     => CX_Calendar::merged( $monday->getTimestamp(), $monday->modify( '+7 days' )->getTimestamp() ),
			'url'      => self::url( $week, $acc['id'] ),
		);
	}

	/**
	 * Adresse de la page imprimable.
	 *
	 * @param string $week Lundi.
	 * @param int    $id   Compte.
	 * @return string
	 */
	public static function url( $week, $id ) {
		return add_query_arg(
			array(
				'cx_report' => $week,
				'account'   => (int) $id,
			),
			home_url( '/' )
		);
	}

	/**
	 * GET /report.
	 *
	 * @param WP_REST_Request $req Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest( WP_REST_Request $req ) {
		$acc = self::account( $req->get_param( 'account' ) );
		if ( ! $acc ) {
			return new WP_Error( 'cx_not_found', 'Aucun compte.', array( 'status' => 404 ) );
		}
		return new WP_REST_Response( self::data( $acc, self::week( $req->get_param( 'week' ) ) ), 200 );
	}

	/**
	 * Nombre au format français.
	 *
	 * @param float|null $v   Valeur.
	 * @param int        $dec Décimales.
	 * @return string
	 */
	private static function nf( $v, $dec = 2 ) {
		return null === $v ? '—' : ( $v < 0 ? '−' : '' ) . number_format( abs( (float) $v ), $dec, ',', ' ' );
	}

	/**
	 * R signé.
	 *
	 * @param float|null $v Valeur.
	 * @return string
	 */
	private static function r( $v ) {
		return null === $v ? '—' : ( $v > 0 ? '+' : '' ) . self::nf( $v, 2 ) . 'R';
	}

	/**
	 * Pourcentage.
	 *
	 * @param float|null $v Fraction.
	 * @return string
	 */
	private static function pct( $v ) {
		return null === $v ? '—' : self::nf( $v * 100, 0 ) . ' %';
	}

	/**
	 * Période lisible.
	 *
	 * @param array $d Données.
	 * @return string
	 */
	public static function period( $d ) {
		$tz = CX_Repo::tz();
		return 'du ' . wp_date( 'j', strtotime( $d['week'] ), $tz ) . ' au ' . wp_date( 'j F Y', strtotime( $d['end'] ), $tz );
	}

	/**
	 * Page imprimable /?cx_report=lundi.
	 */
	public static function page() {
		if ( ! isset( $_GET['cx_report'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( function_exists( 'wpfc_exclude_current_page' ) ) {
			wpfc_exclude_current_page();
		}
		if ( ! CX_Front::can_use() ) {
			status_header( 404 );
			exit;
		}
		$acc = self::account( isset( $_GET['account'] ) ? absint( $_GET['account'] ) : null ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $acc ) {
			status_header( 404 );
			exit;
		}
		$d = self::data( $acc, self::week( sanitize_text_field( wp_unslash( $_GET['cx_report'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo self::html( $d ); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans html().
		exit;
	}

	/**
	 * Tableau HTML simple.
	 *
	 * @param array $head Titres.
	 * @param array $rows Lignes (texte brut, échappé ici).
	 * @return string
	 */
	private static function table( $head, $rows ) {
		$h = '<table><thead><tr>' . implode( '', array_map( fn( $x ) => '<th>' . esc_html( $x ) . '</th>', $head ) ) . '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$h .= '<tr>' . implode( '', array_map( fn( $x ) => '<td>' . esc_html( (string) $x ) . '</td>', $r ) ) . '</tr>';
		}
		return $h . '</tbody></table>';
	}

	/**
	 * Liste à puces.
	 *
	 * @param array $items Textes.
	 * @return string
	 */
	private static function ul( $items ) {
		$items = array_filter( array_map( 'strval', (array) $items ) );
		return $items ? '<ul>' . implode( '', array_map( fn( $x ) => '<li>' . esc_html( $x ) . '</li>', $items ) ) . '</ul>' : '';
	}

	/**
	 * Page du rapport.
	 *
	 * @param array $d Données.
	 * @return string
	 */
	public static function html( $d ) {
		$t      = $d['totals'];
		$p      = $d['progress'];
		$q      = $d['discipline'];
		$n      = $t['n'];
		$status = array(
			'phase1'  => 'Phase 1',
			'phase2'  => 'Phase 2',
			'finance' => 'Compte financé',
			'echoue'  => 'Échoué',
			'retire'  => 'Retiré',
		);
		$kpi    = fn( $v, $k ) => '<div class="kpi"><div class="v">' . esc_html( $v ) . '</div><div class="k">' . esc_html( $k ) . '</div></div>';
		$o      = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">';
		$o     .= '<title>Rapport ' . esc_html( $d['week'] ) . ' · ' . esc_html( $d['account']['name'] ) . '</title><style>' . self::css() . '</style></head><body><main>';
		$o     .= '<div class="bar no-print"><button onclick="window.print()">Imprimer / Enregistrer en PDF</button><span>Dans la fenêtre d’impression, choisissez « Enregistrer au format PDF ».</span></div>';
		$o     .= '<header><div class="brand">Carnet XAU</div><h1>Rapport de la semaine ' . esc_html( self::period( $d ) ) . '</h1>';
		$o     .= '<p class="sub">' . esc_html( $d['account']['name'] . ' · ' . ( $status[ $d['account']['status'] ] ?? '' ) . ' · ' . self::nf( $d['account']['size'], 0 ) . ' $ · édité le ' . wp_date( 'j F Y à H:i', null, CX_Repo::tz() ) ) . '</p></header>';

		// Résultats.
		$o .= '<section><h2>Résultats</h2><div class="kpis">' . $kpi( (string) $n, 'Trades' ) . $kpi( self::pct( $t['wr'] ), 'Réussite' ) . $kpi( $n ? self::r( $t['sumR'] ) : '—', 'Total' ) . $kpi( self::r( $t['avgR'] ), 'R moyen' ) . $kpi( self::nf( $t['pnl'] ) . ' $', 'Résultat' ) . $kpi( null === $t['pf'] ? '∞' : self::nf( $t['pf'] ), 'Facteur de profit' ) . '</div>';
		if ( $n ) {
			$o .= '<p>' . esc_html( sprintf( '%d gagnant(s), %d perdant(s). Meilleur trade %s, pire trade %s.', $t['wins'], $t['losses'], self::r( $t['best'] ), self::r( $t['worst'] ) ) ) . '</p>';
		}
		$o .= '<p class="progress">' . esc_html( sprintf( 'Étape : objectif %s %% (%s $), réalisé %s $ (%s), reste %s $. Marge avant la perte maximale : %s $.', self::nf( $p['pct'], 1 ), self::nf( $p['target'] ), self::nf( $p['profit'] ), 'mt5' === $p['source'] ? 'solde MT5' : 'journal', self::nf( $p['left'] ), self::nf( $p['margin'] ) ) ) . '</p></section>';

		// Par jour et par setup.
		$o .= '<section><h2>Jour par jour</h2>' . self::table( array( 'Jour', 'Trades', 'Réussite', 'Total', 'Résultat' ), array_map( fn( $x ) => array( $x['day'] . ' ' . wp_date( 'j/m', strtotime( $x['date'] ), CX_Repo::tz() ), $x['n'], self::pct( $x['wr'] ), $x['n'] ? self::r( $x['sumR'] ) : '—', $x['n'] ? self::nf( $x['pnl'] ) . ' $' : '—' ), $d['days'] ) );
		if ( $d['setups'] ) {
			$o .= '<h3>Par setup</h3>' . self::table( array( 'Setup', 'Trades', 'Réussite', 'R moyen', 'Total' ), array_map( fn( $x ) => array( $x['label'], $x['n'], self::pct( $x['wr'] ), self::r( $x['avgR'] ), self::r( $x['sumR'] ) ), $d['setups'] ) );
		}
		$o .= '</section>';

		// Discipline.
		$o .= '<section><h2>Discipline</h2>';
		if ( $n ) {
			$o .= self::ul(
				array(
					sprintf( 'Règles respectées : %d sur %d trades.', $q['rules'], $n ),
					sprintf( 'Sur un niveau clé : %d sur %d ; avec le biais : %d sur %d.', $q['onLevel'], $n, $q['withBias'], $n ),
					sprintf( 'Plus longue série de pertes : %d.', $q['streak'] ),
					$q['overDays'] ? 'Limite de trades ou de pertes du jour dépassée : ' . implode( ', ', $q['overDays'] ) . '.' : 'Limites du jour respectées chaque jour.',
					$q['toFill'] ? sprintf( '%d trade(s) MT5 encore à compléter dans le journal.', $q['toFill'] ) : '',
				)
			);
			if ( $d['emotions'] ) {
				$o .= '<h3>Émotions</h3>' . self::table( array( 'Émotion', 'Trades', 'Réussite', 'Total' ), array_map( fn( $x ) => array( $x['label'], $x['n'], self::pct( $x['wr'] ), self::r( $x['sumR'] ) ), $d['emotions'] ) );
			}
		} else {
			$o .= '<p class="muted">Aucun trade cette semaine.</p>';
		}
		$o .= '</section>';

		// Analyse du journal par Claude.
		$co = $d['claude']['coaching'];
		$o .= '<section><h2>Analyse du journal par Claude</h2>';
		if ( $co && ( ! empty( $co['summary'] ) || ! empty( $co['actions'] ) ) ) {
			if ( isset( $co['discipline'] ) && null !== $co['discipline'] && '' !== $co['discipline'] ) {
				$o .= '<p class="score">Discipline : <b>' . esc_html( self::nf( (float) $co['discipline'], 1 ) ) . '/10</b></p>';
			}
			$o .= ! empty( $co['summary'] ) ? '<p>' . nl2br( esc_html( $co['summary'] ) ) . '</p>' : '';
			$o .= ! empty( $co['strengths'] ) ? '<h3>Points forts</h3>' . self::ul( $co['strengths'] ) : '';
			$o .= ! empty( $co['weaknesses'] ) ? '<h3>À corriger</h3>' . self::ul( $co['weaknesses'] ) : '';
			$o .= ! empty( $co['actions'] ) ? '<h3>Pour la semaine prochaine</h3>' . self::ul( $co['actions'] ) : '';
		} else {
			$o .= '<p class="muted">Pas encore d’analyse : Claude la publie avec le bilan de la semaine.</p>';
		}
		$o .= '</section>';

		// Plan de Claude.
		$cl = $d['claude'];
		$o .= '<section><h2>Plan de Claude</h2>';
		if ( $cl['published'] ) {
			$pr = $cl['prediction'];
			$o .= '<p>' . esc_html( sprintf( 'Prévision : haussier %d %%, baissier %d %%, range %d %%. %s', $pr['bull'] ?? 0, $pr['bear'] ?? 0, $pr['range'] ?? 0, $pr['summary'] ?? '' ) ) . '</p>';
			if ( $cl['updates'] ) {
				$o .= '<p class="muted">' . esc_html( $cl['updates'] . ' relecture(s) quotidienne(s).' ) . '</p>';
			}
		}
		$rv = $cl['review'];
		if ( $rv && ! empty( $rv['verdict'] ) ) {
			$vl  = array(
				'bonne'     => 'Bonne',
				'partielle' => 'Partielle',
				'mauvaise'  => 'Mauvaise',
			);
			$o  .= '<p class="score">Verdict : <b>' . esc_html( ( $vl[ $rv['verdict'] ] ?? $rv['verdict'] ) . ( isset( $rv['score'] ) && '' !== $rv['score'] && null !== $rv['score'] ? ' · ' . self::nf( (float) $rv['score'], 1 ) . '/10' : '' ) ) . '</b></p>';
			$o  .= ! empty( $rv['text'] ) ? '<p>' . nl2br( esc_html( $rv['text'] ) ) . '</p>' : '';
			$o  .= ! empty( $rv['lesson'] ) ? '<p><b>Leçon :</b> ' . esc_html( $rv['lesson'] ) . '</p>' : '';
		} elseif ( ! $cl['published'] ) {
			$o .= '<p class="muted">Aucun plan publié pour cette semaine.</p>';
		} else {
			$o .= '<p class="muted">Verdict pas encore publié.</p>';
		}
		$s  = $d['signals'];
		$o .= '<h3>Signaux Claude (EA MT5)</h3><p>' . esc_html( $s['n'] ? sprintf( '%d signal(s), %d clôturé(s), réussite %s, total %s, %d pris par vous.', $s['n'], $s['closed'], self::pct( $s['wr'] ), self::r( $s['sumR'] ), $s['taken'] ) : 'Aucun signal cette semaine.' ) . '</p></section>';

		// Annonces.
		$o .= '<section><h2>Annonces économiques USD</h2>';
		$o .= $d['news'] ? self::table( array( 'Jour', 'Heure', 'Impact', 'Annonce' ), array_map( fn( $e ) => array( ucfirst( wp_date( 'l j', $e['t'], CX_Repo::tz() ) ), $e['time'], 'high' === $e['impact'] ? 'Fort' : 'Moyen', $e['name'] . ( $e['fp'] ? ' (' . $e['fp'] . ')' : '' ) ), $d['news'] ) ) : '<p class="muted">Aucune annonce enregistrée.</p>';
		$o .= '</section>';

		// Trades.
		if ( $d['trades'] ) {
			$o .= '<section class="break"><h2>Trades de la semaine</h2>' . self::table(
				array( 'Date', 'Sens', 'Setup', 'Niveau', 'Stop', 'Lot', 'R', 'Résultat', 'Règles', 'Émotion' ),
				array_map(
					fn( $x ) => array(
						wp_date( 'D j', strtotime( $x['date'] ), CX_Repo::tz() ) . ( $x['time'] ? ' ' . $x['time'] : '' ),
						'achat' === $x['dir'] ? 'Achat' : 'Vente',
						'hors' === $x['setup'] ? 'Hors' : ( $x['setup'] ? $x['setup'] : '—' ),
						$x['level'] ? $x['level'] : '—',
						null === $x['stop'] ? '—' : self::nf( $x['stop'] ) . ' $',
						null === $x['lot'] ? '—' : self::nf( $x['lot'] ),
						self::r( $x['r'] ),
						null === $x['pnl'] ? '—' : self::nf( $x['pnl'] ) . ' $',
						$x['rules'] ? 'Oui' : 'Non',
						self::EMO[ $x['emotion'] ] ?? '—',
					),
					$d['trades']
				)
			) . '</section>';
		}
		return $o . '<footer>Carnet XAU · rapport généré automatiquement · heures de Lubumbashi</footer></main></body></html>';
	}

	/**
	 * Styles de la page (écran et impression A4).
	 *
	 * @return string
	 */
	private static function css() {
		return 'body{margin:0;background:#E9EDF1;color:#1B2430;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
			. 'main{max-width:860px;margin:0 auto;padding:16px}'
			. '.bar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px;font-size:13px;color:#5B6675}'
			. '.bar button{background:#A87A1E;color:#fff;border:0;border-radius:8px;padding:9px 16px;font:600 14px system-ui;cursor:pointer}'
			. 'header,section{background:#fff;border-radius:12px;padding:16px 20px;margin-bottom:12px;border:1px solid #D9DFE6}'
			. '.brand{font-weight:800;color:#A87A1E;letter-spacing:.02em}h1{font-size:22px;margin:4px 0}h2{font-size:17px;margin:0 0 10px}h3{font-size:14px;margin:14px 0 6px}'
			. '.sub,.muted{color:#5B6675}.sub{margin:0;font-size:13.5px}p{margin:6px 0}ul{margin:6px 0;padding-left:20px}'
			. '.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:8px}.kpi{border:1px solid #D9DFE6;border-radius:10px;padding:8px 12px}'
			. '.kpi .v{font-size:20px;font-weight:700;font-variant-numeric:tabular-nums}.kpi .k{font-size:12.5px;color:#5B6675}'
			. '.progress{background:#F4E7C8;color:#5C410A;border-radius:8px;padding:8px 12px}.score b{color:#A87A1E}'
			. 'table{width:100%;border-collapse:collapse;font-size:13.5px;font-variant-numeric:tabular-nums}th,td{text-align:left;padding:5px 6px;border-bottom:1px solid #E3E8EE}th{font-size:12px;color:#5B6675;font-weight:600}'
			. 'section{overflow-x:auto}footer{text-align:center;color:#5B6675;font-size:12px;padding:8px}'
			. '@media (max-width:560px){.kpis{grid-template-columns:repeat(2,1fr)}h1{font-size:19px}}'
			. '@media print{@page{size:A4;margin:14mm}body{background:#fff;font-size:12.5px}main{max-width:none;padding:0}.no-print{display:none}header,section{border:0;border-bottom:1px solid #D9DFE6;border-radius:0;padding:8px 0;margin:0 0 6px;break-inside:avoid}.break{break-before:page}section{overflow:visible}}';
	}

	/**
	 * Résumé Telegram de la semaine.
	 *
	 * @param array $d Données.
	 * @return string
	 */
	public static function summary( $d ) {
		$t     = $d['totals'];
		$lines = array( '📊 <b>Rapport de la semaine</b> ' . esc_html( self::period( $d ) ), esc_html( $d['account']['name'] ) );
		if ( $t['n'] ) {
			$lines[] = sprintf( 'Trades : %d · réussite %s · total %s · %s $', $t['n'], self::pct( $t['wr'] ), self::r( $t['sumR'] ), self::nf( $t['pnl'] ) );
			$lines[] = sprintf( 'Règles respectées : %d/%d · série de pertes max : %d', $d['discipline']['rules'], $t['n'], $d['discipline']['streak'] );
		} else {
			$lines[] = 'Aucun trade cette semaine.';
		}
		$lines[] = sprintf( 'Étape : %s $ sur %s $ (reste %s $)', self::nf( $d['progress']['profit'] ), self::nf( $d['progress']['target'] ), self::nf( $d['progress']['left'] ) );
		if ( $d['signals']['closed'] ) {
			$lines[] = sprintf( 'Signaux Claude : %d clôturés · réussite %s · %s', $d['signals']['closed'], self::pct( $d['signals']['wr'] ), self::r( $d['signals']['sumR'] ) );
		}
		$co = $d['claude']['coaching'];
		if ( $co && ! empty( $co['actions'] ) ) {
			$lines[] = '🎯 ' . esc_html( (string) $co['actions'][0] );
		}
		$lines[] = '<a href="' . esc_url( $d['url'] ) . '">Ouvrir le rapport complet (PDF)</a>';
		return implode( "\n", $lines );
	}

	/**
	 * Envoi du samedi, à partir de 9 h (Lubumbashi), une fois par semaine.
	 *
	 * @param DateTimeImmutable|null $now Instant (par défaut maintenant).
	 */
	public static function weekly( $now = null ) {
		$now = $now instanceof DateTimeImmutable ? $now : new DateTimeImmutable( 'now', CX_Repo::tz() );
		$c   = CX_Telegram::cfg();
		if ( empty( $c['chat'] ) || empty( $c['alerts']['report'] ) || 6 !== (int) $now->format( 'N' ) || (int) $now->format( 'G' ) < 9 ) {
			return;
		}
		$acc  = CX_Telegram::active();
		$week = CX_Telegram::monday( $now );
		if ( $acc && CX_Guard::once( 'report|' . $week ) ) {
			CX_Telegram::send( self::summary( self::data( $acc, $week ) ) );
		}
	}
}
