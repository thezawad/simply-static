<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current options
$options = get_option( 'ss_cache_options', array() );

// Handle form submission
if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'ss_cache_options-options' ) ) {
	$new_options = array(
		'enabled' => isset( $_POST['ss_cache_options']['enabled'] ),
		'serve_cached_content' => isset( $_POST['ss_cache_options']['serve_cached_content'] ),
		'cache_posts' => isset( $_POST['ss_cache_options']['cache_posts'] ),
		'cache_pages' => isset( $_POST['ss_cache_options']['cache_pages'] ),
		'auto_cache_new_posts' => isset( $_POST['ss_cache_options']['auto_cache_new_posts'] ),
		'auto_generate_cache' => isset( $_POST['ss_cache_options']['auto_generate_cache'] ),
	);
	
	update_option( 'ss_cache_options', $new_options );
	$options = $new_options;
	
	echo '<div class="notice notice-success"><p>' . __( 'Settings saved successfully!', 'simply-static-cache' ) . '</p></div>';
}

// Get cache statistics
$cache_dir = WP_CONTENT_DIR . '/static-cache';
$cache_files = 0;
$cache_size = 0;

if ( is_dir( $cache_dir ) ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cache_dir ) );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && $file->getExtension() === 'html' ) {
			$cache_files++;
			$cache_size += $file->getSize();
		}
	}
}

?>
<div class="wrap">
	<h1><?php _e( 'Simply Static Cache Settings', 'simply-static-cache' ); ?></h1>
	
	<div id="ss-cache-admin">
		<!-- Cache Statistics -->
		<div class="card">
			<h2><?php _e( 'Cache Statistics', 'simply-static-cache' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php _e( 'Cached Files', 'simply-static-cache' ); ?></th>
					<td><?php echo number_format( $cache_files ); ?></td>
				</tr>
				<tr>
					<th><?php _e( 'Cache Size', 'simply-static-cache' ); ?></th>
					<td><?php echo size_format( $cache_size ); ?></td>
				</tr>
				<tr>
					<th><?php _e( 'Cache Directory', 'simply-static-cache' ); ?></th>
					<td><code><?php echo esc_html( $cache_dir ); ?></code></td>
				</tr>
			</table>
		</div>

		<!-- Settings Form -->
		<form method="post" action="">
			<?php wp_nonce_field( 'ss_cache_options-options' ); ?>
			
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php _e( 'Enable Static Cache', 'simply-static-cache' ); ?></th>
						<td>
							<label for="enabled">
								<input type="checkbox" id="enabled" name="ss_cache_options[enabled]" value="1" <?php checked( isset( $options['enabled'] ) ? $options['enabled'] : false ); ?> />
								<?php _e( 'Enable static caching functionality', 'simply-static-cache' ); ?>
							</label>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><?php _e( 'Serve Cached Content', 'simply-static-cache' ); ?></th>
						<td>
							<label for="serve_cached_content">
								<input type="checkbox" id="serve_cached_content" name="ss_cache_options[serve_cached_content]" value="1" <?php checked( isset( $options['serve_cached_content'] ) ? $options['serve_cached_content'] : false ); ?> />
								<?php _e( 'Serve cached static content to visitors', 'simply-static-cache' ); ?>
							</label>
							<p class="description"><?php _e( 'When enabled, cached static files will be served instead of dynamic content.', 'simply-static-cache' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><?php _e( 'Auto-Cache New Posts', 'simply-static-cache' ); ?></th>
						<td>
							<label for="auto_cache_new_posts">
								<input type="checkbox" id="auto_cache_new_posts" name="ss_cache_options[auto_cache_new_posts]" value="1" <?php checked( isset( $options['auto_cache_new_posts'] ) ? $options['auto_cache_new_posts'] : false ); ?> />
								<?php _e( 'Automatically cache new posts and pages when published', 'simply-static-cache' ); ?>
							</label>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><?php _e( 'Auto-Generate Missing Cache', 'simply-static-cache' ); ?></th>
						<td>
							<label for="auto_generate_cache">
								<input type="checkbox" id="auto_generate_cache" name="ss_cache_options[auto_generate_cache]" value="1" <?php checked( isset( $options['auto_generate_cache'] ) ? $options['auto_generate_cache'] : false ); ?> />
								<?php _e( 'Automatically generate cache for URLs that don\'t have cached versions', 'simply-static-cache' ); ?>
							</label>
							<p class="description"><?php _e( 'When a visitor requests a URL that isn\'t cached, generate the cache in the background.', 'simply-static-cache' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>

		<!-- Cache Management -->
		<div class="card">
			<h2><?php _e( 'Cache Management', 'simply-static-cache' ); ?></h2>
			
			<div class="ss-cache-actions">
				<div class="ss-cache-action">
					<h3><?php _e( 'Generate Cache for URL', 'simply-static-cache' ); ?></h3>
					<p><?php _e( 'Generate static cache for a specific URL:', 'simply-static-cache' ); ?></p>
					<input type="url" id="cache-url" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" style="width: 300px;" />
					<button type="button" id="generate-cache" class="button button-secondary"><?php _e( 'Generate Cache', 'simply-static-cache' ); ?></button>
				</div>

				<div class="ss-cache-action" style="margin-top: 20px;">
					<h3><?php _e( 'Clear Cache for URL', 'simply-static-cache' ); ?></h3>
					<p><?php _e( 'Clear static cache for a specific URL:', 'simply-static-cache' ); ?></p>
					<input type="url" id="clear-url" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" style="width: 300px;" />
					<button type="button" id="clear-cache" class="button button-secondary"><?php _e( 'Clear Cache', 'simply-static-cache' ); ?></button>
				</div>

				<div class="ss-cache-action" style="margin-top: 20px;">
					<h3><?php _e( 'Clear All Cache', 'simply-static-cache' ); ?></h3>
					<p><?php _e( 'Clear all cached static files:', 'simply-static-cache' ); ?></p>
					<button type="button" id="clear-all-cache" class="button button-secondary" style="color: #d63638;"><?php _e( 'Clear All Cache', 'simply-static-cache' ); ?></button>
				</div>
			</div>

			<div id="ss-cache-message" style="margin-top: 20px;"></div>
		</div>
	</div>
</div>

<style>
.ss-cache-action {
	background: #f9f9f9;
	padding: 15px;
	border: 1px solid #ddd;
	border-radius: 4px;
	margin-bottom: 10px;
}

.ss-cache-action h3 {
	margin-top: 0;
}

#ss-cache-message {
	padding: 10px;
	border-radius: 4px;
	display: none;
}

#ss-cache-message.success {
	background: #d4edda;
	border: 1px solid #c3e6cb;
	color: #155724;
}

#ss-cache-message.error {
	background: #f8d7da;
	border: 1px solid #f5c6cb;
	color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
	function showMessage(message, type) {
		var $msg = $('#ss-cache-message');
		$msg.removeClass('success error').addClass(type).text(message).show();
		setTimeout(function() {
			$msg.fadeOut();
		}, 5000);
	}

	$('#generate-cache').click(function() {
		var url = $('#cache-url').val();
		if (!url) {
			showMessage('<?php _e( 'Please enter a URL', 'simply-static-cache' ); ?>', 'error');
			return;
		}

		$(this).prop('disabled', true).text('<?php _e( 'Generating...', 'simply-static-cache' ); ?>');

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'ss_cache_generate',
				url: url,
				nonce: '<?php echo wp_create_nonce( 'ss_cache_nonce' ); ?>'
			},
			success: function(response) {
				showMessage(response.message, response.success ? 'success' : 'error');
			},
			error: function() {
				showMessage('<?php _e( 'An error occurred', 'simply-static-cache' ); ?>', 'error');
			},
			complete: function() {
				$('#generate-cache').prop('disabled', false).text('<?php _e( 'Generate Cache', 'simply-static-cache' ); ?>');
			}
		});
	});

	$('#clear-cache').click(function() {
		var url = $('#clear-url').val();
		if (!url) {
			showMessage('<?php _e( 'Please enter a URL', 'simply-static-cache' ); ?>', 'error');
			return;
		}

		$(this).prop('disabled', true).text('<?php _e( 'Clearing...', 'simply-static-cache' ); ?>');

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'ss_cache_clear',
				url: url,
				nonce: '<?php echo wp_create_nonce( 'ss_cache_nonce' ); ?>'
			},
			success: function(response) {
				showMessage(response.message, response.success ? 'success' : 'error');
			},
			error: function() {
				showMessage('<?php _e( 'An error occurred', 'simply-static-cache' ); ?>', 'error');
			},
			complete: function() {
				$('#clear-cache').prop('disabled', false).text('<?php _e( 'Clear Cache', 'simply-static-cache' ); ?>');
			}
		});
	});

	$('#clear-all-cache').click(function() {
		if (!confirm('<?php _e( 'Are you sure you want to clear all cached files?', 'simply-static-cache' ); ?>')) {
			return;
		}

		$(this).prop('disabled', true).text('<?php _e( 'Clearing...', 'simply-static-cache' ); ?>');

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'ss_cache_clear_all',
				nonce: '<?php echo wp_create_nonce( 'ss_cache_nonce' ); ?>'
			},
			success: function(response) {
				showMessage(response.message, response.success ? 'success' : 'error');
				if (response.success) {
					// Reload the page to update cache statistics
					setTimeout(function() {
						location.reload();
					}, 2000);
				}
			},
			error: function() {
				showMessage('<?php _e( 'An error occurred', 'simply-static-cache' ); ?>', 'error');
			},
			complete: function() {
				$('#clear-all-cache').prop('disabled', false).text('<?php _e( 'Clear All Cache', 'simply-static-cache' ); ?>');
			}
		});
	});
});
</script>