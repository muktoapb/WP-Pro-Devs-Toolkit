<?php
/**
 * Main plugin orchestrator.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin class.
 *
 * Wires together the module system and the admin interface.
 *
 * @since 1.0.0
 */
final class WPPDT_Plugin {

	/**
	 * Single plugin instance.
	 *
	 * @since 1.0.0
	 * @var WPPDT_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Module manager.
	 *
	 * @since 1.0.0
	 * @var WPPDT_Modules|null
	 */
	public $modules = null;

	/**
	 * Admin handler.
	 *
	 * @since 1.0.0
	 * @var WPPDT_Admin|null
	 */
	public $admin = null;

	/**
	 * REST API controller.
	 *
	 * @since 1.0.0
	 * @var WPPDT_REST_Controller|null
	 */
	public $rest = null;

	/**
	 * Retrieves the single plugin instance.
	 *
	 * @since 1.0.0
	 *
	 * @return WPPDT_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Loads required files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function includes() {
		require_once WPPDT_PLUGIN_DIR . 'includes/class-wppdt-module.php';
		require_once WPPDT_PLUGIN_DIR . 'includes/class-wppdt-modules.php';
		require_once WPPDT_PLUGIN_DIR . 'includes/class-wppdt-rest-controller.php';

		if ( is_admin() ) {
			require_once WPPDT_PLUGIN_DIR . 'admin/class-wppdt-admin.php';
		}
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wp-pro-devs-toolkit',
			false,
			dirname( WPPDT_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initializes the module system and admin interface.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {
		$this->modules = new WPPDT_Modules();
		$this->modules->load();

		$this->rest = new WPPDT_REST_Controller( $this->modules );

		if ( is_admin() ) {
			$this->admin = new WPPDT_Admin( $this->modules );
		}

		/**
		 * Fires after the plugin has fully initialized.
		 *
		 * @since 1.0.0
		 *
		 * @param WPPDT_Plugin $plugin The plugin instance.
		 */
		do_action( 'wppdt_loaded', $this );
	}

	/**
	 * Runs on plugin activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( WPPDT_Modules::OPTION, false ) ) {
			add_option( WPPDT_Modules::OPTION, array() );
		}
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Reserved for cleanup of scheduled events registered by modules.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function deactivate() {
		/**
		 * Fires on plugin deactivation so modules can clean up.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wppdt_deactivate' );
	}
}
