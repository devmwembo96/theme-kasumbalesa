<?php
/**
 * Désinstallation : supprime les tables, les options, la page, la capacité et le rôle.
 *
 * Les captures restent dans la médiathèque. Faites une sauvegarde JSON avant (onglet Comptes).
 *
 * @package CarnetXAU
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

foreach ( array( 'accounts', 'trades', 'plans', 'payouts', 'signals', 'snapshots', 'csignals' ) as $cx_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cx_' . $cx_table ); // phpcs:ignore WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery
}

$cx_page = (int) get_option( 'cx_page_id' );
if ( $cx_page ) {
	wp_delete_post( $cx_page, true );
}

delete_option( 'cx_page_id' );
delete_option( 'cx_db_version' );
delete_option( 'cx_version' );
delete_option( 'cx_settings' );
delete_option( 'cx_refusals' );
delete_option( 'cx_once' );
delete_option( 'cx_last_prune' );
delete_option( 'cx_candles' );
delete_option( 'cx_candles_ref' );
foreach ( (array) $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'cx\\_candles\\_%'" ) as $cx_opt ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	delete_option( $cx_opt );
}
delete_option( 'cx_calendar' );
wp_clear_scheduled_hook( 'cx_cron' );

foreach ( wp_roles()->role_objects as $cx_role ) {
	$cx_role->remove_cap( 'manage_carnet_xau' );
}
remove_role( 'cx_trader' );
