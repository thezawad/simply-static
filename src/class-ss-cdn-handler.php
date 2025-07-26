<?php

namespace Simply_Static;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static CDN Handler class
 * 
 * Handles CDN URL rewriting for media files and uploads
 */
class CDN_Handler {

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
	 * Initialize CDN handler
	 * @return void
	 */
	private function init() {
		// Hook into URL conversion process
		add_filter( 'simply_static_converted_url', array( $this, 'maybe_rewrite_to_cdn' ), 10, 3 );
		
		// Hook into page processing to exclude uploads if needed
		add_filter( 'simply_static_extracted_url', array( $this, 'maybe_exclude_uploads' ), 10, 3 );
	}

	/**
	 * Maybe rewrite URL to CDN
	 *
	 * @param string $url The converted URL
	 * @param Simply_Static\Page $static_page The static page object
	 * @param Simply_Static\Url_Extractor $url_extractor The URL extractor instance
	 * @return string
	 */
	public function maybe_rewrite_to_cdn( $url, $static_page, $url_extractor ) {
		$cdn_domain = $this->options->get( 'cdn_domain' );
		
		// If no CDN domain is set, return original URL
		if ( empty( $cdn_domain ) ) {
			return $url;
		}

		// Check if this is a media file that should be served from CDN
		if ( $this->is_media_file( $url ) ) {
			return $this->rewrite_to_cdn( $url, $cdn_domain );
		}

		return $url;
	}

	/**
	 * Maybe exclude uploads from extraction
	 *
	 * @param string $extracted_url The extracted URL
	 * @param string $original_url The original URL
	 * @param Simply_Static\Page $static_page The static page object
	 * @return string|null
	 */
	public function maybe_exclude_uploads( $extracted_url, $original_url, $static_page ) {
		$exclude_uploads = $this->options->get( 'exclude_uploads' );
		
		// If uploads exclusion is not enabled, return original URL
		if ( ! $exclude_uploads ) {
			return $extracted_url;
		}

		// Check if this is an uploads URL
		if ( $this->is_uploads_url( $extracted_url ) ) {
			// Return null to exclude from extraction
			return null;
		}

		return $extracted_url;
	}

	/**
	 * Check if URL is a media file
	 *
	 * @param string $url The URL to check
	 * @return boolean
	 */
	private function is_media_file( $url ) {
		// Media file extensions
		$media_extensions = array(
			'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
			'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'ogv',
			'mp3', 'wav', 'ogg', 'flac', 'aac',
			'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
			'zip', 'rar', '7z', 'tar', 'gz'
		);

		$extension = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		
		// Check if it's a media file extension
		if ( in_array( $extension, $media_extensions ) ) {
			return true;
		}

		// Check if it's in the uploads directory
		return $this->is_uploads_url( $url );
	}

	/**
	 * Check if URL is an uploads URL
	 *
	 * @param string $url The URL to check
	 * @return boolean
	 */
	private function is_uploads_url( $url ) {
		$uploads_dir = wp_upload_dir();
		$uploads_url = $uploads_dir['baseurl'];
		
		// Check if URL contains uploads path
		return strpos( $url, $uploads_url ) !== false;
	}

	/**
	 * Rewrite URL to CDN
	 *
	 * @param string $url The original URL
	 * @param string $cdn_domain The CDN domain
	 * @return string
	 */
	private function rewrite_to_cdn( $url, $cdn_domain ) {
		$parsed_url = parse_url( $url );
		
		// If URL is already absolute with a different domain, leave it as is
		if ( isset( $parsed_url['host'] ) && $parsed_url['host'] !== parse_url( site_url(), PHP_URL_HOST ) ) {
			return $url;
		}

		// Ensure CDN domain has proper protocol
		$cdn_domain = $this->normalize_cdn_domain( $cdn_domain );
		
		// If it's a relative URL, convert to CDN URL
		if ( ! isset( $parsed_url['host'] ) ) {
			return $cdn_domain . $url;
		}

		// Replace the host with CDN domain
		$parsed_url['scheme'] = parse_url( $cdn_domain, PHP_URL_SCHEME );
		$parsed_url['host'] = parse_url( $cdn_domain, PHP_URL_HOST );
		
		// Rebuild URL
		return $this->build_url( $parsed_url );
	}

	/**
	 * Normalize CDN domain to include protocol
	 *
	 * @param string $cdn_domain The CDN domain
	 * @return string
	 */
	private function normalize_cdn_domain( $cdn_domain ) {
		// Remove trailing slash
		$cdn_domain = rtrim( $cdn_domain, '/' );
		
		// Add protocol if missing
		if ( ! preg_match( '/^https?:\/\//', $cdn_domain ) ) {
			$cdn_domain = 'https://' . $cdn_domain;
		}
		
		return $cdn_domain;
	}

	/**
	 * Build URL from parsed components
	 *
	 * @param array $parsed_url Parsed URL components
	 * @return string
	 */
	private function build_url( $parsed_url ) {
		$url = '';
		
		if ( isset( $parsed_url['scheme'] ) ) {
			$url .= $parsed_url['scheme'] . '://';
		}
		
		if ( isset( $parsed_url['host'] ) ) {
			$url .= $parsed_url['host'];
		}
		
		if ( isset( $parsed_url['port'] ) ) {
			$url .= ':' . $parsed_url['port'];
		}
		
		if ( isset( $parsed_url['path'] ) ) {
			$url .= $parsed_url['path'];
		}
		
		if ( isset( $parsed_url['query'] ) ) {
			$url .= '?' . $parsed_url['query'];
		}
		
		if ( isset( $parsed_url['fragment'] ) ) {
			$url .= '#' . $parsed_url['fragment'];
		}
		
		return $url;
	}
}