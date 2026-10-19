<?php
/**
 * Accès aux tables : validation, requêtes préparées et conversion vers le format de l'interface.
 *
 * Les clés JSON reprennent celles du prototype (date, time, dir, setup…) ; les colonnes
 * gardent les noms du brief.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lecture et écriture des comptes, trades, plans et retraits.
 */
class CX_Repo {

	const STATUSES = array( 'phase1', 'phase2', 'finance', 'echoue', 'retire' );
	const SETUPS   = array( '1', '2', '3', 'hors' );
	const LEVELS   = array( 'EQL', 'EQH', '2 Bottom Liq', '2 Top Liq', 'PDH', 'PDL', 'Session fermée', 'BoS', 'Autre' );
	const EMOTIONS = array( 'calme', 'presse', 'revanche', 'fatigue' );

	/* ------------------------------------------------------------------
	 * Outils de validation
	 * ------------------------------------------------------------------ */

	/**
	 * Erreur de validation (HTTP 400).
	 *
	 * @param string $message Message affiché dans l'interface.
	 * @return WP_Error
	 */
	public static function invalid( $message ) {
		return new WP_Error( 'cx_invalid', $message, array( 'status' => 400 ) );
	}

	/**
	 * Date AAAA-MM-JJ valide, ou null.
	 *
	 * @param mixed $v Valeur reçue.
	 * @return string|null
	 */
	public static function date( $v ) {
		$v = is_string( $v ) ? trim( $v ) : '';
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		return $v;
	}

	/**
	 * Heure HH:MM valide, ou null.
	 *
	 * @param mixed $v Valeur reçue.
	 * @return string|null
	 */
	public static function time( $v ) {
		$v = is_string( $v ) ? trim( $v ) : '';
		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v ) ) {
			return null;
		}
		return substr( $v, 0, 5 );
	}

	/**
	 * Nombre dans une plage, ou null si vide ou invalide.
	 *
	 * @param mixed $v   Valeur reçue (accepte la virgule décimale).
	 * @param float $min Minimum.
	 * @param float $max Maximum.
	 * @return float|null
	 */
	public static function num( $v, $min = -1e9, $max = 1e9 ) {
		if ( is_string( $v ) ) {
			$v = str_replace( array( ',', ' ' ), array( '.', '' ), trim( $v ) );
		}
		if ( '' === $v || null === $v || ! is_numeric( $v ) ) {
			return null;
		}
		$v = (float) $v;
		return ( $v < $min || $v > $max ) ? null : $v;
	}

	/**
	 * Texte d'une ligne.
	 *
	 * @param mixed $v   Valeur reçue.
	 * @param int   $len Longueur maximale.
	 * @return string
	 */
	public static function text( $v, $len = 200 ) {
		return mb_substr( sanitize_text_field( is_scalar( $v ) ? (string) $v : '' ), 0, $len );
	}

	/**
	 * Texte sur plusieurs lignes.
	 *
	 * @param mixed $v   Valeur reçue.
	 * @param int   $len Longueur maximale.
	 * @return string
	 */
	public static function textarea( $v, $len = 10000 ) {
		return mb_substr( sanitize_textarea_field( is_scalar( $v ) ? (string) $v : '' ), 0, $len );
	}

	/**
	 * Valeur dans une liste fermée.
	 *
	 * @param mixed  $v       Valeur reçue.
	 * @param array  $allowed Valeurs permises.
	 * @param string $default Valeur par défaut.
	 * @return string
	 */
	public static function pick( $v, $allowed, $default ) {
		$v = is_scalar( $v ) ? (string) $v : '';
		return in_array( $v, $allowed, true ) ? $v : $default;
	}

	/**
	 * Fuseau du carnet : toujours l'heure de Lubumbashi, quel que soit le réglage du site
	 * (le site de la Ville est en UTC+1). Modifiable par le filtre « cx_timezone ».
	 *
	 * @return DateTimeZone
	 */
	public static function tz() {
		return new DateTimeZone( (string) apply_filters( 'cx_timezone', 'Africa/Lubumbashi' ) );
	}

	/**
	 * Date et heure courantes du carnet.
	 *
	 * @param string $format Format (par défaut MySQL).
	 * @return string
	 */
	public static function clock( $format = 'Y-m-d H:i:s' ) {
		return ( new DateTimeImmutable( 'now', self::tz() ) )->format( $format );
	}

	/**
	 * Date et heure courantes du carnet (MySQL).
	 *
	 * @return string
	 */
	private static function now() {
		return self::clock();
	}

	/**
	 * Décimal en float, null conservé.
	 *
	 * @param mixed $v Valeur de la base.
	 * @return float|null
	 */
	private static function f( $v ) {
		return null === $v ? null : (float) $v;
	}

	/* ------------------------------------------------------------------
	 * Comptes
	 * ------------------------------------------------------------------ */

	/**
	 * Ligne de la base vers l'interface.
	 *
	 * @param object $r Ligne.
	 * @return array
	 */
	private static function account_out( $r ) {
		$params = json_decode( (string) $r->parametres, true );
		return array(
			'id'           => (int) $r->id,
			'name'         => $r->nom,
			'firm'         => $r->prop_firm,
			'model'        => $r->modele,
			'size'         => (float) $r->taille,
			'price'        => (float) $r->prix_challenge,
			'status'       => $r->statut,
			'purchaseDate' => $r->date_achat,
			'phaseStart'   => $r->phase_debut,
			'params'       => array_merge( CX_Install::DEFAULT_PARAMS, is_array( $params ) ? $params : array() ),
			'active'       => (bool) $r->actif,
			'mt5'          => ! empty( $r->mt5_cle ),
		);
	}

	/**
	 * Tous les comptes, le plus récent en dernier.
	 *
	 * @return array
	 */
	public static function accounts() {
		global $wpdb;
		$t = CX_Install::table( 'accounts' );
		return array_map( array( __CLASS__, 'account_out' ), $wpdb->get_results( "SELECT * FROM $t ORDER BY id ASC" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Un compte.
	 *
	 * @param int $id Identifiant.
	 * @return array|null
	 */
	public static function account( $id ) {
		global $wpdb;
		$t = CX_Install::table( 'accounts' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $r ? self::account_out( $r ) : null;
	}

	/**
	 * Paramètres de risque validés.
	 *
	 * @param mixed $in Paramètres reçus.
	 * @return array|WP_Error
	 */
	private static function params_in( $in ) {
		$in     = is_array( $in ) ? $in : array();
		$ranges = array(
			't1'        => array( 0.1, 100 ),
			't2'        => array( 0.1, 100 ),
			'tf'        => array( 0.1, 100 ),
			'daily'     => array( 0.1, 100 ),
			'max'       => array( 0.1, 100 ),
			'mindays'   => array( 0, 365 ),
			'risk'      => array( 0, 1e6 ),
			'riskred'   => array( 0, 1e6 ),
			'redat'     => array( 0, 1e7 ),
			'maxtrades' => array( 1, 100 ),
			'maxloss'   => array( 1, 100 ),
			'spreadmax' => array( 1, 100 ),
			'contract'  => array( 1, 10000 ),
		);
		$out    = array();
		foreach ( $ranges as $k => $r ) {
			$v = array_key_exists( $k, $in ) ? self::num( $in[ $k ], $r[0], $r[1] ) : CX_Install::DEFAULT_PARAMS[ $k ];
			if ( null === $v ) {
				return self::invalid( sprintf( 'Paramètre « %s » invalide.', $k ) );
			}
			$out[ $k ] = in_array( $k, array( 'mindays', 'maxtrades', 'maxloss' ), true ) ? (int) $v : $v;
		}
		return $out;
	}

	/**
	 * Crée ou modifie un compte.
	 *
	 * @param array    $in Données de l'interface.
	 * @param int|null $id Identifiant pour une modification.
	 * @return array|WP_Error Compte enregistré.
	 */
	public static function save_account( $in, $id = null ) {
		global $wpdb;
		$t   = CX_Install::table( 'accounts' );
		$old = $id ? self::account( $id ) : null;
		if ( $id && ! $old ) {
			return new WP_Error( 'cx_not_found', 'Compte introuvable.', array( 'status' => 404 ) );
		}
		$name = self::text( $in['name'] ?? ( $old['name'] ?? '' ), 100 );
		if ( '' === $name ) {
			return self::invalid( 'Donnez un nom au compte.' );
		}
		$size = self::num( $in['size'] ?? ( $old['size'] ?? null ), 1, 1e8 );
		if ( null === $size ) {
			return self::invalid( 'Taille du compte invalide.' );
		}
		$params = self::params_in( array_merge( $old['params'] ?? array(), is_array( $in['params'] ?? null ) ? $in['params'] : array() ) );
		if ( is_wp_error( $params ) ) {
			return $params;
		}
		$status = self::pick( $in['status'] ?? ( $old['status'] ?? 'phase1' ), self::STATUSES, 'phase1' );

		// Nouvelle étape : le décompte des jours tradés repart d'aujourd'hui, sauf date fournie.
		if ( array_key_exists( 'phaseStart', $in ) ) {
			$phase_start = self::date( $in['phaseStart'] );
		} elseif ( $old && $old['status'] !== $status ) {
			$phase_start = CX_Repo::clock( 'Y-m-d' );
		} else {
			$phase_start = $old['phaseStart'] ?? CX_Repo::clock( 'Y-m-d' );
		}

		$row = array(
			'nom'            => $name,
			'prop_firm'      => self::text( $in['firm'] ?? ( $old['firm'] ?? '' ), 100 ),
			'modele'         => self::text( $in['model'] ?? ( $old['model'] ?? '' ), 100 ),
			'taille'         => $size,
			'prix_challenge' => self::num( $in['price'] ?? ( $old['price'] ?? 0 ), 0, 1e7 ) ?? 0,
			'statut'         => $status,
			'date_achat'     => array_key_exists( 'purchaseDate', $in ) ? self::date( $in['purchaseDate'] ) : ( $old['purchaseDate'] ?? null ),
			'phase_debut'    => $phase_start,
			'parametres'     => wp_json_encode( $params ),
			'updated_at'     => self::now(),
		);

		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ) );
		} else {
			$row['created_at'] = self::now();
			$row['actif']      = 0;
			$wpdb->insert( $t, $row );
			$id = (int) $wpdb->insert_id;
			if ( ! $id ) {
				return new WP_Error( 'cx_db', 'Enregistrement impossible.', array( 'status' => 500 ) );
			}
		}

		// Premier compte ou demande explicite : il devient le compte actif.
		if ( ! empty( $in['active'] ) || 1 === count( self::accounts() ) ) {
			self::activate_account( $id );
		}
		return self::account( $id );
	}

	/**
	 * Rend un compte actif (affiché dans l'onglet Jour).
	 *
	 * @param int $id Identifiant.
	 * @return bool
	 */
	public static function activate_account( $id ) {
		global $wpdb;
		$t = CX_Install::table( 'accounts' );
		if ( ! self::account( $id ) ) {
			return false;
		}
		$wpdb->query( "UPDATE $t SET actif = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$wpdb->update( $t, array( 'actif' => 1 ), array( 'id' => $id ) );
		return true;
	}

	/**
	 * Enregistre (ou retire) l'empreinte de la clé MT5 d'un compte.
	 *
	 * @param int         $id   Compte.
	 * @param string|null $hash Empreinte SHA-256, ou null pour révoquer.
	 * @return bool
	 */
	public static function set_mt5_key( $id, $hash ) {
		global $wpdb;
		return false !== $wpdb->update( CX_Install::table( 'accounts' ), array( 'mt5_cle' => $hash ), array( 'id' => $id ) );
	}

	/**
	 * Supprime un compte avec ses trades et ses retraits.
	 *
	 * @param int $id Identifiant.
	 * @return bool
	 */
	public static function delete_account( $id ) {
		global $wpdb;
		$acc = self::account( $id );
		if ( ! $acc ) {
			return false;
		}
		$wpdb->delete( CX_Install::table( 'trades' ), array( 'account_id' => $id ) );
		$wpdb->delete( CX_Install::table( 'payouts' ), array( 'account_id' => $id ) );
		$wpdb->delete( CX_Install::table( 'snapshots' ), array( 'account_id' => $id ) );
		$wpdb->delete( CX_Install::table( 'accounts' ), array( 'id' => $id ) );
		if ( $acc['active'] ) {
			$t     = CX_Install::table( 'accounts' );
			$first = (int) $wpdb->get_var( "SELECT id FROM $t ORDER BY id ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL
			if ( $first ) {
				self::activate_account( $first );
			}
		}
		return true;
	}

	/**
	 * Nombre de trades et de retraits rattachés à un compte.
	 *
	 * @param int $id Identifiant.
	 * @return array
	 */
	public static function account_counts( $id ) {
		global $wpdb;
		$tt = CX_Install::table( 'trades' );
		$tp = CX_Install::table( 'payouts' );
		return array(
			'trades'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tt WHERE account_id = %d", $id ) ), // phpcs:ignore WordPress.DB.PreparedSQL
			'payouts' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tp WHERE account_id = %d", $id ) ), // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}

	/* ------------------------------------------------------------------
	 * Trades
	 * ------------------------------------------------------------------ */

	/**
	 * Ligne de la base vers l'interface.
	 *
	 * @param object $r Ligne.
	 * @return array
	 */
	private static function trade_out( $r ) {
		$cap = $r->capture_id ? (int) $r->capture_id : null;
		return array(
			'id'         => (int) $r->id,
			'accountId'  => (int) $r->account_id,
			'date'       => $r->date,
			'time'       => $r->heure ? substr( $r->heure, 0, 5 ) : '',
			'dir'        => $r->sens,
			'setup'      => $r->setup,
			'level'      => $r->niveau,
			'stop'       => self::f( $r->stop_usd ),
			'lot'        => self::f( $r->lot ),
			'risk'       => self::f( $r->risque_usd ),
			'r'          => (float) $r->resultat_r,
			'pnl'        => self::f( $r->resultat_usd ),
			'onLevel'    => (bool) $r->sur_niveau_cle,
			'withBias'   => (bool) $r->avec_biais,
			'rules'      => (bool) $r->regles_respectees,
			'emotion'    => $r->emotion ? $r->emotion : '',
			'captureId'  => $cap,
			'captureUrl' => $cap ? (string) wp_get_attachment_url( $cap ) : '',
			'link'       => $r->lien,
			'notes'      => $r->notes,
			'source'     => $r->source,
			'toComplete' => (bool) $r->a_completer,
			'createdAt'  => $r->created_at,
		);
	}

	/**
	 * Trades, du plus ancien au plus récent.
	 *
	 * @param int $account_id 0 pour tous les comptes.
	 * @return array
	 */
	public static function trades( $account_id = 0 ) {
		global $wpdb;
		$t = CX_Install::table( 'trades' );
		if ( $account_id ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE account_id = %d ORDER BY date ASC, heure ASC, id ASC", $account_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY date ASC, heure ASC, id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return array_map( array( __CLASS__, 'trade_out' ), $rows );
	}

	/**
	 * Un trade.
	 *
	 * @param int $id Identifiant.
	 * @return array|null
	 */
	public static function trade( $id ) {
		global $wpdb;
		$t = CX_Install::table( 'trades' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $r ? self::trade_out( $r ) : null;
	}

	/**
	 * Crée ou modifie un trade.
	 *
	 * @param array    $in Données de l'interface.
	 * @param int|null $id Identifiant pour une modification.
	 * @return array|WP_Error
	 */
	public static function save_trade( $in, $id = null ) {
		global $wpdb;
		$t = CX_Install::table( 'trades' );
		if ( $id && ! self::trade( $id ) ) {
			return new WP_Error( 'cx_not_found', 'Trade introuvable.', array( 'status' => 404 ) );
		}
		$account = (int) ( $in['accountId'] ?? 0 );
		if ( ! self::account( $account ) ) {
			return self::invalid( 'Choisissez un compte valide.' );
		}
		$date = self::date( $in['date'] ?? '' );
		$r    = self::num( $in['r'] ?? null, -100, 100 );
		if ( ! $date || null === $r ) {
			return self::invalid( 'Indiquez au moins la date et le résultat en R.' );
		}

		$link = esc_url_raw( trim( (string) ( $in['link'] ?? '' ) ), array( 'http', 'https' ) );
		$cap  = absint( $in['captureId'] ?? 0 );
		if ( $cap && ( 'attachment' !== get_post_type( $cap ) || ! wp_attachment_is_image( $cap ) ) ) {
			$cap = 0;
		}
		$risk = self::num( $in['risk'] ?? null, 0, 1e6 );
		$pnl  = self::num( $in['pnl'] ?? null, -1e7, 1e7 );
		if ( null === $pnl && null !== $risk ) {
			$pnl = round( $r * $risk, 2 );
		}
		$emotion = self::pick( $in['emotion'] ?? '', self::EMOTIONS, '' );

		$row = array(
			'account_id'        => $account,
			'date'              => $date,
			'heure'             => self::time( $in['time'] ?? '' ),
			'sens'              => self::pick( $in['dir'] ?? '', array( 'achat', 'vente' ), 'achat' ),
			'setup'             => self::pick( $in['setup'] ?? '', self::SETUPS, '1' ),
			'niveau'            => self::pick( $in['level'] ?? '', self::LEVELS, 'Autre' ),
			'stop_usd'          => self::num( $in['stop'] ?? null, 0, 1e5 ),
			'lot'               => self::num( $in['lot'] ?? null, 0, 1000 ),
			'risque_usd'        => $risk,
			'resultat_r'        => $r,
			'resultat_usd'      => $pnl,
			'sur_niveau_cle'    => empty( $in['onLevel'] ) ? 0 : 1,
			'avec_biais'        => empty( $in['withBias'] ) ? 0 : 1,
			'regles_respectees' => empty( $in['rules'] ) ? 0 : 1,
			'emotion'           => '' === $emotion ? null : $emotion,
			'capture_id'        => $cap ? $cap : null,
			'lien'              => mb_substr( $link, 0, 500 ),
			'notes'             => self::textarea( $in['notes'] ?? '' ),
			// Un trade importé de MT5 est « complété » dès qu'il est enregistré depuis le journal.
			'a_completer'       => 0,
		);

		if ( $id ) {
			$ok = $wpdb->update( $t, $row, array( 'id' => $id ) );
		} else {
			$row['source']     = 'manuel';
			$row['created_at'] = self::now();
			$ok                = $wpdb->insert( $t, $row );
			$id                = (int) $wpdb->insert_id;
		}
		if ( false === $ok ) {
			return new WP_Error( 'cx_db', 'Enregistrement impossible.', array( 'status' => 500 ) );
		}
		return self::trade( $id );
	}

	/**
	 * Supprime un trade (la capture reste dans la médiathèque).
	 *
	 * @param int $id Identifiant.
	 * @return bool
	 */
	public static function delete_trade( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( CX_Install::table( 'trades' ), array( 'id' => $id ) );
	}

	/* ------------------------------------------------------------------
	 * Plans hebdomadaires (globaux : account_id = 0)
	 * ------------------------------------------------------------------ */

	/**
	 * Décode une liste JSON.
	 *
	 * @param string $json JSON de la base.
	 * @return array
	 */
	private static function list_out( $json ) {
		$v = json_decode( (string) $json, true );
		return is_array( $v ) ? $v : array();
	}

	/**
	 * Plan d'une semaine.
	 *
	 * @param string $week Lundi AAAA-MM-JJ.
	 * @return array|null
	 */
	public static function plan( $week ) {
		global $wpdb;
		$t = CX_Install::table( 'plans' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE account_id = 0 AND semaine = %s", $week ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! $r ) {
			return null;
		}
		return array(
			'week'      => $r->semaine,
			'bias'      => $r->biais,
			'wh'        => null === $r->plus_haut ? '' : (float) $r->plus_haut,
			'wl'        => null === $r->plus_bas ? '' : (float) $r->plus_bas,
			'struct'    => $r->structure,
			'fund'      => (object) self::list_out( $r->fondamentaux ),
			'fundnotes' => $r->fond_notes,
			'levels'    => self::list_out( $r->niveaux ),
			'cal'       => self::list_out( $r->annonces ),
			'sc'        => self::list_out( $r->scenarios ),
			'review'      => $r->bilan,
			'updatedAt'   => $r->updated_at,
			// Plan élargi rédigé par Claude (null pour un plan d'origine).
			'content'     => isset( $r->contenu ) && $r->contenu ? json_decode( $r->contenu, true ) : null,
			'author'      => $r->auteur ?? '',
			'publishedAt' => $r->publie_le ?? null,
		);
	}

	/**
	 * Tous les plans (export).
	 *
	 * @return array
	 */
	public static function plans() {
		global $wpdb;
		$t = CX_Install::table( 'plans' );
		return array_map( array( __CLASS__, 'plan' ), $wpdb->get_col( "SELECT semaine FROM $t WHERE account_id = 0 ORDER BY semaine ASC" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Lignes d'une liste (niveaux, annonces, scénarios) nettoyées.
	 *
	 * @param mixed $rows   Liste reçue.
	 * @param array $fields Champ => type (text, num, time, day, textarea).
	 * @param int   $max    Nombre maximal de lignes.
	 * @return array
	 */
	private static function rows_in( $rows, $fields, $max = 30 ) {
		$out  = array();
		$days = array( 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi' );
		foreach ( array_slice( is_array( $rows ) ? $rows : array(), 0, $max ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$o = array();
			foreach ( $fields as $k => $type ) {
				$v = $row[ $k ] ?? '';
				switch ( $type ) {
					case 'num':
						$n       = self::num( $v, 0, 1e6 );
						$o[ $k ] = null === $n ? '' : (string) $n;
						break;
					case 'time':
						$o[ $k ] = (string) self::time( $v );
						break;
					case 'day':
						$o[ $k ] = self::pick( $v, $days, 'lundi' );
						break;
					case 'textarea':
						$o[ $k ] = self::textarea( $v, 2000 );
						break;
					default:
						$o[ $k ] = self::text( $v, 200 );
				}
			}
			$out[] = $o;
		}
		return $out;
	}

	/**
	 * Enregistre le plan d'une semaine.
	 *
	 * @param string $week Lundi AAAA-MM-JJ (déjà validé).
	 * @param array  $in   Données de l'interface.
	 * @return array|WP_Error
	 */
	public static function save_plan( $week, $in ) {
		global $wpdb;
		$t        = CX_Install::table( 'plans' );
		$fund_opt = array( '', 'up', 'down', 'neutral' );
		$fund_in  = is_array( $in['fund'] ?? null ) ? $in['fund'] : array();
		$fund     = array();
		foreach ( array( 'dxy', 'rates', 'fed', 'geo' ) as $k ) {
			$fund[ $k ] = self::pick( $fund_in[ $k ] ?? '', $fund_opt, '' );
		}
		$data = array(
			'biais'        => self::pick( $in['bias'] ?? '', array( '', 'h', 'b', 'n' ), '' ),
			'plus_haut'    => self::num( $in['wh'] ?? null, 0, 1e6 ),
			'plus_bas'     => self::num( $in['wl'] ?? null, 0, 1e6 ),
			'structure'    => self::textarea( $in['struct'] ?? '' ),
			'fondamentaux' => wp_json_encode( $fund ),
			'fond_notes'   => self::textarea( $in['fundnotes'] ?? '' ),
			'niveaux'      => wp_json_encode(
				self::rows_in(
					$in['levels'] ?? array(),
					array(
						'price' => 'num',
						'type'  => 'text',
						'note'  => 'text',
					)
				)
			),
			'annonces'     => wp_json_encode(
				self::rows_in(
					$in['cal'] ?? array(),
					array(
						'day'   => 'day',
						'time'  => 'time',
						'event' => 'text',
					)
				)
			),
			'scenarios'    => wp_json_encode( self::rows_in( $in['sc'] ?? array(), array( 'text' => 'textarea' ) ) ),
			'bilan'        => self::textarea( $in['review'] ?? '' ),
			'updated_at'   => self::now(),
		);
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE account_id = 0 AND semaine = %s", $week ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $exists ) {
			$ok = $wpdb->update( $t, $data, array( 'id' => (int) $exists ) );
		} else {
			$ok = $wpdb->insert(
				$t,
				array_merge(
					$data,
					array(
						'account_id' => 0,
						'semaine'    => $week,
					)
				)
			);
		}
		if ( false === $ok ) {
			return new WP_Error( 'cx_db', 'Enregistrement impossible.', array( 'status' => 500 ) );
		}
		return self::plan( $week );
	}

	/* ------------------------------------------------------------------
	 * Retraits
	 * ------------------------------------------------------------------ */

	/**
	 * Ligne de la base vers l'interface.
	 *
	 * @param object $r Ligne.
	 * @return array
	 */
	private static function payout_out( $r ) {
		return array(
			'id'         => (int) $r->id,
			'accountId'  => (int) $r->account_id,
			'date'       => $r->date,
			'gross'      => (float) $r->montant_brut,
			'share'      => (float) $r->part_trader,
			'fees'       => (float) $r->frais,
			'method'     => $r->moyen,
			'conversion' => $r->conversion,
		);
	}

	/**
	 * Retraits, du plus récent au plus ancien.
	 *
	 * @return array
	 */
	public static function payouts() {
		global $wpdb;
		$t = CX_Install::table( 'payouts' );
		return array_map( array( __CLASS__, 'payout_out' ), $wpdb->get_results( "SELECT * FROM $t ORDER BY date DESC, id DESC" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Crée ou modifie un retrait.
	 *
	 * @param array    $in Données de l'interface.
	 * @param int|null $id Identifiant pour une modification.
	 * @return array|WP_Error
	 */
	public static function save_payout( $in, $id = null ) {
		global $wpdb;
		$t = CX_Install::table( 'payouts' );
		if ( $id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE id = %d", $id ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
			return new WP_Error( 'cx_not_found', 'Retrait introuvable.', array( 'status' => 404 ) );
		}
		$account = (int) ( $in['accountId'] ?? 0 );
		if ( ! self::account( $account ) ) {
			return self::invalid( 'Choisissez un compte valide.' );
		}
		$date  = self::date( $in['date'] ?? '' );
		$share = self::num( $in['share'] ?? null, 0, 1e8 );
		if ( ! $date || null === $share ) {
			return self::invalid( 'Indiquez la date et la part reçue.' );
		}
		$row = array(
			'account_id'   => $account,
			'date'         => $date,
			'montant_brut' => self::num( $in['gross'] ?? null, 0, 1e8 ) ?? $share,
			'part_trader'  => $share,
			'frais'        => self::num( $in['fees'] ?? null, 0, 1e8 ) ?? 0,
			'moyen'        => self::text( $in['method'] ?? '', 50 ),
			'conversion'   => self::textarea( $in['conversion'] ?? '', 2000 ),
		);
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ) );
		} else {
			$row['created_at'] = self::now();
			$wpdb->insert( $t, $row );
			$id = (int) $wpdb->insert_id;
		}
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $r ? self::payout_out( $r ) : new WP_Error( 'cx_db', 'Enregistrement impossible.', array( 'status' => 500 ) );
	}

	/**
	 * Supprime un retrait.
	 *
	 * @param int $id Identifiant.
	 * @return bool
	 */
	public static function delete_payout( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( CX_Install::table( 'payouts' ), array( 'id' => $id ) );
	}
}
