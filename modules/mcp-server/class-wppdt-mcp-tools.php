<?php
/**
 * MCP tool registry (group-aware).
 *
 * Tools are organized into groups (WordPress Core, Elementor, SCF/ACF, Contact
 * Form 7, Gravity Forms, ...). A tool is only exposed when its group is both
 * available (the required plugin is active) and enabled (toggled on by an
 * admin).
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/class-wppdt-mcp-tool-group.php';
require_once dirname( __FILE__ ) . '/class-wppdt-mcp-groups.php';

/**
 * Registers tool groups and executes calls.
 *
 * @since 1.1.0
 */
class WPPDT_MCP_Tools {

	/**
	 * Groups keyed by ID.
	 *
	 * @since 1.1.0
	 * @var WPPDT_MCP_Tool_Group[]
	 */
	private $groups = array();

	/**
	 * Admin enable state per group ID.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private $enabled;

	/**
	 * Constructor.
	 *
	 * @param array $enabled Map of group ID => bool (from settings).
	 */
	public function __construct( $enabled = array() ) {
		$this->enabled = is_array( $enabled ) ? $enabled : array();
		$this->register_groups();
	}

	/**
	 * Builds the group registry.
	 *
	 * @return void
	 */
	private function register_groups() {
		$groups = array(
			new WPPDT_MCP_Group_Core(),
			new WPPDT_MCP_Group_Elementor(),
			new WPPDT_MCP_Group_ACF(),
			new WPPDT_MCP_Group_CF7(),
			new WPPDT_MCP_Group_GravityForms(),
		);

		/**
		 * Filters the MCP tool groups.
		 *
		 * @since 1.1.0
		 *
		 * @param WPPDT_MCP_Tool_Group[] $groups Group instances.
		 */
		$groups = apply_filters( 'wppdt_mcp_tool_groups', $groups );

		foreach ( $groups as $group ) {
			if ( $group instanceof WPPDT_MCP_Tool_Group ) {
				$this->groups[ $group->id() ] = $group;
			}
		}
	}

	/**
	 * Whether a group is toggled on. Core defaults on, others off.
	 *
	 * @param string $id Group ID.
	 * @return bool
	 */
	public function is_group_enabled( $id ) {
		if ( array_key_exists( $id, $this->enabled ) ) {
			return (bool) $this->enabled[ $id ];
		}

		return 'core' === $id;
	}

	/**
	 * Whether a group's tools are actually exposed (available + enabled).
	 *
	 * @param WPPDT_MCP_Tool_Group $group Group.
	 * @return bool
	 */
	private function is_effective( WPPDT_MCP_Tool_Group $group ) {
		return $group->is_available() && $this->is_group_enabled( $group->id() );
	}

	/**
	 * Group metadata for the admin screen.
	 *
	 * @return array
	 */
	public function get_groups_meta() {
		$meta = array();

		foreach ( $this->groups as $group ) {
			$meta[] = array(
				'id'        => $group->id(),
				'label'     => $group->label(),
				'requires'  => $group->requires(),
				'available' => $group->is_available(),
				'enabled'   => $this->is_group_enabled( $group->id() ),
				'tools'     => array_keys( $group->tools() ),
			);
		}

		return $meta;
	}

	/**
	 * Tool definitions for tools/list (effective groups only).
	 *
	 * @return array
	 */
	public function get_definitions() {
		$definitions = array();

		foreach ( $this->groups as $group ) {
			if ( ! $this->is_effective( $group ) ) {
				continue;
			}
			foreach ( $group->tools() as $name => $tool ) {
				$definitions[] = array(
					'name'        => $name,
					'description' => $tool['description'],
					'inputSchema' => $tool['inputSchema'],
				);
			}
		}

		return $definitions;
	}

	/**
	 * Names of all currently exposed tools.
	 *
	 * @return string[]
	 */
	public function get_names() {
		return array_column( $this->get_definitions(), 'name' );
	}

	/**
	 * Executes a tool call and returns an MCP tool result.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Arguments.
	 * @return array
	 */
	public function call( $name, $args ) {
		$args = is_array( $args ) ? $args : array();

		foreach ( $this->groups as $group ) {
			$tools = $group->tools();
			if ( ! isset( $tools[ $name ] ) ) {
				continue;
			}

			if ( ! $this->is_effective( $group ) ) {
				return $this->error_result(
					$group->is_available()
						? sprintf( 'The "%s" tool group is turned off.', $group->label() )
						: sprintf( 'The "%s" tool group is unavailable (%s is not active).', $group->label(), $group->requires() )
				);
			}

			try {
				$data = call_user_func( $tools[ $name ]['handler'], $args );
				if ( is_wp_error( $data ) ) {
					return $this->error_result( $data->get_error_message() );
				}
				return $this->text_result( $data );
			} catch ( \Throwable $e ) {
				return $this->error_result( $e->getMessage() );
			}
		}

		return $this->error_result( sprintf( 'Unknown tool: %s', $name ) );
	}

	/**
	 * Wraps data as a successful MCP text result.
	 *
	 * @param mixed $data Scalar string or structured data.
	 * @return array
	 */
	private function text_result( $data ) {
		$text = is_string( $data )
			? $data
			: wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $text,
				),
			),
		);
	}

	/**
	 * Builds an MCP error result.
	 *
	 * @param string $message Message.
	 * @return array
	 */
	private function error_result( $message ) {
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError' => true,
		);
	}
}
