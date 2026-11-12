<?php
/**
 * Uninstall routine.
 *
 * Runs when the plugin is deleted from the WordPress admin. Removes the
 * plugin's own options. Individual modules are responsible for cleaning up
 * any data they create via the `wppdt_uninstall` hook.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wppdt_enabled_modules' );
delete_option( 'wppdt_mcp_settings' );
delete_option( 'wppdt_mcp_audit' );
delete_option( 'wppdt_mcp_oauth' );

/**
 * Fires during uninstall so modules can remove their own data.
 *
 * @since 1.0.0
 */
do_action( 'wppdt_uninstall' );
