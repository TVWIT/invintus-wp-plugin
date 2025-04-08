<?php

/**
 * Plugin Name:       Invintus Media
 * Plugin URI:        https://taproot.agency
 * Description:       Integrates Invintus Media with WordPress
 * Version: 2.0.10
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            The Taproot Agency
 * Author URI:        https://taproot.agency
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       invintus-plugin
 * Domain Path:       /languages
 *
 * @package           InvintusPlugin
 */
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

// Useful global constants.
define( 'INVINTUS_PLUGIN_VERSION', '2.0.10' );
define( 'INVINTUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INVINTUS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'INVINTUS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'INVINTUS_PLUGIN_FILE', __FILE__ );

require_once INVINTUS_PLUGIN_PATH . 'vendor/autoload.php';

Taproot\Invintus\Invintus::init();

register_activation_hook( __FILE__, ['Taproot\Invintus\Invintus', 'activate'] );
register_deactivation_hook( __FILE__, ['Taproot\Invintus\Invintus', 'deactivate'] );
