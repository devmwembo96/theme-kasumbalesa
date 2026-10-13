<?php
/**
 * Kasumbalesa — fonctions du thème.
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

define( 'KB_VERSION', '1.0.0' );
define( 'KB_DIR', get_template_directory() );
define( 'KB_URI', get_template_directory_uri() );

require KB_DIR . '/inc/helpers.php';
require KB_DIR . '/inc/icons.php';
require KB_DIR . '/inc/setup.php';
require KB_DIR . '/inc/customizer.php';
require KB_DIR . '/inc/enqueue.php';
require KB_DIR . '/inc/template-tags.php';
require KB_DIR . '/inc/seo.php';
require KB_DIR . '/inc/site-setup.php';
