<?php
/**
 * Plugin Name: Simply Static Cache
 * Plugin URI: https://simplystatic.com
 * Description: A high-performance static caching plugin that serves static content without database calls, utilizing Simply Static's generation capabilities.
 * Version: 1.0.0
 * Author: Simply Static Team
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: simply-static-cache
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'SS_CACHE_VERSION', '1.0.0' );
define( 'SS_CACHE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SS_CACHE_URL', plugin_dir_url( __FILE__ ) );
define( 'SS_CACHE_BASENAME', plugin_basename( __FILE__ ) );

// Check if Simply Static is active
if ( ! function_exists( 'is_plugin_active' ) ) {
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

// Initialize the plugin
add_action( 'plugins_loaded', 'ss_cache_init' );

function ss_cache_init() {
	// Check if Simply Static is active
	if ( ! is_plugin_active( 'simply-static/simply-static.php' ) && ! class_exists( 'Simply_Static\Plugin' ) ) {
		add_action( 'admin_notices', 'ss_cache_missing_dependency_notice' );
		return;
	}

	// Load the main plugin class
	require_once SS_CACHE_PATH . 'src/class-ss-cache-plugin.php';
	
	// Initialize the plugin
	SS_Cache_Plugin::instance();
}

/**
 * Show admin notice if Simply Static is not active
 */
function ss_cache_missing_dependency_notice() {
	$message = sprintf(
		__( 'Simply Static Cache requires the Simply Static plugin to be installed and activated. <a href="%s">Install Simply Static</a>', 'simply-static-cache' ),
		admin_url( 'plugin-install.php?s=Simply+Static&tab=search&type=term' )
	);
	echo '<div class="notice notice-error"><p>' . wp_kses_post( $message ) . '</p></div>';
}

/**
 * Activation hook
 */
register_activation_hook( __FILE__, 'ss_cache_activate' );
function ss_cache_activate() {
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
	
	add_option( 'ss_cache_options', $default_options );
}

/**
 * Deactivation hook
 */
register_deactivation_hook( __FILE__, 'ss_cache_deactivate' );
function ss_cache_deactivate() {
	// Clear any scheduled events
	wp_clear_scheduled_hook( 'ss_cache_cleanup' );
	wp_clear_scheduled_hook( 'ss_cache_generate_url' );
}

/**
 * Uninstall hook
 */
register_uninstall_hook( __FILE__, 'ss_cache_uninstall' );
function ss_cache_uninstall() {
	// Include uninstall handler
	include_once SS_CACHE_PATH . 'uninstall-cache.php';
}