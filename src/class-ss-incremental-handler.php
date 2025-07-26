<?php

namespace Simply_Static;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Incremental Export Handler class
 * 
 * Handles tracking changes and incremental exports
 */
class Incremental_Handler {

	/**
	 * Options instance
	 * @var Simply_Static\Options
	 */
	protected $options;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->options = Options::instance();
		$this->init();
	}

	/**
	 * Initialize incremental handler
	 * @return void
	 */
	private function init() {
		// Hook into post save/update to track changes
		add_action( 'save_post', array( $this, 'track_post_change' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'track_post_deletion' ) );
		add_action( 'wp_update_nav_menu', array( $this, 'track_menu_change' ) );
		add_action( 'customize_save_after', array( $this, 'track_customizer_change' ) );
		add_action( 'switch_theme', array( $this, 'track_theme_change' ) );
		
		// Filter pages for incremental export
		add_filter( 'simply_static_fetch_urls_batch', array( $this, 'filter_urls_for_incremental' ), 10, 1 );
	}

	/**
	 * Track post changes
	 *
	 * @param int $post_id Post ID
	 * @param WP_Post $post Post object
	 * @return void
	 */
	public function track_post_change( $post_id, $post ) {
		// Skip auto-saves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip if post is not public
		if ( ! in_array( get_post_status( $post_id ), array( 'publish', 'private' ) ) ) {
			return;
		}

		$this->mark_url_as_changed( get_permalink( $post_id ) );
		
		// Mark taxonomy pages as changed
		$this->mark_taxonomy_pages_changed( $post_id );
		
		// Mark related pages as changed (like archives)
		$this->mark_archive_pages_changed( $post );
		
		// Trigger auto-deploy if enabled
		$this->maybe_trigger_auto_deploy();
	}

	/**
	 * Track post deletion
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function track_post_deletion( $post_id ) {
		$permalink = get_permalink( $post_id );
		$this->mark_url_as_deleted( $permalink );
		
		// Trigger auto-deploy if enabled
		$this->maybe_trigger_auto_deploy();
	}

	/**
	 * Track menu changes
	 *
	 * @param int $menu_id Menu ID
	 * @return void
	 */
	public function track_menu_change( $menu_id ) {
		// Mark all pages as potentially changed since menu might appear on any page
		$this->mark_all_pages_changed();
		
		// Trigger auto-deploy if enabled
		$this->maybe_trigger_auto_deploy();
	}

	/**
	 * Track customizer changes
	 *
	 * @return void
	 */
	public function track_customizer_change() {
		// Mark all pages as potentially changed
		$this->mark_all_pages_changed();
		
		// Trigger auto-deploy if enabled
		$this->maybe_trigger_auto_deploy();
	}

	/**
	 * Track theme changes
	 *
	 * @return void
	 */
	public function track_theme_change() {
		// Mark all pages as changed when theme changes
		$this->mark_all_pages_changed();
		
		// Trigger auto-deploy if enabled
		$this->maybe_trigger_auto_deploy();
	}

	/**
	 * Mark URL as changed
	 *
	 * @param string $url URL to mark as changed
	 * @return void
	 */
	private function mark_url_as_changed( $url ) {
		$changed_urls = $this->get_changed_urls();
		$changed_urls[ $url ] = time();
		$this->save_changed_urls( $changed_urls );
	}

	/**
	 * Mark URL as deleted
	 *
	 * @param string $url URL to mark as deleted
	 * @return void
	 */
	private function mark_url_as_deleted( $url ) {
		$deleted_urls = $this->get_deleted_urls();
		$deleted_urls[ $url ] = time();
		$this->save_deleted_urls( $deleted_urls );
	}

	/**
	 * Mark taxonomy pages as changed
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	private function mark_taxonomy_pages_changed( $post_id ) {
		$taxonomies = get_object_taxonomies( get_post_type( $post_id ), 'objects' );
		
		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$terms = get_the_terms( $post_id, $taxonomy->name );
				if ( $terms && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$term_link = get_term_link( $term );
						if ( ! is_wp_error( $term_link ) ) {
							$this->mark_url_as_changed( $term_link );
						}
					}
				}
			}
		}
	}

	/**
	 * Mark archive pages as changed
	 *
	 * @param WP_Post $post Post object
	 * @return void
	 */
	private function mark_archive_pages_changed( $post ) {
		// Mark post type archive
		$archive_link = get_post_type_archive_link( $post->post_type );
		if ( $archive_link ) {
			$this->mark_url_as_changed( $archive_link );
		}
		
		// Mark author archive
		$author_link = get_author_posts_url( $post->post_author );
		if ( $author_link ) {
			$this->mark_url_as_changed( $author_link );
		}
		
		// Mark date archives
		$year_link = get_year_link( get_the_time( 'Y', $post ) );
		$month_link = get_month_link( get_the_time( 'Y', $post ), get_the_time( 'm', $post ) );
		$day_link = get_day_link( get_the_time( 'Y', $post ), get_the_time( 'm', $post ), get_the_time( 'd', $post ) );
		
		$this->mark_url_as_changed( $year_link );
		$this->mark_url_as_changed( $month_link );
		$this->mark_url_as_changed( $day_link );
		
		// Mark homepage if showing posts
		if ( 'post' === $post->post_type && 'posts' === get_option( 'show_on_front' ) ) {
			$this->mark_url_as_changed( home_url() );
		}
	}

	/**
	 * Mark all pages as changed (for global changes like menu, theme, etc.)
	 *
	 * @return void
	 */
	private function mark_all_pages_changed() {
		// Set a flag that all pages need regeneration
		$this->options->set( 'incremental_full_regeneration_needed', true )->save();
	}

	/**
	 * Filter URLs for incremental export
	 *
	 * @param array $urls Array of URLs to process
	 * @return array
	 */
	public function filter_urls_for_incremental( $urls ) {
		// If incremental export is not enabled, return all URLs
		if ( ! $this->options->get( 'incremental_export' ) ) {
			return $urls;
		}

		// If full regeneration is needed, return all URLs and clear flag
		if ( $this->options->get( 'incremental_full_regeneration_needed' ) ) {
			$this->options->set( 'incremental_full_regeneration_needed', false )->save();
			$this->clear_changed_urls();
			return $urls;
		}

		$changed_urls = $this->get_changed_urls();
		$deleted_urls = $this->get_deleted_urls();
		
		// If no changes, return empty array
		if ( empty( $changed_urls ) && empty( $deleted_urls ) ) {
			return array();
		}

		// Filter URLs to only include changed ones
		$filtered_urls = array();
		foreach ( $urls as $url ) {
			if ( isset( $changed_urls[ $url ] ) ) {
				$filtered_urls[] = $url;
			}
		}

		// Handle deleted URLs (remove from static site)
		foreach ( $deleted_urls as $url => $timestamp ) {
			// Mark for deletion in the static site
			$this->handle_deleted_url( $url );
		}

		// Clear processed changes
		$this->clear_changed_urls();
		$this->clear_deleted_urls();

		return $filtered_urls;
	}

	/**
	 * Handle deleted URL (remove from static site)
	 *
	 * @param string $url URL to remove
	 * @return void
	 */
	private function handle_deleted_url( $url ) {
		// Get the file path for this URL
		$archive_dir = $this->options->get_archive_dir();
		$relative_path = str_replace( Util::origin_url(), '', $url );
		$file_path = $archive_dir . ltrim( $relative_path, '/' );
		
		// Add .html extension if needed
		if ( ! pathinfo( $file_path, PATHINFO_EXTENSION ) ) {
			$file_path .= '/index.html';
		}

		// Remove the file if it exists
		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
		}
	}

	/**
	 * Maybe trigger auto-deploy
	 *
	 * @return void
	 */
	private function maybe_trigger_auto_deploy() {
		if ( ! $this->options->get( 'auto_deploy_enabled' ) ) {
			return;
		}

		// Schedule auto-deploy with a delay to batch changes
		if ( ! wp_next_scheduled( 'simply_static_auto_deploy' ) ) {
			wp_schedule_single_event( time() + 300, 'simply_static_auto_deploy' ); // 5 minute delay
		}
	}

	/**
	 * Get changed URLs
	 *
	 * @return array
	 */
	private function get_changed_urls() {
		return get_option( 'simply_static_changed_urls', array() );
	}

	/**
	 * Save changed URLs
	 *
	 * @param array $urls Changed URLs array
	 * @return void
	 */
	private function save_changed_urls( $urls ) {
		update_option( 'simply_static_changed_urls', $urls );
	}

	/**
	 * Clear changed URLs
	 *
	 * @return void
	 */
	private function clear_changed_urls() {
		delete_option( 'simply_static_changed_urls' );
	}

	/**
	 * Get deleted URLs
	 *
	 * @return array
	 */
	private function get_deleted_urls() {
		return get_option( 'simply_static_deleted_urls', array() );
	}

	/**
	 * Save deleted URLs
	 *
	 * @param array $urls Deleted URLs array
	 * @return void
	 */
	private function save_deleted_urls( $urls ) {
		update_option( 'simply_static_deleted_urls', $urls );
	}

	/**
	 * Clear deleted URLs
	 *
	 * @return void
	 */
	private function clear_deleted_urls() {
		delete_option( 'simply_static_deleted_urls' );
	}
}