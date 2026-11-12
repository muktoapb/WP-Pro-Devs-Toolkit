<?php
/**
 * PHPUnit bootstrap.
 *
 * These are UNIT tests: WordPress is not loaded. Instead we stub the small set
 * of WordPress functions/classes the plugin touches, so the plugin's own logic
 * (auth, OAuth/PKCE, file-path allow-listing, tool-group gating, path building)
 * can be exercised in isolation and deterministically. The WordPress-dependent
 * tool handlers (wp_insert_post, WooCommerce, ...) are covered separately by the
 * live integration smoke test (tests/integration-smoke.php) run via WP-CLI.
 *
 * @package WPProDevs_Toolkit
 */

error_reporting( E_ALL & ~E_DEPRECATED );

$plugin_dir = dirname( __DIR__ ) . '/';

// A scratch "wp-content" so the Files group's allow-listing can be tested
// against a real filesystem, with a wp-config.php sibling that must stay
// unreachable via traversal.
$content_root = sys_get_temp_dir() . '/wppdt-tests-' . getmypid();
@mkdir( $content_root . '/wp-content/themes', 0777, true );
@mkdir( $content_root . '/wp-content/plugins', 0777, true );
@mkdir( $content_root . '/wp-content/mu-plugins', 0777, true );
file_put_contents( $content_root . '/wp-config.php', "<?php // SECRET\n" );
register_shutdown_function( static function () use ( $content_root ) {
	@exec( 'rm -rf ' . escapeshellarg( $content_root ) );
} );

define( 'ABSPATH', $content_root . '/' );
define( 'WP_CONTENT_DIR', $content_root . '/wp-content' );
define( 'WPPDT_VERSION', '9.9.9-test' );
define( 'WPPDT_PLUGIN_DIR', $plugin_dir );
define( 'WPPDT_PLUGIN_FILE', $plugin_dir . 'wp-pro-devs-toolkit.php' );
define( 'WPPDT_PLUGIN_BASENAME', 'wp-pro-devs-toolkit/wp-pro-devs-toolkit.php' );

/** In-memory state reset between tests by WPPDT_TestCase::setUp(). */
$GLOBALS['wppdt_test'] = array(
	'options'      => array(),
	'current_user' => 0,
	'can'          => true,
);

function wppdt_test_reset() {
	$GLOBALS['wppdt_test'] = array(
		'options'      => array(),
		'current_user' => 0,
		'can'          => true,
	);
}

/* ---- Options ---------------------------------------------------------- */
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['wppdt_test']['options'] ) ? $GLOBALS['wppdt_test']['options'][ $k ] : $d;
}
function update_option( $k, $v, $autoload = null ) {
	$GLOBALS['wppdt_test']['options'][ $k ] = $v;
	return true;
}
function add_option( $k, $v ) {
	$GLOBALS['wppdt_test']['options'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['wppdt_test']['options'][ $k ] );
	return true;
}

/* ---- Users / caps ----------------------------------------------------- */
function get_current_user_id() {
	return $GLOBALS['wppdt_test']['current_user'];
}
function wp_set_current_user( $id ) {
	$GLOBALS['wppdt_test']['current_user'] = (int) $id;
}
function current_user_can( $cap ) {
	return (bool) $GLOBALS['wppdt_test']['can'];
}

/* ---- Escaping / sanitizing ------------------------------------------- */
function esc_url_raw( $v ) {
	return is_string( $v ) ? trim( $v ) : '';
}
function esc_url( $v ) {
	return esc_url_raw( $v );
}
function esc_html( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES );
}
function esc_attr( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES );
}
function esc_html__( $s, $d = null ) {
	return esc_html( $s );
}
function sanitize_text_field( $v ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $v ) );
}
function sanitize_key( $v ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) );
}
function sanitize_title( $v ) {
	return strtolower( preg_replace( '/[^a-z0-9\-]+/i', '-', trim( (string) $v ) ) );
}
function sanitize_file_name( $v ) {
	return preg_replace( '/[^a-z0-9._-]/i', '', (string) $v );
}
function absint( $v ) {
	return abs( (int) $v );
}

/* ---- Misc ------------------------------------------------------------- */
function __( $s, $d = null ) {
	return $s;
}
function apply_filters( $tag, $value ) {
	return $value;
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function wp_unslash( $v ) {
	return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v );
}
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}
function untrailingslashit( $s ) {
	return rtrim( $s, '/' );
}
function trailingslashit( $s ) {
	return rtrim( $s, '/' ) . '/';
}
function is_wp_error( $t ) {
	return $t instanceof WP_Error;
}
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}
function wp_trim_words( $t, $n = 20, $more = '' ) {
	return $t;
}
function rest_ensure_response( $v ) {
	return $v instanceof WP_REST_Response ? $v : new WP_REST_Response( $v );
}
function status_header( $code ) {}
function wp_generate_password( $len = 12, $special = true ) {
	return substr( bin2hex( random_bytes( $len ) ), 0, $len );
}

/* ---- Stub WP classes -------------------------------------------------- */
class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_message() {
		return $this->message;
	}
}
class WP_REST_Server {
	const READABLE  = 'GET';
	const CREATABLE = 'POST';
	const DELETABLE = 'DELETE';
}
class WP_REST_Response {
	public $data;
	public $status;
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
}
class WP_REST_Request {
	private $headers;
	private $json;
	public function __construct( $headers = array(), $json = array() ) {
		$this->headers = array_change_key_case( (array) $headers );
		$this->json    = $json;
	}
	public function get_header( $k ) {
		$k = strtolower( $k );
		return isset( $this->headers[ $k ] ) ? $this->headers[ $k ] : null;
	}
	public function get_json_params() {
		return $this->json;
	}
	public function get_body() {
		return $this->json ? json_encode( $this->json ) : '';
	}
	public function get_param( $k ) {
		return isset( $this->json[ $k ] ) ? $this->json[ $k ] : null;
	}
}
class WP_Query {
	public $posts       = array();
	public $found_posts = 0;
	public function __construct( $args = array() ) {}
}

/* ---- Load plugin classes under test ----------------------------------- */
require_once $plugin_dir . 'includes/class-wppdt-module.php';
require_once $plugin_dir . 'includes/class-wppdt-modules.php';
require_once $plugin_dir . 'modules/mcp-server/class-wppdt-mcp-tools.php'; // pulls tool-group + groups + admin-groups.
require_once $plugin_dir . 'modules/mcp-server/class-wppdt-mcp-protocol.php';
require_once $plugin_dir . 'modules/mcp-server/class-wppdt-mcp-server.php';
require_once $plugin_dir . 'modules/mcp-server/class-wppdt-mcp-oauth.php';
require_once $plugin_dir . 'modules/wpvivid-subfolders/class-wppdt-wpvivid-subfolders.php';
require_once $plugin_dir . 'modules/updraftplus-subfolders/class-wppdt-updraftplus-subfolders.php';

require_once __DIR__ . '/TestCase.php';
