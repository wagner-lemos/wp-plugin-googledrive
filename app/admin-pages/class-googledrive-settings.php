<?php
/**
 * Google Drive Settings.
 *
 * @link          https://wpmudev.com/
 * @since         1.1.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2025, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\App\Admin_Pages;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;

class Google_Drive extends Base {
	/**
	 * The page title.
	 *
	 * @var string
	 */
	private $page_title;

	/**
	 * The page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'wpmudev-plugin-test-googledrive';

	/**
	 * Google Drive auth credentials.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $creds = array(); 

	/**
	 * Option name for credentials (reusing the same as original auth).
	 *
	 * @var string
	 */
	private $option_name = 'wpmudev_plugin_tests_auth';

	/**
	 * Page Assets.
	 *
	 * @var array
	 */
	private $page_scripts = array();

	/**
	 * Assets version.
	 *
	 * @var string
	 */
	private $assets_version = '';

	/**
	 * A unique string id to be used in markup and jsx.
	 *
	 * @var string
	 */
	private $unique_id = '';

	/**
	 * Initializes the page.
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public function init() {
		$this->page_title     = __( 'Google Drive Test', 'wpmudev-plugin-test' );
		$this->creds          = get_option( $this->option_name, array() );
		$this->assets_version = ! empty( $this->script_data( 'version' ) ) ? $this->script_data( 'version' ) : WPMUDEV_PLUGINTEST_VERSION;
		$this->unique_id      = "wpmudev_plugintest_drive_main_wrap-{$this->assets_version}";

		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'show_auth_notices' ) );
		// Add body class to admin pages.
		add_filter( 'admin_body_class', array( $this, 'admin_body_classes' ) );
	}

	public function register_admin_page() {
		$page = add_menu_page(
			'Google Drive Test',
			$this->page_title,
			'manage_options',
			$this->page_slug,
			array( $this, 'callback' ),
			'dashicons-cloud',
			7
		);

		add_action( 'load-' . $page, array( $this, 'prepare_assets' ) );
	}

	/**
	 * The admin page callback method.
	 *
	 * @return void
	 */
	public function callback() {
		$this->view();
	}

	/**
	 * Prepares assets.
	 *
	 * @return void
	 */
	public function prepare_assets() {
		if ( ! is_array( $this->page_scripts ) ) {
			$this->page_scripts = array();
		}

		$handle       = 'wpmudev_plugintest_drivepage';
		$src          = WPMUDEV_PLUGINTEST_ASSETS_URL . '/js/drivetestpage.min.js';
		$style_src    = WPMUDEV_PLUGINTEST_ASSETS_URL . '/css/drivetestpage.min.css';
		$dependencies = ! empty( $this->script_data( 'dependencies' ) )
			? $this->script_data( 'dependencies' )
			: array(
				'wp-element',
				'wp-i18n',
				'wp-api-fetch',
			);

		$this->page_scripts[ $handle ] = array(
			'src'       => $src,
			'style_src' => $style_src,
			'deps'      => $dependencies,
			'ver'       => $this->assets_version,
			'strategy'  => true,
			'localize'  => array(
				'dom_element_id'          => $this->unique_id,
				'restEndpointSave'        => 'wpmudev/v1/drive/save-credentials',
				'restEndpointGet'         => 'wpmudev/v1/drive/get-credentials',
				'restEndpointAuth'        => 'wpmudev/v1/drive/auth',
				'restEndpointDisconnect'  => 'wpmudev/v1/drive/disconnect',
				'restEndpointFiles'       => 'wpmudev/v1/drive/files',
				'restEndpointUpload'      => 'wpmudev/v1/drive/upload',
				'restEndpointDownloadUrl' => 'wpmudev/v1/drive/download-url',
				'restEndpointCreate'      => 'wpmudev/v1/drive/create-folder',
				'nonce'                   => wp_create_nonce( 'wp_rest' ),
				'authStatus'              => $this->get_auth_status(),
				'redirectUri'             => home_url( '/wp-json/wpmudev/v1/drive/callback' ),
				'hasCredentials'          => ! empty( $this->creds['client_id'] ) && ! empty( $this->creds['client_secret'] ),
				'i18n'                    => array(
					'title' => __( 'Google Drive Test', 'wpmudev-plugin-test' ),
				),
			),
		);
	}

	/**
	 * Checks if user is authenticated with Google Drive.
	 *
	 * @return bool
	 */
	private function get_auth_status() {
		$access_token = get_option( 'wpmudev_drive_access_token', '' );
		$expires_at   = get_option( 'wpmudev_drive_token_expires', 0 );
		
		return ! empty( $access_token ) && time() < $expires_at;
	}

	/**
	 * Gets assets data for given key.
	 *
	 * @param string $key
	 *
	 * @return string|array
	 */
	protected function script_data( string $key = '' ) {
		$raw_script_data = $this->raw_script_data();

		return ! empty( $key ) && ! empty( $raw_script_data[ $key ] ) ? $raw_script_data[ $key ] : '';
	}

	/**
	 * Gets the script data from assets php file.
	 *
	 * @return array
	 */
	protected function raw_script_data(): array {
		static $script_data = null;

		if ( is_null( $script_data ) && file_exists( WPMUDEV_PLUGINTEST_DIR . 'assets/js/drivetestpage.min.asset.php' ) ) {
			$script_data = include WPMUDEV_PLUGINTEST_DIR . 'assets/js/drivetestpage.min.asset.php';
		}

		return (array) $script_data;
	}

	/**
	 * Prepares assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! empty( $this->page_scripts ) ) {
			foreach ( $this->page_scripts as $handle => $page_script ) {
				wp_register_script(
					$handle,
					$page_script['src'],
					$page_script['deps'],
					$page_script['ver'],
					$page_script['strategy']
				);

				if ( ! empty( $page_script['localize'] ) ) {
					wp_localize_script( $handle, 'wpmudevDriveTest', $page_script['localize'] );
				}

				wp_enqueue_script( $handle );

				if ( ! empty( $page_script['style_src'] ) ) {
					wp_enqueue_style( $handle, $page_script['style_src'], array(), $this->assets_version );
				}
			}
		}
	}

	/**
	 * Prints the wrapper element which React will use as root.
	 *
	 * @return void
	 */
	protected function view() {
		echo '<div id="' . esc_attr( $this->unique_id ) . '" class="sui-wrap"></div>';
	}

	/**
	 * Show authentication notices.
	 *
	 * @return void
	 */
	public function show_auth_notices() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== $this->page_slug ) {
			return;
		}

		if ( isset( $_GET['google_auth'] ) ) {
			if ( $_GET['google_auth'] === 'success' ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . 
					esc_html__( 'Successfully authenticated with Google Drive!', 'wpmudev-plugin-test' ) . 
					'</p></div>';
			} elseif ( $_GET['google_auth'] === 'error' && isset( $_GET['error_message'] ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . 
					esc_html__( 'Authentication failed: ', 'wpmudev-plugin-test' ) . 
					esc_html( urldecode( $_GET['error_message'] ) ) . 
					'</p></div>';
			}
		}
	}

	/**
	 * Adds the SUI class on markup body.
	 *
	 * @param string $classes
	 *
	 * @return string
	 */
	public function admin_body_classes( $classes = '' ) {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $classes;
		}

		$current_screen = get_current_screen();

		if ( empty( $current_screen->id ) || ! strpos( $current_screen->id, $this->page_slug ) ) {
			return $classes;
		}

		$classes .= ' sui-' . str_replace( '.', '-', WPMUDEV_PLUGINTEST_SUI_VERSION ) . ' ';

		return $classes;
	}
}
