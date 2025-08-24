# Simply Static Enhancements

This document describes the new features added to Simply Static to enhance its functionality with S3 deployment, CDN support, incremental builds, and auto-deployment.

## New Features

### 1. S3 Compatible Storage Deployment

Deploy your static site directly to any S3-compatible storage service including AWS S3, DigitalOcean Spaces, Linode Object Storage, and others.

#### Configuration

In the WordPress admin, go to **Simply Static > Settings > Deployment** and select **S3 Compatible Storage** as your delivery method.

Configure the following settings:

- **S3 Access Key**: Your S3 access key ID
- **S3 Secret Key**: Your S3 secret access key  
- **S3 Bucket Name**: The name of your S3 bucket
- **S3 Region**: The AWS region (e.g., us-east-1, eu-west-1)
- **S3 Endpoint**: Optional custom endpoint for S3-compatible services (leave empty for AWS S3)

#### Supported Services

- **AWS S3**: Leave endpoint empty, use standard AWS regions
- **DigitalOcean Spaces**: Set endpoint to `https://nyc3.digitaloceanspaces.com`
- **Linode Object Storage**: Set endpoint to `https://us-east-1.linodeobjects.com`
- **MinIO**: Set endpoint to your MinIO server URL
- **Any S3-compatible service**: Configure the appropriate endpoint

### 2. CDN URL Rewriting

Automatically rewrite media file URLs to use a CDN domain for improved performance.

#### Configuration

In **Simply Static > Settings > General**, configure:

- **CDN Domain**: Your CDN domain (e.g., `https://cdn.example.com`)
- **Exclude Uploads Folder**: Toggle to exclude uploads from static generation

#### How It Works

- Media files (images, videos, documents) are automatically rewritten to use your CDN domain
- Supported file types: jpg, png, gif, mp4, pdf, and many others
- Files in the wp-content/uploads directory are detected and rewritten
- Option to exclude uploads entirely from static generation when using external CDN

### 3. Incremental Export

Only regenerate content that has changed since the last export, significantly reducing export time.

#### Configuration

In **Simply Static > Settings > General**:

- **Enable Incremental Export**: Toggle to enable incremental builds

#### What Triggers Regeneration

- Post/page updates
- Menu changes
- Theme modifications
- Customizer changes
- Taxonomy updates

#### How It Works

- Tracks changes to content using WordPress hooks
- Stores changed URLs and timestamps
- Only processes URLs that have changed since last export
- Automatically handles related pages (archives, taxonomy pages)

### 4. Auto-Deploy

Automatically trigger static site generation and deployment when content changes.

#### Configuration

In **Simply Static > Settings > General**:

- **Enable Auto-Deploy**: Toggle to enable automatic deployment

#### Behavior

- Monitors content changes (posts, pages, menus, theme)
- Schedules deployment with a 5-minute delay to batch multiple changes
- Uses incremental export if enabled
- Works with any configured deployment method (Local, S3, ZIP, etc.)

## Technical Implementation

### New Classes Added

1. **Transfer_Files_S3_Task**: Handles S3 deployment with AWS Signature V4 authentication
2. **CDN_Handler**: Manages CDN URL rewriting and upload exclusion
3. **Incremental_Handler**: Tracks content changes and filters URLs for incremental builds

### WordPress Hooks Used

- `save_post`: Track post/page changes
- `delete_post`: Track post deletions
- `wp_update_nav_menu`: Track menu changes
- `customize_save_after`: Track customizer changes
- `switch_theme`: Track theme changes
- `simply_static_converted_url`: Filter for CDN rewriting
- `simply_static_extracted_url`: Filter for upload exclusion

### Settings Added

New options stored in the `simply-static` option:

- `s3_access_key`: S3 access key
- `s3_secret_key`: S3 secret key
- `s3_bucket`: S3 bucket name
- `s3_region`: S3 region
- `s3_endpoint`: S3 endpoint URL
- `cdn_domain`: CDN domain URL
- `exclude_uploads`: Upload exclusion toggle
- `incremental_export`: Incremental export toggle  
- `auto_deploy_enabled`: Auto-deploy toggle

## Usage Examples

### Example 1: Basic S3 Deployment

1. Set delivery method to "S3 Compatible Storage"
2. Configure AWS credentials and bucket
3. Run export - files will be uploaded to S3

### Example 2: CDN with Upload Exclusion

1. Set CDN domain to `https://cdn.example.com`
2. Enable "Exclude Uploads Folder"
3. Media URLs will point to CDN, uploads won't be included in static files

### Example 3: Auto-Deploying Blog

1. Enable "Incremental Export"
2. Enable "Auto-Deploy"
3. Set delivery method to S3 or Local
4. When you publish a new post, the site auto-deploys within 5 minutes

### Example 4: DigitalOcean Spaces Deployment

1. Set delivery method to "S3 Compatible Storage"
2. Configure:
   - Access Key: Your DO Spaces access key
   - Secret Key: Your DO Spaces secret key
   - Bucket: Your space name
   - Region: nyc3 (or your region)
   - Endpoint: `https://nyc3.digitaloceanspaces.com`

## Migration Notes

- Existing settings are preserved
- New settings have sensible defaults (all features disabled by default)
- No changes to existing export behavior unless new features are enabled
- Fully backward compatible with existing Simply Static installations

## Troubleshooting

### S3 Upload Issues

- Verify your S3 credentials are correct
- Check bucket permissions allow uploads
- Ensure region is set correctly
- For S3-compatible services, verify the endpoint URL

### CDN Issues

- Ensure CDN domain includes protocol (https://)
- Check that media files are properly configured on your CDN
- Test CDN URLs manually to ensure they resolve

### Incremental Export Issues

- If pages aren't updating, try a full export (disable incremental temporarily)
- Check the activity log for any error messages
- Large sites may need adjustment of batch sizes

### Auto-Deploy Issues

- Check WordPress cron is working (`wp cron test` if using WP-CLI)
- Verify export settings are correct
- Check system logs for any errors during auto-deploy