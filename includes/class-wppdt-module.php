<?php
/**
 * Base class for feature modules.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract feature module.
 *
 * Every feature of the plugin extends this class. The {@see WPPDT_Modules}
 * manager only instantiates and boots a module when it has been enabled on
 * the settings screen, so each module's hooks stay dormant until needed.
 *
 * @since 1.0.0
 */
abstract class WPPDT_Module {

	/**
	 * Boots the module by registering its WordPress hooks.
	 *
	 * Called only when the module is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	abstract public function init();
}
