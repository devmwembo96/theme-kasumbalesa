<?php
/**
 * Instrument tradé par le carnet : le Nasdaq 100 (CFD NAS100 chez FundedNext ; US100, USTEC, NDX ou NQ
 * selon le courtier). Tout ce qui dépend de l'actif passe par cette classe : nom, symboles acceptés,
 * séance de New York, valeur d'un point par lot (lue sur les bougies envoyées par l'EA), et début de
 * l'ère NAS100 : les données d'avant (l'or, abandonné le 06/11/2026) restent en base mais n'entrent plus
 * dans aucune analyse, statistique ni écran.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

class CX_Market {

	const NAME    = 'NAS100';
	const LABEL   = 'Nasdaq 100 (NAS100)';
	const BRAND   = 'Carnet NAS100';
	const UNIT    = 'pts';
	const PATTERN = '/NAS100|US100|USTEC|NDX|NQ/i';

	/** Plans de l'or archivés : account_id réservé (colonne non signée), jamais lu par le carnet. */
	const ARCHIVE = 999999999;

	/** Début de l'ère NAS100 (heure de Lubumbashi). */
	const EPOCH = '2026-11-06 00:00:00';

	/** Séance de New York (heure de New York) : la seule séance tradée. */
	const RTH_OPEN  = '09:30';
	const RTH_CLOSE = '16:00';

	/** Stop minimum (points d'indice) : plancher technique au-dessus du spread ; pas de maximum imposé. */
	const STOP_MIN = 2.0;
	const STOP_MAX = 100000.0;

	/** @return bool Symbole du Nasdaq 100. */
	public static function is_symbol( $s ) {
		return (bool) preg_match( self::PATTERN, (string) $s );
	}

	/** @return string Début de l'ère NAS100, « AAAA-MM-JJ HH:MM:SS » (heure du carnet). */
	public static function epoch() {
		return self::EPOCH;
	}

	/** @return int Début de l'ère NAS100 (Unix). */
	public static function epoch_ts() {
		return ( new DateTimeImmutable( self::EPOCH, CX_Repo::tz() ) )->getTimestamp();
	}

	/** @return string Lundi de la première semaine NAS100. */
	public static function epoch_week() {
		return CX_Telegram::monday( new DateTimeImmutable( self::EPOCH, CX_Repo::tz() ) );
	}

	/** Une date « AAAA-MM-JJ… » appartient-elle à l'ère NAS100 ? */
	public static function in_era( $date ) {
		return '' !== (string) $date && strcmp( (string) $date, self::EPOCH ) >= 0;
	}

	/**
	 * Caractéristiques du contrat envoyées par l'EA (ligne SPEC des bougies) pour un compte.
	 *
	 * @param int $id Compte (0 : compte de référence).
	 * @return array {symbol, digits, point, tick_size, tick_value, contract, vol_min, vol_step}
	 */
	public static function spec( $id = 0 ) {
		$d = CX_Live::candle_data( $id );
		return is_array( $d['spec'] ?? null ) ? $d['spec'] : array();
	}

	/**
	 * Valeur d'un point d'indice pour 1 lot ($), d'après le courtier ; à défaut le réglage du compte.
	 *
	 * @param int   $id       Compte.
	 * @param float $fallback Valeur du réglage « contract » du compte.
	 * @return float
	 */
	public static function point_value( $id = 0, $fallback = 1.0 ) {
		$s = self::spec( $id );
		if ( ! empty( $s['tick_size'] ) && ! empty( $s['tick_value'] ) && $s['tick_size'] > 0 ) {
			return round( (float) $s['tick_value'] / (float) $s['tick_size'], 6 );
		}
		return max( 0.0001, (float) $fallback );
	}

	/** Points d'indice lisibles : « 42,5 pts ». */
	public static function pts( $v, $dec = 1 ) {
		return number_format( (float) $v, $dec, ',', ' ' ) . ' ' . self::UNIT;
	}
}
