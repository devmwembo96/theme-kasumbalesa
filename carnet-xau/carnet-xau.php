<?php
/**
 * Plugin Name: Carnet XAU
 * Description: Carnet de trading XAUUSD pour les challenges de prop firm : calcul du jour, plan hebdomadaire, journal, statistiques, comptes et retraits ; réception MT5 et TradingView, alertes Telegram. Page hors du site : absente des menus, de la recherche et des plans du site, jamais indexée.
 * Version: 2.36.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Text Domain: carnet-xau
 *
 * @package CarnetXAU
 */

defined( 'ABSPATH' ) || exit;

define( 'CX_VERSION', '2.36.0' );
define( 'CX_DB_VERSION', '6' );
define( 'CX_FILE', __FILE__ );
define( 'CX_DIR', plugin_dir_path( __FILE__ ) );
define( 'CX_URL', plugin_dir_url( __FILE__ ) );
define( 'CX_CAP', 'manage_carnet_xau' );

require CX_DIR . 'includes/class-cx-install.php';
require CX_DIR . 'includes/class-cx-repo.php';
require CX_DIR . 'includes/class-cx-rest.php';
require CX_DIR . 'includes/class-cx-export.php';
require CX_DIR . 'includes/class-cx-front.php';
require CX_DIR . 'includes/class-cx-guard.php';
require CX_DIR . 'includes/class-cx-ingest.php';
require CX_DIR . 'includes/class-cx-telegram.php';
require CX_DIR . 'includes/class-cx-plan.php';
require CX_DIR . 'includes/class-cx-live.php';
require CX_DIR . 'includes/class-cx-calendar.php';
require CX_DIR . 'includes/class-cx-report.php';
require CX_DIR . 'includes/class-cx-update.php';
require CX_DIR . 'includes/class-cx-tuning.php';
require CX_DIR . 'includes/class-cx-score.php';
require CX_DIR . 'includes/class-cx-chart.php';
require CX_DIR . 'includes/class-cx-coach.php';
require CX_DIR . 'includes/class-cx-news.php';
require CX_DIR . 'includes/class-cx-edge.php';
require CX_DIR . 'includes/class-cx-value.php';

register_activation_hook( __FILE__, array( 'CX_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CX_Install', 'deactivate' ) );

// Sur « init » : les types de contenu doivent exister pour mettre la page à jour.
add_action( 'init', array( 'CX_Install', 'maybe_upgrade' ), 20 );
CX_Rest::init();
CX_Front::init();
CX_Ingest::init();
CX_Telegram::init();
CX_Plan::init();
CX_Live::init();
CX_Calendar::init();
CX_Report::init();
CX_Update::init();
CX_Tuning::init();
CX_Score::init();
CX_Chart::init();
CX_Coach::init();
CX_News::init();
CX_Edge::init();
CX_Value::init();
