<?php
/**
 * Invintus Plugin Main Class
 *
 * This class encapsulates the functionality for the Invintus plugin, including
 * custom post type registration, taxonomy registration, and integration with the Invintus API.
 *
 * @package InvintusPlugin
 */

namespace Taproot\Invintus;

class Invintus {
  /**
   * Singleton instance of the class.
   *
   * @var Invintus|null
   */
  private static $instance = null;

  /**
   * Custom post type slug.
   *
   * @var string
   */
  private $cpt_slug = 'invintus_video';

  /**
   * Custom post type slug for URL rewrite.
   *
   * @var string
   */
  private $cpt_slug_rewrite = 'video';

  /**
   * Invintus API base URL.
   *
   * @var string
   */
  private $invintus_api_url = 'https://api.v3.invintus.com/v2';

  /**
   * The default path for the watch redirect endpoint.
   *
   * @var string
   */
  private $default_watch_endpoint = 'watch';

  /**
   * Holds various plugin settings.
   *
   * @var Settings
   */
  private $settings;

  /**
   * Handles API interactions.
   *
   * @var API
   */
  private $api;

  /**
   * Manages Gutenberg block integration.
   *
   * @var Block
   */
  private $block;

  /**
   * This property is used to store the metadata for the plugin.
   *
   * @var mixed
   */
  private $metadata;

  /**
   * This property is used to store the client ID for the Invintus API.
   *
   * @var string
   */
  private $client_id;

  /**
   * Initializes the plugin by setting up the required hooks and functionalities.
   * This method acts as a constructor for the singleton instance.
   *
   * @return Invintus The singleton instance.
   */
  public static function init() {
    if ( null === self::$instance ):
      self::$instance = new self();
      self::$instance->setup();
    endif;

    return self::$instance;
  }

  /**
   * Sets up the class.
   *
   * This method is called after the class is instantiated.
   * It calls the 'actions' and 'filters' methods to add the action and filter hooks.
   * Finally, it fires the 'invintus_options_loaded' action.
   */
  private function setup() {
    $this->actions();
    $this->filters();

    $this->settings = new Settings();
    $this->settings->setup();

    $this->api = new API;
    $this->api->setup();

    $this->block = new Block( $this->get_client_id() );
    $this->block->setup();

    $this->metadata = new Metadata;
    $this->metadata->setup();

    do_action( 'invintus_options_loaded' );
  }

  public function settings() {
    return $this->settings;
  }

  /**
   * Adds action hooks.
   *
   * This method is called in the 'setup' method.
   */
  private function actions() {
    add_action( 'init', [$this, 'register_custom_post_type'] );
    add_action( 'init', [$this, 'register_custom_category'] );
    add_action( 'init', [$this, 'register_custom_tag'] );
    add_action( 'init', [$this, 'create_watch_redirect'] );
    add_action( 'activated_plugin', [$this, 'refresh_permalinks'] );
  }

  /**
   * Adds filter hooks.
   *
   * This method is called in the 'setup' method.
   */
  private function filters() {
    add_filter( 'https_ssl_verify', [$this, 'maybe_verify_ssl'] );
    add_filter( 'get_post_status',  [$this, 'maybe_allow_future_events'], 10, 2 );
    add_filter( 'get_post_status',  [$this, 'allow_live_events'], 10, 2 );
  }

  /**
   * Activates the plugin.
   *
   * This method is called when the plugin is activated.
   * It initializes the class, registers the custom post type, category, and tag, and flushes the rewrite rules.
   */
  public static function activate() {
    self::init()->register_custom_post_type();
    self::init()->register_custom_category();
    self::init()->register_custom_tag();
    self::init()->create_tables();

    flush_rewrite_rules();
  }

  /**
   * Deactivates the plugin.
   *
   * This method is called when the plugin is deactivated.
   * It flushes the rewrite rules.
   */
  public static function deactivate() {
    flush_rewrite_rules();
  }

  /**
   * Initializes the settings if they are not already initialized.
   *
   * This method first checks if the 'settings' property is already set. If it's set, the method returns immediately.
   * Then, it creates a new instance of the Settings class, passing the custom post type slug to the constructor.
   * Finally, it calls the 'setup' method on the 'settings' instance.
   */
  public function maybe_init_settings() {
    if ( $this->settings ) return;

    $this->settings = new Settings();
  }

  /**
   * Retrieves the custom post type slug.
   *
   * @return string The custom post type slug.
   */
  public function get_cpt_slug() {
    return apply_filters( 'invintus/register/slug/cpt', $this->cpt_slug );
  }

  /**
   * Retrieves the custom post type slug rewrite.
   *
   * @return string The custom post type slug rewrite.
   */
  public function get_cpt_slug_rewrite() {
    return apply_filters( 'invintus/register/slug/rewrite', $this->cpt_slug_rewrite );
  }

  /**
   * Retrieves the default watch endpoint.
   *
   * This method constructs the default watch endpoint by appending the default watch path to the custom post type slug rewrite.
   * The custom post type slug rewrite is retrieved by calling the 'get_cpt_slug_rewrite' method.
   *
   * @return string The default watch endpoint.
   */
  public function get_default_watch_endpoint() {
    return sprintf( '%s/%s', $this->get_cpt_slug_rewrite(), $this->default_watch_endpoint );
  }

  /**
   * Retrieves the watch URL endpoint.
   *
   * @return string The watch URL endpoint.
   */
  public function get_watch_endpoint() {
    $this->maybe_init_settings();

    $settings_watch_path = $this->settings->get_option( 'invintus_watch_path' );
    $endpoint           = $settings_watch_path ? $settings_watch_path : $this->get_default_watch_endpoint();

    return apply_filters( 'invintus/events/watch/endpoint', $endpoint );
  }

  /**
   * Retrieves the preview URL.
   *
   * @return string The preview URL.
   */
  public function get_preview_url() {
    return apply_filters( 'invintus/events/preview/endpoint', sprintf( '%s/preview', $this->get_cpt_slug_rewrite() ) );
  }

  /**
   * Retrieves the Invintus script URL.
   *
   * @return string The Invintus script URL.
   */
  public function get_invintus_script_url() {
    return apply_filters( 'invintus/player/script/url', 'https://player.invintus.com/app.js' );
  }

  /**
   * Registers the custom post type.
   *
   * This method is called in the 'actions' method.
   * It sets up the labels, rewrite rules, and arguments for the custom post type and registers it.
   */
  public function register_custom_post_type() {
    $icon = file_get_contents( sprintf( '%sassets/svg/invintus-menu-icon.svg', INVINTUS_PLUGIN_PATH ) );

    $labels = array(
      'name'                  => _x( 'Videos', 'Post Type General Name', 'invintus' ),
      'singular_name'         => _x( 'Video', 'Post Type Singular Name', 'invintus' ),
      'menu_name'             => __( 'Invintus Videos', 'invintus' ),
      'name_admin_bar'        => __( 'Invintus Video', 'invintus' ),
      'archives'              => __( 'Video Archives', 'invintus' ),
      'attributes'            => __( 'Video Attributes', 'invintus' ),
      'parent_item_colon'     => __( 'Parent Item:', 'invintus' ),
      'all_items'             => __( 'All Videos', 'invintus' ),
      'add_new_item'          => __( 'Add New Video', 'invintus' ),
      'add_new'               => __( 'Add New', 'invintus' ),
      'new_item'              => __( 'New Video', 'invintus' ),
      'edit_item'             => __( 'Edit Video', 'invintus' ),
      'update_item'           => __( 'Update Video', 'invintus' ),
      'view_item'             => __( 'View Video', 'invintus' ),
      'view_items'            => __( 'View Videos', 'invintus' ),
      'search_items'          => __( 'Search Video', 'invintus' ),
      'not_found'             => __( 'Not found', 'invintus' ),
      'not_found_in_trash'    => __( 'Not found in Trash', 'invintus' ),
      'featured_image'        => __( 'Featured Image', 'invintus' ),
      'set_featured_image'    => __( 'Set featured image', 'invintus' ),
      'remove_featured_image' => __( 'Remove featured image', 'invintus' ),
      'use_featured_image'    => __( 'Use as featured image', 'invintus' ),
      'insert_into_item'      => __( 'Insert into video', 'invintus' ),
      'uploaded_to_this_item' => __( 'Uploaded to this video', 'invintus' ),
      'items_list'            => __( 'Videos list', 'invintus' ),
      'items_list_navigation' => __( 'Videos list navigation', 'invintus' ),
      'filter_items_list'     => __( 'Filter videos list', 'invintus' ),
    );

    $rewrite = array(
      'slug'                  => $this->get_cpt_slug_rewrite(),
      'with_front'            => true,
      'pages'                 => true,
      'feeds'                 => true,
    );

    $args = array(
      'label'                 => __( 'Video', 'invintus' ),
      'description'           => __( 'Video Description', 'invintus' ),
      'labels'                => $labels,
      'supports'              => array( 'title', 'editor', 'thumbnail' ),
      'taxonomies'            => array( 'invintus_category', 'invintus_tag' ),
      'hierarchical'          => false,
      'public'                => true,
      'show_ui'               => true,
      'show_in_menu'          => true,
      'menu_position'         => 5,
      'show_in_admin_bar'     => true,
      'show_in_nav_menus'     => true,
      'can_export'            => true,
      'has_archive'           => true,
      'exclude_from_search'   => false,
      'publicly_queryable'    => true,
      'rewrite'               => $rewrite,
      'capability_type'       => 'page',
      'show_in_rest'          => true,
      'menu_icon'             => 'data:image/svg+xml;base64,' . base64_encode( $icon ),
      'supports'              => ['title', 'editor', 'custom-fields']
    );

    register_post_type( $this->get_cpt_slug(), $args );
  }

  /**
   * Registers the custom category taxonomy.
   *
   * This method is called in the 'actions' method.
   * It sets up the labels and arguments for the custom category taxonomy and registers it.
   */
  public function register_custom_category() {
    $labels = array(
      'name'                       => _x( 'Categories', 'Taxonomy General Name', 'invintus' ),
      'singular_name'              => _x( 'Category', 'Taxonomy Singular Name', 'invintus' ),
      'menu_name'                  => __( 'Invintus Categories', 'invintus' ),
      'all_items'                  => __( 'All Items', 'invintus' ),
      'parent_item'                => __( 'Parent Item', 'invintus' ),
      'parent_item_colon'          => __( 'Parent Item:', 'invintus' ),
      'new_item_name'              => __( 'New Item Name', 'invintus' ),
      'add_new_item'               => __( 'Add New Item', 'invintus' ),
      'edit_item'                  => __( 'Edit Item', 'invintus' ),
      'update_item'                => __( 'Update Item', 'invintus' ),
      'view_item'                  => __( 'View Item', 'invintus' ),
      'separate_items_with_commas' => __( 'Separate items with commas', 'invintus' ),
      'add_or_remove_items'        => __( 'Add or remove items', 'invintus' ),
      'choose_from_most_used'      => __( 'Choose from the most used', 'invintus' ),
      'popular_items'              => __( 'Popular Items', 'invintus' ),
      'search_items'               => __( 'Search Items', 'invintus' ),
      'not_found'                  => __( 'Not Found', 'invintus' ),
      'no_terms'                   => __( 'No items', 'invintus' ),
      'items_list'                 => __( 'Items list', 'invintus' ),
      'items_list_navigation'      => __( 'Items list navigation', 'invintus' ),
    );

    $args = array(
      'labels'                     => $labels,
      'hierarchical'               => true,
      'public'                     => true,
      'show_ui'                    => true,
      'show_admin_column'          => true,
      'show_in_nav_menus'          => true,
      'show_tagcloud'              => true,
      'show_in_rest'               => true,
    );

    register_taxonomy( 'invintus_category', apply_filters( 'invintus/register/categories', [$this->get_cpt_slug()] ), $args );
  }

  /**
   * Registers the custom tag taxonomy.
   *
   * This method is called in the 'actions' method.
   * It sets up the labels and arguments for the custom tag taxonomy and registers it.
   */
  public function register_custom_tag() {
    $labels = array(
      'name'                       => _x( 'Tags', 'Taxonomy General Name', 'invintus' ),
      'singular_name'              => _x( 'Tag', 'Taxonomy Singular Name', 'invintus' ),
      'menu_name'                  => __( 'Invintus Tags', 'invintus' ),
      'all_items'                  => __( 'All Items', 'invintus' ),
      'parent_item'                => __( 'Parent Item', 'invintus' ),
      'parent_item_colon'          => __( 'Parent Item:', 'invintus' ),
      'new_item_name'              => __( 'New Item Name', 'invintus' ),
      'add_new_item'               => __( 'Add New Item', 'invintus' ),
      'edit_item'                  => __( 'Edit Item', 'invintus' ),
      'update_item'                => __( 'Update Item', 'invintus' ),
      'view_item'                  => __( 'View Item', 'invintus' ),
      'separate_items_with_commas' => __( 'Separate items with commas', 'invintus' ),
      'add_or_remove_items'        => __( 'Add or remove items', 'invintus' ),
      'choose_from_most_used'      => __( 'Choose from the most used', 'invintus' ),
      'popular_items'              => __( 'Popular Items', 'invintus' ),
      'search_items'               => __( 'Search Items', 'invintus' ),
      'not_found'                  => __( 'Not Found', 'invintus' ),
      'no_terms'                   => __( 'No items', 'invintus' ),
      'items_list'                 => __( 'Items list', 'invintus' ),
      'items_list_navigation'      => __( 'Items list navigation', 'invintus' ),
    );
    $args = array(
      'labels'                     => $labels,
      'hierarchical'               => false,
      'public'                     => true,
      'show_ui'                    => true,
      'show_admin_column'          => true,
      'show_in_nav_menus'          => true,
      'show_tagcloud'              => true,
      'show_in_rest'               => true,
    );

    register_taxonomy( 'invintus_tag', apply_filters( 'invintus/register/tags', [$this->get_cpt_slug()] ), $args );
  }

  /**
   * Safely retrieves a value from an array.
   *
   * @param array $array The array from which to retrieve the value.
   * @param mixed $key The key of the value to retrieve.
   * @param mixed $default The default value to return if the key is not found in the array.
   * @return mixed The value from the array, or the default value.
   */
  public function maybe_get( $array = [], $key = 0, $default = null ) {
    return isset( $array[$key] ) ? $array[$key] : $default;
  }

  /**
   * Retrieves the API key.
   *
   * @return string The API key.
   */
  public function get_api_key() {
    return $this->settings->get_api_key();
  }

  /**
   * Retrieves the client ID.
   *
   * @return string The client ID.
   */
  public function get_client_id() {
    return $this->settings->get_client_id();
  }

  /**
   * Retrieves the API URL.
   *
   * @return string The API URL.
   */
  public function get_api_url() {
    return apply_filters( 'invintus/api/url', $this->invintus_api_url );
  }

  /**
   * Verifies SSL connection based on the environment.
   * This can be filtered to return false in local or development environments.
   *
   * @return bool Whether to verify SSL.
   */
  public function maybe_verify_ssl() {
    switch ( wp_get_environment_type() ):
      case 'local':
      case 'development':
        return false;
        break;

      case 'staging':
        return true;
        break;

      case 'production':
      default:
        return true;
        break;
    endswitch;
  }

  /**
   * Allows future events to be published.
   *
   * @param string $post_status The current post status.
   * @param WP_Post $post The post object.
   * @return string The updated post status.
   */
  public function maybe_allow_future_events( $post_status, $post ) {
    $can_public_future_events = get_option( 'can_public_future_events' );

    if ( !$can_public_future_events ) return $post_status;

    if ( $post->post_type == $this->get_cpt_slug() and 'future' == $post_status ) return 'publish';

    return $post_status;
  }

  /**
   * Allows live events to be published.
   *
   * @param string $post_status The current post status.
   * @param WP_Post $post The post object.
   * @return string The updated post status.
   */
  public function allow_live_events( $post_status, $post ) {
    if ( $post->post_type == $this->get_cpt_slug() and 'live' == $post_status ) return 'publish';

    return $post_status;
  }

  /**
   * Creates a redirect for the watch URL.
   */
  public function create_watch_redirect() {
    $url_path = trim( parse_url( add_query_arg( [] ), PHP_URL_PATH ), '/' );

    if ( $url_path === $this->get_watch_endpoint() ):
      include INVINTUS_PLUGIN_PATH . 'templates/watch-redirect.php';
      exit;
    endif;
  }

  /**
   * Creates the necessary tables.
   *
   * This method creates the necessary tables by creating a new instance of the DB class
   * and calling its create_tables method.
   */
  public function create_tables() {
    $db = new DB;
    $db->create_tables();
  }

  /**
   * Retrieves the settings for the custom post type.
   *
   * This method retrieves the settings for the custom post type from the options table in the database.
   * The option name is constructed by appending '_settings' to the custom post type slug.
   *
   * @return mixed The settings for the custom post type.
   */
  public function get_settings() {
    return get_option( sprintf( '%s_settings', $this->cpt_slug ) );
  }

  /**
   * Retrieves a specific option from the settings.
   *
   * This method retrieves a specific option from the settings.
   * If the option does not exist, it returns a default value.
   *
   * @param string $option The name of the option to retrieve.
   * @param mixed $default The default value to return if the option does not exist.
   * @return mixed The value of the option, or the default value if the option does not exist.
   */
  public function get_option( $option, $default = null ) {
    return $this->maybe_get( $this->get_settings(), $option, $default );
  }

  /**
   * Determines if payloads can be logged.
   *
   * This method retrieves the 'can_log_payloads' option from the database and applies the 'invintus/settings/logs' filter to it.
   * Other plugins can use this filter to modify the value of 'can_log_payloads'.
   *
   * @return mixed The filtered value of 'can_log_payloads'.
   */
  public function can_log_payloads() {
    $can_log_payloads = get_option( 'can_log_payloads' );
    return apply_filters( 'invintus/settings/logs', $can_log_payloads );
  }

  /**
   * Refreshes the permalinks after plugin activation.
   * This ensures our custom post type and endpoints are properly registered.
   *
   * @param string $plugin The plugin file that was activated.
   */
  public function refresh_permalinks( $plugin )
  {
    if ( plugin_basename( constant('INVINTUS_PLUGIN_FILE') ) === $plugin ):
      // Wait for the next request to flush rewrite rules
      // This ensures all custom post types are registered
      add_action( 'shutdown', function() { flush_rewrite_rules(); } );
    endif;
  }
}
