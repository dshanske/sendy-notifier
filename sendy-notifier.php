<?php
/**
 * Main Loader for Sendy Notifier
 *
 * @package           SendyNotifier
 * @author            David Shanske
 * @version           1.0.0
 * @wordpress-plugin
 * Plugin Name:       Sendy Notifier
 * Description:       Advanced modular suite for Sendy integration with dynamic visual previews, manual triggers, and full lead-gen customization.
 * Version:           1.0.0
 * Author:            David Shanske
 * Text Domain:       sendy-notifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load modular components.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpsn-core.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpsn-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpsn-frontend.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpsn-renderer.php';

/**
 * Initializes the rebranded plugin suite.
 */
function sendy_notifier_init() {
	new WPSN_Core();
	new WPSN_Admin();
	new WPSN_Frontend();
}
add_action( 'plugins_loaded', 'sendy_notifier_init' );
