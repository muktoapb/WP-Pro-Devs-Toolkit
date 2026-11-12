<?php
/**
 * Admin interface.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin menu and mounts the React settings app.
 *
 * @since 1.0.0
 */
class WPPDT_Admin {

	/**
	 * Settings page slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PAGE_SLUG = 'wppdt-settings';

	/**
	 * Script and style handle.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ASSET_HANDLE = 'wppdt-admin';

	/**
	 * Module manager.
	 *
	 * @since 1.0.0
	 * @var WPPDT_Modules
	 */
	private $modules;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param WPPDT_Modules $modules Module manager.
	 */
	public function __construct( WPPDT_Modules $modules ) {
		$this->modules = $modules;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . WPPDT_PLUGIN_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Registers the top-level admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'WP Pro Devs Toolkit', 'wp-pro-devs-toolkit' ),
			__( 'Pro Devs Toolkit', 'wp-pro-devs-toolkit' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-admin-tools',
			80
		);
	}

	/**
	 * Adds a settings link to the plugins list row.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'wp-pro-devs-toolkit' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Enqueues the React app on the settings screen only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = WPPDT_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			self::ASSET_HANDLE,
			WPPDT_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( self::ASSET_HANDLE, 'wp-pro-devs-toolkit', WPPDT_PLUGIN_DIR . 'languages' );

		wp_enqueue_style( 'wp-components' );

		if ( file_exists( WPPDT_PLUGIN_DIR . 'build/style-index.css' ) ) {
			wp_enqueue_style(
				self::ASSET_HANDLE,
				WPPDT_PLUGIN_URL . 'build/style-index.css',
				array( 'wp-components' ),
				$asset['version']
			);
		}
	}

	/**
	 * Renders the settings page container.
	 *
	 * The interface itself is rendered by the React app into the root node.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="wppdt-admin-root">
				<p><?php esc_html_e( 'Loading…', 'wp-pro-devs-toolkit' ); ?></p>
			</div>
		</div>
		<?php
	}
}
