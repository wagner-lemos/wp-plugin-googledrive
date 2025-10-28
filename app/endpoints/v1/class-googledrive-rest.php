<?php
/**
 * Google Drive API endpoints using direct HTTP calls.
 *
 * @link          https://wpmudev.com/
 * @since         1.1.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2025, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\Endpoints\V1;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;

class Drive_API extends Base {

	/**
	 * OAuth redirect URI.
	 *
	 * @var string
	 */
	private $redirect_uri;

	/**
	 * Google Drive API scopes.
	 *
	 * @var array
	 */
    private $scopes = array(
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/drive.readonly',
    );

	/**
	 * Initialize the class.
	 */
	public function init() {
		$this->redirect_uri = home_url( '/wp-json/wpmudev/v1/drive/callback' );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Save credentials endpoint
		register_rest_route( 'wpmudev/v1/drive', '/save-credentials', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'save_credentials' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'client_id' => array(
					'required' => true,
					'type'     => 'string',
				),
				'client_secret' => array(
					'required' => true,
					'type'     => 'string',
				),
				'_wpnonce' => array(
					'required' => false,
					'type'     => 'string',
				),
			),
		) );

		// Get credentials endpoint
		register_rest_route( 'wpmudev/v1/drive', '/get-credentials', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_credentials' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		// Authentication endpoint
		register_rest_route( 'wpmudev/v1/drive', '/auth', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'start_auth' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'_wpnonce' => array(
					'required' => false,
					'type'     => 'string',
				),
			),
		) );

		// OAuth callback
		register_rest_route( 'wpmudev/v1/drive', '/callback', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_callback' ),
		) );

		// List files
		register_rest_route( 'wpmudev/v1/drive', '/files', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_files' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		// Upload file
		register_rest_route( 'wpmudev/v1/drive', '/upload', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'upload_file' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		// Get download URL
		register_rest_route( 'wpmudev/v1/drive', '/download-url', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_download_url' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'file_id' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		) );

		// Create folder
		register_rest_route( 'wpmudev/v1/drive', '/create-folder', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_folder' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'name' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		) );

		// Disconnect/Revoke access
		register_rest_route( 'wpmudev/v1/drive', '/disconnect', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'disconnect' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'_wpnonce' => array(
					'required' => false,
					'type'     => 'string',
				),
			),
		) );
	}

	/**
	 * Get saved Google Drive credentials.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_credentials( WP_REST_Request $request ) {
		$credentials = get_option( 'wpmudev_plugin_tests_auth', array() );

		if ( empty( $credentials ) ) {
			return new WP_REST_Response( array(
				'success'     => false,
				'credentials' => null,
				'message'     => __( 'No credentials found', 'wpmudev-plugin-test' ),
			) );
		}

		// Return credentials with masked secret
		return new WP_REST_Response( array(
			'success'     => true,
			'credentials' => array(
				'client_id'     => $credentials['client_id'] ?? '',
				'client_secret' => '******************************',
			),
		) );
	}

	/**
	 * Save Google Drive credentials.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_credentials( WP_REST_Request $request ) {
		// Verify nonce for security
		$nonce = $request->get_param( '_wpnonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed. Please refresh the page and try again.', 'wpmudev-plugin-test' ), array( 'status' => 403 ) );
		}

		$client_id     = sanitize_text_field( $request->get_param( 'client_id' ) );
		$client_secret = sanitize_text_field( $request->get_param( 'client_secret' ) );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error( 'missing_credentials', __( 'Client ID and Client Secret are required', 'wpmudev-plugin-test' ), array( 'status' => 400 ) );
		}

		$credentials = array(
			'client_id'     => $client_id,
			'client_secret' => $this->encrypt_credential( $client_secret ),
		);

		update_option( 'wpmudev_plugin_tests_auth', $credentials );

		return new WP_REST_Response( array(
			'success' => true,
			'message' => __( 'Credentials saved successfully', 'wpmudev-plugin-test' ),
		) );
	}

	/**
	 * Start OAuth authentication.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_auth( WP_REST_Request $request ) {
		// Verify nonce for security
		$nonce = $request->get_param( '_wpnonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed. Please refresh the page and try again.', 'wpmudev-plugin-test' ), array( 'status' => 403 ) );
		}

		$auth_creds = get_option( 'wpmudev_plugin_tests_auth', array() );
		
		if ( empty( $auth_creds['client_id'] ) || empty( $auth_creds['client_secret'] ) ) {
			return new WP_Error( 'no_credentials', __( 'Please save your Google Drive credentials first', 'wpmudev-plugin-test' ), array( 'status' => 400 ) );
		}
		
		$client_secret = $this->decrypt_credential( $auth_creds['client_secret'] );

		$state = wp_generate_password( 32, false );
		set_transient( 'wpmudev_drive_auth_state', $state, 600 ); // 10 minutes

		// Use a more compatible OAuth URL format
		$auth_url = add_query_arg( array(
			'client_id'     => $auth_creds['client_id'],
			'redirect_uri'  => urlencode( $this->redirect_uri ),
			'scope'         => implode( ' ', $this->scopes ),
			'response_type' => 'code',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
			'include_granted_scopes' => 'true',
		), 'https://accounts.google.com/o/oauth2/v2/auth' );

		return new WP_REST_Response( array(
			'auth_url' => $auth_url,
			'message'  => __( 'Opening Google authentication in a new window. If blocked, please check your browser settings.', 'wpmudev-plugin-test' ),
		) );
	}

	/**
	 * Handle OAuth callback.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_callback( WP_REST_Request $request ) {
		$code  = $request->get_param( 'code' );
		$state = $request->get_param( 'state' );
		$error = $request->get_param( 'error' );

		if ( ! empty( $error ) ) {
			$redirect_url = add_query_arg( array(
				'page'          => 'wpmudev-plugin-test-googledrive',
				'google_auth'   => 'error',
				'error_message' => urlencode( $error ),
			), admin_url( 'admin.php' ) );
			wp_redirect( $redirect_url );
			exit;
		}

		$stored_state = get_transient( 'wpmudev_drive_auth_state' );
		if ( $state !== $stored_state ) {
			$redirect_url = add_query_arg( array(
				'page'          => 'wpmudev-plugin-test-googledrive',
				'google_auth'   => 'error',
				'error_message' => urlencode( __( 'Invalid state parameter', 'wpmudev-plugin-test' ) ),
			), admin_url( 'admin.php' ) );
			wp_redirect( $redirect_url );
			exit;
		}

		if ( empty( $code ) ) {
			$redirect_url = add_query_arg( array(
				'page'          => 'wpmudev-plugin-test-googledrive',
				'google_auth'   => 'error',
				'error_message' => urlencode( __( 'No authorization code received', 'wpmudev-plugin-test' ) ),
			), admin_url( 'admin.php' ) );
			wp_redirect( $redirect_url );
			exit;
		}

		$auth_creds = get_option( 'wpmudev_plugin_tests_auth', array() );
		$client_secret = $this->decrypt_credential( $auth_creds['client_secret'] );
		
		// Exchange code for tokens
		$token_response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'body' => array(
				'client_id'     => $auth_creds['client_id'],
				'client_secret' => $client_secret,
				'code'          => $code,
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => $this->redirect_uri,
			),
		) );

		if ( is_wp_error( $token_response ) ) {
			$redirect_url = add_query_arg( array(
				'page'          => 'wpmudev-plugin-test-googledrive',
				'google_auth'   => 'error',
				'error_message' => urlencode( $token_response->get_error_message() ),
			), admin_url( 'admin.php' ) );
			wp_redirect( $redirect_url );
			exit;
		}

		$token_data = json_decode( wp_remote_retrieve_body( $token_response ), true );

		if ( isset( $token_data['error'] ) ) {
			$redirect_url = add_query_arg( array(
				'page'          => 'wpmudev-plugin-test-googledrive',
				'google_auth'   => 'error',
				'error_message' => urlencode( $token_data['error_description'] ?? $token_data['error'] ),
			), admin_url( 'admin.php' ) );
			wp_redirect( $redirect_url );
			exit;
		}

		// Store tokens
		update_option( 'wpmudev_drive_access_token', $token_data['access_token'] );
		if ( ! empty( $token_data['refresh_token'] ) ) {
			update_option( 'wpmudev_drive_refresh_token', $token_data['refresh_token'] );
		}
		update_option( 'wpmudev_drive_token_expires', time() + $token_data['expires_in'] );

		// Clean up state
		delete_transient( 'wpmudev_drive_auth_state' );

		// Redirect back to admin page with success message
		$redirect_url = add_query_arg( array(
			'page'        => 'wpmudev-plugin-test-googledrive',
			'google_auth' => 'success',
		), admin_url( 'admin.php' ) );

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Ensure we have a valid access token.
	 *
	 * @return bool
	 */
	private function ensure_valid_token() {
		$access_token = get_option( 'wpmudev_drive_access_token', '' );
		$expires_at   = get_option( 'wpmudev_drive_token_expires', 0 );

		if ( empty( $access_token ) || time() >= $expires_at ) {
			// Try to refresh token
			$refresh_token = get_option( 'wpmudev_drive_refresh_token', '' );
			if ( empty( $refresh_token ) ) {
				return false;
			}

			$auth_creds = get_option( 'wpmudev_plugin_tests_auth', array() );
			$client_secret = $this->decrypt_credential( $auth_creds['client_secret'] );
			
			$refresh_response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
				'body' => array(
					'client_id'     => $auth_creds['client_id'],
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
			) );

			if ( is_wp_error( $refresh_response ) ) {
				return false;
			}

			$refresh_data = json_decode( wp_remote_retrieve_body( $refresh_response ), true );

			if ( isset( $refresh_data['error'] ) ) {
				return false;
			}

			update_option( 'wpmudev_drive_access_token', $refresh_data['access_token'] );
			update_option( 'wpmudev_drive_token_expires', time() + $refresh_data['expires_in'] );
		}

		return true;
	}

	/**
	 * List files from Google Drive.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_files( WP_REST_Request $request ) {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', __( 'Not authenticated with Google Drive', 'wpmudev-plugin-test' ), array( 'status' => 401 ) );
		}

		try {
			$page_size = max( 1, (int) $request->get_param( 'page_size' ) ?: 20 );
			$page_token = sanitize_text_field( (string) $request->get_param( 'page_token' ) );
			$query     = sanitize_text_field( (string) ( $request->get_param( 'q' ) ?: 'trashed=false' ) );

			$url = 'https://www.googleapis.com/drive/v3/files';
			$params = array(
				'pageSize' => $page_size,
				'q'        => $query,
				'fields'   => 'nextPageToken,files(id,name,mimeType,size,modifiedTime,webViewLink)',
			);
			if ( ! empty( $page_token ) ) {
				$params['pageToken'] = $page_token;
			}

			$response = wp_remote_get( add_query_arg( $params, $url ), array(
				'headers' => array(
					'Authorization' => 'Bearer ' . get_option( 'wpmudev_drive_access_token' ),
					'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				),
				'timeout' => 30,
			) );

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'api_error', $response->get_error_message(), array( 'status' => 500 ) );
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( isset( $data['error'] ) ) {
				return new WP_Error( 'api_error', $data['error']['message'], array( 'status' => 500 ) );
			}

			return new WP_REST_Response( array(
				'files'         => $data['files'] ?? array(),
				'nextPageToken' => $data['nextPageToken'] ?? '',
			) );

		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Upload file to Google Drive.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_file( WP_REST_Request $request ) {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', __( 'Not authenticated with Google Drive', 'wpmudev-plugin-test' ), array( 'status' => 401 ) );
		}

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file uploaded', 'wpmudev-plugin-test' ), array( 'status' => 400 ) );
		}

		$file = $files['file'];
		$name = sanitize_text_field( $request->get_param( 'name' ) ?: $file['name'] );

		try {
			// Create file metadata
			$metadata = array(
				'name' => $name,
			);

			// Upload file
			$boundary = wp_generate_password( 16, false );
			$body = "--{$boundary}\r\n";
			$body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
			$body .= json_encode( $metadata ) . "\r\n";
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Type: {$file['type']}\r\n\r\n";
			$body .= file_get_contents( $file['tmp_name'] ) . "\r\n";
			$body .= "--{$boundary}--";

			$response = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . get_option( 'wpmudev_drive_access_token' ),
					'Content-Type'  => "multipart/related; boundary={$boundary}",
					'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				),
				'body' => $body,
				'timeout' => 60,
			) );

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'upload_error', $response->get_error_message(), array( 'status' => 500 ) );
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( isset( $data['error'] ) ) {
				return new WP_Error( 'upload_error', $data['error']['message'], array( 'status' => 500 ) );
			}

			return new WP_REST_Response( array(
				'success' => true,
				'file'    => $data,
				'message' => __( 'File uploaded successfully', 'wpmudev-plugin-test' ),
			) );

		} catch ( Exception $e ) {
			return new WP_Error( 'upload_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Get download URL for a file from Google Drive.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_download_url( WP_REST_Request $request ) {
		// Check authentication first
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', __( 'Not authenticated with Google Drive', 'wpmudev-plugin-test' ), array( 'status' => 401 ) );
		}

		$file_id = sanitize_text_field( $request->get_param( 'file_id' ) );

		if ( empty( $file_id ) ) {
			return new WP_Error( 'missing_file_id', __( 'File ID is required', 'wpmudev-plugin-test' ), array( 'status' => 400 ) );
		}

		try {
			// Get file metadata to check if it exists and get filename
			$metadata_response = wp_remote_get( "https://www.googleapis.com/drive/v3/files/{$file_id}", array(
				'headers' => array(
					'Authorization' => 'Bearer ' . get_option( 'wpmudev_drive_access_token' ),
				),
				'timeout' => 30,
			) );

			if ( is_wp_error( $metadata_response ) ) {
				return new WP_Error( 'metadata_error', __( 'Failed to get file metadata', 'wpmudev-plugin-test' ), array( 'status' => 500 ) );
			}

			$metadata = json_decode( wp_remote_retrieve_body( $metadata_response ), true );

			if ( isset( $metadata['error'] ) ) {
				return new WP_Error( 'api_error', __( 'Google Drive API error: ', 'wpmudev-plugin-test' ) . $metadata['error']['message'], array( 'status' => 500 ) );
			}

			$filename = $metadata['name'] ?? 'download';
			
			// Return the direct Google Drive download URL
			$download_url = "https://www.googleapis.com/drive/v3/files/{$file_id}?alt=media";
			
			return new WP_REST_Response( array(
				'success'      => true,
				'download_url' => $download_url,
				'filename'     => $filename,
				'access_token' => get_option( 'wpmudev_drive_access_token' ),
			) );

		} catch ( Exception $e ) {
			return new WP_Error( 'download_error', __( 'Error getting download URL: ', 'wpmudev-plugin-test' ) . $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Create folder in Google Drive.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_folder( WP_REST_Request $request ) {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', __( 'Not authenticated with Google Drive', 'wpmudev-plugin-test' ), array( 'status' => 401 ) );
		}

		$name = sanitize_text_field( $request->get_param( 'name' ) );

		try {
			$metadata = array(
				'name'     => $name,
				'mimeType' => 'application/vnd.google-apps.folder',
			);

			$response = wp_remote_post( 'https://www.googleapis.com/drive/v3/files', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . get_option( 'wpmudev_drive_access_token' ),
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				),
				'body' => json_encode( $metadata ),
				'timeout' => 30,
			) );

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'create_error', $response->get_error_message(), array( 'status' => 500 ) );
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( isset( $data['error'] ) ) {
				return new WP_Error( 'create_error', $data['error']['message'], array( 'status' => 500 ) );
			}

			return new WP_REST_Response( array(
				'success' => true,
				'folder'  => $data,
				'message' => __( 'Folder created successfully', 'wpmudev-plugin-test' ),
			) );

		} catch ( Exception $e ) {
			return new WP_Error( 'create_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Disconnect from Google Drive and revoke access.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function disconnect( WP_REST_Request $request ) {
		// Verify nonce for security
		$nonce = $request->get_param( '_wpnonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed. Please refresh the page and try again.', 'wpmudev-plugin-test' ), array( 'status' => 403 ) );
		}

		try {
			$access_token = get_option( 'wpmudev_drive_access_token', '' );
			
			if ( empty( $access_token ) ) {
				return new WP_Error( 'no_tokens', __( 'No active connection found', 'wpmudev-plugin-test' ), array( 'status' => 400 ) );
			}

			// Revoke the access token with Google
			$revoke_url = add_query_arg( array(
				'token' => $access_token,
			), 'https://oauth2.googleapis.com/revoke' );

			$response = wp_remote_post( $revoke_url, array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
			) );

			// Clear stored tokens regardless of revoke response
			delete_option( 'wpmudev_drive_access_token' );
			delete_option( 'wpmudev_drive_refresh_token' );
			delete_option( 'wpmudev_drive_token_expires' );
			delete_transient( 'wpmudev_drive_auth_state' );

			// Log the revoke attempt
			if ( is_wp_error( $response ) ) {
				error_log( 'Google Drive disconnect: Failed to revoke token - ' . $response->get_error_message() );
			} else {
				$response_code = wp_remote_retrieve_response_code( $response );
				if ( $response_code === 200 ) {
					error_log( 'Google Drive disconnect: Token revoked successfully' );
				} else {
					error_log( 'Google Drive disconnect: Token revoke returned code ' . $response_code );
				}
			}

			return new WP_REST_Response( array(
				'success' => true,
				'message' => __( 'Successfully disconnected from Google Drive', 'wpmudev-plugin-test' ),
			) );

		} catch ( Exception $e ) {
			// Even if revoke fails, clear local tokens
			delete_option( 'wpmudev_drive_access_token' );
			delete_option( 'wpmudev_drive_refresh_token' );
			delete_option( 'wpmudev_drive_token_expires' );
			delete_transient( 'wpmudev_drive_auth_state' );
			
			return new WP_Error( 'disconnect_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Encrypt credential for secure storage.
	 *
	 * @param string $credential The credential to encrypt.
	 * @return string Encrypted credential.
	 */
	private function encrypt_credential( $credential ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $credential );
		}

		$key = $this->get_encryption_key();
		$iv = random_bytes( 16 );
		$encrypted = openssl_encrypt( $credential, 'AES-256-CBC', $key, 0, $iv );
		
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt credential from storage.
	 *
	 * @param string $encrypted_credential The encrypted credential.
	 * @return string Decrypted credential.
	 */
	private function decrypt_credential( $encrypted_credential ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $encrypted_credential );
		}

		$key = $this->get_encryption_key();
		$data = base64_decode( $encrypted_credential );
		$iv = substr( $data, 0, 16 );
		$encrypted = substr( $data, 16 );
		
		return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
	}

	/**
	 * Get encryption key for credentials.
	 *
	 * @return string Encryption key.
	 */
	private function get_encryption_key() {
		$key = get_option( 'wpmudev_plugin_test_encryption_key' );
		
		if ( empty( $key ) ) {
			$key = wp_generate_password( 32, true, true );
			update_option( 'wpmudev_plugin_test_encryption_key', $key );
		}
		
		return hash( 'sha256', $key . AUTH_SALT );
	}
}
