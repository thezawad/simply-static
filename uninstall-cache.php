<?php
/**
 * Uninstall handler for Static Cache by Fionetix
 */

// Exit if accessed directly or not from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'fionetix_cache_options' );

// Clear any scheduled events
wp_clear_scheduled_hook( 'fionetix_cache_generate_url' );
wp_clear_scheduled_hook( 'fionetix_cache_cleanup' );

// Optionally remove cache files (commented out for safety)
/*
$cache_dir = WP_CONTENT_DIR . '/static-cache';
if ( is_dir( $cache_dir ) ) {
	// Recursively delete cache directory
	function fionetix_cache_delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			
			if ( is_dir( $path ) ) {
				fionetix_cache_delete_directory( $path );
			} else {
				unlink( $path );
			}
		}
		
		rmdir( $dir );
	}
	
	fionetix_cache_delete_directory( $cache_dir );
}
*/