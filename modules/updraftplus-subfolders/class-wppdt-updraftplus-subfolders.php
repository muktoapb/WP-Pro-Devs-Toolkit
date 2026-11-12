<?php
/**
 * UpdraftPlus Backup Subfolders module.
 *
 * Organizes UpdraftPlus remote backups into a per-site subfolder so backups from
 * many websites stay separated inside the same cloud account.
 *
 * It works by filtering UpdraftPlus remote storage options and paths at runtime
 * via `updraftplus_backupmodule_get_options` and `updraftplus_dropbox_modpath` —
 * no UpdraftPlus core files are modified, so the feature survives plugin updates.
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
class WPPDT_UpdraftPlus_Subfolders extends WPPDT_Module {

	/**
	 * Registers hooks. Called only when the module is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {
		// Priority 20 so we run after UpdraftPlus and any add-on option filters (priority 10).
		add_filter( 'updraftplus_backupmodule_get_options', array( $this, 'filter_module_options' ), 20, 2 );
		add_filter( 'updraftplus_dropbox_modpath', array( $this, 'filter_dropbox_modpath' ), 20, 2 );
	}

	/**
	 * Injects the per-site subfolder into a storage module's runtime options.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $options Storage module options.
	 * @param object $module  UpdraftPlus_BackupModule instance.
	 * @return array
	 */
	public function filter_module_options( $options, $module ) {
		if ( ! is_array( $options ) || ! is_object( $module ) || ! method_exists( $module, 'get_id' ) ) {
			return $options;
		}

		$method = (string) $module->get_id();

		switch ( $method ) {
			case 'googledrive':
				// UpdraftPlus's id_from_path() natively walks/creates slash-separated
				// folder hierarchies on Google Drive when 'folder' is used.
				// Only rewrite 'folder' when a specific 'parentid' is not pinned.
				if ( empty( $options['parentid'] ) ) {
					$base              = isset( $options['folder'] ) ? (string) $options['folder'] : '';
					$options['folder'] = self::nested_path( $base, 'UpdraftPlus' );
				}
				break;

			case 's3':
			case 's3generic':
			case 'dreamobjects':
			case 'googlecloud':
				// S3-family modules store "bucket" or "bucket/prefix" in 'path'.
				// Only append the subfolder when a bucket has been configured.
				if ( ! empty( $options['path'] ) && is_string( $options['path'] ) ) {
					$options['path'] = self::s3_nested_path( $options['path'] );
				}
				break;

			case 'ftp':
			case 'sftp':
			case 'webdav':
				// Path-based server storage: append the site subfolder to the remote path.
				if ( isset( $options['path'] ) && is_string( $options['path'] ) && '' !== trim( $options['path'] ) ) {
					$options['path'] = self::nested_path( $options['path'], '' );
				}
				break;

			case 'onedrive':
			case 'pcloud':
			case 'azure':
			case 'backblaze':
				// Add-on cloud providers that use 'folder' or 'path'.
				if ( isset( $options['folder'] ) && is_string( $options['folder'] ) ) {
					$options['folder'] = self::nested_path( $options['folder'], '' );
				} elseif ( ! empty( $options['path'] ) && is_string( $options['path'] ) ) {
					$options['path'] = self::nested_path( $options['path'], '' );
				}
				break;
		}

		return $options;
	}

	/**
	 * Injects the per-site subfolder into Dropbox file and search paths.
	 *
	 * UpdraftPlus routes all Dropbox operations (upload, listfiles, download,
	 * delete) through `updraftplus_dropbox_modpath`. For `listfiles()`, `$file`
	 * is an empty string (`''`); for file operations, `$file` is the filename
	 * (or `folder/filename` if the Dropbox Folders add-on is active).
	 *
	 * @since 1.0.0
	 *
	 * @param string $file   Relative file path or empty string for directory search.
	 * @param object $module UpdraftPlus_BackupModule_dropbox instance.
	 * @return string
	 */
	public function filter_dropbox_modpath( $file, $module ) {
		if ( ! is_string( $file ) ) {
			return $file;
		}

		$sub  = self::site_subfolder();
		$trim = trim( $file, '/' );

		// When listfiles() runs, $file is '' (or 'custom_folder' if the premium
		// Dropbox Folders add-on ran at priority 10).
		if ( '' === $trim ) {
			$folder = '';
			if ( is_object( $module ) && method_exists( $module, 'get_options' ) ) {
				$opts = $module->get_options();
				if ( is_array( $opts ) && ! empty( $opts['folder'] ) && is_string( $opts['folder'] ) ) {
					$folder = trim( $opts['folder'], '/' );
				}
			}

			return self::nested_path( $folder, '' );
		}

		$segments = explode( '/', $trim );
		$count    = count( $segments );

		// Single segment: could be a backup filename (e.g. 'backup_...-db.gz') or
		// a folder name injected by the Dropbox Folders add-on during listfiles('').
		if ( 1 === $count ) {
			$folder = '';
			if ( is_object( $module ) && method_exists( $module, 'get_options' ) ) {
				$opts = $module->get_options();
				if ( is_array( $opts ) && ! empty( $opts['folder'] ) && is_string( $opts['folder'] ) ) {
					$folder = trim( $opts['folder'], '/' );
				}
			}

			// If this single segment is the configured Dropbox folder (from listfiles('')),
			// append the site subfolder to it.
			if ( '' !== $folder && $trim === $folder ) {
				return self::nested_path( $folder, '' );
			}

			// Already the site subfolder itself.
			if ( $trim === $sub ) {
				return $trim;
			}

			// Otherwise $trim is a filename: prepend optional folder + site subfolder.
			$prefix = '' !== $folder ? self::nested_path( $folder, '' ) : $sub;
			return $prefix . '/' . $trim;
		}

		// Multiple segments (e.g. 'custom_folder/backup_....zip' or already 'example.com/backup_....zip').
		$filename   = array_pop( $segments );
		$dir_prefix = implode( '/', $segments );

		// If the last segment was actually the site subfolder (from nested folder in listfiles), return as-is.
		if ( $filename === $sub ) {
			return $trim;
		}

		$nested_dir = self::nested_path( $dir_prefix, '' );

		return $nested_dir . '/' . $filename;
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
		 * Filters the per-site subfolder name used for UpdraftPlus backups.
		 *
		 * @since 1.0.0
		 *
		 * @param string $sub Sanitized subfolder name.
		 */
		return apply_filters( 'wppdt_updraftplus_subfolder', $sub );
	}

	/**
	 * Appends the per-site subfolder to a storage's configured base path.
	 *
	 * Rules:
	 *  - A leading slash on the base path is preserved (FTP/SFTP/WebDAV absolute paths).
	 *  - An empty or root ("/") base uses `$default_base` when provided, or just
	 *    the site subfolder when `$default_base` is empty.
	 *  - The append is idempotent: if the base already ends with the
	 *    subfolder, it is not added twice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $base         The storage's configured path.
	 * @param string $default_base Fallback parent folder when `$base` is empty.
	 * @return string
	 */
	public static function nested_path( $base, $default_base = 'UpdraftPlus' ) {
		$base    = (string) $base;
		$leading = ( '' !== $base && '/' === $base[0] );

		$base_trim = trim( $base, '/' );
		if ( '' === $base_trim ) {
			$base_trim = trim( (string) $default_base, '/' );
		}

		$sub = self::site_subfolder();

		if ( '' === $base_trim ) {
			$nested = $sub;
		} else {
			$segments = explode( '/', $base_trim );
			if ( end( $segments ) !== $sub ) {
				$base_trim .= '/' . $sub;
			}
			$nested = $base_trim;
		}

		$path = $leading ? '/' . $nested : $nested;

		/**
		 * Filters the full nested path used for an UpdraftPlus storage's backups.
		 *
		 * @since 1.0.0
		 *
		 * @param string $path The computed nested path.
		 * @param string $base The original base path.
		 * @param string $sub  The per-site subfolder name.
		 */
		return apply_filters( 'wppdt_updraftplus_nested_path', $path, $base, $sub );
	}

	/**
	 * Appends the per-site subfolder to an S3-style `bucket` or `bucket/prefix` path.
	 *
	 * UpdraftPlus stores the bucket name and optional key prefix together in
	 * `$options['path']` (e.g. `mybucket` or `mybucket/backups`). When non-empty,
	 * this appends the site subfolder after the bucket/prefix (`mybucket/example.com`
	 * or `mybucket/backups/example.com`) idempotently.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Configured S3 bucket/path string.
	 * @return string
	 */
	public static function s3_nested_path( $path ) {
		$path = trim( (string) $path, '/' );
		if ( '' === $path ) {
			return '';
		}

		$sub      = self::site_subfolder();
		$segments = explode( '/', $path );

		// Always keep the first segment as the bucket name; only avoid appending
		// when there is already a path segment after the bucket ending in $sub.
		if ( count( $segments ) < 2 || end( $segments ) !== $sub ) {
			$path .= '/' . $sub;
		}

		/**
		 * Filters the nested S3 bucket/prefix path used for UpdraftPlus backups.
		 *
		 * @since 1.0.0
		 *
		 * @param string $path The computed bucket/prefix path.
		 * @param string $sub  The per-site subfolder name.
		 */
		return apply_filters( 'wppdt_updraftplus_s3_nested_path', $path, $sub );
	}
}
