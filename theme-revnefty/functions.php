<?php
/**
 * RevNefty — fonctions du thème.
 *
 * @package RevNefty
 */

defined( 'ABSPATH' ) || exit;

define( 'RN_VERSION', '1.2.1' );
define( 'RN_DIR', get_template_directory() );
define( 'RN_URI', get_template_directory_uri() );

require RN_DIR . '/inc/helpers.php';
require RN_DIR . '/inc/icons.php';
require RN_DIR . '/inc/setup.php';
require RN_DIR . '/inc/customizer.php';
require RN_DIR . '/inc/enqueue.php';
require RN_DIR . '/inc/template-tags.php';
