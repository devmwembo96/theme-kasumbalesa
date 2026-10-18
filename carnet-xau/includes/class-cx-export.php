<?php
/**
 * Exports : CSV des trades (Excel français) et sauvegarde JSON complète.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Téléchargements.
 */
class CX_Export {

	/**
	 * En-têtes de téléchargement.
	 *
	 * @param string $type     Type MIME.
	 * @param string $filename Nom du fichier.
	 */
	private static function headers( $type, $filename ) {
		nocache_headers();
		header( 'Content-Type: ' . $type . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );
	}

	/**
	 * Neutralise les formules dans une cellule (=, +, -, @ en tête de texte).
	 *
	 * @param mixed $v Valeur.
	 * @return string
	 */
	private static function cell( $v ) {
		if ( is_bool( $v ) ) {
			return $v ? 'oui' : 'non';
		}
		if ( is_float( $v ) || is_int( $v ) ) {
			return str_replace( '.', ',', (string) $v );
		}
		$v = (string) $v;
		return ( '' !== $v && preg_match( '/^[=+\-@\t\r]/', $v ) ) ? "'" . $v : $v;
	}

	/**
	 * CSV des trades, séparateur « ; », virgule décimale, BOM UTF-8.
	 *
	 * @param int $account_id 0 pour tous les comptes.
	 */
	public static function csv( $account_id = 0 ) {
		$names = array();
		foreach ( CX_Repo::accounts() as $a ) {
			$names[ $a['id'] ] = $a['name'];
		}
		self::headers( 'text/csv', 'carnet-xau-trades-' . CX_Repo::clock( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'Compte', 'Date', 'Heure', 'Sens', 'Setup', 'Niveau', 'Stop ($)', 'Lot', 'Risque ($)', 'Résultat (R)', 'Résultat ($)', 'Sur niveau clé', 'Avec le biais', 'Règles respectées', 'Émotion', 'Capture', 'Lien', 'Notes' ), ';' );
		foreach ( CX_Repo::trades( $account_id ) as $t ) {
			$row = array(
				$names[ $t['accountId'] ] ?? '',
				$t['date'],
				$t['time'],
				$t['dir'],
				'hors' === $t['setup'] ? 'Hors setup' : 'Setup ' . $t['setup'],
				$t['level'],
				$t['stop'] ?? '',
				$t['lot'] ?? '',
				$t['risk'] ?? '',
				$t['r'],
				$t['pnl'] ?? '',
				$t['onLevel'],
				$t['withBias'],
				$t['rules'],
				$t['emotion'],
				$t['captureUrl'],
				$t['link'],
				$t['notes'],
			);
			fputcsv( $out, array_map( array( __CLASS__, 'cell' ), $row ), ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Sauvegarde complète : comptes, trades, plans, retraits.
	 */
	public static function json() {
		self::headers( 'application/json', 'carnet-xau-sauvegarde-' . CX_Repo::clock( 'Y-m-d' ) . '.json' );
		echo wp_json_encode(
			array(
				'plugin'     => 'carnet-xau',
				'version'    => CX_VERSION,
				'exportedAt' => CX_Repo::clock( 'c' ),
				'accounts'   => CX_Repo::accounts(),
				'trades'     => CX_Repo::trades(),
				'plans'      => CX_Repo::plans(),
				'payouts'    => CX_Repo::payouts(),
				'claudeSignals' => CX_Live::list_signals()->get_data(),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		exit;
	}
}
