<?php
/**
 * MCP Server module.
 *
 * Exposes the site over the Model Context Protocol (JSON-RPC 2.0 over HTTP) so
 * AI assistants and scripts can call tools that manage WordPress content,
 * Elementor layouts and more.
 *
 * Two independent switches guard it:
 *   1. The module toggle (Features screen) — loads this code and the submenu.
 *   2. The "MCP Server: ON/OFF" kill switch on the submenu — the live switch
 *      for the endpoint. When off, the endpoint refuses every request before
 *      auth or any tool runs.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP server controller: routes, authentication, settings, token and audit log.
 *
 * @since 1.1.0
 */
class WPPDT_MCP_Server extends WPPDT_Module {

	const REST_NAMESPACE = 'wppdt/v1';
	const OPTION         = 'wppdt_mcp_settings';
	const AUDIT_OPTION   = 'wppdt_mcp_audit';
	const AUDIT_LIMIT    = 50;
	const PAGE_SLUG      = 'wppdt-mcp';
	const TOKEN_PREFIX   = 'wppdt_';

	/**
	 * Tool registry.
	 *
	 * @since 1.1.0
	 * @var WPPDT_MCP_Tools|null
	 */
	private $tools = null;

	/**
	 * OAuth authorization server.
	 *
	 * @since 1.3.0
	 * @var WPPDT_MCP_OAuth|null
	 */
	private $oauth = null;

	/**
	 * Module directory path.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $dir;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->dir = WPPDT_PLUGIN_DIR . 'modules/mcp-server/';
	}

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function init() {
		require_once $this->dir . 'class-wppdt-mcp-tools.php';
		require_once $this->dir . 'class-wppdt-mcp-protocol.php';
		require_once $this->dir . 'class-wppdt-mcp-oauth.php';

		$this->build_tools();

		$this->oauth = new WPPDT_MCP_OAuth( $this );
		$this->oauth->init();

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Let the MCP endpoint through security plugins that block the REST API
		// for unauthenticated requests. Our endpoint still enforces its own
		// bearer-token auth and kill switch.
		add_filter( 'aios_whitelisted_rest_routes', array( $this, 'allow_rest_namespace' ) );
	}

	/**
	 * Whitelists the MCP namespace with All-In-One WP Security's REST firewall.
	 *
	 * Harmless when AIOWPS is not installed (the filter simply never runs).
	 *
	 * @since 1.1.0
	 *
	 * @param array $routes Whitelisted route namespaces.
	 * @return array
	 */
	public function allow_rest_namespace( $routes ) {
		if ( ! is_array( $routes ) ) {
			$routes = array();
		}

		if ( ! in_array( self::REST_NAMESPACE, $routes, true ) ) {
			$routes[] = self::REST_NAMESPACE;
		}

		return $routes;
	}

	/**
	 * Builds the tool registry from the current group settings.
	 *
	 * @return void
	 */
	private function build_tools() {
		$settings     = $this->get_settings();
		$this->tools  = new WPPDT_MCP_Tools( isset( $settings['tool_groups'] ) ? $settings['tool_groups'] : array() );
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	/**
	 * Returns the stored settings with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			'active'        => false,
			'token_hash'    => '',
			'token_user'    => 0,
			'token_created' => 0,
			'token_last4'   => '',
			'tool_groups'   => array(),
		);

		$stored = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * Whether the MCP server is switched on.
	 *
	 * @return bool
	 */
	public function is_active() {
		$settings = $this->get_settings();

		return ! empty( $settings['active'] );
	}

	/* ---------------------------------------------------------------------
	 * Token
	 * ------------------------------------------------------------------- */

	/**
	 * Generates a new access token, stores its hash, and returns the plaintext
	 * (shown to the admin only once).
	 *
	 * @return string The plaintext token.
	 */
	public function generate_token() {
		$plain = self::TOKEN_PREFIX . bin2hex( random_bytes( 24 ) );

		$settings                  = $this->get_settings();
		$settings['token_hash']    = hash( 'sha256', $plain );
		$settings['token_user']    = get_current_user_id();
		$settings['token_created'] = time();
		$settings['token_last4']   = substr( $plain, -4 );

		update_option( self::OPTION, $settings );

		return $plain;
	}

	/**
	 * Revokes the current token.
	 *
	 * @return void
	 */
	public function revoke_token() {
		$settings                  = $this->get_settings();
		$settings['token_hash']    = '';
		$settings['token_user']    = 0;
		$settings['token_created'] = 0;
		$settings['token_last4']   = '';

		update_option( self::OPTION, $settings );
	}

	/**
	 * Verifies a plaintext token against the stored hash.
	 *
	 * @param string $plain Token supplied by the client.
	 * @return int The associated user ID, or 0 if invalid.
	 */
	public function verify_token( $plain ) {
		$settings = $this->get_settings();

		if ( empty( $settings['token_hash'] ) || ! is_string( $plain ) || '' === $plain ) {
			return 0;
		}

		if ( ! hash_equals( $settings['token_hash'], hash( 'sha256', $plain ) ) ) {
			return 0;
		}

		return (int) $settings['token_user'];
	}

	/* ---------------------------------------------------------------------
	 * Audit log
	 * ------------------------------------------------------------------- */

	/**
	 * Records a tool call in the audit log (most recent first, capped).
	 *
	 * @param string $tool    Tool name.
	 * @param bool   $ok      Whether it succeeded.
	 * @param string $summary Short human-readable summary.
	 * @return void
	 */
	public function audit( $tool, $ok, $summary = '' ) {
		$log = get_option( self::AUDIT_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'    => time(),
				'tool'    => (string) $tool,
				'ok'      => (bool) $ok,
				'summary' => (string) $summary,
				'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			)
		);

		update_option( self::AUDIT_OPTION, array_slice( $log, 0, self::AUDIT_LIMIT ) );
	}

	/**
	 * Returns the endpoint URL clients connect to.
	 *
	 * @return string
	 */
	public function endpoint_url() {
		return rest_url( self::REST_NAMESPACE . '/mcp' );
	}

	/* ---------------------------------------------------------------------
	 * REST routes
	 * ------------------------------------------------------------------- */

	/**
	 * Registers the MCP protocol endpoint and the admin management endpoints.
	 *
	 * @return void
	 */
	public function register_routes() {
		// The MCP protocol endpoint — authenticated by bearer token.
		register_rest_route(
			self::REST_NAMESPACE,
			'/mcp',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_mcp' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);

		// Admin management endpoints — authenticated by the WordPress session.
		$admin_perms = array( $this, 'admin_permissions' );

		register_rest_route(
			self::REST_NAMESPACE,
			'/mcp-admin/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'admin_get_settings' ),
					'permission_callback' => $admin_perms,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'admin_update_settings' ),
					'permission_callback' => $admin_perms,
					'args'                => array(
						'active' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/mcp-admin/groups',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_toggle_group' ),
				'permission_callback' => $admin_perms,
				'args'                => array(
					'group'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'enabled' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/mcp-admin/token',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'admin_generate_token' ),
					'permission_callback' => $admin_perms,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'admin_revoke_token' ),
					'permission_callback' => $admin_perms,
				),
			)
		);
	}

	/**
	 * Permission check for the admin management endpoints.
	 *
	 * @return bool
	 */
	public function admin_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Authenticates an MCP protocol request.
	 *
	 * Enforces the kill switch first, then the bearer token. On success the
	 * current user is set to the token owner so capability checks and content
	 * authorship behave correctly.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function authenticate( WP_REST_Request $request ) {
		if ( ! $this->is_active() ) {
			return new WP_Error( 'wppdt_mcp_disabled', __( 'The MCP server is switched off.', 'wp-pro-devs-toolkit' ), array( 'status' => 503 ) );
		}

		$header = $request->get_header( 'authorization' );

		if ( ! $header || 0 !== stripos( $header, 'bearer ' ) ) {
			return $this->unauthorized( __( 'Missing bearer token.', 'wp-pro-devs-toolkit' ) );
		}

		$token = trim( substr( $header, 7 ) );

		// Accept either the static admin token or an OAuth access token.
		$user = $this->verify_token( $token );
		if ( ! $user && $this->oauth ) {
			$user = $this->oauth->validate_bearer( $token );
		}

		if ( ! $user ) {
			return $this->unauthorized( __( 'Invalid access token.', 'wp-pro-devs-toolkit' ) );
		}

		wp_set_current_user( $user );

		return true;
	}

	/**
	 * Builds a 401 error and points clients at the OAuth discovery metadata
	 * (per the MCP authorization spec) via the WWW-Authenticate header.
	 *
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function unauthorized( $message ) {
		$metadata = home_url( '/.well-known/oauth-protected-resource' );
		if ( ! headers_sent() ) {
			header( sprintf( 'WWW-Authenticate: Bearer resource_metadata="%s"', esc_url_raw( $metadata ) ) );
		}

		return new WP_Error( 'wppdt_mcp_unauthorized', $message, array( 'status' => 401 ) );
	}

	/**
	 * Dispatches an MCP protocol request through the JSON-RPC handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_mcp( WP_REST_Request $request ) {
		$body = $request->get_json_params();

		if ( empty( $body ) ) {
			$decoded = json_decode( $request->get_body(), true );
			$body    = is_array( $decoded ) ? $decoded : null;
		}

		$protocol = new WPPDT_MCP_Protocol( $this->tools, $this );

		// A JSON-RPC batch is an array of messages; a single call is one object.
		if ( is_array( $body ) && isset( $body[0] ) ) {
			$responses = array();
			foreach ( $body as $message ) {
				$response = $protocol->handle( (array) $message );
				if ( null !== $response ) {
					$responses[] = $response;
				}
			}
			return new WP_REST_Response( $responses, 200 );
		}

		$response = $protocol->handle( is_array( $body ) ? $body : array() );

		// Notifications produce no response body (HTTP 202 Accepted).
		if ( null === $response ) {
			return new WP_REST_Response( null, 202 );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/* ---------------------------------------------------------------------
	 * Admin endpoint callbacks
	 * ------------------------------------------------------------------- */

	/**
	 * Builds the status payload for the admin screen.
	 *
	 * @return array
	 */
	private function status_payload() {
		$settings = $this->get_settings();
		$audit    = get_option( self::AUDIT_OPTION, array() );

		return array(
			'active'       => (bool) $settings['active'],
			'hasToken'     => '' !== $settings['token_hash'],
			'tokenLast4'   => $settings['token_last4'],
			'tokenCreated' => (int) $settings['token_created'],
			'endpoint'     => $this->endpoint_url(),
			'groups'       => $this->tools ? $this->tools->get_groups_meta() : array(),
			'audit'        => is_array( $audit ) ? array_slice( $audit, 0, 10 ) : array(),
		);
	}

	/**
	 * GET settings/status.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_get_settings() {
		return rest_ensure_response( $this->status_payload() );
	}

	/**
	 * POST settings (toggle the kill switch).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_update_settings( WP_REST_Request $request ) {
		$settings           = $this->get_settings();
		$settings['active'] = (bool) $request->get_param( 'active' );
		update_option( self::OPTION, $settings );

		return rest_ensure_response( $this->status_payload() );
	}

	/**
	 * POST a tool-group toggle.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function admin_toggle_group( WP_REST_Request $request ) {
		$group   = sanitize_key( $request->get_param( 'group' ) );
		$enabled = (bool) $request->get_param( 'enabled' );

		$settings = $this->get_settings();
		if ( ! isset( $settings['tool_groups'] ) || ! is_array( $settings['tool_groups'] ) ) {
			$settings['tool_groups'] = array();
		}
		$settings['tool_groups'][ $group ] = $enabled;
		update_option( self::OPTION, $settings );

		// Rebuild so the response reflects the new state.
		$this->build_tools();

		return rest_ensure_response( $this->status_payload() );
	}

	/**
	 * POST token (generate/regenerate). Returns the plaintext once.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_generate_token() {
		$token              = $this->generate_token();
		$payload            = $this->status_payload();
		$payload['token']   = $token;

		return rest_ensure_response( $payload );
	}

	/**
	 * DELETE token (revoke).
	 *
	 * @return WP_REST_Response
	 */
	public function admin_revoke_token() {
		$this->revoke_token();

		return rest_ensure_response( $this->status_payload() );
	}

	/* ---------------------------------------------------------------------
	 * Admin screen
	 * ------------------------------------------------------------------- */

	/**
	 * Registers the MCP Server submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			WPPDT_Admin::PAGE_SLUG,
			__( 'MCP Server', 'wp-pro-devs-toolkit' ),
			__( 'MCP Server', 'wp-pro-devs-toolkit' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueues the shared React bundle on the MCP submenu.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::PAGE_SLUG ) ) {
			return;
		}

		WPPDT_Admin::enqueue_app();
	}

	/**
	 * Renders the MCP submenu container (React mounts into the root node).
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MCP Server', 'wp-pro-devs-toolkit' ); ?></h1>
			<div id="wppdt-mcp-root">
				<p><?php esc_html_e( 'Loading…', 'wp-pro-devs-toolkit' ); ?></p>
			</div>
		</div>
		<?php
	}
}
