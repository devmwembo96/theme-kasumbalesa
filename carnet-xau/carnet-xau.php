<?php
/**
 * Plugin Name: Carnet XAU
 * Description: Carnet de trading XAUUSD pour les challenges de prop firm : calcul du jour, plan hebdomadaire, journal, statistiques, comptes et retraits. Page hors du site : absente des menus, de la recherche et des plans du site, jamais indexée.
 * Version: 1.1.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Text Domain: carnet-xau
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

define( 'CX_VERSION', '1.1.0' );
define( 'CX_DB_VERSION', '2' );
define( 'CX_FILE', __FILE__ );
define( 'CX_DIR', plugin_dir_path( __FILE__ ) );
define( 'CX_URL', plugin_dir_url( __FILE__ ) );
define( 'CX_CAP', 'manage_carnet_xau' );

require CX_DIR . 'includes/class-cx-install.php';
require CX_DIR . 'includes/class-cx-repo.php';
require CX_DIR . 'includes/class-cx-rest.php';
require CX_DIR . 'includes/class-cx-export.php';
require CX_DIR . 'includes/class-cx-front.php';

register_activation_hook( __FILE__, array( 'CX_Install', 'activate' ) );

// Sur « init » : les types de contenu doivent exister pour mettre la page à jour.
add_action( 'init', array( 'CX_Install', 'maybe_upgrade' ), 20 );
CX_Rest::init();
CX_Front::init();
