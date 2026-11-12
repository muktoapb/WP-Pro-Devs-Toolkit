<?php
/**
 * SECURITY: MCP endpoint authentication (kill switch, hashed tokens, OAuth).
 *
 * @package WPProDevs_Toolkit
 */

final class ServerAuthTest extends WPPDT_TestCase {

	private function server(): WPPDT_MCP_Server {
		return new WPPDT_MCP_Server();
	}

	private function bearer( string $token ): WP_REST_Request {
		return new WP_REST_Request( array( 'authorization' => 'Bearer ' . $token ) );
	}

	public function test_token_is_stored_hashed_never_plaintext(): void {
		wp_set_current_user( 1 );
		$server = $this->server();
		$plain  = $server->generate_token();

		$stored = $GLOBALS['wppdt_test']['options']['wppdt_mcp_settings'];
		$this->assertStringStartsWith( 'wppdt_', $plain );
		$this->assertNotSame( $plain, $stored['token_hash'] );
		$this->assertSame( hash( 'sha256', $plain ), $stored['token_hash'] );
		$this->assertStringNotContainsString( substr( $plain, 6 ), wp_json_encode( $stored ) );
	}

	public function test_verify_token_accepts_valid_rejects_invalid(): void {
		wp_set_current_user( 1 );
		$server = $this->server();
		$plain  = $server->generate_token();

		$this->assertSame( 1, $server->verify_token( $plain ) );
		$this->assertSame( 0, $server->verify_token( 'wppdt_wrong' ) );
		$this->assertSame( 0, $server->verify_token( '' ) );

		$server->revoke_token();
		$this->assertSame( 0, $server->verify_token( $plain ) );
	}

	public function test_kill_switch_blocks_when_inactive(): void {
		$server = $this->server(); // inactive by default.
		$result = $server->authenticate( $this->bearer( 'anything' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 503, $result->data['status'] );
	}

	public function test_missing_and_invalid_tokens_return_401(): void {
		$GLOBALS['wppdt_test']['options']['wppdt_mcp_settings'] = array( 'active' => true );
		$server = $this->server();

		$noHeader = $server->authenticate( new WP_REST_Request( array() ) );
		$this->assertSame( 401, $noHeader->data['status'] );

		$bad = $server->authenticate( $this->bearer( 'wppdt_nope' ) );
		$this->assertSame( 401, $bad->data['status'] );
	}

	public function test_valid_static_token_authenticates_as_owner(): void {
		$GLOBALS['wppdt_test']['options']['wppdt_mcp_settings'] = array( 'active' => true );
		wp_set_current_user( 3 );
		$server = $this->server();
		$plain  = $server->generate_token(); // owned by user 3.

		wp_set_current_user( 0 );
		$this->assertTrue( $server->authenticate( $this->bearer( $plain ) ) );
		$this->assertSame( 3, get_current_user_id() );
	}

	public function test_oauth_access_token_also_authenticates(): void {
		$GLOBALS['wppdt_test']['options']['wppdt_mcp_settings'] = array( 'active' => true );
		$server = $this->server();

		$oauthServer = new class() {
			public function is_active() {
				return true;
			}
		};
		$oauth  = new WPPDT_MCP_OAuth( $oauthServer );
		$tokens = $oauth->issue_tokens( 'client', 8, 'mcp' );

		// Inject the OAuth handler (init() would register hooks we don't need here).
		$ref  = new ReflectionProperty( WPPDT_MCP_Server::class, 'oauth' );
		$ref->setAccessible( true );
		$ref->setValue( $server, $oauth );

		$this->assertTrue( $server->authenticate( $this->bearer( $tokens['access_token'] ) ) );
		$this->assertSame( 8, get_current_user_id() );
	}

	public function test_admin_endpoints_require_capability(): void {
		$server                          = $this->server();
		$GLOBALS['wppdt_test']['can']    = true;
		$this->assertTrue( $server->admin_permissions() );
		$GLOBALS['wppdt_test']['can']    = false;
		$this->assertFalse( $server->admin_permissions() );
	}
}
