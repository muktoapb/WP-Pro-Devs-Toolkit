<?php
/**
 * Google Drive nested folder resolver.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a slash-separated path to a Google Drive folder ID, creating any
 * missing folders along the way.
 *
 * WPVivid's own resolver only finds/creates a single folder at the Drive root
 * ("'root' in parents"), which cannot nest. This walks each path segment,
 * finding or creating it inside the previous segment, so "a/b/c" becomes a
 * real nested folder structure.
 *
 * The logic is isolated here (instead of inside the storage subclass) so it can
 * be unit tested against a mocked Drive service.
 *
 * @since 1.0.0
 */
class WPPDT_GDrive_Folders {

	/**
	 * Finds or creates the folder chain for a path and returns the leaf ID.
	 *
	 * @since 1.0.0
	 *
	 * @param object $service A WPvivid_Google_Service_Drive-compatible object.
	 * @param string $path    Slash-separated folder path, e.g. "wpvivid_backup/example.com".
	 * @return string|false The leaf folder ID, or false on failure/empty path.
	 */
	public static function resolve( $service, $path ) {
		$segments = array_values( array_filter( explode( '/', (string) $path ), array( __CLASS__, 'is_non_empty' ) ) );

		if ( empty( $segments ) ) {
			return false;
		}

		$parent    = 'root';
		$folder_id = false;

		foreach ( $segments as $name ) {
			$escaped = str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $name );

			$response = $service->files->listFiles(
				array(
					'q'      => "name = '" . $escaped . "' and '" . $parent . "' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
					'fields' => 'nextPageToken, files(id, name, mimeType)',
				)
			);

			$files = $response->getFiles();

			if ( count( $files ) === 0 ) {
				$metadata = new WPvivid_Google_Service_Drive_DriveFile(
					array(
						'name'     => $name,
						'mimeType' => 'application/vnd.google-apps.folder',
						'parents'  => array( $parent ),
					)
				);

				$file      = $service->files->create( $metadata, array( 'fields' => 'id' ) );
				$folder_id = $file->id;
			} else {
				$first     = reset( $files );
				$folder_id = $first->getId();
			}

			if ( empty( $folder_id ) ) {
				return false;
			}

			$parent = $folder_id;
		}

		return $folder_id;
	}

	/**
	 * Array filter callback: keep non-empty, non-whitespace segments.
	 *
	 * @since 1.0.0
	 *
	 * @param string $segment Path segment.
	 * @return bool
	 */
	public static function is_non_empty( $segment ) {
		return '' !== trim( (string) $segment );
	}
}
