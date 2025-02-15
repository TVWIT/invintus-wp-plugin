# Invintus WordPress Plugin

A WordPress plugin for integrating Invintus video content into your WordPress site. This plugin provides a custom post type for managing Invintus videos and a settings page for configuring your Invintus API credentials.

## Features

- Custom post type for Invintus videos
- Settings page for API configuration
- Player preference selection
- Modern block editor support
- Automatic video embedding

## Installation

### Automatic Installation (Recommended)

1. Log in to your WordPress dashboard
2. Go to Plugins > Add New
3. Click the "Upload Plugin" button at the top of the page
4. Click "Choose File" and select the invintus.zip file
5. Click "Install Now"
6. After installation completes, click "Activate Plugin"

### Manual Installation

1. Download the latest release zip file
2. Unzip the file
3. Upload the `invintus` folder to your `/wp-content/plugins/` directory via FTP or file manager
4. Log in to your WordPress dashboard
5. Go to Plugins > Installed Plugins
6. Find "Invintus" in the list and click "Activate"

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Node.js 16+ (for development)
- Composer (for PHP dependencies)

## Development Setup

1. Clone this repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins
   git clone [repository-url] invintus
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install Node.js dependencies:
   ```bash
   npm install
   ```

4. Build the assets:
   ```bash
   # For development (with watch mode)
   npm run dev

   # For production
   npm run build
   ```

## Configuration

1. Activate the plugin in WordPress admin
2. Go to Invintus Videos > Settings
3. Enter your API credentials:
   - API Key
   - Client ID

Alternatively, you can define these in your `wp-config.php`:
```php
define( 'INVINTUS_API_KEY', 'your-api-key' );
define( 'INVINTUS_CLIENT_ID', 'your-client-id' );
```

## Creating a Release

This plugin includes a release script that creates a distribution-ready ZIP file. The script:
1. Runs `composer install` with production optimizations
2. Excludes development files
3. Creates a ZIP file with only the necessary files

To create a release:

```bash
# Basic usage (outputs to current directory)
node create-release.js

# Specify output directory
node create-release.js outdir=/path/to/output
```

The script will:
1. Run `composer install --no-dev --optimize-autoloader`
2. Create a ZIP file containing only production files
3. Exclude development files like:
   - `.git`
   - `node_modules`
   - Development configuration files
   - Build files
   - Test files

The resulting ZIP file can be used to install the plugin on any WordPress site.
