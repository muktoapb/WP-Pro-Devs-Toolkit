<?php
/**
 * Plugin Name:       WP Pro Devs Toolkit
 * Plugin URI:        https://wpprodevs.com/
 * Description:       A collection of common features and site enhancements used across WP ProDevs managed WordPress websites. Each feature can be enabled or disabled individually per site.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mukto
 * Author URI:        https://mukto.info
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-pro-devs-toolkit
 * Domain Path:       /languages
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WPPDT_VERSION', '1.0.1' );
define( 'WPPDT_PLUGIN_FILE', __FILE__ );
define( 'WPPDT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPPDT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPPDT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WPPDT_PLUGIN_DIR . 'includes/class-wppdt-plugin.php';

register_activation_hook( __FILE__, array( 'WPPDT_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPPDT_Plugin', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @since 1.0.0
 *
 * @return WPPDT_Plugin
 */
function wppdt() {
	return WPPDT_Plugin::instance();
}

// Boot the plugin.
wppdt();
