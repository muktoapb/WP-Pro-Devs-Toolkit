<?php
/**
 * Base test case: resets the in-memory WordPress state before each test.
 *
 * @package WPProDevs_Toolkit
 */

use PHPUnit\Framework\TestCase;

abstract class WPPDT_TestCase extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		wppdt_test_reset();
	}

	/** Base64url with no padding (matches the plugin's PKCE encoding). */
	protected function base64url( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}
}
