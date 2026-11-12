<?php
/**
 * WPVivid Backup Subfolders module.
 *
 * Organizes WPVivid remote backups into a per-site subfolder so backups from
 * many websites stay separated inside the same cloud account.
 *
 * It works by replacing WPVivid's remote storage handler classes via the
 * `wpvivid_remote_register` filter with thin subclasses of our own — no
 * WPVivid core files are modified, so the feature survives plugin updates.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module controller.
 *
 * @since 1.0.0
 */
class WPPDT_WPVivid_Subfolders extends WPPDT_Module {

	/**
	 * Module directory path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $dir;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->dir = WPPDT_PLUGIN_DIR . 'modules/wpvivid-subfolders/';
	}

	/**
	 * Registers hooks. Called only when the module is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {
		// Priority 20 so we override WPVivid's own registrations (priority 10).
		add_filter( 'wpvivid_remote_register', array( $this, 'register_handlers' ), 20 );
	}

	/**
	 * Swaps in subfolder-aware storage handlers.
	 *
	 * Only runs when WPVivid builds its remote collection, so the WPVivid
	 * parent classes are guaranteed to be loaded at this point.
	 *
	 * @since 1.0.0
	 *
	 * @param array $map Storage type => handler class name.
	 * @return array
	 */
	public function register_handlers( $map ) {
		if ( ! is_array( $map ) ) {
			return $map;
		}

		require_once $this->dir . 'class-wppdt-gdrive-folders.php';
		require_once $this->dir . 'class-wppdt-remote-handlers.php';

		// Google Drive needs real nested-folder creation. Only swap it in when
		// the WPVivid internals we rely on are present, so a future WPVivid
		// change can never break backups — it just disables the nesting.
		if ( isset( $map['googledrive'] )
			&& class_exists( 'WPPDT_GoogleDrive_Subfolders' )
			&& method_exists( 'Wpvivid_Google_drive', '_upload' )
			&& method_exists( 'Wpvivid_Google_drive', 'get_client' )
			&& method_exists( 'Wpvivid_Google_drive', 'delete_exist_file' )
			&& method_exists( 'Wpvivid_Google_drive', 'check_token' )
			&& method_exists( 'Wpvivid_Google_drive', 'get_download_url' )
		) {
			$map['googledrive'] = 'WPPDT_GoogleDrive_Subfolders';
		}

		// Path-based providers nest natively; we only inject the subfolder path.
		$native = array(
			'dropbox'  => 'WPPDT_Dropbox_Subfolders',
			'amazons3' => 'WPPDT_AmazonS3_Subfolders',
			's3compat' => 'WPPDT_S3Compat_Subfolders',
			'ftp'      => 'WPPDT_FTP_Subfolders',
			'sftp'     => 'WPPDT_SFTP_Subfolders',
		);

		foreach ( $native as $type => $handler ) {
			if ( isset( $map[ $type ] ) && class_exists( $handler ) ) {
				$map[ $type ] = $handler;
			}
		}

		return $map;
	}

	/**
	 * Returns the sanitized per-site subfolder name (the site domain).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function site_subfolder() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';

		// Keep only characters that are safe as a folder name everywhere.
		$sub = preg_replace( '/[^a-z0-9._-]+/', '-', $host );
		$sub = trim( (string) $sub, '-._' );

		if ( '' === $sub ) {
			$sub = 'site';
		}

		/**
		 * Filters the per-site subfolder name used for backups.
		 *
		 * @since 1.0.0
		 *
		 * @param string $sub Sanitized subfolder name.
		 */
		return apply_filters( 'wppdt_wpvivid_subfolder', $sub );
	}

	/**
	 * Appends the per-site subfolder to a storage's configured base path.
	 *
	 * Rules:
	 *  - A leading slash on the base path is preserved (FTP/SFTP absolute
	 *    paths, Dropbox root).
	 *  - An empty or root ("/") base becomes "wpvivid_backup" so there is a
	 *    sensible parent folder.
	 *  - The append is idempotent: if the base already ends with the
	 *    subfolder, it is not added twice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $base The storage's configured path.
	 * @return string
	 */
	public static function nested_path( $base ) {
		$base    = (string) $base;
		$leading = ( '' !== $base && '/' === $base[0] );

		$base_trim = trim( $base, '/' );
		if ( '' === $base_trim ) {
			$base_trim = 'wpvivid_backup';
		}

		$sub      = self::site_subfolder();
		$segments = explode( '/', $base_trim );

		if ( end( $segments ) !== $sub ) {
			$base_trim .= '/' . $sub;
		}

		$path = $leading ? '/' . $base_trim : $base_trim;

		/**
		 * Filters the full nested path used for a storage's backups.
		 *
		 * @since 1.0.0
		 *
		 * @param string $path The computed nested path.
		 * @param string $base The original base path.
		 * @param string $sub  The per-site subfolder name.
		 */
		return apply_filters( 'wppdt_wpvivid_nested_path', $path, $base, $sub );
	}
}
