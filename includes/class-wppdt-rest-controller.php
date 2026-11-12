<?php
/**
 * REST API controller for the settings screen.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the module list and enabled state to the React admin app.
 *
 * @since 1.0.0
 */
class WPPDT_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const REST_NAMESPACE = 'wppdt/v1';

	/**
	 * Module manager.
	 *
	 * @since 1.0.0
	 * @var WPPDT_Modules
	 */
	private $modules;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param WPPDT_Modules $modules Module manager.
	 */
	public function __construct( WPPDT_Modules $modules ) {
		$this->modules = $modules;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers REST routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'enabled' => array(
							'type'              => 'array',
							'required'          => true,
							'items'             => array( 'type' => 'string' ),
							'sanitize_callback' => array( $this, 'sanitize_ids' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check for the settings routes.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Sanitizes an array of module IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	public function sanitize_ids( $value ) {
		return array_map( 'sanitize_key', (array) $value );
	}

	/**
	 * Returns the current settings.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response(
			array(
				'modules' => $this->module_list(),
				'enabled' => $this->modules->get_enabled(),
			)
		);
	}

	/**
	 * Saves the enabled modules.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ) {
		$registered = array_keys( $this->modules->get_registered() );
		$submitted  = (array) $request->get_param( 'enabled' );
		$enabled    = array_values( array_intersect( $registered, $submitted ) );

		update_option( WPPDT_Modules::OPTION, $enabled );

		return rest_ensure_response(
			array(
				'modules' => $this->module_list(),
				'enabled' => $enabled,
				'saved'   => true,
			)
		);
	}

	/**
	 * Builds a client-friendly list of registered modules.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function module_list() {
		$list = array();

		foreach ( $this->modules->get_registered() as $id => $args ) {
			$list[] = array(
				'id'          => $id,
				'label'       => isset( $args['label'] ) ? $args['label'] : $id,
				'description' => isset( $args['description'] ) ? $args['description'] : '',
			);
		}

		return $list;
	}
}
