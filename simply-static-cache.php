<?php
/**
 * Plugin Name: Static Cache by Fionetix
 * Plugin URI: https://www.fionetix.com
 * Description: A high-performance static caching plugin that serves static content without database calls. Built for speed and reliability.
 * Version: 1.0.0
 * Author: Fionetix and Zawad Bin Hafiz
 * Author URI: https://www.fionetix.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: static-cache-fionetix
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'FIONETIX_CACHE_VERSION', '1.0.0' );
define( 'FIONETIX_CACHE_PATH', plugin_dir_path( __FILE__ ) );
define( 'FIONETIX_CACHE_URL', plugin_dir_url( __FILE__ ) );
define( 'FIONETIX_CACHE_BASENAME', plugin_basename( __FILE__ ) );

// Initialize the plugin
add_action( 'plugins_loaded', 'fionetix_cache_init' );

function fionetix_cache_init() {
	// Load the main plugin class
	require_once FIONETIX_CACHE_PATH . 'src/class-fionetix-cache-plugin.php';
	
	// Initialize the plugin
	Fionetix_Cache_Plugin::instance();
}

/**
 * Activation hook
 */
register_activation_hook( __FILE__, 'fionetix_cache_activate' );
function fionetix_cache_activate() {
	// Create cache directory
	$cache_dir = WP_CONTENT_DIR . '/static-cache';
	if ( ! file_exists( $cache_dir ) ) {
		wp_mkdir_p( $cache_dir );
		
		// Add .htaccess file for security
		$htaccess_content = "# Static Cache Directory\n";
		$htaccess_content .= "# Allow only static files\n";
		$htaccess_content .= "<Files \"*.php\">\n";
		$htaccess_content .= "    Require all denied\n";
		$htaccess_content .= "</Files>\n";
		
		file_put_contents( $cache_dir . '/.htaccess', $htaccess_content );
	}
	
	// Set default options
	$default_options = array(
		'enabled' => true,
		'cache_posts' => true,
		'cache_pages' => true,
		'cache_archives' => false,
		'auto_cache_new_posts' => true,
		'serve_cached_content' => true,
	);
	
	add_option( 'fionetix_cache_options', $default_options );
}

/**
 * Deactivation hook
 */
register_deactivation_hook( __FILE__, 'fionetix_cache_deactivate' );
function fionetix_cache_deactivate() {
	// Clear any scheduled events
	wp_clear_scheduled_hook( 'fionetix_cache_cleanup' );
	wp_clear_scheduled_hook( 'fionetix_cache_generate_url' );
}

/**
 * Uninstall hook
 */
register_uninstall_hook( __FILE__, 'fionetix_cache_uninstall' );
function fionetix_cache_uninstall() {
	// Include uninstall handler
	include_once FIONETIX_CACHE_PATH . 'uninstall-cache.php';
}