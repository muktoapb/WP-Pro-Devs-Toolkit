<?php
/**
 * Full-admin MCP tool groups: Files, Site, Users (Phase 2).
 *
 * These groups have no third-party dependency (they operate on WordPress and
 * the filesystem directly), so they are always "available" but — like every
 * non-core group — default to OFF and must be switched on deliberately.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme & plugin file access.
 *
 * All paths are hard-restricted to wp-content/themes, wp-content/plugins and
 * wp-content/mu-plugins via {@see WPPDT_MCP_Group_Files::safe_path()}, which
 * resolves symlinks/traversal and rejects anything outside those roots (so
 * wp-config.php and WordPress core can never be reached). Writes make a .bak
 * copy first and honour the DISALLOW_FILE_EDIT constant.
 *
 * @since 1.2.0
 */
class WPPDT_MCP_Group_Files extends WPPDT_MCP_Tool_Group {

	const MAX_READ_BYTES = 1048576; // 1 MB.

	public function id() {
		return 'files';
	}

	public function label() {
		return __( 'Theme & Plugin Files', 'wp-pro-devs-toolkit' );
	}

	public function is_available() {
		return true;
	}

	public function tools() {
		return array(
			'list_files' => array(
				'description' => 'List files and folders inside wp-content/themes, wp-content/plugins or wp-content/mu-plugins. Omit "path" to list those roots.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'path'      => array( 'type' => 'string', 'description' => 'Path relative to wp-content, e.g. "themes/twentytwentyfour".' ),
						'recursive' => array( 'type' => 'boolean' ),
					),
				),
				'handler'     => array( $this, 'list_files' ),
			),
			'read_file'  => array(
				'description' => 'Read a theme or plugin text file.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'path' => array( 'type' => 'string' ) ),
					'required'   => array( 'path' ),
				),
				'handler'     => array( $this, 'read_file' ),
			),
			'write_file' => array(
				'description' => 'Write a theme or plugin file. Backs up the existing file to <file>.bak first. Set create=true to make a new file.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'path'     => array( 'type' => 'string' ),
						'contents' => array( 'type' => 'string' ),
						'create'   => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'path', 'contents' ),
				),
				'handler'     => array( $this, 'write_file' ),
			),
		);
	}

	/**
	 * Allowed absolute roots (resolved), or empty if none exist.
	 *
	 * @return string[]
	 */
	public static function allowed_roots() {
		$roots = array(
			WP_CONTENT_DIR . '/themes',
			WP_CONTENT_DIR . '/plugins',
			WP_CONTENT_DIR . '/mu-plugins',
		);

		$resolved = array();
		foreach ( $roots as $root ) {
			$real = realpath( $root );
			if ( false !== $real ) {
				$resolved[] = $real;
			}
		}

		return $resolved;
	}

	/**
	 * Resolves a user-supplied path to an absolute path proven to be inside an
	 * allowed root, or throws.
	 *
	 * @param string $path       Relative (to wp-content) or absolute path.
	 * @param bool   $must_exist Whether the path must already exist.
	 * @return string Absolute, validated path.
	 * @throws InvalidArgumentException|RuntimeException On any violation.
	 */
	public static function safe_path( $path, $must_exist = true ) {
		$path = (string) $path;

		if ( '' === $path ) {
			throw new InvalidArgumentException( 'A "path" is required.' );
		}
		if ( false !== strpos( $path, "\0" ) ) {
			throw new InvalidArgumentException( 'Invalid path.' );
		}

		$candidate = ( '/' === $path[0] ) ? $path : WP_CONTENT_DIR . '/' . ltrim( $path, '/' );
		$roots     = self::allowed_roots();

		if ( $must_exist ) {
			$real = realpath( $candidate );
			if ( false === $real ) {
				throw new RuntimeException( 'Path not found: ' . $path );
			}
		} else {
			$base = basename( $candidate );
			if ( '' === $base || '.' === $base || '..' === $base ) {
				throw new InvalidArgumentException( 'Invalid file name.' );
			}
			$parent = realpath( dirname( $candidate ) );
			if ( false === $parent ) {
				throw new RuntimeException( 'Parent directory does not exist: ' . $path );
			}
			$real = $parent . '/' . $base;
		}

		foreach ( $roots as $root ) {
			if ( $real === $root || 0 === strpos( $real, $root . '/' ) ) {
				return $real;
			}
		}

		throw new RuntimeException( 'Path is outside the allowed theme/plugin directories: ' . $path );
	}

	/**
	 * Converts an absolute path back to a wp-content-relative path for output.
	 *
	 * @param string $abs Absolute path.
	 * @return string
	 */
	private static function relative( $abs ) {
		$base = realpath( WP_CONTENT_DIR );
		if ( $base && 0 === strpos( $abs, $base . '/' ) ) {
			return substr( $abs, strlen( $base ) + 1 );
		}
		return $abs;
	}

	public function list_files( $args ) {
		$path = isset( $args['path'] ) ? (string) $args['path'] : '';

		if ( '' === $path ) {
			// No path: list the allowed roots' immediate children.
			$dirs = self::allowed_roots();
		} else {
			$dirs = array( self::safe_path( $path, true ) );
		}

		$recursive = ! empty( $args['recursive'] );
		$entries   = array();

		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$this->scan( $dir, $recursive, $entries );
		}

		return array(
			'path'    => '' === $path ? '(themes, plugins, mu-plugins)' : $path,
			'entries' => $entries,
		);
	}

	/**
	 * Collects directory entries into $entries.
	 *
	 * @param string $dir       Absolute directory.
	 * @param bool   $recursive Recurse into subdirectories.
	 * @param array  $entries   Accumulator (by reference).
	 * @return void
	 */
	private function scan( $dir, $recursive, &$entries ) {
		$items = @scandir( $dir );
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$abs    = $dir . '/' . $item;
			$is_dir = is_dir( $abs );
			$entries[] = array(
				'path' => self::relative( $abs ),
				'type' => $is_dir ? 'dir' : 'file',
				'size' => $is_dir ? null : (int) filesize( $abs ),
			);
			if ( $is_dir && $recursive ) {
				$this->scan( $abs, true, $entries );
			}
		}
	}

	public function read_file( $args ) {
		$abs = self::safe_path( isset( $args['path'] ) ? $args['path'] : '', true );

		if ( ! is_file( $abs ) ) {
			throw new RuntimeException( 'Not a file.' );
		}
		$size = filesize( $abs );
		if ( $size > self::MAX_READ_BYTES ) {
			throw new RuntimeException( sprintf( 'File is too large to read (%d bytes, limit %d).', $size, self::MAX_READ_BYTES ) );
		}

		$contents = file_get_contents( $abs );
		if ( false === $contents ) {
			throw new RuntimeException( 'Could not read the file.' );
		}
		if ( false !== strpos( $contents, "\0" ) ) {
			throw new RuntimeException( 'File appears to be binary; refusing to return it as text.' );
		}

		return array(
			'path'     => self::relative( $abs ),
			'size'     => (int) $size,
			'contents' => $contents,
		);
	}

	public function write_file( $args ) {
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			throw new RuntimeException( 'File editing is disabled on this site (DISALLOW_FILE_EDIT).' );
		}
		if ( defined( 'WPPDT_MCP_DISABLE_FILE_WRITE' ) && WPPDT_MCP_DISABLE_FILE_WRITE ) {
			throw new RuntimeException( 'File writing has been disabled for the MCP server.' );
		}
		if ( ! isset( $args['contents'] ) ) {
			throw new InvalidArgumentException( '"contents" is required.' );
		}

		$create   = ! empty( $args['create'] );
		$abs      = self::safe_path( isset( $args['path'] ) ? $args['path'] : '', ! $create );
		$existed  = is_file( $abs );
		$backup   = '';

		if ( ! $existed && ! $create ) {
			throw new RuntimeException( 'File does not exist. Pass create=true to create it.' );
		}

		if ( $existed ) {
			$backup_abs = $abs . '.bak';
			if ( ! @copy( $abs, $backup_abs ) ) {
				throw new RuntimeException( 'Could not create a backup; aborting write.' );
			}
			$backup = self::relative( $backup_abs );
		}

		$bytes = file_put_contents( $abs, (string) $args['contents'] );
		if ( false === $bytes ) {
			throw new RuntimeException( 'Could not write the file (check permissions).' );
		}

		return array(
			'path'    => self::relative( $abs ),
			'bytes'   => (int) $bytes,
			'created' => ! $existed,
			'backup'  => $backup,
		);
	}
}

/**
 * Site administration: plugins, themes and options.
 *
 * @since 1.2.0
 */
class WPPDT_MCP_Group_Site extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'site';
	}

	public function label() {
		return __( 'Site Administration', 'wp-pro-devs-toolkit' );
	}

	public function is_available() {
		return true;
	}

	public function tools() {
		return array(
			'list_plugins'      => array(
				'description' => 'List installed plugins with their status and version.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
				'handler'     => array( $this, 'list_plugins' ),
			),
			'activate_plugin'   => array(
				'description' => 'Activate a plugin by its file path (e.g. "akismet/akismet.php").',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string' ) ),
					'required'   => array( 'plugin' ),
				),
				'handler'     => array( $this, 'activate_plugin' ),
			),
			'deactivate_plugin' => array(
				'description' => 'Deactivate a plugin by its file path.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string' ) ),
					'required'   => array( 'plugin' ),
				),
				'handler'     => array( $this, 'deactivate_plugin' ),
			),
			'list_themes'       => array(
				'description' => 'List installed themes.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
				'handler'     => array( $this, 'list_themes' ),
			),
			'switch_theme'      => array(
				'description' => 'Activate a theme by its stylesheet/directory name.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'stylesheet' => array( 'type' => 'string' ) ),
					'required'   => array( 'stylesheet' ),
				),
				'handler'     => array( $this, 'switch_theme' ),
			),
			'get_option'        => array(
				'description' => 'Get a site option value by name.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'name' => array( 'type' => 'string' ) ),
					'required'   => array( 'name' ),
				),
				'handler'     => array( $this, 'get_option' ),
			),
			'update_option'     => array(
				'description' => 'Update a site option. Use with care — some options can affect site availability.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'name'  => array( 'type' => 'string' ),
						'value' => array( 'description' => 'New value (string, number, boolean or array).' ),
					),
					'required'   => array( 'name', 'value' ),
				),
				'handler'     => array( $this, 'update_option' ),
			),
			'install_plugin'    => array(
				'description' => 'Install a plugin from the WordPress.org directory (by "slug") or from a ZIP "url".',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array( 'type' => 'string' ),
						'url'  => array( 'type' => 'string' ),
					),
				),
				'handler'     => array( $this, 'install_plugin' ),
			),
			'update_plugin'     => array(
				'description' => 'Update an installed plugin to its latest version.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string' ) ),
					'required'   => array( 'plugin' ),
				),
				'handler'     => array( $this, 'update_plugin' ),
			),
			'delete_plugin'     => array(
				'description' => 'Delete an installed plugin by its file path.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'plugin' => array( 'type' => 'string' ) ),
					'required'   => array( 'plugin' ),
				),
				'handler'     => array( $this, 'delete_plugin' ),
			),
			'install_theme'     => array(
				'description' => 'Install a theme from the WordPress.org directory (by "slug") or from a ZIP "url".',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array( 'type' => 'string' ),
						'url'  => array( 'type' => 'string' ),
					),
				),
				'handler'     => array( $this, 'install_theme' ),
			),
			'update_theme'      => array(
				'description' => 'Update an installed theme to its latest version.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'stylesheet' => array( 'type' => 'string' ) ),
					'required'   => array( 'stylesheet' ),
				),
				'handler'     => array( $this, 'update_theme' ),
			),
			'update_core'       => array(
				'description' => 'Update WordPress core to the latest available version (no-op if already up to date).',
				'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
				'handler'     => array( $this, 'update_core' ),
			),
		);
	}

	private function load_plugin_api() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	public function list_plugins( $args ) {
		$this->load_plugin_api();
		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );

		$plugins = array();
		foreach ( $all as $file => $data ) {
			$plugins[] = array(
				'plugin'  => $file,
				'name'    => $data['Name'],
				'version' => $data['Version'],
				'active'  => in_array( $file, $active, true ),
			);
		}

		return array( 'plugins' => $plugins );
	}

	public function activate_plugin( $args ) {
		$this->load_plugin_api();
		$plugin = isset( $args['plugin'] ) ? (string) $args['plugin'] : '';
		if ( '' === $plugin ) {
			throw new InvalidArgumentException( '"plugin" is required.' );
		}

		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'plugin' => $plugin, 'active' => true );
	}

	public function deactivate_plugin( $args ) {
		$this->load_plugin_api();
		$plugin = isset( $args['plugin'] ) ? (string) $args['plugin'] : '';
		if ( '' === $plugin ) {
			throw new InvalidArgumentException( '"plugin" is required.' );
		}

		deactivate_plugins( $plugin );

		return array( 'plugin' => $plugin, 'active' => false );
	}

	public function list_themes( $args ) {
		$current = get_stylesheet();
		$themes  = array();
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$themes[] = array(
				'stylesheet' => $stylesheet,
				'name'       => $theme->get( 'Name' ),
				'version'    => $theme->get( 'Version' ),
				'active'     => $stylesheet === $current,
			);
		}

		return array( 'themes' => $themes );
	}

	public function switch_theme( $args ) {
		$stylesheet = isset( $args['stylesheet'] ) ? (string) $args['stylesheet'] : '';
		if ( '' === $stylesheet ) {
			throw new InvalidArgumentException( '"stylesheet" is required.' );
		}
		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			throw new RuntimeException( 'Theme not found: ' . $stylesheet );
		}

		switch_theme( $stylesheet );

		return array( 'stylesheet' => $stylesheet, 'active' => true );
	}

	public function get_option( $args ) {
		$name = isset( $args['name'] ) ? (string) $args['name'] : '';
		if ( '' === $name ) {
			throw new InvalidArgumentException( '"name" is required.' );
		}

		return array(
			'name'  => $name,
			'value' => get_option( $name, null ),
		);
	}

	public function update_option( $args ) {
		$name = isset( $args['name'] ) ? (string) $args['name'] : '';
		if ( '' === $name ) {
			throw new InvalidArgumentException( '"name" is required.' );
		}
		if ( ! array_key_exists( 'value', $args ) ) {
			throw new InvalidArgumentException( '"value" is required.' );
		}

		$updated = update_option( $name, $args['value'] );

		return array(
			'name'    => $name,
			'updated' => (bool) $updated,
		);
	}

	/**
	 * Loads the WordPress upgrader/installer API.
	 *
	 * @return void
	 */
	private function load_upgrader_api() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/theme-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
	}

	/**
	 * Resolves a download URL from a slug (wp.org) or an explicit URL.
	 *
	 * @param array  $args Tool args.
	 * @param string $type 'plugin' or 'theme'.
	 * @return string|WP_Error
	 */
	private function resolve_package( $args, $type ) {
		if ( ! empty( $args['url'] ) ) {
			return esc_url_raw( $args['url'] );
		}
		$slug = isset( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
		if ( '' === $slug ) {
			return new WP_Error( 'wppdt_mcp_missing_source', 'Provide a "slug" or a "url".' );
		}

		if ( 'plugin' === $type ) {
			$info = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		} else {
			$info = themes_api( 'theme_information', array( 'slug' => $slug ) );
		}
		if ( is_wp_error( $info ) ) {
			return $info;
		}

		return $info->download_link;
	}

	public function install_plugin( $args ) {
		$this->load_upgrader_api();
		$package = $this->resolve_package( $args, 'plugin' );
		if ( is_wp_error( $package ) ) {
			return $package;
		}

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $package );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			throw new RuntimeException( 'Plugin installation failed.' );
		}

		return array(
			'installed' => true,
			'plugin'    => $upgrader->plugin_info(),
		);
	}

	public function update_plugin( $args ) {
		$this->load_upgrader_api();
		$plugin = isset( $args['plugin'] ) ? (string) $args['plugin'] : '';
		if ( '' === $plugin ) {
			throw new InvalidArgumentException( '"plugin" is required.' );
		}

		wp_update_plugins();
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->upgrade( $plugin );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'plugin' => $plugin, 'updated' => (bool) $result );
	}

	public function delete_plugin( $args ) {
		$this->load_upgrader_api();
		$plugin = isset( $args['plugin'] ) ? (string) $args['plugin'] : '';
		if ( '' === $plugin ) {
			throw new InvalidArgumentException( '"plugin" is required.' );
		}

		if ( is_plugin_active( $plugin ) ) {
			deactivate_plugins( $plugin );
		}
		$result = delete_plugins( array( $plugin ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'plugin' => $plugin, 'deleted' => true );
	}

	public function install_theme( $args ) {
		$this->load_upgrader_api();
		$package = $this->resolve_package( $args, 'theme' );
		if ( is_wp_error( $package ) ) {
			return $package;
		}

		$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $package );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			throw new RuntimeException( 'Theme installation failed.' );
		}

		return array( 'installed' => true, 'theme' => $upgrader->theme_info() ? $upgrader->theme_info()->get_stylesheet() : '' );
	}

	public function update_theme( $args ) {
		$this->load_upgrader_api();
		$stylesheet = isset( $args['stylesheet'] ) ? (string) $args['stylesheet'] : '';
		if ( '' === $stylesheet ) {
			throw new InvalidArgumentException( '"stylesheet" is required.' );
		}

		wp_update_themes();
		$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->upgrade( $stylesheet );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'stylesheet' => $stylesheet, 'updated' => (bool) $result );
	}

	public function update_core( $args ) {
		$this->load_upgrader_api();
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/class-core-upgrader.php';

		wp_version_check( array(), true );
		$updates = get_core_updates();

		if ( empty( $updates ) || ! isset( $updates[0] ) || 'latest' === $updates[0]->response || 'development' === $updates[0]->response ) {
			global $wp_version;
			return array( 'updated' => false, 'message' => 'WordPress is already up to date.', 'version' => $wp_version );
		}

		$upgrader = new Core_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->upgrade( $updates[0] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'updated' => true, 'version' => isset( $updates[0]->version ) ? $updates[0]->version : '' );
	}
}

/**
 * User management.
 *
 * @since 1.2.0
 */
class WPPDT_MCP_Group_Users extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'users';
	}

	public function label() {
		return __( 'Users', 'wp-pro-devs-toolkit' );
	}

	public function is_available() {
		return true;
	}

	public function tools() {
		return array(
			'list_users'  => array(
				'description' => 'List users. Optional role, search and pagination.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'role'     => array( 'type' => 'string' ),
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
				'handler'     => array( $this, 'list_users' ),
			),
			'get_user'    => array(
				'description' => 'Get a user by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_user' ),
			),
			'create_user' => array(
				'description' => 'Create a user.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'username' => array( 'type' => 'string' ),
						'email'    => array( 'type' => 'string' ),
						'password' => array( 'type' => 'string' ),
						'role'     => array( 'type' => 'string' ),
					),
					'required'   => array( 'username', 'email' ),
				),
				'handler'     => array( $this, 'create_user' ),
			),
			'update_user' => array(
				'description' => 'Update a user (email, password, role, display name).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => array( 'type' => 'integer' ),
						'email'        => array( 'type' => 'string' ),
						'password'     => array( 'type' => 'string' ),
						'role'         => array( 'type' => 'string' ),
						'display_name' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'update_user' ),
			),
			'delete_user' => array(
				'description' => 'Delete a user, optionally reassigning their content to another user ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer' ),
						'reassign' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'delete_user' ),
			),
		);
	}

	public function list_users( $args ) {
		$query = array(
			'number' => isset( $args['per_page'] ) ? min( 100, max( 1, absint( $args['per_page'] ) ) ) : 20,
			'paged'  => isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1,
		);
		if ( ! empty( $args['role'] ) ) {
			$query['role'] = sanitize_key( $args['role'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$query['search'] = '*' . sanitize_text_field( $args['search'] ) . '*';
		}

		$users = array();
		foreach ( get_users( $query ) as $user ) {
			$users[] = $this->shape( $user );
		}

		return array( 'users' => $users );
	}

	public function get_user( $args ) {
		$id   = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
		$user = $id ? get_userdata( $id ) : false;
		if ( ! $user ) {
			throw new RuntimeException( 'User not found.' );
		}

		return $this->shape( $user );
	}

	public function create_user( $args ) {
		$username = isset( $args['username'] ) ? sanitize_user( $args['username'] ) : '';
		$email    = isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '';
		if ( '' === $username || '' === $email ) {
			throw new InvalidArgumentException( '"username" and "email" are required.' );
		}

		$password = isset( $args['password'] ) && '' !== $args['password'] ? (string) $args['password'] : wp_generate_password( 20 );

		$userarr = array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => $password,
		);
		if ( ! empty( $args['role'] ) ) {
			$userarr['role'] = sanitize_key( $args['role'] );
		}

		$id = wp_insert_user( $userarr );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return array( 'id' => (int) $id, 'username' => $username );
	}

	public function update_user( $args ) {
		$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
		if ( ! $id || ! get_userdata( $id ) ) {
			throw new RuntimeException( 'User not found.' );
		}

		$userarr = array( 'ID' => $id );
		if ( isset( $args['email'] ) ) {
			$userarr['user_email'] = sanitize_email( $args['email'] );
		}
		if ( ! empty( $args['password'] ) ) {
			$userarr['user_pass'] = (string) $args['password'];
		}
		if ( ! empty( $args['role'] ) ) {
			$userarr['role'] = sanitize_key( $args['role'] );
		}
		if ( isset( $args['display_name'] ) ) {
			$userarr['display_name'] = sanitize_text_field( $args['display_name'] );
		}

		$result = wp_update_user( $userarr );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'id' => $id, 'updated' => true );
	}

	public function delete_user( $args ) {
		$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
		if ( ! $id || ! get_userdata( $id ) ) {
			throw new RuntimeException( 'User not found.' );
		}
		if ( is_multisite() ) {
			throw new RuntimeException( 'Deleting users is not supported on multisite via this tool.' );
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$reassign = isset( $args['reassign'] ) && absint( $args['reassign'] ) ? absint( $args['reassign'] ) : null;
		$deleted  = wp_delete_user( $id, $reassign );

		return array( 'id' => $id, 'deleted' => (bool) $deleted );
	}

	/**
	 * Shapes a WP_User for output.
	 *
	 * @param WP_User $user User.
	 * @return array
	 */
	private function shape( $user ) {
		return array(
			'id'           => (int) $user->ID,
			'username'     => $user->user_login,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'roles'        => array_values( (array) $user->roles ),
			'registered'   => $user->user_registered,
		);
	}
}
