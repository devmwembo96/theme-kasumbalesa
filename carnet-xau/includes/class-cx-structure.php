<?php
/**
 * Structures du marché calculées par le carnet, à partir des bougies du compte de référence (VPS) :
 * Claude ne devine plus les BOS, FVG ou OB dans les bougies brutes, il cite ces objets.
 *
 * Portage fidèle des détecteurs du labo de recherche (Python, engine/objects.py, testés contre le regard
 * vers l'avenir), contrôlé par un test de parité sur les mêmes bougies (tests/structure-parity.py) :
 * - pivots (swings) : haut en i si high[i] > les k bougies d'avant et >= les k d'après ; connu à i+k ;
 * - BOS / CHoCH : CLÔTURE au-delà du dernier pivot confirmé non encore cassé (une mèche ne suffit pas) ;
 * - déplacement : 1 à 3 bougies de même couleur, corps >= 60 % de l'étendue, corps cumulé >= 1,5 ATR ;
 * - FVG : trou entre la bougie i-2 et la bougie i, bougie centrale de déplacement, taille >= 0,25 ATR ;
 *   états mitigé, milieu (CE), comblé, invalidé (clôture au-delà) -> iFVG de sens opposé ;
 * - order block : dernière bougie opposée (10 bougies au plus) avant un BOS/CHoCH accompagné d'un
 *   déplacement ; zone open–low (achat) ou high–open (vente) ; mitigé, invalidé -> breaker, expiré ;
 * - EQH / EQL : deux pivots à 0,1 ATR près, 5 bougies d'écart au moins, rien entre eux au-delà ;
 * - sweep : mèche au-delà d'un niveau (pivot, égaux, PDH/PDL, Asie) puis clôture de retour en 3 bougies,
 *   pénétration >= 0,05 ATR et < 1 ATR ; sinon « cassure » ;
 * - niveaux de séance : Asie (18:00 New York → 07:00 Londres), Londres (07:00 Londres → 08:15 New York),
 *   jour serveur (PDH/PDL, calé sur les bougies D1 du courtier), semaine (PWH/PWL) ;
 * - premium / discount : position du prix dans le dernier range de swing H4 et dans la veille.
 * ATR de Wilder (14) connu à l'ouverture de la bougie. Seules les bougies CLOSES servent ; la bougie en
 * cours est rendue à part (`forming`). Chaque objet a un identifiant stable « UT:type:heure d'origine ».
 *
 * GET /structures : structures de D1, H4, H1, M15 et M5, état des données.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_Structure {

	/** Durée d'une bougie (s). */
	const TF = array(
		'D1'  => 86400,
		'H4'  => 14400,
		'H1'  => 3600,
		'M15' => 900,
		'M5'  => 300,
	);

	/** Réglages des détecteurs (valeurs par défaut du labo). */
	const P = array(
		'atr'        => 14,
		'k'          => 2,
		'eq_tol'     => 0.1,
		'eq_min'     => 5,
		'eq_prior'   => 3,
		'eq_look'    => 200,
		'sw_dmin'    => 0.05,
		'sw_dmax'    => 1.0,
		'sw_ret'     => 3,
		'disp_m'     => 1.5,
		'disp_r'     => 0.6,
		'disp_len'   => 3,
		'fvg_min'    => 0.25,
		'ob_leg'     => 10,
		'ob_age'     => 288,
	);

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			CX_Rest::NS,
			'/structures',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => array( 'CX_Rest', 'can' ),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Outils
	 * ------------------------------------------------------------------ */

	/**
	 * ATR de Wilder ; atr[i] dépend des bougies 0..i ; null pendant le préchauffage.
	 *
	 * @param array $b Bougies.
	 * @param int   $p Période.
	 * @return array
	 */
	public static function atr( $b, $p ) {
		$n   = count( $b );
		$out = array_fill( 0, $n, null );
		if ( $n < $p ) {
			return $out;
		}
		$tr = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$tr[ $i ] = $i ? max( $b[ $i ]['h'] - $b[ $i ]['l'], abs( $b[ $i ]['h'] - $b[ $i - 1 ]['c'] ), abs( $b[ $i ]['l'] - $b[ $i - 1 ]['c'] ) ) : $b[ $i ]['h'] - $b[ $i ]['l'];
		}
		$a           = array_sum( array_slice( $tr, 0, $p ) ) / $p;
		$out[ $p - 1 ] = $a;
		for ( $i = $p; $i < $n; $i++ ) {
			$a         = ( $a * ( $p - 1 ) + $tr[ $i ] ) / $p;
			$out[ $i ] = $a;
		}
		return $out;
	}

	/** Premier j >= $start tel que $x[j] > $v (max) ou < $v (min) ; non strict : >= / <= ; -1 sinon. */
	private static function first( $x, $start, $v, $max, $strict ) {
		$n = count( $x );
		for ( $j = max( 0, $start ); $j < $n; $j++ ) {
			if ( $max ? ( $strict ? $x[ $j ] > $v : $x[ $j ] >= $v ) : ( $strict ? $x[ $j ] < $v : $x[ $j ] <= $v ) ) {
				return $j;
			}
		}
		return -1;
	}

	/** Événement standard. */
	private static function ev( $b, $type, $dir, $io, $ic, $price, $top, $bot, $extra = array(), $tconf = null ) {
		return array(
			'type'      => $type,
			'dir'       => (int) $dir,
			'io'        => (int) $io,
			'ic'        => (int) $ic,
			't_origin'  => $b[ $io ]['t'],
			't_confirm' => null !== $tconf ? (int) $tconf : $b[ $ic ]['tc'],
			'price'     => round( (float) $price, 2 ),
			'top'       => round( (float) $top, 2 ),
			'bottom'    => round( (float) $bot, 2 ),
		) + $extra;
	}

	/* ------------------------------------------------------------------
	 * Détecteurs (même logique que engine/objects.py)
	 * ------------------------------------------------------------------ */

	public static function pivots( $b, $k ) {
		$n   = count( $b );
		$out = array();
		for ( $i = $k; $i + $k < $n; $i++ ) {
			$lh = -INF;
			$ll = INF;
			$rh = -INF;
			$rl = INF;
			for ( $r = 1; $r <= $k; $r++ ) {
				$lh = max( $lh, $b[ $i - $r ]['h'] );
				$ll = min( $ll, $b[ $i - $r ]['l'] );
				$rh = max( $rh, $b[ $i + $r ]['h'] );
				$rl = min( $rl, $b[ $i + $r ]['l'] );
			}
			if ( $b[ $i ]['h'] > $lh && $b[ $i ]['h'] >= $rh ) {
				$out[] = self::ev( $b, 'pivot_haut', 1, $i, $i + $k, $b[ $i ]['h'], $b[ $i ]['h'], $b[ $i ]['h'] );
			}
			if ( $b[ $i ]['l'] < $ll && $b[ $i ]['l'] <= $rl ) {
				$out[] = self::ev( $b, 'pivot_bas', -1, $i, $i + $k, $b[ $i ]['l'], $b[ $i ]['l'], $b[ $i ]['l'] );
			}
		}
		return $out;
	}

	public static function equal_levels( $b, $piv, $aref ) {
		$hi  = array_column( $b, 'h' );
		$lo  = array_column( $b, 'l' );
		$out = array();
		foreach ( array( array( 'egaux_hauts', 'pivot_haut', 1 ), array( 'egaux_bas', 'pivot_bas', -1 ) ) as list( $typ, $src, $d ) ) {
			$s = array_values( array_filter( $piv, fn( $e ) => $e['type'] === $src ) );
			usort( $s, fn( $x, $y ) => $x['io'] <=> $y['io'] );
			$m = count( $s );
			for ( $kk = 1; $kk <= self::P['eq_prior']; $kk++ ) {
				for ( $a = 0; $a + $kk < $m; $a++ ) {
					$pa  = $s[ $a ];
					$pb  = $s[ $a + $kk ];
					$at  = $aref[ $pb['ic'] ];
					$gap = $pb['io'] - $pa['io'];
					if ( null === $at || abs( $pa['price'] - $pb['price'] ) > self::P['eq_tol'] * $at || $gap < self::P['eq_min'] || $gap > self::P['eq_look'] ) {
						continue;
					}
					$lvl = 1 === $d ? max( $pa['price'], $pb['price'] ) : min( $pa['price'], $pb['price'] );
					$j   = 1 === $d ? self::first( $hi, $pa['io'] + 1, $lvl, true, true ) : self::first( $lo, $pa['io'] + 1, $lvl, false, true );
					if ( -1 !== $j && $j < $pb['io'] ) {
						continue;
					}
					$out[] = self::ev( $b, $typ, $d, $pa['io'], $pb['ic'], $lvl, max( $pa['price'], $pb['price'] ), min( $pa['price'], $pb['price'] ) );
				}
			}
		}
		return $out;
	}

	/**
	 * Balayages des niveaux [{price, side, t_confirm, source, id}] ; dédoublonnés (un par bougie et par sens).
	 */
	public static function sweeps( $b, $levels, $aref ) {
		$n   = count( $b );
		$t   = array_column( $b, 't' );
		$out = array();
		foreach ( $levels as $lv ) {
			$L     = $lv['price'];
			$side  = $lv['side'];
			$start = $n;
			foreach ( $t as $i => $ti ) {
				if ( $ti >= $lv['t_confirm'] ) {
					$start = $i;
					break;
				}
			}
			$j = -1;
			for ( $x = $start; $x < $n; $x++ ) {
				if ( 1 === $side ? $b[ $x ]['h'] > $L : $b[ $x ]['l'] < $L ) {
					$j = $x;
					break;
				}
			}
			if ( $j < 0 ) {
				continue;
			}
			$a  = $aref[ $j ];
			$cb = -1;
			$xb = -1;
			for ( $r = 0; $r < self::P['sw_ret']; $r++ ) {
				$q = $j + $r;
				if ( $q >= $n ) {
					break;
				}
				if ( $cb < 0 && ( 1 === $side ? $b[ $q ]['c'] <= $L : $b[ $q ]['c'] >= $L ) ) {
					$cb = $q;
				}
				if ( $xb < 0 && null !== $a && ( 1 === $side ? $b[ $q ]['h'] > $L + self::P['sw_dmax'] * $a : $b[ $q ]['l'] < $L - self::P['sw_dmax'] * $a ) ) {
					$xb = $q;
				}
			}
			$is_c    = $cb >= 0;
			$x_first = $xb >= 0 && ( ! $is_c || $xb <= $cb );
			$end     = $j + self::P['sw_ret'] - 1;
			$conf    = $is_c && ! $x_first ? $cb : ( $x_first ? $xb : ( $end < $n ? $end : -1 ) );
			if ( $conf < 0 ) {
				continue;
			}
			$ext = 1 === $side ? -INF : INF;
			for ( $q = $j; $q <= $conf; $q++ ) {
				$ext = 1 === $side ? max( $ext, $b[ $q ]['h'] ) : min( $ext, $b[ $q ]['l'] );
			}
			$pen = 1 === $side ? $ext - $L : $L - $ext;
			$typ = 'cassure';
			if ( null !== $a && $is_c && ! $x_first ) {
				$typ = $pen >= self::P['sw_dmin'] * $a ? 'sweep' : 'depassement_faible';
			}
			$out[] = self::ev(
				$b,
				$typ,
				'cassure' === $typ ? $side : -$side,
				$j,
				$conf,
				$L,
				1 === $side ? $ext : $L,
				1 === $side ? $L : $ext,
				array(
					'extreme' => round( $ext, 2 ),
					'source'  => $lv['source'],
					'level'   => $lv['id'],
				)
			);
		}
		// Un seul sweep par bougie de décision et par sens : le niveau le plus extrême.
		$keep = array();
		$rest = array();
		foreach ( $out as $e ) {
			if ( 'sweep' !== $e['type'] ) {
				$rest[] = $e;
				continue;
			}
			$k = $e['ic'] . '|' . $e['dir'];
			$e['n_niveaux'] = ( $keep[ $k ]['n_niveaux'] ?? 0 ) + 1;
			// Haut balayé (dir −1) : le plus haut ; bas balayé : le plus bas.
			if ( ! isset( $keep[ $k ] ) || ( -1 === $e['dir'] ? $e['price'] > $keep[ $k ]['price'] : $e['price'] < $keep[ $k ]['price'] ) ) {
				$keep[ $k ] = $e;
			} else {
				$keep[ $k ]['n_niveaux'] = $e['n_niveaux'];
			}
		}
		return array_merge( array_values( $keep ), $rest );
	}

	public static function displacement( $b, $aref ) {
		$n     = count( $b );
		$found = array_fill( 0, $n, 0 );
		$okb   = array();
		$col   = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$rng       = $b[ $i ]['h'] - $b[ $i ]['l'];
			$body      = abs( $b[ $i ]['c'] - $b[ $i ]['o'] );
			$col[ $i ] = $b[ $i ]['c'] <=> $b[ $i ]['o'];
			$okb[ $i ] = $rng > 0 && $body >= self::P['disp_r'] * $rng && 0 !== $col[ $i ];
		}
		for ( $L = 1; $L <= self::P['disp_len']; $L++ ) {
			for ( $i = $L - 1; $i < $n; $i++ ) {
				if ( $found[ $i ] ) {
					continue;
				}
				$cum  = 0.0;
				$good = true;
				for ( $r = 0; $r < $L; $r++ ) {
					$good = $good && $okb[ $i - $r ] && $col[ $i - $r ] === $col[ $i ];
					$cum += abs( $b[ $i - $r ]['c'] - $b[ $i - $r ]['o'] );
				}
				$a0 = $aref[ $i - $L + 1 ];
				if ( $good && null !== $a0 && $cum >= self::P['disp_m'] * $a0 ) {
					$found[ $i ] = $L;
				}
			}
		}
		$out = array();
		foreach ( $found as $i => $L ) {
			if ( ! $L ) {
				continue;
			}
			$top = -INF;
			$bot = INF;
			for ( $r = 0; $r < $L; $r++ ) {
				$top = max( $top, $b[ $i - $r ]['h'] );
				$bot = min( $bot, $b[ $i - $r ]['l'] );
			}
			$out[] = self::ev( $b, 'deplacement', $col[ $i ], $i - $L + 1, $i, $b[ $i ]['c'], $top, $bot, array( 'n_bougies' => $L ) );
		}
		return $out;
	}

	/** BOS / CHoCH à la clôture ; renvoie [événements, tendance finale]. */
	public static function structure( $b, $piv ) {
		$n       = count( $b );
		$avail_h = array();
		$avail_l = array();
		$sorted  = $piv;
		usort( $sorted, fn( $x, $y ) => array( $x['ic'], $x['io'] ) <=> array( $y['ic'], $y['io'] ) );
		foreach ( $sorted as $p ) {
			if ( 'pivot_haut' === $p['type'] ) {
				$avail_h[ $p['ic'] + 1 ] = $p;
			} else {
				$avail_l[ $p['ic'] + 1 ] = $p;
			}
		}
		$rh    = null;
		$rl    = null;
		$trend = 0;
		$out   = array();
		for ( $j = 0; $j < $n; $j++ ) {
			if ( isset( $avail_h[ $j ] ) ) {
				$rh = $avail_h[ $j ];
			}
			if ( isset( $avail_l[ $j ] ) ) {
				$rl = $avail_l[ $j ];
			}
			$bu    = null !== $rh && $b[ $j ]['c'] > $rh['price'];
			$bd    = null !== $rl && $b[ $j ]['c'] < $rl['price'];
			$order = ( $bu && $bd ) ? ( $b[ $j ]['c'] < $b[ $j ]['o'] ? array( 'dn', 'up' ) : array( 'up', 'dn' ) ) : ( $bu ? array( 'up' ) : ( $bd ? array( 'dn' ) : array() ) );
			foreach ( $order as $sd ) {
				$ref   = 'up' === $sd ? $rh : $rl;
				$d     = 'up' === $sd ? 1 : -1;
				$out[] = self::ev( $b, $trend === -$d ? 'choch' : 'bos', $d, $ref['io'], $j, $ref['price'], $b[ $j ]['h'], $b[ $j ]['l'], array( 'tendance_avant' => $trend ) );
				$trend = $d;
				if ( 'up' === $sd ) {
					$rh = null;
				} else {
					$rl = null;
				}
			}
		}
		return array( $out, $trend );
	}

	public static function fvg( $b, $aref ) {
		$n   = count( $b );
		$out = array();
		if ( $n < 3 ) {
			return $out;
		}
		$h  = array_column( $b, 'h' );
		$l  = array_column( $b, 'l' );
		$c  = array_column( $b, 'c' );
		for ( $i = 2; $i < $n; $i++ ) {
			$m    = $b[ $i - 1 ];
			$rng  = $m['h'] - $m['l'];
			$body = abs( $m['c'] - $m['o'] );
			$disp = $rng > 0 && $body >= self::P['disp_r'] * $rng && null !== $aref[ $i - 1 ] && $body >= self::P['disp_m'] * $aref[ $i - 1 ];
			foreach ( array( 1, -1 ) as $d ) {
				list( $bot, $top ) = 1 === $d ? array( $h[ $i - 2 ], $l[ $i ] ) : array( $h[ $i ], $l[ $i - 2 ] );
				$size = $top - $bot;
				if ( ! ( $size > 0 ) || null === $aref[ $i ] || $size < self::P['fvg_min'] * $aref[ $i ] || ( $m['c'] <=> $m['o'] ) !== $d || ! $disp ) {
					continue;
				}
				$mid   = ( $bot + $top ) / 2;
				$e     = self::ev( $b, 'fvg', $d, $i - 1, $i, $mid, $top, $bot, array( 'taille_atr' => round( $size / $aref[ $i ], 2 ) ) );
				$state = array();
				foreach ( array( 'fvg_mitige' => 1 === $d ? $top : $bot, 'fvg_ce' => $mid, 'fvg_comble' => 1 === $d ? $bot : $top ) as $typ => $lvl ) {
					$j = 1 === $d ? self::first( $l, $i + 1, $lvl, false, false ) : self::first( $h, $i + 1, $lvl, true, false );
					if ( $j >= 0 ) {
						$state[ $typ ] = self::ev( $b, $typ, $d, $i - 1, $j, $lvl, $top, $bot );
					}
				}
				$ji = 1 === $d ? self::first( $c, $i + 1, $bot, false, true ) : self::first( $c, $i + 1, $top, true, true );
				if ( $ji >= 0 ) {
					$state['fvg_invalide'] = self::ev( $b, 'fvg_invalide', $d, $i - 1, $ji, $c[ $ji ], $top, $bot );
					$state['ifvg']         = self::ev( $b, 'ifvg', -$d, $i - 1, $ji, $mid, $top, $bot );
				}
				$e['states'] = $state;
				$out[]       = $e;
			}
		}
		return $out;
	}

	public static function order_blocks( $b, $struct, $disp ) {
		$n   = count( $b );
		$h   = array_column( $b, 'h' );
		$l   = array_column( $b, 'l' );
		$c   = array_column( $b, 'c' );
		$out = array();
		foreach ( array( 1, -1 ) as $d ) {
			$st = array_values( array_filter( $struct, fn( $e ) => $e['dir'] === $d ) );
			usort( $st, fn( $x, $y ) => $x['ic'] <=> $y['ic'] );
			$dis  = array_values( array_map( fn( $e ) => $e['ic'], array_filter( $disp, fn( $e ) => $e['dir'] === $d ) ) );
			sort( $dis );
			$seen = array();
			foreach ( $st as $s ) {
				$bb   = $s['ic'];
				$cand = null;
				for ( $j = $bb - 1; $j > max( -1, $bb - 1 - self::P['ob_leg'] ); $j-- ) {
					if ( ( 1 === $d && $b[ $j ]['c'] < $b[ $j ]['o'] ) || ( -1 === $d && $b[ $j ]['c'] > $b[ $j ]['o'] ) ) {
						$cand = $j;
						break;
					}
				}
				if ( null === $cand || isset( $seen[ $cand ] ) ) {
					continue;
				}
				$ok = false;
				foreach ( $dis as $x ) {
					if ( $x >= $cand + 1 ) {
						$ok = $x <= $bb;
						break;
					}
				}
				if ( ! $ok ) {
					continue;
				}
				$seen[ $cand ] = true;
				// Zone open–low (achat : bougie baissière) ou high–open (vente).
				list( $top, $bot ) = 1 === $d ? array( $b[ $cand ]['o'], $b[ $cand ]['l'] ) : array( $b[ $cand ]['h'], $b[ $cand ]['o'] );
				$mid = ( $top + $bot ) / 2;
				$e   = self::ev( $b, 'ob', $d, $cand, $bb, $mid, $top, $bot );
				$lim = $bb + self::P['ob_age'];
				$jm  = 1 === $d ? self::first( $l, $bb + 1, $top, false, false ) : self::first( $h, $bb + 1, $bot, true, false );
				$jv  = 1 === $d ? self::first( $c, $bb + 1, $bot, false, true ) : self::first( $c, $bb + 1, $top, true, true );
				$jm  = ( $jm >= 0 && $jm <= $lim ) ? $jm : -1;
				$jv  = ( $jv >= 0 && $jv <= $lim ) ? $jv : -1;
				$state = array();
				if ( $jm >= 0 ) {
					$state['ob_mitige'] = self::ev( $b, 'ob_mitige', $d, $cand, $jm, $mid, $top, $bot );
				}
				if ( $jm < 0 && $jv < 0 && $lim < $n ) {
					$state['ob_expire'] = self::ev( $b, 'ob_expire', $d, $cand, $lim, $mid, $top, $bot );
				}
				if ( $jv >= 0 ) {
					$state['ob_invalide'] = self::ev( $b, 'ob_invalide', $d, $cand, $jv, $c[ $jv ], $top, $bot );
					$state['breaker']     = self::ev( $b, 'breaker', -$d, $cand, $jv, $mid, $top, $bot );
					$jr                   = 1 === $d ? self::first( $h, $jv + 1, $bot, true, false ) : self::first( $l, $jv + 1, $top, false, false );
					$jx                   = 1 === $d ? self::first( $c, $jv + 1, $top, true, true ) : self::first( $c, $jv + 1, $bot, false, true );
					if ( $jr >= 0 ) {
						$state['breaker_retest'] = self::ev( $b, 'breaker_retest', -$d, $cand, $jr, $mid, $top, $bot );
					}
					if ( $jx >= 0 ) {
						$state['breaker_invalide'] = self::ev( $b, 'breaker_invalide', -$d, $cand, $jx, $c[ $jx ], $top, $bot );
					}
				}
				$e['states'] = $state;
				$out[]       = $e;
			}
		}
		return $out;
	}

	/**
	 * Haut et bas des bougies ouvertes dans chaque fenêtre [s, e) (une par jour serveur), confirmés à la
	 * clôture de la dernière bougie si elle atteint e, sinon à la clôture de la bougie suivante.
	 *
	 * @param array  $b     Bougies.
	 * @param array  $wins  [[s, e, jour], …] en Unix.
	 * @param string $name  Préfixe des types.
	 * @return array
	 */
	public static function window_levels( $b, $wins, $name ) {
		$n   = count( $b );
		$out = array();
		foreach ( $wins as list( $s, $e, $day ) ) {
			$hi = null;
			$lo = null;
			$last = -1;
			$i0   = -1;
			for ( $i = 0; $i < $n; $i++ ) {
				if ( $b[ $i ]['t'] < $s || $b[ $i ]['t'] >= $e ) {
					continue;
				}
				if ( $i0 < 0 ) {
					$i0 = $i;
				}
				if ( null === $hi || $b[ $i ]['h'] > $b[ $hi ]['h'] ) {
					$hi = $i;
				}
				if ( null === $lo || $b[ $i ]['l'] < $b[ $lo ]['l'] ) {
					$lo = $i;
				}
				$last = $i;
			}
			if ( $last < 0 ) {
				continue;
			}
			if ( $b[ $last ]['tc'] >= $e ) {
				$tconf = $b[ $last ]['tc'];
			} elseif ( $last + 1 < $n ) {
				$tconf = $b[ $last + 1 ]['tc'];
			} else {
				continue;
			}
			$x     = array( 'jour' => $day, 't_debut' => $s, 't_fin' => $e );
			$out[] = self::ev( $b, $name . '_haut', 1, $hi, $last, $b[ $hi ]['h'], $b[ $hi ]['h'], $b[ $lo ]['l'], $x, $tconf );
			$out[] = self::ev( $b, $name . '_bas', -1, $lo, $last, $b[ $lo ]['l'], $b[ $hi ]['h'], $b[ $lo ]['l'], $x, $tconf );
		}
		return $out;
	}

	/**
	 * Ouvertures des bougies D1 du courtier (Unix) : elles fixent le début réel du jour serveur (le serveur
	 * FundedNext suit les changements d'heure européens : 17:00 ou 18:00 New York selon la semaine). Vide :
	 * règle fixe « New York + 7 h » (celle du labo, pour le test de parité).
	 *
	 * @var int[]
	 */
	public static $d1 = array();

	/** Ouverture du jour serveur qui contient $ts. */
	private static function d1_open( $ts ) {
		$best = null;
		foreach ( self::$d1 as $o ) {
			if ( $o <= $ts ) {
				$best = $o;
			}
		}
		return null !== $best && $ts < $best + 2 * DAY_IN_SECONDS ? $best : null;
	}

	/** Jour serveur (Y-m-d) d'un instant. */
	public static function server_day( $ts ) {
		$o = self::$d1 ? self::d1_open( $ts ) : null;
		if ( null !== $o ) {
			return gmdate( 'Y-m-d', $o + 12 * 3600 );
		}
		return ( new DateTimeImmutable( '@' . ( $ts + 7 * 3600 ) ) )->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'Y-m-d' );
	}

	/** Premier instant >= $after à l'heure $hm dans le fuseau $tz, en partant du jour $day (−2 … +1 jours). */
	private static function occurrence( $day, $hm, $tz, $after ) {
		$best = null;
		foreach ( array( -2, -1, 0, 1 ) as $off ) {
			$u = ( new DateTimeImmutable( $day . ' ' . $hm, new DateTimeZone( $tz ) ) )->modify( $off . ' days' )->getTimestamp();
			if ( $u >= $after && ( null === $best || $u < $best ) ) {
				$best = $u;
			}
		}
		return $best;
	}

	/** Début du jour serveur $day : ouverture de sa bougie D1, sinon veille 17:00 New York. */
	private static function day_start( $day ) {
		foreach ( self::$d1 as $o ) {
			if ( gmdate( 'Y-m-d', $o + 12 * 3600 ) === $day ) {
				return $o;
			}
		}
		return ( new DateTimeImmutable( $day . ' 17:00', new DateTimeZone( 'America/New_York' ) ) )->modify( '-1 day' )->getTimestamp();
	}

	/** Fenêtres [s, e) par jour serveur présent dans les bougies. */
	public static function windows( $b, $start, $start_tz, $end, $end_tz ) {
		$days = array_values( array_unique( array_map( fn( $x ) => self::server_day( $x['t'] ), $b ) ) );
		$out  = array();
		foreach ( $days as $d ) {
			$s = self::occurrence( $d, $start, $start_tz, self::day_start( $d ) );
			$e = self::occurrence( $d, $end, $end_tz, $s + 60 );
			$out[] = array( $s, $e, $d );
		}
		return $out;
	}

	/** Fenêtres du jour serveur entier et de la semaine serveur. */
	public static function day_windows( $b ) {
		$days = array_values( array_unique( array_map( fn( $x ) => self::server_day( $x['t'] ), $b ) ) );
		$day  = array();
		$week = array();
		foreach ( $days as $d ) {
			$day[] = array( self::day_start( $d ), self::day_start( ( new DateTimeImmutable( $d ) )->modify( '+1 day' )->format( 'Y-m-d' ) ), $d );
			$mon   = ( new DateTimeImmutable( $d ) )->modify( '-' . ( (int) ( new DateTimeImmutable( $d ) )->format( 'N' ) - 1 ) . ' days' )->format( 'Y-m-d' );
			$week[ $mon ] = array( self::day_start( $mon ), self::day_start( ( new DateTimeImmutable( $mon ) )->modify( '+5 days' )->format( 'Y-m-d' ) ), $mon );
		}
		return array( $day, array_values( $week ) );
	}

	/* ------------------------------------------------------------------
	 * Calcul complet d'une unité de temps
	 * ------------------------------------------------------------------ */

	/**
	 * Tous les événements d'une UT (bougies closes seulement).
	 *
	 * @param array $rows [[unix, o, h, l, c], …].
	 * @param int   $sec  Durée d'une bougie.
	 * @return array {bars, atr, pivots, equal, sweeps, displacement, structure, trend, fvg, ob, sessions}
	 */
	public static function compute( $rows, $sec ) {
		$b = array();
		foreach ( $rows as $r ) {
			$b[] = array(
				't'  => (int) $r[0],
				'tc' => (int) $r[0] + $sec,
				'o'  => (float) $r[1],
				'h'  => (float) $r[2],
				'l'  => (float) $r[3],
				'c'  => (float) $r[4],
			);
		}
		$atr  = self::atr( $b, self::P['atr'] );
		$aref = array_merge( array( null ), array_slice( $atr, 0, max( 0, count( $atr ) - 1 ) ) );
		$piv  = self::pivots( $b, self::P['k'] );
		$eq   = self::equal_levels( $b, $piv, $aref );
		$disp = self::displacement( $b, $aref );
		list( $st, $trend ) = self::structure( $b, $piv );
		$fvg  = self::fvg( $b, $aref );
		$ob   = self::order_blocks( $b, $st, $disp );
		$ses  = array();
		if ( $sec <= 3600 && $b ) {
			list( $dw, $ww ) = self::day_windows( $b );
			$ses = array_merge(
				self::window_levels( $b, self::windows( $b, '18:00', 'America/New_York', '07:00', 'Europe/London' ), 'asie' ),
				self::window_levels( $b, self::windows( $b, '07:00', 'Europe/London', '08:15', 'America/New_York' ), 'londres' ),
				self::window_levels( $b, $dw, 'jour' ),
				self::window_levels( $b, $ww, 'semaine' )
			);
		}
		// Niveaux de liquidité balayables : pivots, égaux, haut/bas du jour et de l'Asie.
		$levels = array();
		foreach ( array_merge( $piv, $eq, array_filter( $ses, fn( $e ) => preg_match( '/^(jour|asie)_/', $e['type'] ) ) ) as $e ) {
			$levels[] = array(
				'price'     => $e['price'],
				'side'      => $e['dir'],
				't_confirm' => $e['t_confirm'],
				'source'    => $e['type'],
				'id'        => $e['type'] . ':' . $e['t_origin'],
			);
		}
		return array(
			'bars'         => $b,
			'atr'          => $atr,
			'pivots'       => $piv,
			'equal'        => $eq,
			'sweeps'       => self::sweeps( $b, $levels, $aref ),
			'displacement' => $disp,
			'structure'    => $st,
			'trend'        => $trend,
			'fvg'          => $fvg,
			'ob'           => $ob,
			'sessions'     => $ses,
		);
	}

	/* ------------------------------------------------------------------
	 * Résumé pour Claude
	 * ------------------------------------------------------------------ */

	/** Heure de Lubumbashi « jj/mm hh:mm ». */
	private static function hm( $ts ) {
		return wp_date( 'd/m H:i', (int) $ts, CX_Repo::tz() );
	}

	/** Objet présenté : identifiant, prix et heures lisibles. */
	private static function obj( $tf, $e, $extra = array() ) {
		return array(
			'id'      => $tf . ':' . $e['type'] . ':' . $e['t_origin'],
			'type'    => $e['type'],
			// Niveaux (pivots, égaux, séances) : côté haut ou bas ; autres objets : sens haussier ou baissier.
			'sens'    => preg_match( '/^(pivot|egaux|asie|londres|jour|semaine)/', $e['type'] ) ? ( 1 === $e['dir'] ? 'haut' : 'bas' ) : ( 1 === $e['dir'] ? 'haussier' : ( -1 === $e['dir'] ? 'baissier' : 'neutre' ) ),
			'prix'    => $e['price'],
			'haut'    => $e['top'],
			'bas'     => $e['bottom'],
			'origine' => self::hm( $e['t_origin'] ),
			'confirme' => self::hm( $e['t_confirm'] ),
		) + $extra;
	}

	/**
	 * Résumé d'une UT : bougie en cours à part, structures encore actives au moment des données.
	 *
	 * @param string $tf   UT.
	 * @param array  $rows Bougies reçues (dernière éventuellement en cours).
	 * @param int    $now  Instant des données (Unix).
	 * @return array
	 */
	public static function summarize( $tf, $rows, $now ) {
		$sec     = self::TF[ $tf ];
		$closed  = array_values( array_filter( $rows, fn( $r ) => (int) $r[0] + $sec <= $now ) );
		$forming = array_values( array_filter( $rows, fn( $r ) => (int) $r[0] + $sec > $now ) );
		$x       = self::compute( $closed, $sec );
		$b       = $x['bars'];
		$n       = count( $b );
		$last    = $n ? $b[ $n - 1 ] : null;
		$price   = $forming ? (float) end( $forming )[4] : ( $last ? $last['c'] : null );
		$atr     = $n ? end( $x['atr'] ) : null;
		$near    = function ( $list ) use ( $price ) {
			usort( $list, fn( $a, $b2 ) => abs( $a['prix'] - $price ) <=> abs( $b2['prix'] - $price ) );
			return array_slice( $list, 0, 6 );
		};
		// FVG encore ouverts (ni comblés ni invalidés) et iFVG non invalidés par la suite.
		$fvg  = array();
		$ifvg = array();
		foreach ( $x['fvg'] as $e ) {
			$s = $e['states'];
			if ( ! isset( $s['fvg_comble'] ) && ! isset( $s['fvg_invalide'] ) ) {
				$fvg[] = self::obj( $tf, $e, array( 'etat' => isset( $s['fvg_ce'] ) ? 'milieu touché' : ( isset( $s['fvg_mitige'] ) ? 'touché' : 'vierge' ), 'taille_atr' => $e['taille_atr'] ) );
			} elseif ( isset( $s['ifvg'] ) ) {
				$i  = $s['ifvg'];
				$cl = array_column( $b, 'c' );
				$kx = -1 === $i['dir'] ? self::first( $cl, $i['ic'] + 1, $i['top'], true, true ) : self::first( $cl, $i['ic'] + 1, $i['bottom'], false, true );
				if ( $kx < 0 ) {
					$ifvg[] = self::obj( $tf, $i );
				}
			}
		}
		$ob  = array();
		$brk = array();
		foreach ( $x['ob'] as $e ) {
			$s = $e['states'];
			if ( ! isset( $s['ob_invalide'] ) && ! isset( $s['ob_expire'] ) ) {
				$ob[] = self::obj( $tf, $e, array( 'etat' => isset( $s['ob_mitige'] ) ? 'touché' : 'vierge' ) );
			} elseif ( isset( $s['breaker'] ) && ! isset( $s['breaker_invalide'] ) ) {
				$brk[] = self::obj( $tf, $s['breaker'], array( 'etat' => isset( $s['breaker_retest'] ) ? 'retesté' : 'vierge' ) );
			}
		}
		// Liquidité : pivots et égaux pas encore pris (aucune mèche au-delà depuis leur confirmation).
		$liq = array();
		$hi  = array_column( $b, 'h' );
		$lo  = array_column( $b, 'l' );
		foreach ( array_merge( $x['pivots'], $x['equal'] ) as $e ) {
			$from = $e['ic'] + 1;
			$j    = 1 === $e['dir'] ? self::first( $hi, $from, $e['price'], true, true ) : self::first( $lo, $from, $e['price'], false, true );
			if ( $j < 0 ) {
				$liq[] = self::obj( $tf, $e, array( 'cote' => 1 === $e['dir'] ? 'BSL (au-dessus)' : 'SSL (en dessous)' ) );
			}
		}
		$recent = fn( $list, $k ) => array_slice( array_map( fn( $e ) => self::obj( $tf, $e ), $list ), -$k );
		usort( $x['structure'], fn( $a, $b2 ) => $a['ic'] <=> $b2['ic'] );
		$sw = array_values( array_filter( $x['sweeps'], fn( $e ) => 'sweep' === $e['type'] ) );
		usort( $sw, fn( $a, $b2 ) => $a['ic'] <=> $b2['ic'] );
		$ds = $x['displacement'];
		usort( $ds, fn( $a, $b2 ) => $a['ic'] <=> $b2['ic'] );
		$out = array(
			'ut'          => $tf,
			'bougies'     => $n,
			'derniere'    => $last ? array( 'ouverture' => self::hm( $last['t'] ), 'o' => $last['o'], 'h' => $last['h'], 'l' => $last['l'], 'c' => $last['c'] ) : null,
			'en_cours'    => $forming ? array( 'ouverture' => self::hm( (int) $forming[0][0] ), 'o' => (float) $forming[0][1], 'h' => (float) $forming[0][2], 'l' => (float) $forming[0][3], 'c' => (float) $forming[0][4], 'note' => 'bougie non close : jamais utilisée pour les structures' ) : null,
			'atr'         => null === $atr ? null : round( $atr, 2 ),
			'tendance'    => 1 === $x['trend'] ? 'haussière' : ( -1 === $x['trend'] ? 'baissière' : 'indéfinie' ),
			'structure'   => array_slice( array_map( fn( $e ) => self::obj( $tf, $e, array( 'casse' => $e['price'] ) ), $x['structure'] ), -5 ),
			'swings'      => $recent( $x['pivots'], 8 ),
			'fvg'         => null === $price ? $fvg : $near( $fvg ),
			'ifvg'        => null === $price ? $ifvg : $near( $ifvg ),
			'ob'          => null === $price ? $ob : $near( $ob ),
			'breakers'    => null === $price ? $brk : $near( $brk ),
			'liquidite'   => null === $price ? $liq : $near( $liq ),
			'sweeps'      => array_slice( array_map( fn( $e ) => self::obj( $tf, $e, array( 'extreme' => $e['extreme'], 'niveau' => $e['level'] ) ), $sw ), -4 ),
			'deplacements' => array_slice( array_map( fn( $e ) => self::obj( $tf, $e ), $ds ), -3 ),
		);
		if ( $x['sessions'] ) {
			$ses = array();
			foreach ( $x['sessions'] as $e ) {
				$ses[ preg_replace( '/_(haut|bas)$/', '', $e['type'] ) ][ $e['jour'] ][ 1 === $e['dir'] ? 'haut' : 'bas' ] = $e['price'];
			}
			foreach ( $ses as $k => $v ) {
				ksort( $v );
				$ses[ $k ] = array_slice( $v, -2, null, true );
			}
			$out['seances'] = $ses;
		}
		return $out;
	}

	/**
	 * Structures de toutes les UT et état des données, à partir des bougies du compte de référence.
	 *
	 * @return array
	 */
	public static function snapshot() {
		$data = CX_Live::candle_data();
		if ( empty( $data['tf'] ) ) {
			return array( 'ok' => false, 'note' => 'Aucune bougie reçue du compte de référence.' );
		}
		$now = ( new DateTimeImmutable( (string) ( $data['received'] ?? $data['at'] ?? 'now' ), CX_Repo::tz() ) )->getTimestamp();
		$key = 'cx_struct_' . md5( (string) ( $data['received'] ?? '' ) . (string) ( $data['account_id'] ?? '' ) );
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		self::$d1 = array_map( fn( $r ) => (int) $r[0], (array) ( $data['tf']['D1'] ?? array() ) );
		$tfs      = array();
		foreach ( self::TF as $tf => $sec ) {
			$rows = (array) ( $data['tf'][ $tf ] ?? array() );
			if ( $rows ) {
				$tfs[ $tf ] = self::summarize( $tf, $rows, $now );
			}
		}
		// Premium / discount : dans le dernier range de swing H4 et dans la veille (jour serveur).
		$pd    = array();
		$price = isset( $tfs['M5']['en_cours'] ) ? $tfs['M5']['en_cours']['c'] : ( $tfs['M15']['derniere']['c'] ?? null );
		if ( null !== $price && ! empty( $data['tf']['H4'] ) ) {
			$h4  = self::compute( array_values( array_filter( $data['tf']['H4'], fn( $r ) => (int) $r[0] + 14400 <= $now ) ), 14400 );
			$ph  = array_values( array_filter( $h4['pivots'], fn( $e ) => 'pivot_haut' === $e['type'] ) );
			$pl  = array_values( array_filter( $h4['pivots'], fn( $e ) => 'pivot_bas' === $e['type'] ) );
			if ( $ph && $pl ) {
				$hi = end( $ph )['price'];
				$lo = end( $pl )['price'];
				if ( $hi > $lo ) {
					$pos           = ( $price - $lo ) / ( $hi - $lo );
					$pd['swing_h4'] = array( 'haut' => $hi, 'bas' => $lo, 'equilibre' => round( ( $hi + $lo ) / 2, 2 ), 'position' => round( $pos, 2 ), 'zone' => $pos > 0.5 ? 'premium' : ( $pos < 0.5 ? 'discount' : 'équilibre' ) );
				}
			}
		}
		if ( null !== $price && ! empty( $tfs['M15']['seances']['jour'] ) ) {
			// Seules les journées terminées sont confirmées : la dernière est la veille.
			$days = $tfs['M15']['seances']['jour'];
			$prev = end( $days );
			if ( $prev && $prev['haut'] > $prev['bas'] ) {
				$pos          = ( $price - $prev['bas'] ) / ( $prev['haut'] - $prev['bas'] );
				$pd['veille'] = array( 'haut' => $prev['haut'], 'bas' => $prev['bas'], 'position' => round( $pos, 2 ), 'zone' => $pos > 0.5 ? 'premium' : ( $pos < 0.5 ? 'discount' : 'équilibre' ) );
			}
		}
		$age = time() - $now;
		$out = array(
			'ok'       => true,
			'symbole'  => 'XAUUSD',
			'compte'   => $data['account'] ?? '',
			'courtier' => $data['firm'] ?? '',
			'donnees'  => array(
				'recues'      => self::hm( $now ),
				'age_minutes' => (int) round( $age / 60 ),
				'fraiches'    => $age < 30 * MINUTE_IN_SECONDS,
			),
			'prix'     => $price,
			'premium_discount' => $pd,
			'ut'       => $tfs,
			'regles'   => 'Objets calculés sur bougies closes seulement. BOS/CHoCH = clôture au-delà du dernier pivot confirmé (une mèche ne suffit pas). Pivot connu k=2 bougies après son sommet. Heures de Lubumbashi. Citer les objets par leur id.',
		);
		set_transient( $key, $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Confluence d'un signal de l'EA, en faits (pas de note) : sens de la décision de Claude pour la séance
	 * du jour, zone calculée sous l'entrée, sweep récent dans le même sens.
	 *
	 * @param string $side  buy|sell.
	 * @param float  $entry Entrée.
	 * @return string[]
	 */
	public static function confluence( $side, $entry ) {
		$out  = array();
		$buy  = 'buy' === $side;
		$want = $buy ? 'achat' : 'vente';
		$p    = CX_Repo::plan( CX_Live::week() );
		$day  = wp_date( 'Y-m-d', time(), CX_Repo::tz() );
		$ses  = array_values( array_filter( (array) ( $p['content']['sessions'] ?? array() ), fn( $x ) => $x['date'] === $day && ! empty( $x['decision'] ) ) );
		if ( $ses ) {
			$s    = end( $ses );
			$name = 'us' === $s['kind'] ? 'New York' : 'Londres';
			if ( 'deux_scenarios' === $s['decision'] ) {
				$out[] = ( $s['lean'] === $want ? '✅' : '➖' ) . ' Plan de ' . $name . ' : deux scénarios, ' . ( $s['lean'] ? ( 'achat' === $s['lean'] ? 'achat privilégié' : 'vente privilégiée' ) : 'aucun privilégié' );
			} else {
				$out[] = ( $s['decision'] === $want ? '✅ Dans le sens' : '⚠️ Contre le sens' ) . ' de la décision du plan de ' . $name . ' (' . $s['decision'] . ')';
			}
		}
		$snap = self::snapshot();
		if ( empty( $snap['ok'] ) || null === $entry ) {
			return $out;
		}
		$zone = null;
		// La zone la plus fine d'abord (M5, puis M15, H1, H4).
		foreach ( array( 'M5', 'M15', 'H1', 'H4' ) as $tf ) {
			foreach ( array( 'ob' => 'OB', 'fvg' => 'FVG', 'ifvg' => 'iFVG', 'breakers' => 'Breaker' ) as $k => $lab ) {
				foreach ( (array) ( $snap['ut'][ $tf ][ $k ] ?? array() ) as $z ) {
					if ( null === $zone && $entry >= $z['bas'] - 0.5 && $entry <= $z['haut'] + 0.5 && ( $buy ? 'haussier' : 'baissier' ) === $z['sens'] ) {
						$zone = $lab . ' ' . $tf . ' ' . number_format( $z['bas'], 2, ',', ' ' ) . '–' . number_format( $z['haut'], 2, ',', ' ' );
					}
				}
			}
		}
		$out[] = $zone ? '✅ Entrée dans une zone calculée : ' . $zone : '➖ Aucune zone calculée du même sens sous l’entrée';
		$sweep = null;
		foreach ( array( 'M5', 'M15' ) as $tf ) {
			foreach ( (array) ( $snap['ut'][ $tf ]['sweeps'] ?? array() ) as $w ) {
				if ( ( $buy ? 'haussier' : 'baissier' ) === $w['sens'] ) {
					$sweep = 'sweep ' . $tf . ' de ' . number_format( $w['prix'], 2, ',', ' ' ) . ' (' . $w['confirme'] . ')';
				}
			}
		}
		$out[] = $sweep ? '✅ Liquidité balayée avant : ' . $sweep : '➖ Pas de sweep récent dans ce sens';
		return $out;
	}

	/** @return WP_REST_Response */
	public static function rest() {
		return new WP_REST_Response( self::snapshot(), 200 );
	}
}
