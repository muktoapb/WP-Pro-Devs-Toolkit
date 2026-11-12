<?php
/**
 * OAuth 2.1 authorization server tests (DCR, PKCE, token lifecycle).
 *
 * @package WPProDevs_Toolkit
 */

final class OAuthTest extends WPPDT_TestCase {

	private function oauth() {
		$server = new class() {
			public function is_active() {
				return true;
			}
		};
		return new WPPDT_MCP_OAuth( $server );
	}

	public function test_metadata_advertises_pkce_and_public_clients(): void {
		$m = $this->oauth()->as_metadata();
		$this->assertSame( array( 'code' ), $m['response_types_supported'] );
		$this->assertSame( array( 'S256' ), $m['code_challenge_methods_supported'] );
		$this->assertSame( array( 'none' ), $m['token_endpoint_auth_methods_supported'] );
		$this->assertSame( array( $this->oauth()->issuer() ), $this->oauth()->resource_metadata()['authorization_servers'] );
	}

	public function test_dynamic_client_registration(): void {
		$oauth = $this->oauth();
		$res   = $oauth->rest_register( new WP_REST_Request( array(), array( 'redirect_uris' => array( 'https://client.test/cb' ), 'client_name' => 'Claude' ) ) );
		$this->assertSame( 201, $res->status );
		$this->assertStringStartsWith( 'wppdt_', $res->data['client_id'] );

		$bad = $oauth->rest_register( new WP_REST_Request( array(), array() ) );
		$this->assertSame( 400, $bad->status );
	}

	public function test_full_pkce_authorization_code_flow(): void {
		$oauth     = $this->oauth();
		$client_id = $oauth->rest_register( new WP_REST_Request( array(), array( 'redirect_uris' => array( 'https://client.test/cb' ) ) ) )->data['client_id'];

		$verifier  = 'a-high-entropy-verifier-string-01234567890';
		$challenge = $this->base64url( hash( 'sha256', $verifier, true ) );

		$code = $oauth->issue_code(
			array( 'client_id' => $client_id, 'redirect_uri' => 'https://client.test/cb', 'code_challenge' => $challenge, 'scope' => 'mcp' ),
			7
		);
		$this->assertStringStartsWith( 'wppdt_ac_', $code );

		$tok = $oauth->rest_token( new WP_REST_Request( array(), array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'client_id'     => $client_id,
			'redirect_uri'  => 'https://client.test/cb',
			'code_verifier' => $verifier,
		) ) );

		$this->assertSame( 200, $tok->status );
		$this->assertSame( 'Bearer', $tok->data['token_type'] );
		$this->assertSame( 7, $oauth->validate_bearer( $tok->data['access_token'] ) );
	}

	public function test_wrong_pkce_verifier_is_rejected(): void {
		$oauth     = $this->oauth();
		$client_id = $oauth->rest_register( new WP_REST_Request( array(), array( 'redirect_uris' => array( 'https://c/cb' ) ) ) )->data['client_id'];
		$challenge = $this->base64url( hash( 'sha256', 'the-right-verifier', true ) );
		$code      = $oauth->issue_code( array( 'client_id' => $client_id, 'redirect_uri' => 'https://c/cb', 'code_challenge' => $challenge, 'scope' => 'mcp' ), 1 );

		$tok = $oauth->rest_token( new WP_REST_Request( array(), array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'client_id'     => $client_id,
			'redirect_uri'  => 'https://c/cb',
			'code_verifier' => 'the-WRONG-verifier',
		) ) );
		$this->assertSame( 400, $tok->status );
		$this->assertSame( 'invalid_grant', $tok->data['error'] );
	}

	public function test_authorization_code_is_single_use(): void {
		$oauth     = $this->oauth();
		$client_id = $oauth->rest_register( new WP_REST_Request( array(), array( 'redirect_uris' => array( 'https://c/cb' ) ) ) )->data['client_id'];
		$verifier  = 'verifier-value-abcdefghijklmnop';
		$challenge = $this->base64url( hash( 'sha256', $verifier, true ) );
		$code      = $oauth->issue_code( array( 'client_id' => $client_id, 'redirect_uri' => 'https://c/cb', 'code_challenge' => $challenge, 'scope' => 'mcp' ), 1 );
		$req       = array( 'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $client_id, 'redirect_uri' => 'https://c/cb', 'code_verifier' => $verifier );

		$this->assertSame( 200, $oauth->rest_token( new WP_REST_Request( array(), $req ) )->status );
		$this->assertSame( 400, $oauth->rest_token( new WP_REST_Request( array(), $req ) )->status );
	}

	public function test_refresh_token_rotates(): void {
		$oauth     = $this->oauth();
		$client_id = $oauth->rest_register( new WP_REST_Request( array(), array( 'redirect_uris' => array( 'https://c/cb' ) ) ) )->data['client_id'];
		$tokens    = $oauth->issue_tokens( $client_id, 5, 'mcp' );

		$rt = $oauth->rest_token( new WP_REST_Request( array(), array( 'grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $client_id ) ) );
		$this->assertSame( 200, $rt->status );
		$this->assertSame( 5, $oauth->validate_bearer( $rt->data['access_token'] ) );

		// The used refresh token must no longer work (rotation).
		$again = $oauth->rest_token( new WP_REST_Request( array(), array( 'grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $client_id ) ) );
		$this->assertSame( 400, $again->status );
	}

	public function test_expired_access_token_is_invalid(): void {
		$oauth  = $this->oauth();
		$tokens = $oauth->issue_tokens( 'c', 9, 'mcp' );
		$this->assertSame( 9, $oauth->validate_bearer( $tokens['access_token'] ) );

		$store = $GLOBALS['wppdt_test']['options']['wppdt_mcp_oauth'];
		foreach ( $store['tokens'] as $k => $v ) {
			$store['tokens'][ $k ]['expires'] = time() - 10;
		}
		$GLOBALS['wppdt_test']['options']['wppdt_mcp_oauth'] = $store;

		$this->assertSame( 0, $oauth->validate_bearer( $tokens['access_token'] ) );
	}
}
