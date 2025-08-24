<?php
/**
 * Simply Static Enhanced Configuration Example
 * 
 * This file shows how to configure the new Simply Static features
 * for a complete static site setup with CDN and auto-deployment.
 */

// Example: Complete WordPress configuration using the new features

// Configuration for a typical blog setup:
// - Main domain: example.com  
// - CDN domain: cdn.example.com
// - S3 bucket for hosting
// - Auto-deployment enabled

$simply_static_config = array(
    // Basic settings
    'destination_url_type' => 'absolute',
    'destination_scheme' => 'https://',
    'destination_host' => 'example.com',
    'delivery_method' => 's3',
    
    // S3 Configuration for hosting
    's3_access_key' => 'AKIAIOSFODNN7EXAMPLE',
    's3_secret_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    's3_bucket' => 'my-static-site-bucket',
    's3_region' => 'us-east-1',
    's3_endpoint' => '', // Empty for AWS S3
    
    // CDN Configuration for media files
    'cdn_domain' => 'https://cdn.example.com',
    'exclude_uploads' => true, // Don't include uploads in static files
    
    // Performance and automation
    'incremental_export' => true,
    'auto_deploy_enabled' => true,
    
    // Additional optimizations
    'generate_404' => true,
    'force_replace_url' => true,
);

// Example WordPress hooks for additional customization

// Hook to modify S3 upload behavior
add_filter('simply_static_s3_object_params', function($params, $file_path, $s3_key) {
    // Add custom headers for specific file types
    if (pathinfo($s3_key, PATHINFO_EXTENSION) === 'html') {
        $params['CacheControl'] = 'max-age=3600'; // 1 hour cache for HTML
    } else {
        $params['CacheControl'] = 'max-age=31536000'; // 1 year cache for assets
    }
    
    return $params;
}, 10, 3);

// Hook to customize CDN rewriting
add_filter('simply_static_cdn_rewrite_url', function($cdn_url, $original_url) {
    // Add version parameter to asset URLs
    if (strpos($original_url, '/wp-content/themes/') !== false) {
        $cdn_url .= '?v=' . get_option('theme_version', '1.0');
    }
    
    return $cdn_url;
}, 10, 2);

// Hook to exclude specific URLs from incremental processing
add_filter('simply_static_incremental_exclude_url', function($exclude, $url) {
    // Always regenerate admin and login pages
    if (strpos($url, '/wp-admin/') !== false || strpos($url, '/wp-login.php') !== false) {
        return true;
    }
    
    return $exclude;
}, 10, 2);

// Custom deployment notification
add_action('simply_static_deployment_complete', function($deployment_method, $stats) {
    // Send notification when deployment completes
    if ($deployment_method === 's3' && function_exists('wp_mail')) {
        $message = sprintf(
            "Static site deployment completed!\n\nDeployment method: %s\nFiles uploaded: %d\nTotal time: %s seconds",
            $deployment_method,
            $stats['files_processed'],
            $stats['total_time']
        );
        
        wp_mail(
            get_option('admin_email'),
            'Static Site Deployed',
            $message
        );
    }
});

// Example: DigitalOcean Spaces configuration
$digitalocean_config = array(
    'delivery_method' => 's3',
    's3_access_key' => 'DO_SPACES_KEY',
    's3_secret_key' => 'DO_SPACES_SECRET',
    's3_bucket' => 'my-space-name',
    's3_region' => 'nyc3',
    's3_endpoint' => 'https://nyc3.digitaloceanspaces.com',
    'destination_scheme' => 'https://',
    'destination_host' => 'my-space-name.nyc3.digitaloceanspaces.com',
    'cdn_domain' => 'https://my-space-name.nyc3.cdn.digitaloceanspaces.com',
);

// Example: MinIO self-hosted configuration
$minio_config = array(
    'delivery_method' => 's3',
    's3_access_key' => 'MINIO_ACCESS_KEY',
    's3_secret_key' => 'MINIO_SECRET_KEY',
    's3_bucket' => 'static-site',
    's3_region' => 'us-east-1',
    's3_endpoint' => 'https://minio.example.com',
    'destination_scheme' => 'https://',
    'destination_host' => 'static.example.com',
);

// Example: Local development with CDN for production assets
$local_dev_config = array(
    'delivery_method' => 'local',
    'local_dir' => '/var/www/static-site/',
    'destination_url_type' => 'relative',
    'cdn_domain' => 'https://cdn.example.com', // Still use CDN for media
    'exclude_uploads' => false, // Include uploads in local build
    'incremental_export' => true,
    'auto_deploy_enabled' => false, // Manual deploys in dev
);

// WordPress CLI command examples for the new features

/*
# Trigger incremental export via WP-CLI
wp eval "do_action('simply_static_site_export_cron', get_current_blog_id(), 'incremental');"

# Clear incremental change tracking
wp option delete simply_static_changed_urls
wp option delete simply_static_deleted_urls

# Test S3 configuration
wp eval "
\$options = get_option('simply-static');
\$task = new Simply_Static\Transfer_Files_S3_Task();
echo 'S3 configuration: ';
var_dump([
    'bucket' => \$options['s3_bucket'],
    'region' => \$options['s3_region'],
    'endpoint' => \$options['s3_endpoint']
]);
"

# Force full regeneration (clear incremental state)
wp option update simply-static incremental_full_regeneration_needed true

# Test CDN URL rewriting
wp eval "
\$handler = new Simply_Static\CDN_Handler();
\$test_url = 'https://example.com/wp-content/uploads/2023/image.jpg';
echo 'CDN rewrite test: ' . \$test_url;
"
*/

// Example nginx configuration for serving static files with CDN
/*
server {
    listen 443 ssl http2;
    server_name example.com;
    
    root /var/www/static-site;
    index index.html;
    
    # Cache static assets
    location ~* \.(css|js|png|jpg|jpeg|gif|webp|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        
        # Redirect media files to CDN
        if ($uri ~ "^/wp-content/uploads/") {
            return 301 https://cdn.example.com$uri;
        }
    }
    
    # Handle HTML files
    location / {
        try_files $uri $uri/ /index.html;
        expires 1h;
        add_header Cache-Control "public";
    }
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
*/

// Example CloudFlare Workers script for advanced CDN handling
/*
addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
    const url = new URL(request.url)
    
    // Handle media files
    if (url.pathname.startsWith('/wp-content/uploads/')) {
        // Serve from origin with optimization
        const response = await fetch(request)
        
        // Add cache headers
        const newResponse = new Response(response.body, response)
        newResponse.headers.set('Cache-Control', 'public, max-age=31536000')
        newResponse.headers.set('CDN-Cache-Control', 'max-age=31536000')
        
        return newResponse
    }
    
    // Handle other requests normally
    return fetch(request)
}
*/