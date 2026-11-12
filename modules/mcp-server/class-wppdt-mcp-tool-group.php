<?php
/**
 * Base class for an MCP tool group.
 *
 * A group bundles related tools (e.g. all Elementor tools) behind an
 * availability check (is the required plugin active?) and an admin on/off
 * toggle. Tools in a group are only exposed when the group is both available
 * and enabled.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract tool group.
 *
 * @since 1.1.0
 */
abstract class WPPDT_MCP_Tool_Group {

	/**
	 * Unique group ID (e.g. "elementor").
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Human-readable label.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Name of the plugin this group needs, for display ('' when none).
	 *
	 * @return string
	 */
	public function requires() {
		return '';
	}

	/**
	 * Whether the dependency is present so the group can be used.
	 *
	 * @return bool
	 */
	abstract public function is_available();

	/**
	 * The tools provided by this group.
	 *
	 * @return array Map of tool name => array( description, inputSchema, handler ).
	 */
	abstract public function tools();

	/**
	 * Reads and validates a required post ID argument.
	 *
	 * @param array $args Arguments.
	 * @return int
	 * @throws InvalidArgumentException When missing/invalid.
	 */
	protected function require_id( $args ) {
		$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;

		if ( ! $id ) {
			throw new InvalidArgumentException( 'A valid "id" is required.' );
		}

		return $id;
	}
}
