<?php
/**
 * MCP JSON-RPC 2.0 protocol handler.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles MCP messages: initialize, tools/list, tools/call, ping and the
 * initialized notification.
 *
 * @since 1.1.0
 */
class WPPDT_MCP_Protocol {

	/**
	 * MCP protocol version this server implements.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const PROTOCOL_VERSION = '2025-06-18';

	/**
	 * Tool registry.
	 *
	 * @since 1.1.0
	 * @var WPPDT_MCP_Tools
	 */
	private $tools;

	/**
	 * Server controller (for audit logging).
	 *
	 * @since 1.1.0
	 * @var WPPDT_MCP_Server
	 */
	private $server;

	/**
	 * Constructor.
	 *
	 * @param WPPDT_MCP_Tools  $tools  Tool registry.
	 * @param WPPDT_MCP_Server $server Server controller.
	 */
	public function __construct( $tools, $server ) {
		$this->tools  = $tools;
		$this->server = $server;
	}

	/**
	 * Handles a single JSON-RPC message.
	 *
	 * @param array $message Decoded JSON-RPC message.
	 * @return array|null Response, or null for notifications (no reply).
	 */
	public function handle( $message ) {
		$id     = isset( $message['id'] ) ? $message['id'] : null;
		$method = isset( $message['method'] ) ? $message['method'] : '';
		$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();

		// Notifications have no "id" and expect no response.
		$is_notification = ! array_key_exists( 'id', $message );

		switch ( $method ) {
			case 'initialize':
				return $this->result( $id, $this->initialize( $params ) );

			case 'tools/list':
				return $this->result( $id, array( 'tools' => $this->tools->get_definitions() ) );

			case 'tools/call':
				return $this->result( $id, $this->call_tool( $params ) );

			case 'ping':
				return $this->result( $id, new stdClass() );

			case 'notifications/initialized':
			case 'notifications/cancelled':
				return null;

			default:
				if ( $is_notification ) {
					return null;
				}
				return $this->error( $id, -32601, 'Method not found: ' . $method );
		}
	}

	/**
	 * Builds the initialize response.
	 *
	 * @param array $params Client params.
	 * @return array
	 */
	private function initialize( $params ) {
		return array(
			'protocolVersion' => self::PROTOCOL_VERSION,
			'capabilities'    => array(
				'tools' => new stdClass(),
			),
			'serverInfo'      => array(
				'name'    => 'WP Pro Devs Toolkit',
				'version' => defined( 'WPPDT_VERSION' ) ? WPPDT_VERSION : '1.0.0',
			),
		);
	}

	/**
	 * Executes a tools/call request.
	 *
	 * @param array $params Params: name, arguments.
	 * @return array MCP tool result.
	 */
	private function call_tool( $params ) {
		$name = isset( $params['name'] ) ? (string) $params['name'] : '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		$result = $this->tools->call( $name, $args );

		$ok      = empty( $result['isError'] );
		$summary = '';
		if ( isset( $result['content'][0]['text'] ) ) {
			$summary = wp_trim_words( $result['content'][0]['text'], 20, '…' );
		}
		$this->server->audit( $name, $ok, $summary );

		return $result;
	}

	/**
	 * Wraps a successful result, or null for notifications.
	 *
	 * @param mixed $id     Request ID.
	 * @param mixed $result Result payload.
	 * @return array|null
	 */
	private function result( $id, $result ) {
		if ( null === $id ) {
			return null;
		}

		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Builds a JSON-RPC error response.
	 *
	 * @param mixed  $id      Request ID.
	 * @param int    $code    Error code.
	 * @param string $message Error message.
	 * @return array
	 */
	private function error( $id, $code, $message ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
