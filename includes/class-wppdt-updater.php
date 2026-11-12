<?php
/**
 * Self-hosted update support.
 *
 * Lets the plugin update itself from its GitHub repository (via tags/releases)
 * instead of the WordPress.org directory, and enables automatic updates on
 * every site so a new tag rolls out to the whole fleet.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the Plugin Update Checker library to the GitHub repository.
 *
 * @since 1.1.0
 */
class WPPDT_Updater {

	/**
	 * GitHub repository that hosts the plugin.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const REPOSITORY = 'https://github.com/muktoapb/wppd-toolkit/';

	/**
	 * Branch used as the update detection base.
	 *
	 * With this branch, the checker prefers the latest GitHub release, then the
	 * highest version tag, then the branch itself — so pushing a version tag is
	 * enough to publish an update.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const BRANCH = 'main';

	/**
	 * The update checker instance.
	 *
	 * @since 1.1.0
	 * @var object|null
	 */
	private $checker = null;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Loads the library and registers the update checker.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function setup() {
		$library = WPPDT_PLUGIN_DIR . 'includes/lib/plugin-update-checker/plugin-update-checker.php';

		if ( ! is_readable( $library ) ) {
			return;
		}

		require_once $library;

		if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v6\\PucFactory' ) ) {
			return;
		}

		$this->checker = \YahnisElsts\PluginUpdateChecker\v6\PucFactory::buildUpdateChecker(
			self::REPOSITORY,
			WPPDT_PLUGIN_FILE,
			'wp-pro-devs-toolkit'
		);

		if ( method_exists( $this->checker, 'setBranch' ) ) {
			$this->checker->setBranch( self::BRANCH );
		}

		add_filter( 'auto_update_plugin', array( $this, 'force_auto_update' ), 10, 2 );
	}

	/**
	 * Forces automatic updates for this plugin on every site.
	 *
	 * Define the WPPDT_DISABLE_AUTOUPDATE constant (true) in wp-config.php to
	 * opt a specific site out and fall back to the normal manual/per-site
	 * behaviour.
	 *
	 * @since 1.1.0
	 *
	 * @param bool|null $update Whether to auto-update. Null means "use default".
	 * @param object    $item   The update offer object.
	 * @return bool|null
	 */
	public function force_auto_update( $update, $item ) {
		if ( defined( 'WPPDT_DISABLE_AUTOUPDATE' ) && WPPDT_DISABLE_AUTOUPDATE ) {
			return $update;
		}

		if ( isset( $item->plugin ) && WPPDT_PLUGIN_BASENAME === $item->plugin ) {
			return true;
		}

		return $update;
	}
}
