<?php

namespace Simply_Static;

/**
 * Class which handles S3 transfer files task.
 */
class Transfer_Files_S3_Task extends Task {

	use canProcessPages;

	use canTransfer;

	/**
	 * Task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'transfer_files_s3';

	/**
	 * S3 bucket name.
	 *
	 * @var string
	 */
	protected $bucket = '';

	/**
	 * S3 region.
	 *
	 * @var string
	 */
	protected $region = '';

	/**
	 * S3 access key.
	 *
	 * @var string
	 */
	protected $access_key = '';

	/**
	 * S3 secret key.
	 *
	 * @var string
	 */
	protected $secret_key = '';

	/**
	 * S3 endpoint URL (for S3-compatible services).
	 *
	 * @var string
	 */
	protected $endpoint = '';

	/**
	 * Archive directory.
	 *
	 * @var string
	 */
	protected $archive_dir = '';

	/**
	 * Upload a batch of files to S3
	 *
	 * @return boolean true if done, false if not done.
	 */
	public function perform() {
		$this->bucket      = $this->options->get( 's3_bucket' );
		$this->region      = $this->options->get( 's3_region' ) ?: 'us-east-1';
		$this->access_key  = $this->options->get( 's3_access_key' );
		$this->secret_key  = $this->options->get( 's3_secret_key' );
		$this->endpoint    = $this->options->get( 's3_endpoint' );
		$this->archive_dir = $this->options->get_archive_dir();

		// Validate S3 configuration
		if ( empty( $this->bucket ) || empty( $this->access_key ) || empty( $this->secret_key ) ) {
			$this->save_status_message( __( 'S3 configuration is incomplete. Please check your S3 settings.', 'simply-static' ), 'error' );
			return true;
		}

		$done = $this->process_pages();

		if ( $done ) {
			$this->save_status_message( __( 'Files uploaded to S3.', 'simply-static' ) );
		}

		return $done;
	}

	/**
	 * Process a single page for S3 upload
	 *
	 * @param Page $static_page The page to process.
	 * @return void
	 */
	public function process_page( $static_page ) {
		$relative_file_path = $this->get_relative_file_path( $static_page );
		$file_path = $this->archive_dir . $relative_file_path;

		if ( ! file_exists( $file_path ) ) {
			return;
		}

		// Upload file to S3
		$uploaded = $this->upload_file_to_s3( $file_path, $relative_file_path );

		if ( $uploaded ) {
			$static_page->set_status_message( __( 'Uploaded to S3', 'simply-static' ) );
		} else {
			$static_page->set_status_message( __( 'Failed to upload to S3', 'simply-static' ) );
		}

		$static_page->save();
	}

	/**
	 * Upload a file to S3
	 *
	 * @param string $file_path Local file path.
	 * @param string $s3_key S3 key (relative path).
	 * @return boolean
	 */
	private function upload_file_to_s3( $file_path, $s3_key ) {
		// Remove leading slash from S3 key
		$s3_key = ltrim( $s3_key, '/' );

		try {
			// Use WordPress HTTP API for S3 upload
			$file_content = file_get_contents( $file_path );
			if ( false === $file_content ) {
				return false;
			}

			// Generate authorization headers for S3
			$headers = $this->generate_s3_headers( $s3_key, $file_content );

			// Determine S3 URL
			$s3_url = $this->get_s3_url( $s3_key );

			// Upload using WordPress HTTP API
			$response = wp_remote_request( $s3_url, array(
				'method'  => 'PUT',
				'headers' => $headers,
				'body'    => $file_content,
				'timeout' => 60,
			) );

			if ( is_wp_error( $response ) ) {
				$this->save_status_message( sprintf( __( 'S3 upload error for %s: %s', 'simply-static' ), $s3_key, $response->get_error_message() ), 'error' );
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code === 200 || $response_code === 201 ) {
				return true;
			} else {
				$response_body = wp_remote_retrieve_body( $response );
				$this->save_status_message( sprintf( __( 'S3 upload failed for %s: HTTP %d - %s', 'simply-static' ), $s3_key, $response_code, $response_body ), 'error' );
				return false;
			}

		} catch ( Exception $e ) {
			$this->save_status_message( sprintf( __( 'S3 upload exception for %s: %s', 'simply-static' ), $s3_key, $e->getMessage() ), 'error' );
			return false;
		}
	}

	/**
	 * Generate S3 authorization headers
	 *
	 * @param string $s3_key S3 key.
	 * @param string $content File content.
	 * @return array
	 */
	private function generate_s3_headers( $s3_key, $content ) {
		$timestamp = gmdate( 'Ymd\THis\Z' );
		$date = gmdate( 'Ymd' );
		$content_type = $this->get_content_type( $s3_key );
		$content_hash = hash( 'sha256', $content );

		$headers = array(
			'Content-Type'                => $content_type,
			'Content-Length'              => strlen( $content ),
			'x-amz-content-sha256'        => $content_hash,
			'x-amz-date'                  => $timestamp,
		);

		// Generate AWS Signature Version 4
		$canonical_request = $this->create_canonical_request( 'PUT', $s3_key, $headers, $content_hash );
		$string_to_sign = $this->create_string_to_sign( $timestamp, $date, $canonical_request );
		$signature = $this->calculate_signature( $string_to_sign, $date );

		// Create authorization header
		$credential = $this->access_key . '/' . $date . '/' . $this->region . '/s3/aws4_request';
		$authorization = 'AWS4-HMAC-SHA256 Credential=' . $credential . ',SignedHeaders=' . $this->get_signed_headers( $headers ) . ',Signature=' . $signature;

		$headers['Authorization'] = $authorization;

		return $headers;
	}

	/**
	 * Create canonical request for AWS Signature V4
	 *
	 * @param string $method HTTP method.
	 * @param string $s3_key S3 key.
	 * @param array $headers Headers.
	 * @param string $content_hash Content hash.
	 * @return string
	 */
	private function create_canonical_request( $method, $s3_key, $headers, $content_hash ) {
		$canonical_uri = '/' . $s3_key;
		$canonical_query_string = '';

		// Create canonical headers
		$canonical_headers = '';
		$signed_headers = array();
		foreach ( $headers as $name => $value ) {
			$name = strtolower( $name );
			$signed_headers[] = $name;
			$canonical_headers .= $name . ':' . trim( $value ) . "\n";
		}
		sort( $signed_headers );

		return implode( "\n", array(
			$method,
			$canonical_uri,
			$canonical_query_string,
			$canonical_headers,
			implode( ';', $signed_headers ),
			$content_hash
		) );
	}

	/**
	 * Create string to sign for AWS Signature V4
	 *
	 * @param string $timestamp Timestamp.
	 * @param string $date Date.
	 * @param string $canonical_request Canonical request.
	 * @return string
	 */
	private function create_string_to_sign( $timestamp, $date, $canonical_request ) {
		$scope = $date . '/' . $this->region . '/s3/aws4_request';
		return implode( "\n", array(
			'AWS4-HMAC-SHA256',
			$timestamp,
			$scope,
			hash( 'sha256', $canonical_request )
		) );
	}

	/**
	 * Calculate AWS Signature V4
	 *
	 * @param string $string_to_sign String to sign.
	 * @param string $date Date.
	 * @return string
	 */
	private function calculate_signature( $string_to_sign, $date ) {
		$date_key = hash_hmac( 'sha256', $date, 'AWS4' . $this->secret_key, true );
		$date_region_key = hash_hmac( 'sha256', $this->region, $date_key, true );
		$date_region_service_key = hash_hmac( 'sha256', 's3', $date_region_key, true );
		$signing_key = hash_hmac( 'sha256', 'aws4_request', $date_region_service_key, true );

		return hash_hmac( 'sha256', $string_to_sign, $signing_key );
	}

	/**
	 * Get signed headers string
	 *
	 * @param array $headers Headers.
	 * @return string
	 */
	private function get_signed_headers( $headers ) {
		$signed_headers = array();
		foreach ( array_keys( $headers ) as $name ) {
			$signed_headers[] = strtolower( $name );
		}
		sort( $signed_headers );
		return implode( ';', $signed_headers );
	}

	/**
	 * Get S3 URL for upload
	 *
	 * @param string $s3_key S3 key.
	 * @return string
	 */
	private function get_s3_url( $s3_key ) {
		if ( ! empty( $this->endpoint ) ) {
			// Custom S3-compatible endpoint
			return rtrim( $this->endpoint, '/' ) . '/' . $this->bucket . '/' . $s3_key;
		} else {
			// Standard AWS S3
			return 'https://' . $this->bucket . '.s3.' . $this->region . '.amazonaws.com/' . $s3_key;
		}
	}

	/**
	 * Get content type for file
	 *
	 * @param string $file_path File path.
	 * @return string
	 */
	private function get_content_type( $file_path ) {
		$mime_types = array(
			'html' => 'text/html',
			'htm'  => 'text/html',
			'css'  => 'text/css',
			'js'   => 'application/javascript',
			'json' => 'application/json',
			'xml'  => 'application/xml',
			'txt'  => 'text/plain',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'svg'  => 'image/svg+xml',
			'pdf'  => 'application/pdf',
			'zip'  => 'application/zip',
		);

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		return isset( $mime_types[ $extension ] ) ? $mime_types[ $extension ] : 'application/octet-stream';
	}

	/**
	 * Get relative file path for a static page
	 *
	 * @param Page $static_page The static page.
	 * @return string
	 */
	private function get_relative_file_path( $static_page ) {
		$url = $static_page->url;
		$origin_url = Util::origin_url();

		// Remove origin URL to get relative path
		$relative_path = str_replace( $origin_url, '', $url );
		$relative_path = ltrim( $relative_path, '/' );

		// Handle index files
		if ( empty( $relative_path ) || substr( $relative_path, -1 ) === '/' ) {
			$relative_path .= 'index.html';
		}

		return $relative_path;
	}
}