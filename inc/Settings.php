<?php

/**
 * Create the settings page for the custom post type.
 *
 * @package InvintusPlugin
 */

namespace Taproot\Invintus;

class Settings
{
  /**
   * The assets.
   *
   * This property is used to store the options assets for the plugin.
   *
   * @var mixed
   */
  private $assets;

  /**
   * The assets data.
   *
   * This property is used to store the data for the options assets, such as dependencies and version.
   *
   * @var array
   */
  private $assets_data;
  /**
   * The suffix of the hook.
   *
   * @var string
   */
  private $hook_suffix;

  /**
   * The URL of the Invintus player script.
   *
   * @var string
   */
  private $invintus_player_script_url;

  /**
   * Sets up the class.
   *
   * This method is called after the class is instantiated.
   * It calls the 'actions' and 'filters' methods to add the action and filter hooks.
   */
  public function setup()
  {
    $this->actions();
    $this->filters();

    $this->assets = sprintf( '%sbuild/settings.asset.php', INVINTUS_PLUGIN_PATH );

    if ( file_exists( $this->assets ) ):
      $this->assets_data = require_once $this->assets;
    else:
      $this->assets_data = [
        'dependencies' => [],
        'version'      => filemtime( sprintf( '%sbuild/settings.js', INVINTUS_PLUGIN_PATH ) )
      ];
    endif;
  }

  /**
   * Adds the options page to the admin menu.
   *
   * This method is hooked into the 'admin_menu' action hook.
   * It adds a submenu page under the custom post type menu, and hooks the 'load_options_page_scripts' and 'enqueue_options_page_styles' methods into the appropriate action hooks.
   */
  public function add_options_page()
  {
    $this->settings_title = __( 'Invintus Video Settings', 'invintus' );

    // Add the submenu page.
    $this->hook_suffix = add_submenu_page(
      sprintf( 'edit.php?post_type=%s', $this->invintus()->get_cpt_slug() ),
      $this->settings_title,
      __( 'Settings', 'invintus' ),
      'manage_options',
      sprintf( '%s_settings', $this->invintus()->get_cpt_slug() ),
      [$this, 'render_options_page']
    );

    // Hook the 'load_options_page_scripts' method into the 'load-{page}' action.
    add_action( sprintf( 'load-%s', $this->hook_suffix ), [$this, 'load_options_page_scripts'] );

    // Hook the 'enqueue_options_page_styles' method into the 'admin_print_styles-{page}' action.
    add_action( sprintf( 'admin_print_styles-%s', $this->hook_suffix ), [$this, 'enqueue_options_page_styles'] );
  }

  /**
   * Enqueues scripts and styles for the admin page.
   *
   * This method is hooked into the 'admin_enqueue_scripts' action hook.
   * It registers and enqueues the 'invintus-app' script, and localizes it with data from the plugin's settings.
   * It also enqueues styles for the options page.
   *
   * @param string $hook_suffix The current admin page.
   */
  public function admin_scripts( $hook_suffix )
  {
    // Get the current screen
    $screen = get_current_screen();

    // Register and enqueue the Invintus player script
    wp_register_script( 'invintus-app', $this->invintus()->get_invintus_script_url(), ['underscore'], null, true );

    // Localize the script with necessary data
    wp_localize_script( 'invintus-app', 'invintusConfig', [
      'nonce'                => wp_create_nonce( 'wp_rest' ),
      'clientId'             => $this->get_client_id(),
      'defaultPlayerId'      => $this->get_option( 'invintus_player_preference_default' ),
      'playerPreferences'    => $this->get_invintus_player_preferences(),
      'siteUrl'              => get_site_url(),
      'playerUrl'            => sprintf( '%s/%s', get_site_url(), $this->invintus()->get_preview_url() ),
      'defaultWatchUrl'      => get_home_url( null, $this->invintus()->get_default_watch_endpoint() ),
      'defaultWatchEndpoint' => $this->invintus()->get_default_watch_endpoint(),
    ] );

    // Always enqueue the script on admin pages
    wp_enqueue_script( 'invintus-app' );

    // If we're on the options page, enqueue additional scripts and styles
    if ( $hook_suffix === $this->hook_suffix ):
      $this->enqueue_options_page_styles();
      $this->enqueue_options_page_scripts();
    endif;
  }

  /**
   * Enqueues the scripts for the settings page.
   */
  public function enqueue_options_page_scripts()
  {
    $dependencies   = $this->assets_data['dependencies'];
    $dependencies[] = 'invintus-app';

    wp_enqueue_script(
      'taproot-invintus-settings',
      sprintf( '%sbuild/settings.js', INVINTUS_PLUGIN_URL ),
      $dependencies,
      $this->assets_data['version'],
    );
  }

  /**
   * Enqueues the styles for the settings page.
   */
  public function enqueue_options_page_styles()
  {
    wp_enqueue_style(
      'taproot-invintus-settings',
      sprintf( '%sbuild/settings.css', INVINTUS_PLUGIN_URL ),
      ['wp-components'],
      $this->assets_data['version']
    );
  }

  /**
   * Returns the API key.
   *
   * If the INVINTUS_API_KEY constant is defined and not empty, it returns its value.
   * Otherwise, it returns the value of the 'invintus_api_key' option.
   *
   * @return string The API key.
   */
  public function get_api_key()
  {
    return $this->has_defined_api_key() ? INVINTUS_API_KEY : $this->get_option( 'invintus_api_key' );
  }

  /**
   * Returns the client ID.
   *
   * If the INVINTUS_CLIENT_ID constant is defined and not empty, it returns its value.
   * Otherwise, it returns the value of the 'invintus_client_id' option.
   *
   * @return string The client ID.
   */
  public function get_client_id()
  {
    return $this->has_defined_client_id() ? INVINTUS_CLIENT_ID : $this->get_option( 'invintus_client_id' );
  }

  /**
   * Gets the player preferences from the Invintus API.
   *
   * This method makes a POST request to the 'Player/getPlayerPreference' endpoint of the Invintus API.
   * It uses the 'clientID' and 'Wsc-api-key' headers for authentication.
   * It caches the response in a transient for 1 day.
   * If the transient exists, it returns the cached data.
   * If the request is successful, it returns the player preferences.
   * If the request fails, it returns the error message or false.
   *
   * @return array|string|false The player preferences, or the error message, or false.
   */
  public function get_invintus_player_preferences()
  {
    $endpoint       = sprintf( '%s/Player/getPlayerPreference', $this->invintus()->get_api_url() );
    $transient_slug = 'invintus_player_prefs';
    $cached_data    = get_transient( $transient_slug );
    $cache_time     = DAY_IN_SECONDS * 1;
    $preferences    = [];

    // If the transient exists, return the cached data.
    if ( $cached_data !== false ) return apply_filters( 'invintus/player/preferences', $cached_data );

    $args = [
      'headers' => [
        'Content-Type'  => 'application/json',
        'Authorization' => $this->get_api_key()
      ],
      'body' => json_encode( [
        'clientID' => $this->get_client_id()
      ] )
    ];

    // If the INVINTUS_VENDOR_KEY constant is defined, add it to the headers.
    if ( defined( 'INVINTUS_VENDOR_KEY' ) && INVINTUS_VENDOR_KEY )
      $args['headers']['Wsc-api-key'] = INVINTUS_VENDOR_KEY;

    // Make the POST request.
    $response = wp_remote_post( $endpoint, $args );

    // If there is an error, return the error message.
    if ( is_wp_error( $response ) ) return $response->get_error_message();

    // If the response code is not 200, return false.
    $response_code = wp_remote_retrieve_response_code( $response );
    if ( $response_code != 200 ) return false;

    // Decode the response body.
    $api_response = json_decode( wp_remote_retrieve_body( $response ), true );

    // If the 'data' key exists in the response, get its value.
    $data = $api_response['data'] ?? false;

    // If there is no data, return.
    if ( !$data ) return;

    // For each item in the data, add the player preference to the preferences array.
    foreach ( $data as $pref ):
      $preferences[$pref['prefID']] = $pref['playerPref']['name'];
    endforeach;

    // Set the transient with the preferences array and the cache time.
    set_transient( $transient_slug, $preferences, $cache_time );

    // Return the preferences array.
    return apply_filters( 'invintus/player/preferences', $preferences );
  }

  /**
   * Gets the value of an option.
   *
   * This method is used in the 'add_settings_field' method to get the current value of a field.
   * It gets the options from the database, and returns the value of the specified key if it exists, or an empty string if it doesn't.
   *
   * @param  string $key The key of the option.
   * @return string The value of the option.
   */
  public function get_option( $key = null )
  {
    if ( !$key ) return;

    $options = get_option( $this->invintus()->get_cpt_slug() . '_settings' );

    return $options[$key] ?? '';
  }

  /**
   * Hooks the 'admin_scripts' method into the 'admin_enqueue_scripts' action.
   */
  public function load_options_page_scripts()
  {
    add_action( 'admin_enqueue_scripts', [$this, 'admin_scripts'] );
  }

  /**
   * Purges and refreshes player preferences.
   *
   * This method is used to delete the 'invintus_player_prefs' transient.
   * It checks if the 'nonce' POST parameter is set and verifies it.
   * If the nonce is invalid, it sends a JSON response with an error.
   * If the nonce is valid, it deletes the 'invintus_player_prefs' transient.
   */
  public function purge_and_refresh_preferences()
  {
    // Check if the 'nonce' POST parameter is set and verify it.
    if ( !isset( $_POST['nonce'] ) || !wp_verify_nonce( $_POST['nonce'], 'invintus_purge_and_refresh_preferences' ) )
      // If the nonce is invalid, send a JSON response with an error.
        wp_send_json_error( __( 'Invalid nonce.', 'invintus' ) );

    // If the nonce is valid, delete the 'invintus_player_prefs' transient.
    delete_transient( 'invintus_player_prefs' );
  }

  /**
   * Renders the settings page.
   *
   * This method is used as the callback for the 'add_submenu_page' function in the 'add_options_page' method.
   */
  public function render_options_page()
  {
    // Define the path to the view file.
    $view_path = sprintf( '%sviews/settings-page.php', INVINTUS_PLUGIN_PATH );

    // If the view file exists, include it.
    if ( file_exists( $view_path ) ):
      include $view_path;
    endif;
  }

  /**
   * Returns a new instance of the Invintus class.
   *
   * @return Invintus The new instance of the Invintus class.
   */
  private function Invintus()
  {
    return new Invintus();
  }

  /**
   * Adds action hooks.
   *
   * This method is called in the 'setup' method.
   */
  private function actions()
  {
    add_action( 'admin_menu', [$this, 'add_options_page'] );
    add_action( 'admin_enqueue_scripts', [$this, 'admin_scripts'] );
  }

  /**
   * Adds filter hooks.
   *
   * This method is called in the 'setup' method.
   */
  private function filters()
  {
  }

  /**
   * Checks if the INVINTUS_API_KEY constant is defined and not empty.
   *
   * @return bool True if INVINTUS_API_KEY is defined and not empty, false otherwise.
   */
  private function has_defined_api_key()
  {
    return ( defined( 'INVINTUS_API_KEY' ) && !empty( INVINTUS_API_KEY ) ) ? true : false;
  }

  /**
   * Checks if the INVINTUS_CLIENT_ID constant is defined and not empty.
   *
   * @return bool True if INVINTUS_CLIENT_ID is defined and not empty, false otherwise.
   */
  private function has_defined_client_id()
  {
    return ( defined( 'INVINTUS_CLIENT_ID' ) && !empty( INVINTUS_CLIENT_ID ) ) ? true : false;
  }
}
