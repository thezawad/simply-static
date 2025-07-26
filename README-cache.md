# Static Cache by Fionetix

A high-performance WordPress caching plugin that serves static content without database calls, providing lightning-fast page loading speeds.

## Features

- **Zero Database Calls**: Serves cached content directly from the filesystem
- **Automatic Cache Generation**: Creates static cache when URLs are requested
- **Auto-Cache New Posts**: Automatically caches new posts and pages when published
- **Smart Cache Invalidation**: Removes cache when content is updated
- **Simple Management**: Easy-to-use admin interface for cache control
- **Minimal Resource Usage**: Lightweight plugin with minimal overhead
- **Independent Operation**: Works standalone without external dependencies

## Requirements

- WordPress 6.2 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to `/wp-content/plugins/static-cache-fionetix/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure settings via Settings > Static Cache

## How It Works

1. **Request Interception**: The plugin intercepts incoming requests early in the WordPress loading process
2. **Cache Check**: Looks for a cached static version of the requested URL in `wp-content/static-cache/`
3. **Serve or Generate**: If cache exists, serves it directly. If not, generates cache in the background
4. **No Database**: Cached content is served without any database queries
5. **Auto-Invalidation**: Cache is automatically cleared when content is updated

## Configuration Options

- **Enable Static Cache**: Master switch for the caching functionality
- **Serve Cached Content**: Control whether to serve cached files to visitors
- **Auto-Cache New Posts**: Automatically create cache when posts are published
- **Auto-Generate Missing Cache**: Generate cache for URLs that don't have cached versions

## Cache Management

The admin interface provides tools to:
- Generate cache for specific URLs
- Clear cache for specific URLs  
- Clear all cached files
- View cache statistics (file count, total size)

## About the Developers

**Static Cache by Fionetix** is developed by:
- **Fionetix** - [www.fionetix.com](https://www.fionetix.com)
- **Zawad Bin Hafiz** - [www.zbh.one](https://www.zbh.one)

## File Structure

```
wp-content/static-cache/
├── index.html          # Homepage cache
├── about/
│   └── index.html      # About page cache
├── blog/
│   └── post-title/
│       └── index.html  # Individual post cache
└── .htaccess          # Security rules
```

## Performance Benefits

- **Faster Loading**: Static files load significantly faster than dynamic content
- **Reduced Server Load**: No PHP processing or database queries for cached content
- **Better Scalability**: Can handle high traffic without performance degradation
- **Lower Resource Usage**: Minimal CPU and memory usage for serving content

## Technical Details

- Cache files are stored as `.html` files in `wp-content/static-cache/`
- URLs are mapped to filesystem paths (e.g., `/about/` → `/static-cache/about.html`)
- Content is generated using HTTP requests to ensure accurate representation
- Cache headers are set for optimal browser caching
- Security `.htaccess` file prevents direct access to PHP files in cache directory

## Hooks and Filters

### Actions
- `ss_cache_generate_url` - Scheduled action for cache generation
- `publish_post` / `publish_page` - Triggers auto-caching of new content
- `post_updated` - Triggers cache invalidation for updated content

### Filters
- `ss_cache_max_age` - Modify cache headers max-age value (default: 3600 seconds)

## Compatibility

- Works alongside most WordPress themes and plugins
- Compatible with multisite installations
- Works with various permalink structures
- Integrates well with CDNs and other optimization plugins

## Troubleshooting

### Cache Not Working
1. Check if plugin is enabled in settings
2. Verify `wp-content/static-cache/` directory is writable
3. Check for conflicting plugins
4. Review error logs for issues

### Content Not Updating
1. Clear cache for specific URLs after content changes
2. Ensure cache invalidation is working
3. Check if logged-in users are being served cached content

## Development

The plugin follows WordPress coding standards and includes:
- Proper sanitization and validation
- Nonce verification for security
- Error handling and logging
- Internationalization support

## Changelog

### 1.0.0
- Initial release
- Core caching functionality
- Admin interface
- Auto-cache for new posts
- Cache management tools

## Support

For issues and feature requests, please check the WordPress support forums or plugin documentation.

## License

GPL-2.0+ - Same as WordPress core licensing.