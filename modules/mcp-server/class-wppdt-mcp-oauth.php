<?php
/**
 * OAuth 2.1 authorization server for the MCP endpoint (Phase 3).
 *
 * Implements the pieces MCP web clients (ChatGPT, claude.ai) need:
 *  - Protected Resource Metadata  (RFC 9728, /.well-known/oauth-protected-resource)
 *  - Authorization Server Metadata (RFC 8414, /.well-known/oauth-authorization-server)
 *  - Dynamic Client Registration  (RFC 7591)
 *  - Authorization Code + PKCE (S256) with an admin consent screen
 *  - Token + refresh endpoints
 *  - Opaque bearer access tokens validated by the MCP endpoint
 *
 * Tokens/codes/clients are stored (hashed) in a non-autoloaded option with
 * expiry GC, so there is no database migration. Public clients only (PKCE, no
 * client secret), which is what the MCP web clients use.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP OAuth authorization server.
 *
 * @since 1.3.0
 */
class WPPDT_MCP_OAuth {

	const STORE_OPTION = 'wppdt_mcp_oauth';
	const CODE_TTL     = 300;     // 5 minutes.
	const TOKEN_TTL    = 3600;    // 1 hour.
	const REFRESH_TTL  = 2592000; // 30 days.
	const MAX_CLIENTS  = 100;
	const QUERY_VAR    = 'wppdt_mcp_oauth';

	/**
	 * Server controller (for the kill switch).
	 *
	 * @var WPPDT_MCP_Server
	 */
	private $server;

	/**
	 * Constructor.
	 *
	 * @param WPPDT_MCP_Server $server Server controller.
	 */
	public function __construct( $server ) {
		$this->server = $server;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'maybe_handle_request' ), 1 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/* =====================================================================
	 * URLs / metadata
	 * ================================================================== */

	/**
	 * The issuer (site origin).
	 *
	 * @return string
	 */
	public function issuer() {
		return untrailingslashit( home_url() );
	}

	/**
	 * The protected resource (the MCP endpoint).
	 *
	 * @return string
	 */
	public function resource() {
		return rest_url( WPPDT_MCP_Server::REST_NAMESPACE . '/mcp' );
	}

	/**
	 * Authorization endpoint URL.
	 *
	 * @return string
	 */
	public function authorize_url() {
		return home_url( '/?' . self::QUERY_VAR . '=authorize' );
	}

	/**
	 * Token endpoint URL.
	 *
	 * @return string
	 */
	public function token_url() {
		return rest_url( WPPDT_MCP_Server::REST_NAMESPACE . '/oauth/token' );
	}

	/**
	 * Dynamic client registration endpoint URL.
	 *
	 * @return string
	 */
	public function register_url() {
		return rest_url( WPPDT_MCP_Server::REST_NAMESPACE . '/oauth/register' );
	}

	/**
	 * Authorization Server Metadata (RFC 8414).
	 *
	 * @return array
	 */
	public function as_metadata() {
		return array(
			'issuer'                                => $this->issuer(),
			'authorization_endpoint'                => $this->authorize_url(),
			'token_endpoint'                        => $this->token_url(),
			'registration_endpoint'                 => $this->register_url(),
			'response_types_supported'              => array( 'code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'token_endpoint_auth_methods_supported' => array( 'none' ),
			'scopes_supported'                      => array( 'mcp' ),
		);
	}

	/**
	 * Protected Resource Metadata (RFC 9728).
	 *
	 * @return array
	 */
	public function resource_metadata() {
		return array(
			'resource'              => $this->resource(),
			'authorization_servers' => array( $this->issuer() ),
			'scopes_supported'      => array( 'mcp' ),
			'bearer_methods_supported' => array( 'header' ),
		);
	}

	/* =====================================================================
	 * Front-controller for .well-known + authorize (served outside REST)
	 * ================================================================== */

	/**
	 * Handles .well-known discovery and the authorize/consent flow.
	 *
	 * @return void
	 */
	public function maybe_handle_request() {
		$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );

		if ( $this->path_is( $path, '/.well-known/oauth-authorization-server' ) ) {
			$this->send_json( $this->as_metadata() );
		}
		if ( $this->path_is( $path, '/.well-known/oauth-protected-resource' ) ) {
			$this->send_json( $this->resource_metadata() );
		}

		// Authorization endpoint (query-var routed).
		if ( isset( $_GET[ self::QUERY_VAR ] ) && 'authorize' === $_GET[ self::QUERY_VAR ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->handle_authorize();
		}
	}

	/**
	 * Whether the request path matches a well-known suffix.
	 *
	 * @param string $path   Request path.
	 * @param string $suffix Expected suffix.
	 * @return bool
	 */
	private function path_is( $path, $suffix ) {
		return $path === $suffix || ( '' !== $path && substr( $path, -strlen( $suffix ) ) === $suffix );
	}

	/**
	 * Emits a JSON response and exits.
	 *
	 * @param array $data   Payload.
	 * @param int   $status HTTP status.
	 * @return void
	 */
	private function send_json( $data, $status = 200 ) {
		if ( ! $this->server->is_active() ) {
			status_header( 404 );
			exit;
		}
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		echo wp_json_encode( $data );
		exit;
	}

	/* =====================================================================
	 * Authorization + consent
	 * ================================================================== */

	/**
	 * Handles the authorization endpoint (GET consent + POST approval).
	 *
	 * @return void
	 */
	private function handle_authorize() {
		if ( ! $this->server->is_active() ) {
			status_header( 404 );
			exit;
		}

		$params = $this->authorize_params();

		// Validate the client and redirect URI before trusting any redirect.
		$client = $this->get_client( $params['client_id'] );
		if ( ! $client || ! in_array( $params['redirect_uri'], $client['redirect_uris'], true ) ) {
			$this->authorize_error_page( __( 'Invalid client or redirect URI.', 'wp-pro-devs-toolkit' ) );
		}
		if ( 'code' !== $params['response_type'] || '' === $params['code_challenge'] || 'S256' !== $params['code_challenge_method'] ) {
			$this->redirect_error( $params, 'invalid_request' );
		}

		// The resource owner must be a logged-in administrator.
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $this->current_authorize_url() ) );
			exit;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->authorize_error_page( __( 'Your account is not allowed to authorize MCP access.', 'wp-pro-devs-toolkit' ) );
		}

		// Handle the approve/deny submission.
		if ( isset( $_POST['wppdt_consent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			check_admin_referer( 'wppdt_mcp_consent' );

			if ( 'approve' !== $_POST['wppdt_consent'] ) {
				$this->redirect_error( $params, 'access_denied' );
			}

			$code = $this->issue_code( $params, get_current_user_id() );
			$args = array( 'code' => $code );
			if ( '' !== $params['state'] ) {
				$args['state'] = $params['state'];
			}
			wp_safe_redirect( add_query_arg( $args, $params['redirect_uri'] ) );
			exit;
		}

		$this->render_consent( $params, $client );
		exit;
	}

	/**
	 * Reads and sanitizes the authorization request parameters.
	 *
	 * @return array
	 */
	private function authorize_params() {
		$get = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return array(
			'response_type'         => isset( $get['response_type'] ) ? sanitize_text_field( $get['response_type'] ) : '',
			'client_id'             => isset( $get['client_id'] ) ? sanitize_text_field( $get['client_id'] ) : '',
			'redirect_uri'          => isset( $get['redirect_uri'] ) ? esc_url_raw( $get['redirect_uri'] ) : '',
			'state'                 => isset( $get['state'] ) ? sanitize_text_field( $get['state'] ) : '',
			'scope'                 => isset( $get['scope'] ) ? sanitize_text_field( $get['scope'] ) : 'mcp',
			'code_challenge'        => isset( $get['code_challenge'] ) ? sanitize_text_field( $get['code_challenge'] ) : '',
			'code_challenge_method' => isset( $get['code_challenge_method'] ) ? sanitize_text_field( $get['code_challenge_method'] ) : '',
		);
	}

	/**
	 * Rebuilds the current authorize URL (for the login round-trip).
	 *
	 * @return string
	 */
	private function current_authorize_url() {
		$get = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return home_url( add_query_arg( array_map( 'rawurlencode', $get ), '/' ) );
	}

	/**
	 * Redirects back to the client with an OAuth error.
	 *
	 * @param array  $params Authorize params.
	 * @param string $error  OAuth error code.
	 * @return void
	 */
	private function redirect_error( $params, $error ) {
		$args = array( 'error' => $error );
		if ( '' !== $params['state'] ) {
			$args['state'] = $params['state'];
		}
		wp_safe_redirect( add_query_arg( $args, $params['redirect_uri'] ) );
		exit;
	}

	/**
	 * Renders a terminal error page (when we cannot safely redirect).
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function authorize_error_page( $message ) {
		status_header( 400 );
		wp_die( esc_html( $message ), esc_html__( 'MCP authorization error', 'wp-pro-devs-toolkit' ), array( 'response' => 400 ) );
	}

	/**
	 * Renders the consent screen.
	 *
	 * @param array $params Authorize params.
	 * @param array $client Registered client.
	 * @return void
	 */
	private function render_consent( $params, $client ) {
		$name = ! empty( $client['client_name'] ) ? $client['client_name'] : $params['client_id'];
		$host = wp_parse_url( $params['redirect_uri'], PHP_URL_HOST );

		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex" />
	<title><?php esc_html_e( 'Authorize MCP access', 'wp-pro-devs-toolkit' ); ?></title>
	<style>
		body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f0f0f1;margin:0;padding:40px 16px;color:#1d2327}
		.card{max-width:440px;margin:0 auto;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:28px}
		h1{font-size:20px;margin:0 0 8px}
		p{line-height:1.5;color:#3c434a}
		.app{font-weight:600}
		.site{color:#646970;font-size:13px;word-break:break-all}
		.actions{display:flex;gap:10px;margin-top:24px}
		button{flex:1;padding:10px 16px;border-radius:4px;border:1px solid #2271b1;font-size:14px;cursor:pointer}
		.approve{background:#2271b1;color:#fff}
		.deny{background:#fff;color:#2271b1}
	</style>
</head>
<body>
	<div class="card">
		<h1><?php esc_html_e( 'Authorize MCP access', 'wp-pro-devs-toolkit' ); ?></h1>
		<p>
			<span class="app"><?php echo esc_html( $name ); ?></span>
			<?php esc_html_e( 'is requesting access to manage this WordPress site through the MCP server.', 'wp-pro-devs-toolkit' ); ?>
		</p>
		<p class="site"><?php echo esc_html( sprintf( /* translators: %s: redirect host */ __( 'It will be redirected to: %s', 'wp-pro-devs-toolkit' ), $host ) ); ?></p>
		<p><?php echo esc_html( sprintf( /* translators: %s: user login */ __( 'Approving grants access as %s.', 'wp-pro-devs-toolkit' ), wp_get_current_user()->user_login ) ); ?></p>
		<form method="post" action="<?php echo esc_url( $this->current_authorize_url() ); ?>">
			<?php wp_nonce_field( 'wppdt_mcp_consent' ); ?>
			<div class="actions">
				<button class="deny" type="submit" name="wppdt_consent" value="deny"><?php esc_html_e( 'Deny', 'wp-pro-devs-toolkit' ); ?></button>
				<button class="approve" type="submit" name="wppdt_consent" value="approve"><?php esc_html_e( 'Approve', 'wp-pro-devs-toolkit' ); ?></button>
			</div>
		</form>
	</div>
</body>
</html>
		<?php
	}

	/* =====================================================================
	 * REST endpoints: register + token
	 * ================================================================== */

	/**
	 * Registers the DCR and token REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			WPPDT_MCP_Server::REST_NAMESPACE,
			'/oauth/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_register' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			WPPDT_MCP_Server::REST_NAMESPACE,
			'/oauth/token',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_token' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Dynamic Client Registration (RFC 7591).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_register( WP_REST_Request $request ) {
		if ( ! $this->server->is_active() ) {
			return new WP_REST_Response( array( 'error' => 'server_disabled' ), 404 );
		}

		$body          = $request->get_json_params();
		$redirect_uris = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] ) ? $body['redirect_uris'] : array();
		$redirect_uris = array_values( array_filter( array_map( 'esc_url_raw', $redirect_uris ) ) );

		if ( empty( $redirect_uris ) ) {
			return new WP_REST_Response(
				array(
					'error'             => 'invalid_redirect_uri',
					'error_description' => 'At least one redirect_uri is required.',
				),
				400
			);
		}

		$client_id   = 'wppdt_' . bin2hex( random_bytes( 16 ) );
		$client_name = isset( $body['client_name'] ) ? sanitize_text_field( $body['client_name'] ) : '';

		$store = $this->get_store();
		// Cap the number of stored clients to avoid unbounded growth.
		if ( count( $store['clients'] ) >= self::MAX_CLIENTS ) {
			$store['clients'] = array_slice( $store['clients'], -1 * ( self::MAX_CLIENTS - 1 ), null, true );
		}
		$store['clients'][ $client_id ] = array(
			'client_id'     => $client_id,
			'client_name'   => $client_name,
			'redirect_uris' => $redirect_uris,
			'created'       => time(),
		);
		$this->save_store( $store );

		return new WP_REST_Response(
			array(
				'client_id'                 => $client_id,
				'client_id_issued_at'       => time(),
				'redirect_uris'             => $redirect_uris,
				'client_name'               => $client_name,
				'grant_types'               => array( 'authorization_code', 'refresh_token' ),
				'response_types'            => array( 'code' ),
				'token_endpoint_auth_method' => 'none',
			),
			201
		);
	}

	/**
	 * Token endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_token( WP_REST_Request $request ) {
		if ( ! $this->server->is_active() ) {
			return new WP_REST_Response( array( 'error' => 'server_disabled' ), 404 );
		}

		$grant = (string) $request->get_param( 'grant_type' );

		if ( 'authorization_code' === $grant ) {
			return $this->grant_authorization_code( $request );
		}
		if ( 'refresh_token' === $grant ) {
			return $this->grant_refresh_token( $request );
		}

		return $this->token_error( 'unsupported_grant_type' );
	}

	/**
	 * Handles the authorization_code grant.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	private function grant_authorization_code( WP_REST_Request $request ) {
		$code          = (string) $request->get_param( 'code' );
		$client_id     = (string) $request->get_param( 'client_id' );
		$redirect_uri  = (string) $request->get_param( 'redirect_uri' );
		$code_verifier = (string) $request->get_param( 'code_verifier' );

		$store = $this->get_store();
		$hash  = $this->hash( $code );
		$entry = isset( $store['codes'][ $hash ] ) ? $store['codes'][ $hash ] : null;

		if ( ! $entry || $entry['expires'] < time() ) {
			return $this->token_error( 'invalid_grant' );
		}

		// One-time use.
		unset( $store['codes'][ $hash ] );
		$this->save_store( $store );

		if ( $entry['client_id'] !== $client_id || $entry['redirect_uri'] !== $redirect_uri ) {
			return $this->token_error( 'invalid_grant' );
		}
		if ( ! $this->verify_pkce( $code_verifier, $entry['code_challenge'] ) ) {
			return $this->token_error( 'invalid_grant', 'PKCE verification failed.' );
		}

		return new WP_REST_Response( $this->issue_tokens( $entry['client_id'], (int) $entry['user_id'], $entry['scope'] ), 200 );
	}

	/**
	 * Handles the refresh_token grant.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	private function grant_refresh_token( WP_REST_Request $request ) {
		$refresh   = (string) $request->get_param( 'refresh_token' );
		$client_id = (string) $request->get_param( 'client_id' );

		$store = $this->get_store();
		$hash  = $this->hash( $refresh );
		$entry = isset( $store['refresh'][ $hash ] ) ? $store['refresh'][ $hash ] : null;

		if ( ! $entry || $entry['expires'] < time() || $entry['client_id'] !== $client_id ) {
			return $this->token_error( 'invalid_grant' );
		}

		// Rotate: drop the used refresh token.
		unset( $store['refresh'][ $hash ] );
		$this->save_store( $store );

		return new WP_REST_Response( $this->issue_tokens( $entry['client_id'], (int) $entry['user_id'], $entry['scope'] ), 200 );
	}

	/**
	 * Builds a token error response.
	 *
	 * @param string $error       OAuth error code.
	 * @param string $description Optional description.
	 * @return WP_REST_Response
	 */
	private function token_error( $error, $description = '' ) {
		$body = array( 'error' => $error );
		if ( '' !== $description ) {
			$body['error_description'] = $description;
		}
		return new WP_REST_Response( $body, 400 );
	}

	/* =====================================================================
	 * Issue / validate
	 * ================================================================== */

	/**
	 * Issues an authorization code and stores it.
	 *
	 * @param array $params  Authorize params.
	 * @param int   $user_id Approving user.
	 * @return string The plaintext code.
	 */
	public function issue_code( $params, $user_id ) {
		$code  = 'wppdt_ac_' . bin2hex( random_bytes( 24 ) );
		$store = $this->get_store();

		$store['codes'][ $this->hash( $code ) ] = array(
			'client_id'      => $params['client_id'],
			'redirect_uri'   => $params['redirect_uri'],
			'code_challenge' => $params['code_challenge'],
			'user_id'        => (int) $user_id,
			'scope'          => $params['scope'],
			'expires'        => time() + self::CODE_TTL,
		);
		$this->save_store( $store );

		return $code;
	}

	/**
	 * Issues access + refresh tokens.
	 *
	 * @param string $client_id Client.
	 * @param int    $user_id   User.
	 * @param string $scope     Scope.
	 * @return array Token response.
	 */
	public function issue_tokens( $client_id, $user_id, $scope ) {
		$access  = 'wppdt_at_' . bin2hex( random_bytes( 24 ) );
		$refresh = 'wppdt_rt_' . bin2hex( random_bytes( 24 ) );

		$store = $this->get_store();
		$store['tokens'][ $this->hash( $access ) ] = array(
			'client_id' => $client_id,
			'user_id'   => (int) $user_id,
			'scope'     => $scope,
			'expires'   => time() + self::TOKEN_TTL,
		);
		$store['refresh'][ $this->hash( $refresh ) ] = array(
			'client_id' => $client_id,
			'user_id'   => (int) $user_id,
			'scope'     => $scope,
			'expires'   => time() + self::REFRESH_TTL,
		);
		$this->save_store( $store );

		return array(
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => self::TOKEN_TTL,
			'refresh_token' => $refresh,
			'scope'         => $scope,
		);
	}

	/**
	 * Validates an access token and returns its user ID (0 if invalid).
	 *
	 * @param string $token Bearer access token.
	 * @return int
	 */
	public function validate_bearer( $token ) {
		if ( ! is_string( $token ) || 0 !== strpos( $token, 'wppdt_at_' ) ) {
			return 0;
		}

		$store = $this->get_store();
		$entry = isset( $store['tokens'][ $this->hash( $token ) ] ) ? $store['tokens'][ $this->hash( $token ) ] : null;

		if ( ! $entry || $entry['expires'] < time() ) {
			return 0;
		}

		return (int) $entry['user_id'];
	}

	/**
	 * Verifies a PKCE S256 code verifier against a stored challenge.
	 *
	 * @param string $verifier  Code verifier.
	 * @param string $challenge Stored S256 challenge.
	 * @return bool
	 */
	public function verify_pkce( $verifier, $challenge ) {
		if ( '' === $verifier || '' === $challenge ) {
			return false;
		}
		$computed = $this->base64url( hash( 'sha256', $verifier, true ) );
		return hash_equals( $challenge, $computed );
	}

	/* =====================================================================
	 * Helpers
	 * ================================================================== */

	/**
	 * Base64url-encodes binary data (no padding).
	 *
	 * @param string $bin Binary.
	 * @return string
	 */
	private function base64url( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	/**
	 * Hashes a secret for storage/lookup.
	 *
	 * @param string $value Secret.
	 * @return string
	 */
	private function hash( $value ) {
		return hash( 'sha256', (string) $value );
	}

	/**
	 * Returns a registered client, or null.
	 *
	 * @param string $client_id Client ID.
	 * @return array|null
	 */
	public function get_client( $client_id ) {
		$store = $this->get_store();
		return isset( $store['clients'][ $client_id ] ) ? $store['clients'][ $client_id ] : null;
	}

	/**
	 * Loads the store, garbage-collecting expired codes/tokens.
	 *
	 * @return array
	 */
	private function get_store() {
		$store = get_option( self::STORE_OPTION, array() );
		if ( ! is_array( $store ) ) {
			$store = array();
		}
		$store += array(
			'clients' => array(),
			'codes'   => array(),
			'tokens'  => array(),
			'refresh' => array(),
		);

		$now     = time();
		$changed = false;
		foreach ( array( 'codes', 'tokens', 'refresh' ) as $bucket ) {
			foreach ( $store[ $bucket ] as $key => $entry ) {
				if ( isset( $entry['expires'] ) && $entry['expires'] < $now ) {
					unset( $store[ $bucket ][ $key ] );
					$changed = true;
				}
			}
		}
		if ( $changed ) {
			update_option( self::STORE_OPTION, $store, false );
		}

		return $store;
	}

	/**
	 * Persists the store (non-autoloaded).
	 *
	 * @param array $store Store.
	 * @return void
	 */
	private function save_store( $store ) {
		update_option( self::STORE_OPTION, $store, false );
	}
}
