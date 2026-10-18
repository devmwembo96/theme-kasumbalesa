<?php
/**
 * Réglages des connexions et protection des points d'entrée machine (MT5, TradingView,
 * Telegram) : jetons secrets comparés en temps constant, limite de débit simple par IP,
 * journal des refus.
 *
 * Seules les empreintes SHA-256 des jetons MT5 et TradingView sont stockées. Le jeton du bot
 * Telegram est conservé tel quel : il sert à appeler l'API de Telegram.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secrets, limites et journal.
 */
class CX_Guard {

	const OPT       = 'cx_settings';
	const LOG       = 'cx_refusals';
	const MAX_FAILS = 10;

	/**
	 * Réglages enregistrés.
	 *
	 * @return array
	 */
	public static function settings() {
		$s = get_option( self::OPT, array() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Modifie une partie des réglages.
	 *
	 * @param string $key   Clé de premier niveau.
	 * @param mixed  $value Valeur (null supprime la clé).
	 */
	public static function set( $key, $value ) {
		$s = self::settings();
		if ( null === $value ) {
			unset( $s[ $key ] );
		} else {
			$s[ $key ] = $value;
		}
		update_option( self::OPT, $s, false );
	}

	/**
	 * Nouveau jeton aléatoire.
	 *
	 * @param int $len Longueur.
	 * @return string
	 */
	public static function secret( $len = 40 ) {
		return wp_generate_password( $len, false );
	}

	/**
	 * Empreinte d'un jeton.
	 *
	 * @param string $s Jeton.
	 * @return string
	 */
	public static function hash( $s ) {
		return hash( 'sha256', (string) $s );
	}

	/**
	 * Le jeton reçu correspond-il à l'empreinte ? (temps constant)
	 *
	 * @param string $given Jeton reçu.
	 * @param string $hash  Empreinte attendue.
	 * @return bool
	 */
	public static function matches( $given, $hash ) {
		return is_string( $given ) && strlen( $given ) >= 16 && is_string( $hash ) && '' !== $hash && hash_equals( $hash, self::hash( $given ) );
	}

	/**
	 * Adresse IP du client.
	 *
	 * @return string
	 */
	public static function ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Limite de débit : refuse au-delà de $max requêtes par minute et par IP, ou après
	 * trop de jetons invalides.
	 *
	 * @param string $bucket Point d'entrée (mt5, tradingview, telegram).
	 * @param int    $max    Requêtes par minute.
	 * @return WP_Error|null
	 */
	public static function throttle( $bucket, $max ) {
		$ip = md5( self::ip() );
		if ( (int) get_transient( 'cx_rf_' . $bucket . $ip ) >= self::MAX_FAILS ) {
			return new WP_Error( 'cx_rate_limited', 'Trop de tentatives refusées. Réessayez dans un quart d’heure.', array( 'status' => 429 ) );
		}
		$k = 'cx_rl_' . $bucket . $ip;
		$n = (int) get_transient( $k ) + 1;
		set_transient( $k, $n, MINUTE_IN_SECONDS );
		if ( $n > $max ) {
			return new WP_Error( 'cx_rate_limited', 'Trop de requêtes.', array( 'status' => 429 ) );
		}
		return null;
	}

	/**
	 * Refus : journalisé, compté pour la limite, réponse 401.
	 *
	 * @param string $bucket Point d'entrée.
	 * @param string $reason Motif.
	 * @return WP_Error
	 */
	public static function refuse( $bucket, $reason ) {
		$k = 'cx_rf_' . $bucket . md5( self::ip() );
		set_transient( $k, (int) get_transient( $k ) + 1, 15 * MINUTE_IN_SECONDS );
		$log   = (array) get_option( self::LOG, array() );
		$log[] = array(
			'time'   => current_time( 'mysql' ),
			'ip'     => self::ip(),
			'entry'  => $bucket,
			'reason' => $reason,
		);
		update_option( self::LOG, array_slice( $log, -50 ), false );
		return new WP_Error( 'cx_forbidden', 'Jeton invalide.', array( 'status' => 401 ) );
	}

	/**
	 * Derniers refus, du plus récent au plus ancien.
	 *
	 * @return array
	 */
	public static function refusals() {
		return array_reverse( (array) get_option( self::LOG, array() ) );
	}

	/**
	 * Liste de clés déjà traitées (alertes envoyées…), pour ne jamais agir deux fois.
	 *
	 * @param string $key Clé de l'événement.
	 * @return bool true si la clé était nouvelle (et vient d'être notée).
	 */
	public static function once( $key ) {
		$done = (array) get_option( 'cx_once', array() );
		if ( isset( $done[ $key ] ) ) {
			return false;
		}
		$done[ $key ] = time();
		update_option( 'cx_once', array_slice( $done, -300, null, true ), false );
		return true;
	}
}
