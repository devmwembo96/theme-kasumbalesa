<?php
/**
 * Installation : tables, capacité, rôle, page privée et compte par défaut.
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

/**
 * Création et mise à jour du schéma.
 */
class CX_Install {

	/**
	 * Paramètres de risque par défaut (FundedNext Stellar 2-Step, 6 000 $).
	 */
	const DEFAULT_PARAMS = array(
		't1'        => 8,
		't2'        => 5,
		'tf'        => 4.5,
		'daily'     => 5,
		'max'       => 10,
		'mindays'   => 5,
		'risk'      => 50,
		'riskred'   => 35,
		'redat'     => 300,
		'maxtrades' => 4,
		'maxloss'   => 3,
		'spreadmax' => 15,
		'contract'  => 100,
	);

	/**
	 * Noms complets des tables.
	 *
	 * @param string $name accounts, trades, plans ou payouts.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'cx_' . $name;
	}

	/**
	 * Activation du plugin.
	 */
	public static function activate() {
		self::create_tables();
		self::add_caps();
		self::ensure_page();
		self::ensure_default_account();
		update_option( 'cx_db_version', CX_DB_VERSION, false );
	}

	/**
	 * Met le schéma à jour après une nouvelle version du plugin.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'cx_db_version' ) !== CX_DB_VERSION ) {
			self::create_tables();
			self::add_caps();
			update_option( 'cx_db_version', CX_DB_VERSION, false );
		}
	}

	/**
	 * Crée ou met à jour les tables (dbDelta).
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$c        = $wpdb->get_charset_collate();
		$accounts = self::table( 'accounts' );
		$trades   = self::table( 'trades' );
		$plans    = self::table( 'plans' );
		$payouts  = self::table( 'payouts' );

		dbDelta(
			"CREATE TABLE $accounts (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  nom varchar(100) NOT NULL DEFAULT '',
  prop_firm varchar(100) NOT NULL DEFAULT '',
  modele varchar(100) NOT NULL DEFAULT '',
  taille decimal(12,2) NOT NULL DEFAULT 0,
  prix_challenge decimal(12,2) NOT NULL DEFAULT 0,
  statut varchar(10) NOT NULL DEFAULT 'phase1',
  date_achat date DEFAULT NULL,
  phase_debut date DEFAULT NULL,
  parametres longtext NOT NULL,
  actif tinyint(1) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY statut (statut)
) $c;
CREATE TABLE $trades (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  account_id bigint(20) unsigned NOT NULL DEFAULT 0,
  date date NOT NULL,
  heure time DEFAULT NULL,
  sens varchar(5) NOT NULL DEFAULT 'achat',
  setup varchar(4) NOT NULL DEFAULT '1',
  niveau varchar(20) NOT NULL DEFAULT '',
  stop_usd decimal(12,2) DEFAULT NULL,
  lot decimal(8,2) DEFAULT NULL,
  risque_usd decimal(12,2) DEFAULT NULL,
  resultat_r decimal(6,2) NOT NULL DEFAULT 0,
  resultat_usd decimal(12,2) DEFAULT NULL,
  sur_niveau_cle tinyint(1) NOT NULL DEFAULT 0,
  avec_biais tinyint(1) NOT NULL DEFAULT 0,
  regles_respectees tinyint(1) NOT NULL DEFAULT 0,
  emotion varchar(10) DEFAULT NULL,
  capture_id bigint(20) unsigned DEFAULT NULL,
  lien varchar(500) NOT NULL DEFAULT '',
  notes text NOT NULL,
  source varchar(6) NOT NULL DEFAULT 'manuel',
  ticket_mt5 varchar(32) DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ticket_mt5 (ticket_mt5),
  KEY account_id (account_id),
  KEY date (date)
) $c;
CREATE TABLE $plans (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  account_id bigint(20) unsigned NOT NULL DEFAULT 0,
  semaine date NOT NULL,
  biais varchar(1) NOT NULL DEFAULT '',
  plus_haut decimal(10,2) DEFAULT NULL,
  plus_bas decimal(10,2) DEFAULT NULL,
  structure text NOT NULL,
  fondamentaux longtext NOT NULL,
  fond_notes text NOT NULL,
  niveaux longtext NOT NULL,
  annonces longtext NOT NULL,
  scenarios longtext NOT NULL,
  bilan text NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY account_semaine (account_id,semaine)
) $c;
CREATE TABLE $payouts (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  account_id bigint(20) unsigned NOT NULL DEFAULT 0,
  date date NOT NULL,
  montant_brut decimal(12,2) NOT NULL DEFAULT 0,
  part_trader decimal(12,2) NOT NULL DEFAULT 0,
  frais decimal(12,2) NOT NULL DEFAULT 0,
  moyen varchar(50) NOT NULL DEFAULT '',
  conversion text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY account_id (account_id)
) $c;"
		);
	}

	/**
	 * Capacité pour les administrateurs, et rôle dédié.
	 */
	public static function add_caps() {
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( CX_CAP ) ) {
			$admin->add_cap( CX_CAP );
		}
		if ( ! get_role( 'cx_trader' ) ) {
			// read_private_pages : la page du carnet est privée.
			add_role(
				'cx_trader',
				'Trader Carnet XAU',
				array(
					'read'               => true,
					'read_private_pages' => true,
					'upload_files'       => true,
					CX_CAP               => true,
				)
			);
		}
	}

	/**
	 * Crée la page privée du carnet si elle n'existe plus. N'ajoute rien aux menus.
	 */
	public static function ensure_page() {
		$id = (int) get_option( 'cx_page_id' );
		if ( $id && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
			return;
		}
		$id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'private',
				'post_title'     => 'Carnet XAU',
				'post_name'      => 'carnet-xau',
				'post_content'   => '<!-- wp:shortcode -->[carnet_xau]<!-- /wp:shortcode -->',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			// Modèle « Pleine largeur » du thème actif, s'il existe : pas d'en-tête de page.
			if ( file_exists( get_template_directory() . '/templates/template-full-width.php' ) ) {
				update_post_meta( $id, '_wp_page_template', 'templates/template-full-width.php' );
			}
			update_option( 'cx_page_id', (int) $id, false );
		}
	}

	/**
	 * Premier compte, pour que l'onglet Jour fonctionne dès l'activation.
	 */
	public static function ensure_default_account() {
		global $wpdb;
		$table = self::table( 'accounts' );
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) > 0 ) { // phpcs:ignore WordPress.DB.PreparedSQL -- nom de table interne.
			return;
		}
		CX_Repo::save_account(
			array(
				'name'         => 'Challenge 1',
				'firm'         => 'FundedNext',
				'model'        => 'Stellar 2-Step',
				'size'         => 6000,
				'price'        => 0,
				'status'       => 'phase1',
				'purchaseDate' => current_time( 'Y-m-d' ),
				'phaseStart'   => current_time( 'Y-m-d' ),
				'params'       => self::DEFAULT_PARAMS,
				'active'       => true,
			)
		);
	}
}
