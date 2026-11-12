<?php
/**
 * Feature module manager.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers available feature modules and boots the enabled ones.
 *
 * @since 1.0.0
 */
class WPPDT_Modules {

	/**
	 * Option key storing the list of enabled module IDs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION = 'wppdt_enabled_modules';

	/**
	 * Registered modules keyed by module ID.
	 *
	 * Each entry is an array with the keys: label, description, file, class.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $registered = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_modules();
	}

	/**
	 * Builds the registry of available modules.
	 *
	 * Features are added here as they are developed. Each entry maps a unique
	 * module ID to its metadata and the class that implements it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_modules() {
		$modules = array(
			'wpvivid-subfolders' => array(
				'label'       => __( 'WPVivid Backup Subfolders', 'wp-pro-devs-toolkit' ),
				'description' => __( 'Organize WPVivid remote backups into a per-site subfolder named after your site domain (e.g. wpvivid_backup/example.com). Works with Google Drive, Dropbox, Amazon S3, S3-compatible, FTP and SFTP storage.', 'wp-pro-devs-toolkit' ),
				'file'        => WPPDT_PLUGIN_DIR . 'modules/wpvivid-subfolders/class-wppdt-wpvivid-subfolders.php',
				'class'       => 'WPPDT_WPVivid_Subfolders',
			),
		);

		/**
		 * Filters the list of registered modules.
		 *
		 * @since 1.0.0
		 *
		 * @param array $modules Registered modules keyed by module ID.
		 */
		$this->registered = apply_filters( 'wppdt_registered_modules', $modules );
	}

	/**
	 * Returns all registered modules.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_registered() {
		return $this->registered;
	}

	/**
	 * Returns the list of enabled module IDs.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_enabled() {
		$enabled = get_option( self::OPTION, array() );

		return is_array( $enabled ) ? $enabled : array();
	}

	/**
	 * Determines whether a module is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Module ID.
	 * @return bool
	 */
	public function is_enabled( $id ) {
		return in_array( $id, $this->get_enabled(), true );
	}

	/**
	 * Boots every enabled module.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load() {
		foreach ( $this->registered as $id => $args ) {
			if ( ! $this->is_enabled( $id ) ) {
				continue;
			}

			$instance = $this->instantiate( $args );

			if ( $instance instanceof WPPDT_Module ) {
				$instance->init();
			}
		}
	}

	/**
	 * Loads a module file and instantiates its class.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Module registration arguments.
	 * @return WPPDT_Module|null
	 */
	private function instantiate( $args ) {
		if ( empty( $args['class'] ) ) {
			return null;
		}

		if ( ! empty( $args['file'] ) && is_readable( $args['file'] ) ) {
			require_once $args['file'];
		}

		if ( ! class_exists( $args['class'] ) ) {
			return null;
		}

		return new $args['class']();
	}
}
