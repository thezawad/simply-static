<?php

/**
 * Main plugin class for Simply Static Cache
 */
class SS_Cache_Plugin {

	/**
	 * Plugin instance
	 *
	 * @var SS_Cache_Plugin
	 */
	private static $instance = null;

	/**
	 * Plugin options
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Cache directory path
	 *
	 * @var string
	 */
	private $cache_dir = '';

	/**
	 * Get plugin instance
	 *
	 * @return SS_Cache_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->cache_dir = WP_CONTENT_DIR . '/static-cache';
		$this->options = get_option( 'ss_cache_options', array() );
		
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Early request interception - highest priority
		add_action( 'init', array( $this, 'maybe_serve_cached_content' ), 1 );
		
		// Post publishing hooks
		add_action( 'publish_post', array( $this, 'cache_post_on_publish' ) );
		add_action( 'publish_page', array( $this, 'cache_post_on_publish' ) );
		add_action( 'post_updated', array( $this, 'invalidate_post_cache' ), 10, 3 );
		
		// Cron event handler for cache generation
		add_action( 'ss_cache_generate_url', array( $this, 'generate_cache_for_url' ) );
		
		// Admin interface
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}

		// AJAX handlers
		add_action( 'wp_ajax_ss_cache_generate', array( $this, 'ajax_cache_url' ) );
		add_action( 'wp_ajax_ss_cache_clear', array( $this, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_ss_cache_clear_all', array( $this, 'ajax_clear_all_cache' ) );
	}

	/**
	 * Check if we should serve cached content and serve it if available
	 */
	public function maybe_serve_cached_content() {
		// Skip if disabled
		if ( ! $this->get_option( 'enabled' ) || ! $this->get_option( 'serve_cached_content' ) ) {
			return;
		}

		// Skip for admin, feed, REST API, etc.
		if ( is_admin() || is_feed() || is_preview() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		// Skip if user is logged in (optional - could be made configurable)
		if ( is_user_logged_in() ) {
			return;
		}

		// Get current URL
		$current_url = $this->get_current_url();
		$cache_file = $this->get_cache_file_path( $current_url );

		// Serve cached content if it exists
		if ( file_exists( $cache_file ) && is_readable( $cache_file ) ) {
			$this->serve_cached_file( $cache_file );
			exit; // Important: stop WordPress processing
		}

		// If no cache exists and auto-caching is enabled, generate it
		if ( $this->get_option( 'auto_generate_cache' ) ) {
			// Schedule cache generation for this URL (non-blocking)
			wp_schedule_single_event( time() + 5, 'ss_cache_generate_url', array( $current_url ) );
		}
	}

	/**
	 * Serve cached file content
	 *
	 * @param string $cache_file Path to cached file
	 */
	private function serve_cached_file( $cache_file ) {
		// Get file content
		$content = file_get_contents( $cache_file );
		
		if ( false === $content ) {
			return; // Let WordPress handle the request normally
		}

		// Set appropriate headers
		$this->set_cache_headers();
		
		// Output the cached content
		echo $content;
	}

	/**
	 * Set appropriate cache headers
	 */
	private function set_cache_headers() {
		// Set content type
		header( 'Content-Type: text/html; charset=UTF-8' );
		
		// Set cache headers
		$max_age = apply_filters( 'ss_cache_max_age', 3600 ); // 1 hour default
		header( 'Cache-Control: public, max-age=' . $max_age );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $max_age ) . ' GMT' );
		
		// Add custom header to identify cached content
		header( 'X-Simply-Static-Cache: HIT' );
	}

	/**
	 * Generate cache for a URL by making an HTTP request
	 *
	 * @param string $url URL to cache
	 * @return bool Success status
	 */
	public function generate_cache_for_url( $url ) {
		try {
			// Make HTTP request to get the page content
			$response = wp_remote_get( $url, array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent' => 'Simply Static Cache/1.0.0',
				),
			) );

			if ( is_wp_error( $response ) ) {
				error_log( 'SS Cache: Error fetching URL ' . $url . ': ' . $response->get_error_message() );
				return false;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			
			// Only cache successful responses
			if ( $status_code !== 200 ) {
				error_log( 'SS Cache: Non-200 response for URL ' . $url . ': ' . $status_code );
				return false;
			}

			$content = wp_remote_retrieve_body( $response );
			
			if ( empty( $content ) ) {
				error_log( 'SS Cache: Empty content for URL ' . $url );
				return false;
			}

			// Save the content to cache
			$cache_file = $this->get_cache_file_path( $url );
			$cache_dir = dirname( $cache_file );

			// Create directory if it doesn't exist
			if ( ! file_exists( $cache_dir ) ) {
				wp_mkdir_p( $cache_dir );
			}

			// Write content to file
			$success = file_put_contents( $cache_file, $content );
			
			if ( false !== $success ) {
				// Log success
				error_log( 'SS Cache: Successfully cached URL ' . $url . ' to ' . $cache_file );
				return true;
			} else {
				error_log( 'SS Cache: Failed to write cache file for URL ' . $url );
				return false;
			}

		} catch ( Exception $e ) {
			error_log( 'SS Cache: Exception generating cache for ' . $url . ': ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Cache post when published
	 *
	 * @param int $post_id Post ID
	 */
	public function cache_post_on_publish( $post_id ) {
		if ( ! $this->get_option( 'auto_cache_new_posts' ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Get post URL
		$post_url = get_permalink( $post_id );
		
		// Schedule cache generation
		wp_schedule_single_event( time() + 10, 'ss_cache_generate_url', array( $post_url ) );
	}

	/**
	 * Invalidate cache when post is updated
	 *
	 * @param int $post_id Post ID
	 * @param WP_Post $post_after Post object after update
	 * @param WP_Post $post_before Post object before update
	 */
	public function invalidate_post_cache( $post_id, $post_after, $post_before ) {
		$post_url = get_permalink( $post_id );
		$this->clear_url_cache( $post_url );
	}

	/**
	 * Clear cache for a specific URL
	 *
	 * @param string $url URL to clear cache for
	 * @return bool Success status
	 */
	public function clear_url_cache( $url ) {
		$cache_file = $this->get_cache_file_path( $url );
		
		if ( file_exists( $cache_file ) ) {
			return unlink( $cache_file );
		}
		
		return true; // File doesn't exist, so it's "cleared"
	}

	/**
	 * Get cache file path for a URL
	 *
	 * @param string $url URL
	 * @return string Cache file path
	 */
	private function get_cache_file_path( $url ) {
		// Parse URL
		$parsed = parse_url( $url );
		$path = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		
		// Remove trailing slash and add .html
		$path = rtrim( $path, '/' );
		if ( empty( $path ) ) {
			$path = '/index';
		}
		
		$cache_file = $this->cache_dir . $path . '.html';
		
		return $cache_file;
	}

	/**
	 * Get current URL
	 *
	 * @return string Current URL
	 */
	private function get_current_url() {
		$protocol = is_ssl() ? 'https://' : 'http://';
		$host = $_SERVER['HTTP_HOST'];
		$uri = $_SERVER['REQUEST_URI'];
		
		return $protocol . $host . $uri;
	}

	/**
	 * Get option value
	 *
	 * @param string $key Option key
	 * @param mixed $default Default value
	 * @return mixed Option value
	 */
	private function get_option( $key, $default = false ) {
		return isset( $this->options[ $key ] ) ? $this->options[ $key ] : $default;
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Simply Static Cache', 'simply-static-cache' ),
			__( 'Static Cache', 'simply-static-cache' ),
			'manage_options',
			'ss-cache-settings',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Initialize admin settings
	 */
	public function admin_init() {
		register_setting( 'ss_cache_options', 'ss_cache_options' );
	}

	/**
	 * Admin page content
	 */
	public function admin_page() {
		include SS_CACHE_PATH . 'templates/admin-page.php';
	}

	/**
	 * AJAX handler for caching a URL
	 */
	public function ajax_cache_url() {
		check_ajax_referer( 'ss_cache_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$url = sanitize_url( $_POST['url'] );
		$success = $this->generate_cache_for_url( $url );

		wp_send_json( array(
			'success' => $success,
			'message' => $success ? __( 'Cache generated successfully', 'simply-static-cache' ) : __( 'Failed to generate cache', 'simply-static-cache' )
		) );
	}

	/**
	 * AJAX handler for clearing cache
	 */
	public function ajax_clear_cache() {
		check_ajax_referer( 'ss_cache_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$url = sanitize_url( $_POST['url'] );
		$success = $this->clear_url_cache( $url );

		wp_send_json( array(
			'success' => $success,
			'message' => $success ? __( 'Cache cleared successfully', 'simply-static-cache' ) : __( 'Failed to clear cache', 'simply-static-cache' )
		) );
	}

	/**
	 * AJAX handler for clearing all cache
	 */
	public function ajax_clear_all_cache() {
		check_ajax_referer( 'ss_cache_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$success = $this->clear_all_cache();

		wp_send_json( array(
			'success' => $success,
			'message' => $success ? __( 'All cache cleared successfully', 'simply-static-cache' ) : __( 'Failed to clear all cache', 'simply-static-cache' )
		) );
	}

	/**
	 * Clear all cached files
	 *
	 * @return bool Success status
	 */
	public function clear_all_cache() {
		if ( ! is_dir( $this->cache_dir ) ) {
			return true; // No cache directory, so it's "cleared"
		}

		try {
			$this->delete_directory_contents( $this->cache_dir );
			return true;
		} catch ( Exception $e ) {
			error_log( 'SS Cache: Error clearing all cache: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Recursively delete directory contents
	 *
	 * @param string $dir Directory path
	 */
	private function delete_directory_contents( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			
			if ( is_dir( $path ) ) {
				$this->delete_directory_contents( $path );
				rmdir( $path );
			} else {
				// Only delete .html files and .htaccess files we created
				if ( pathinfo( $path, PATHINFO_EXTENSION ) === 'html' || basename( $path ) === '.htaccess' ) {
					unlink( $path );
				}
			}
		}
	}
}