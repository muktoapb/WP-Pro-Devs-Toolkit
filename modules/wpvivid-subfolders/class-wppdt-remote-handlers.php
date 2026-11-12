<?php
/**
 * WPVivid storage handler subclasses.
 *
 * These extend WPVivid's own remote storage classes, so this file must only be
 * loaded after WPVivid has defined them (it is required from
 * WPPDT_WPVivid_Subfolders::register_handlers(), which runs while WPVivid is
 * building its remote collection).
 *
 * Each class definition is guarded with class_exists() so a missing parent
 * never causes a fatal error.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared constructor for path-based providers that nest natively.
 *
 * It lets the parent class run as usual, then rewrites the configured path to
 * include the per-site subfolder. The provider's own upload logic creates the
 * nested directories (Dropbox auto-creates parents, S3 uses key prefixes,
 * FTP/SFTP create the directory).
 *
 * @since 1.0.0
 */
trait WPPDT_Nested_Path_Trait {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $options Remote storage options. Empty when WPVivid builds
	 *                       the object only to register admin hooks.
	 */
	public function __construct( $options = array() ) {
		parent::__construct( $options );

		if ( ! empty( $options ) && isset( $this->options['path'] ) ) {
			$this->options['path'] = WPPDT_WPVivid_Subfolders::nested_path( $this->options['path'] );
		}
	}
}

if ( class_exists( 'WPvivid_Dropbox' ) && ! class_exists( 'WPPDT_Dropbox_Subfolders' ) ) {
	/**
	 * Dropbox storage with per-site subfolder.
	 */
	class WPPDT_Dropbox_Subfolders extends WPvivid_Dropbox {
		use WPPDT_Nested_Path_Trait;
	}
}

if ( class_exists( 'WPvivid_AMAZONS3Class' ) && ! class_exists( 'WPPDT_AmazonS3_Subfolders' ) ) {
	/**
	 * Amazon S3 storage with per-site subfolder.
	 */
	class WPPDT_AmazonS3_Subfolders extends WPvivid_AMAZONS3Class {
		use WPPDT_Nested_Path_Trait;
	}
}

if ( class_exists( 'Wpvivid_S3Compat' ) && ! class_exists( 'WPPDT_S3Compat_Subfolders' ) ) {
	/**
	 * S3-compatible storage with per-site subfolder.
	 */
	class WPPDT_S3Compat_Subfolders extends Wpvivid_S3Compat {
		use WPPDT_Nested_Path_Trait;
	}
}

if ( class_exists( 'WPvivid_FTPClass' ) && ! class_exists( 'WPPDT_FTP_Subfolders' ) ) {
	/**
	 * FTP storage with per-site subfolder.
	 */
	class WPPDT_FTP_Subfolders extends WPvivid_FTPClass {
		use WPPDT_Nested_Path_Trait;
	}
}

if ( class_exists( 'WPvivid_SFTPClass' ) && ! class_exists( 'WPPDT_SFTP_Subfolders' ) ) {
	/**
	 * SFTP storage with per-site subfolder.
	 */
	class WPPDT_SFTP_Subfolders extends WPvivid_SFTPClass {
		use WPPDT_Nested_Path_Trait;
	}
}

if ( class_exists( 'Wpvivid_Google_drive' ) && ! class_exists( 'WPPDT_GoogleDrive_Subfolders' ) ) {
	/**
	 * Google Drive storage with real nested subfolders.
	 *
	 * Google Drive addresses folders by ID, and WPVivid's private get_folder()
	 * only finds/creates a single folder at the Drive root, so it cannot nest
	 * and cannot be overridden on its own (private methods are not polymorphic).
	 *
	 * The three public entry points (upload/download/cleanup) are therefore
	 * reproduced from WPVivid with a single change: the folder is resolved via
	 * WPPDT_GDrive_Folders::resolve() instead of the parent's get_folder().
	 * All heavy lifting (chunked transfer, progress tracking, token refresh)
	 * still runs through the parent's public helpers.
	 *
	 * @since 1.0.0
	 */
	class WPPDT_GoogleDrive_Subfolders extends Wpvivid_Google_drive {

		/**
		 * Constructor: inject the per-site nested path.
		 *
		 * @param array $options Remote storage options.
		 */
		public function __construct( $options = array() ) {
			parent::__construct( $options );

			if ( ! empty( $options ) && isset( $this->options['path'] ) ) {
				$this->options['path'] = WPPDT_WPVivid_Subfolders::nested_path( $this->options['path'] );
			}
		}

		/**
		 * Uploads backup files into the nested folder.
		 *
		 * Mirrors Wpvivid_Google_drive::upload(); only the folder resolution
		 * differs.
		 *
		 * @param string $task_id  Task ID.
		 * @param array  $files    Files to upload.
		 * @param mixed  $callback Progress callback.
		 * @return array
		 */
		public function upload( $task_id, $files, $callback = '' ) {
			global $wpvivid_plugin;

			$client = $this->get_client();
			if ( $client['result'] == WPVIVID_FAILED ) {
				return $client;
			}
			$client = $client['data'];

			if ( $client === false ) {
				return array( 'result' => WPVIVID_FAILED, 'error' => 'Token refresh failed.' );
			}

			$service = new WPvivid_Google_Service_Drive( $client );
			$path    = $this->options['path'];
			$wpvivid_plugin->wpvivid_log->WriteLog( 'Check upload folder ' . $path, 'notice' );
			$folder_id = WPPDT_GDrive_Folders::resolve( $service, $path );

			if ( $folder_id == false ) {
				return array( 'result' => WPVIVID_FAILED, 'error' => 'Unable to create the backup folder on Google Drive. Please make sure the folder is writable and try again.' );
			}

			$upload_job = WPvivid_taskmanager::get_backup_sub_task_progress( $task_id, 'upload', WPVIVID_REMOTE_GOOGLEDRIVE );
			if ( empty( $upload_job ) ) {
				$job_data = array();
				foreach ( $files as $file ) {
					$file_data['size']     = filesize( $file );
					$file_data['uploaded'] = 0;
					$file_data['resumeUri'] = false;
					$file_data['progress'] = false;
					$job_data[ basename( $file ) ] = $file_data;
				}
				WPvivid_taskmanager::update_backup_sub_task_progress( $task_id, 'upload', WPVIVID_REMOTE_GOOGLEDRIVE, WPVIVID_UPLOAD_UNDO, 'Start uploading', $job_data );
				$upload_job = WPvivid_taskmanager::get_backup_sub_task_progress( $task_id, 'upload', WPVIVID_REMOTE_GOOGLEDRIVE );
			}

			foreach ( $files as $file ) {
				if ( is_array( $upload_job['job_data'] ) && array_key_exists( basename( $file ), $upload_job['job_data'] ) ) {
					if ( $upload_job['job_data'][ basename( $file ) ]['uploaded'] == 1 ) {
						continue;
					}
				}

				$this->last_time = time();
				$this->last_size = 0;

				if ( ! file_exists( $file ) ) {
					return array( 'result' => WPVIVID_FAILED, 'error' => $file . ' not found. The file might has been moved, renamed or deleted. Please reload the list and verify the file exists.' );
				}
				$wpvivid_plugin->wpvivid_log->WriteLog( 'Start uploading ' . basename( $file ), 'notice' );
				$wpvivid_plugin->set_time_limit( $task_id );
				$result = $this->_upload( $task_id, $file, $client, $service, $folder_id, $callback );
				if ( $result['result'] !== WPVIVID_SUCCESS ) {
					return $result;
				} else {
					WPvivid_taskmanager::wpvivid_reset_backup_retry_times( $task_id );
				}
				$ref = $this->check_token( $client, $service );
				if ( $ref['result'] !== WPVIVID_SUCCESS ) {
					return $ref;
				}
			}
			WPvivid_taskmanager::update_backup_sub_task_progress( $task_id, 'upload', WPVIVID_REMOTE_GOOGLEDRIVE, WPVIVID_UPLOAD_SUCCESS, 'Uploading completed.', $upload_job['job_data'] );
			return array( 'result' => WPVIVID_SUCCESS );
		}

		/**
		 * Downloads a backup file from the nested folder.
		 *
		 * Mirrors Wpvivid_Google_drive::download(); only the folder resolution
		 * differs.
		 *
		 * @param array  $file       File descriptor.
		 * @param string $local_path Local destination directory.
		 * @param mixed  $callback   Progress callback.
		 * @return array
		 */
		public function download( $file, $local_path, $callback = '' ) {
			try {
				global $wpvivid_plugin;
				$this->current_file_name = $file['file_name'];
				$this->current_file_size = $file['size'];
				$this->set_token();
				$wpvivid_plugin->wpvivid_download_log->WriteLog( 'Google Drive get client.', 'notice' );
				$client = $this->get_client();
				if ( $client['result'] == WPVIVID_FAILED ) {
					return $client;
				}
				$client = $client['data'];

				if ( $client === false ) {
					return array( 'result' => WPVIVID_FAILED, 'error' => 'Token refresh failed.' );
				}

				$service = new WPvivid_Google_Service_Drive( $client );

				$path = $this->options['path'];
				$wpvivid_plugin->wpvivid_download_log->WriteLog( 'Create local file.', 'notice' );
				$folder_id = WPPDT_GDrive_Folders::resolve( $service, $path );

				if ( $folder_id == false ) {
					return array( 'result' => WPVIVID_FAILED, 'error' => 'Unable to locate the backup folder on Google Drive. Please make sure the folder is writable and try again.' );
				}

				$response = $service->files->listFiles(
					array(
						'q'      => "name='" . $file['file_name'] . "' and '" . $folder_id . "' in parents",
						'fields' => 'files(id,size,webContentLink)',
					)
				);

				if ( sizeof( $response->getFiles() ) == 0 ) {
					return array( 'result' => WPVIVID_FAILED, 'error' => 'Downloading file failed. The file might be deleted or network doesn\'t work properly. Please verify the file and confirm the network connection and try again later.' );
				} else {
					$fileSize = $file['size'];
					$file_id  = '';
					foreach ( $response->getFiles() as $file ) {
						$file_id = $file->getId();
						break;
					}
					$wpvivid_plugin->wpvivid_download_log->WriteLog( 'Get download url.', 'notice' );
					$download_url = $this->get_download_url( $client, $file_id );

					if ( ! empty( $file_id ) || ! empty( $download_url ) ) {
						$file_path = trailingslashit( $local_path ) . $this->current_file_name;

						if ( file_exists( $file_path ) ) {
							$offset = filesize( $file_path );
						} else {
							$offset = 0;
						}

						$fh                  = fopen( $file_path, 'a' );
						$download_chunk_size = isset( $this->options['download_chunk_size'] ) && ! empty( $this->options['download_chunk_size'] ) ? $this->options['download_chunk_size'] : WPVIVID_GOOGLEDRIVE_UPLOAD_SIZE;
						$http                = $client->authorize();
						$wpvivid_plugin->wpvivid_download_log->WriteLog( 'Downloading file ' . $this->current_file_name . ', Size: ' . $this->current_file_size, 'notice' );
						while ( $offset < $fileSize ) {
							$download_end = min( $offset + $download_chunk_size - 1, $fileSize - 1 );

							if ( $offset > 0 ) {
								$options['headers']['Range'] = 'bytes=' . $offset . '-' . $download_end;
							} else {
								$options['headers']['Range'] = 'bytes=0-' . $download_end;
							}
							$request       = new WPvividGuzzleHttp\Psr7\Request( 'GET', $download_url, $options['headers'] );
							$http_request  = $http->send( $request );
							$http_response = $http_request->getStatusCode();
							if ( 200 == $http_response || 206 == $http_response ) {
								fwrite( $fh, $http_request->getBody()->getContents(), $download_chunk_size );
								$offset = $download_end + 1;
							} else {
								throw new Exception( 'Failed to obtain any new data at size: ' . $offset . ' http code:' . $http_response );
							}

							if ( ( time() - $this->last_time ) > 3 ) {
								if ( is_callable( $callback ) ) {
									call_user_func_array(
										$callback,
										array( $offset, $this->current_file_name, $this->current_file_size, $this->last_time, $this->last_size )
									);
								}
								$this->last_size = $offset;
								$this->last_time = time();
							}
						}
						fclose( $fh );
					} else {
						return array( 'result' => WPVIVID_FAILED, 'error' => 'Downloading file failed. The file might be deleted or network doesn\'t work properly. Please verify the file and confirm the network connection and try again later.' );
					}
				}
			} catch ( Exception $e ) {
				return array( 'result' => WPVIVID_FAILED, 'error' => $e->getMessage() );
			}
			return array( 'result' => WPVIVID_SUCCESS );
		}

		/**
		 * Deletes backup files from the nested folder (retention cleanup).
		 *
		 * Mirrors Wpvivid_Google_drive::cleanup(); only the folder resolution
		 * differs.
		 *
		 * @param array $files File names to remove.
		 * @return array
		 */
		public function cleanup( $files ) {
			$client = $this->get_client();
			if ( $client['result'] == WPVIVID_FAILED ) {
				return $client;
			}
			$client = $client['data'];

			if ( $client === false ) {
				return array( 'result' => WPVIVID_FAILED, 'error' => 'Token refresh failed.' );
			}

			$service = new WPvivid_Google_Service_Drive( $client );

			$path      = $this->options['path'];
			$folder_id = WPPDT_GDrive_Folders::resolve( $service, $path );

			if ( $folder_id == false ) {
				return array( 'result' => WPVIVID_FAILED, 'error' => 'Unable to locate the backup folder on Google Drive. Please make sure the folder is writable and try again.' );
			}

			foreach ( $files as $file ) {
				$delete_files = $service->files->listFiles(
					array(
						'q'      => "name='" . $file . "' and '" . $folder_id . "' in parents",
						'fields' => 'nextPageToken, files(id, name,mimeType)',
					)
				);

				if ( sizeof( $delete_files->getFiles() ) == 0 ) {
					continue;
				} else {
					foreach ( $delete_files->getFiles() as $file_google_drive ) {
						$file_id = $file_google_drive->getId();
						$service->files->delete( $file_id );
					}
				}
			}
			return array( 'result' => WPVIVID_SUCCESS );
		}
	}
}
