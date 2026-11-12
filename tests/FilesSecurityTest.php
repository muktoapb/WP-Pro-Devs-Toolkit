<?php
/**
 * SECURITY: Theme/plugin file tool path allow-listing.
 *
 * Proves that the Files group can only read/write inside the allowed
 * wp-content roots and that traversal / absolute escapes to wp-config.php or
 * the rest of the filesystem are rejected.
 *
 * @package WPProDevs_Toolkit
 */

final class FilesSecurityTest extends WPPDT_TestCase {

	private $files;

	protected function setUp(): void {
		parent::setUp();
		$this->files = new WPPDT_MCP_Group_Files();

		// Fresh fixtures each test.
		@mkdir( WP_CONTENT_DIR . '/themes/testtheme', 0777, true );
		@mkdir( WP_CONTENT_DIR . '/plugins/testplugin', 0777, true );
		file_put_contents( WP_CONTENT_DIR . '/themes/testtheme/style.css', "body{color:red}\n" );
		file_put_contents( WP_CONTENT_DIR . '/plugins/testplugin/plugin.php', "<?php // test\n" );
	}

	public function test_valid_paths_resolve_inside_allowed_roots(): void {
		$this->assertIsString( WPPDT_MCP_Group_Files::safe_path( 'themes/testtheme/style.css', true ) );
		$this->assertIsString( WPPDT_MCP_Group_Files::safe_path( 'plugins/testplugin/plugin.php', true ) );
	}

	/**
	 * @dataProvider traversalProvider
	 */
	public function test_traversal_and_absolute_escapes_are_blocked( string $path ): void {
		$this->expectException( \Throwable::class );
		WPPDT_MCP_Group_Files::safe_path( $path, true );
	}

	public static function traversalProvider(): array {
		return array(
			'parent traversal to wp-config' => array( 'themes/../../wp-config.php' ),
			'relative parent'               => array( '../wp-config.php' ),
			'absolute etc passwd'           => array( '/etc/passwd' ),
			'absolute wp-config'            => array( ABSPATH . 'wp-config.php' ),
			'empty path'                    => array( '' ),
			'deep traversal'                => array( 'plugins/testplugin/../../../../../../etc/hosts' ),
		);
	}

	public function test_read_returns_contents_but_refuses_outside_paths(): void {
		$r = $this->files->read_file( array( 'path' => 'themes/testtheme/style.css' ) );
		$this->assertStringContainsString( 'color:red', $r['contents'] );

		$this->expectException( \Throwable::class );
		$this->files->read_file( array( 'path' => '../wp-config.php' ) );
	}

	public function test_write_backs_up_then_overwrites(): void {
		$w = $this->files->write_file( array( 'path' => 'themes/testtheme/style.css', 'contents' => "body{color:blue}\n" ) );
		$this->assertStringContainsString( '.bak', $w['backup'] );
		$this->assertSame( 'body{color:blue}', trim( file_get_contents( WP_CONTENT_DIR . '/themes/testtheme/style.css' ) ) );
		$this->assertSame( 'body{color:red}', trim( file_get_contents( WP_CONTENT_DIR . '/themes/testtheme/style.css.bak' ) ) );
	}

	public function test_create_new_file_and_reject_outside_writes(): void {
		$c = $this->files->write_file( array( 'path' => 'plugins/testplugin/new.php', 'contents' => "<?php\n", 'create' => true ) );
		$this->assertTrue( $c['created'] );
		$this->assertFileExists( WP_CONTENT_DIR . '/plugins/testplugin/new.php' );

		$this->expectException( \Throwable::class );
		$this->files->write_file( array( 'path' => '../hack.php', 'contents' => 'x', 'create' => true ) );
	}

	public function test_disallow_file_edit_blocks_writes(): void {
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}
		$this->expectException( \Throwable::class );
		$this->files->write_file( array( 'path' => 'themes/testtheme/style.css', 'contents' => 'x' ) );
	}
}
