# Invintus WordPress Plugin User Guide

## Overview

The Invintus WordPress Plugin allows you to easily integrate Invintus video content into your WordPress site. It provides a custom post type for managing Invintus videos, settings for configuring your Invintus API credentials, and webhooks to automatically create and update video pages based on Invintus actions.

## Disclaimer

You need an account at [Invintus](https://invintus.com) to use this plugin. Please ensure you have your API credentials ready.


## Features

- Custom post type for Invintus videos
- Settings page for API configuration
- Player preference selection
- Modern block editor support
- Automatic video embedding
- Webhooks to update video status and metadata

## Installation

### Automatic Installation (Recommended)

1. Log in to your WordPress dashboard.
2. Go to Plugins > Add New.
3. Click the "Upload Plugin" button at the top of the page.
4. Click "Choose File" and select the `invintus.zip` file.
5. Click "Install Now".
6. After installation completes, click "Activate Plugin".

### Manual Installation

1. Download the latest release zip file.
2. Unzip the file.
3. Upload the `invintus` folder to your `/wp-content/plugins/` directory via FTP or file manager.
4. Log in to your WordPress dashboard.
5. Go to Plugins > Installed Plugins.
6. Find "Invintus" in the list and click "Activate".

## Configuration

1. Activate the plugin in WordPress admin.
2. Go to Invintus Videos > Settings.
3. Enter your API credentials:
   - API Key
   - Client ID

Alternatively, you can define these in your `wp-config.php`:
```php
define( 'INVINTUS_API_KEY', 'your-api-key' );
define( 'INVINTUS_CLIENT_ID', 'your-client-id' );
```

## Using the Plugin

### Adding a New Video

1. Go to Invintus Videos > Add New.
2. Enter the video details, including the title, description, and any other relevant information.
3. Publish the video.

### Managing Video Metadata

The plugin provides a sidebar in the block editor to manage video metadata. To access it:

1. Edit a video post.
2. Click on the "Invintus Metadata" option in the sidebar.
3. Update the metadata fields as needed.

### Webhooks

The plugin is designed to receive webhooks from Invintus to automatically create and update video pages. When an Invintus action occurs, the plugin will:

1. Create a new video page if it doesn't exist.
2. Update the status and metadata of the video page.

To set up webhooks in WordPress:

1. Ensure you have a user called "invintusHooks" in your WordPress site. If not, create one:
   - Go to Users > Add New.
   - Fill in the required details and set the username as "invintusHooks".
   - Assign an appropriate role of Administrator.
   - Click "Add New User".

2. Add an application password for the "invintus-hooks" user:
   - Go to Users > All Users.
   - Click on the "invintusHooks" user to edit the user profile.
   - Scroll down to the "Application Passwords" section.
   - Enter a name for the application password (e.g., "Invintus Webhook") and click "Add New Application Password".
   - Copy the generated application password and save it securely. You will need this password to authenticate the webhook requests.

3. Contact Invintus support through the support portal in the control center. Provide them with the following information:
   - The username ("invintusHooks") and the application password you generated.
   - The URL to your WordPress site where the webhook should be sent:
     ```
     https://your-wordpress-site.com/wp-json/invintus/v2
      for adding/updating
      https://your-wordpress-site.com/wp-json/invintus/v2/events/crud
     ```

4. Invintus support will set up the webhook for you based on the provided information.

### Settings

You can configure various settings for the plugin:

1. Go to Invintus Videos > Settings.
2. Update the settings as needed, including API credentials and player preferences.

## Advanced: overriding the player URL

By default the plugin loads the Invintus player from `https://player.invintus.com/app.js`. Sites that need to point at a different build (for example, partner-supplied or self-hosted) can override the URL via a WordPress filter without modifying the plugin.

The recommended pattern is a [must-use plugin](https://wordpress.org/documentation/article/must-use-plugins/) at `wp-content/mu-plugins/invintus-player.php`:

```php
<?php
add_filter( 'invintus/player/script/url', function( $url ) {
    return 'https://example.com/path/to/your/app.js';
} );
```

Why `mu-plugins`:

- Loads before regular plugins, so the filter is in place when the plugin asks for the URL.
- Cannot be deactivated from the wp-admin Plugins screen.
- Survives plugin updates -- updating the Invintus plugin will not touch your override.

To remove the override, delete the file. No restart or plugin reactivation required.

The override can be scoped to specific routes by checking the current request inside the filter callback (e.g. `is_page()`, `is_singular()`, or `$_SERVER['REQUEST_URI']`). Always accept the default URL as the first argument and return it as the fallback so other filters and the default remain in effect.

The player script is only enqueued where it is actually needed: the Invintus Settings page, the block editor, and front-end posts that contain the Invintus block. The filter does not run on screens that do not load the player.

### Cross-origin player builds

Some player builds split their runtime into separate chunks loaded via dynamic `import()`. When the player is served from a different origin than the site, the browser will refuse to resolve the relative chunk URLs unless the `<script>` tag is marked as a CORS-enabled request. Symptom: console errors like `Failed to resolve module specifier './chunks/...'`.

If the override URL points to such a build, add a companion filter alongside the URL override:

```php
add_filter( 'invintus/player/script/url',         function() {
    return 'https://example.com/path/to/your/app.js';
} );

add_filter( 'invintus/player/script/crossorigin', function() {
    return 'anonymous';
} );
```

The `invintus/player/script/crossorigin` filter sets the `crossorigin` attribute on the player `<script>` tag. The target URL must respond with `Access-Control-Allow-Origin: *` (or a matching origin) -- if it does not, the browser will reject the script load. The default Invintus player URL does not require this, so the filter should only be added when overriding the URL.

## Support

For support, please contact support@invintus.com.
