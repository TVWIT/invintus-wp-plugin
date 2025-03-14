<?php

/**
 * Create API routes.
 *
 * @package InvintusPlugin
 */

namespace Taproot\Invintus;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_Query;
use DateTimeImmutable;
use DateTimeZone;

class API extends WP_REST_Controller
{
  /**
   * The namespace of the API.
   *
   * @var string
   */
  protected $api_namespace = 'invintus';

  /**
   * The version of the API.
   *
   * @var string
   */
  protected $api_version = '2';

  /**
   * The actions that trigger a delete operation.
   *
   * @var array
   */
  private $delete_actions = ['delete'];

  /**
   * The name of the settings option in the database.
   *
   * @var string
   */
  private $settings_name = 'invintus_video_settings';

  /**
   * The actions that trigger an upsert operation.
   *
   * @var array
   */
  private $upsert_actions = ['add', 'update', 'new', 'stop'];

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

    do_action( 'invintus_api_loaded' );
  }

  /**
   * Creates a custom post status.
   *
   * This method is called in the 'setup' method.
   * It sets up the arguments for the custom post status and registers it.
   */
  public function create_post_status()
  {
    $args = [
      'label'                     => _x( 'Live', 'Status General Name', 'invnitus' ),
      'label_count'               => _n_noop( 'Live (%s)', 'Live (%s)', 'invnitus' ),
      'public'                    => false,
      'show_in_admin_all_list'    => true,
      'show_in_admin_status_list' => true,
      'exclude_from_search'       => true,
    ];

    register_post_status( 'live', $args );
  }

  /**
   * Gets player preferences.
   *
   * This function first gets the parameters from the request.
   * It then checks if there are any errors in the request.
   * If there are errors, it returns a WP_Error with the error message.
   * If there are no errors, it gets the transient for the player preferences.
   * Finally, it returns a response with a success message.
   *
   * @param  WP_REST_Request           $request The request.
   * @return WP_Error|WP_REST_Response The error or the response.
   */
  public function get_player_prefs( WP_REST_Request $request )
  {
    $params = $request->get_params();

    $request_errors = $this->maybe_get( $params, 'errors' );
    $has_errors     = $this->maybe_get( $request_errors, 'hasError' );

    if ( $has_errors ):

      return new WP_Error( 'rest_invintus_error', $request_errors['message'], ['status' => rest_authorization_required_code()] );

    endif;

    $player_prefs = get_transient( 'invintus_player_prefs' );

    return rest_ensure_response( ['message' => __( 'Player preferences purged.', 'invintus' )] );
  }

  /**
   * Retrieves the settings.
   *
   * This method first gets the parameters from the request.
   * If the 'errors' parameter is set and 'hasError' is true, it returns a WP_Error with the error message and status code.
   * Otherwise, it retrieves the settings from the database and returns them in a WP_REST_Response.
   *
   * @param  WP_REST_Request           $request The request.
   * @return WP_Error|WP_REST_Response The settings in a WP_REST_Response on success, or a WP_Error on failure.
   */
  public function get_settings( WP_REST_Request $request )
  {
    $data   = [];
    $params = $request->get_params();

    $request_errors = $this->maybe_get( $params, 'errors' );
    $has_errors     = $this->maybe_get( $request_errors, 'hasError' );

    if ( $has_errors ):

      return new WP_Error( 'rest_invintus_error', $request_errors['message'], ['status' => rest_authorization_required_code()] );

    endif;

    $settings                                = get_option( $this->settings_name );
    $settings['invintus_player_preferences'] = $this->settings()->get_invintus_player_preferences();

    return rest_ensure_response( $settings );
  }

  /**
   * Checks if there are any live events.
   *
   * This method retrieves the live events from the Invintus API.
   * It caches the result for 1 minute to reduce the number of API calls.
   *
   * @return bool True if there are live events, false otherwise.
   */
  public function is_live()
  {
    $endpoint       = sprintf( '%s/Listings/getBasic', $this->invintus()->get_api_url() );
    $transient_slug = 'invintus_is_live';
    $cached_data    = get_transient( $transient_slug );
    $cache_time     = MINUTE_IN_SECONDS * 1;
    $events         = [];

    $date = new DateTimeImmutable();

    $start = $date->format( 'Y-m-d' );
    $stop  = $date->format( 'Y-m-d' );

    // Display cached data if it exists
    if ( $cached_data !== false ):

      $is_live = (bool) $cached_data;

    else:

      $args = [
        'timeout' => 30,
        'headers' => [
          'Content-Type'  => 'application/json',
          'Authorization' => $this->invintus()->get_api_key()
        ],
        'body' => json_encode( [
          'clientID'   => $this->invintus()->get_client_id(),
          'startDate'  => $start,
          'endDate'    => $stop,
          'resultMax'  => 1,
          'resultPage' => 1,
          'getLive'    => true,
        ] )
      ];

      if ( defined( 'INVINTUS_VENDOR_KEY' ) && INVINTUS_VENDOR_KEY )
        $args['headers']['Wsc-api-key'] = INVINTUS_VENDOR_KEY;

      $response = wp_remote_post( $endpoint, $args );

      if ( is_wp_error( $response ) ) return $response->get_error_message();

      $response_code = wp_remote_retrieve_response_code( $response );

      if ( $response_code != 200 ) return false;

      $body = wp_remote_retrieve_body( $response );

      if ( empty( $body ) ) return false;

      $api_response = json_decode( $body, true );

      $data = acf_maybe_get( $api_response, 'data', [] );

      $is_live = $data ? true : false;

      set_transient( $transient_slug, $is_live, $cache_time );

    endif;

    return $is_live;
  }

  /**
   * Convert's Invintus data into WP friendly data
   *
   * This method is called when the 'events/crud' route is accessed.
   * It retrieves the parameters and authorization header from the request, checks for errors, and performs the appropriate action based on the action type.
   *
   * @param  WP_REST_Request $request The REST API request.
   * @return mixed           The response to the REST API request.
   */
  public function process_payload( WP_REST_Request $request )
  {
    global $wpdb;

    $data       = [];
    $table_name = $wpdb->prefix . 'invintus_logs';
    $params     = $request->get_params();
    $auth       = $request->get_header( 'authorization' );

    $can_log_payloads = $this->invintus()->get_option( 'can_log_payloads' );

    $request_errors = $this->maybe_get( $params, 'errors' );
    $has_errors     = $this->maybe_get( $request_errors, 'hasError' );

    if ( $has_errors && $can_log_payloads ):

      $wpdb->insert(
        $table_name,
        [
          'event_id' => '',
          'action'   => 'error',
          'payload'  => $request_errors['message'],
          'date'     => date( 'Y-m-d H:i:s' )
        ],
      );

      return new WP_Error( 'rest_invintus_error', $request_errors['message'], ['status' => rest_authorization_required_code()] );

    endif;

    // Check and delete log entries based on the 'invintus_log_retention' option.
    $this->maybe_delete_log_entries();

    // Check for Invintus actions
    $action      = $this->maybe_get( $params, 'action' );
    $action_type = $this->maybe_get( $action, 'type' );

    // If the method is not 'events', return an error response
    if ( 'events' != $this->maybe_get( $action, 'method' ) )
      return new WP_Error( 'rest_invintus_method_error', __( 'Invalid method.', 'invintus' ), ['status' => rest_authorization_required_code()] );

    // Push response into new data model
    // excluding the "action" parameter since we
    // already validate it above
    $data = ['action' => $action, 'event' => $this->maybe_get( $params, 'data' )];

    $_event_id = explode( '_', $data['event']['eventID'] );
    $event_id  = end( $_event_id );

    if ( $can_log_payloads ):

      $wpdb->insert(
        $table_name,
        [
          'event_id' => $event_id,
          'action'   => $action_type,
          'payload'  => $request->get_body(),
          'date'     => date( 'Y-m-d H:i:s' )
        ],
      );

    endif;

    if ( in_array( $action_type, $this->upsert_actions ) ):

      $response = $this->upsert_data( $data );

      return $response;

    elseif ( in_array( $action_type, $this->delete_actions ) ):

      $response = $this->delete_events( $data );

      return $response;

    else:

      return new WP_Error( 'rest_invintus_invalid_action', __( 'Sorry, the action you are trying to perform does not exist.', 'invintus' ), ['status' => rest_authorization_required_code()] );

    endif;

    wp_send_json_success( $data );
  }

  /**
   * Purges player preferences.
   *
   * This function first gets the parameters from the request.
   * It then checks if there are any errors in the request.
   * If there are errors, it returns a WP_Error with the error message.
   * If there are no errors, it deletes the transient for the player preferences.
   * Finally, it returns the player preferences from the settings.
   *
   * @param  WP_REST_Request           $request The request.
   * @return WP_Error|WP_REST_Response The error or the response.
   */
  public function purge_player_prefs( WP_REST_Request $request )
  {
    $params = $request->get_params();

    $request_errors = $this->maybe_get( $params, 'errors' );
    $has_errors     = $this->maybe_get( $request_errors, 'hasError' );

    if ( $has_errors ):

      return new WP_Error( 'rest_invintus_error', $request_errors['message'], ['status' => rest_authorization_required_code()] );

    endif;

    delete_transient( 'invintus_player_prefs' );

    return rest_ensure_response( $this->settings()->get_invintus_player_preferences() );
  }

  /**
   * Registers the REST API routes.
   *
   * This method is called in the 'setup' method.
   * It retrieves the API options and registers the 'events/crud' and 'events/is_live' routes.
   */
  public function register_routes()
  {
    $options = $this->get_options();

    register_rest_route( sprintf( '%s/v%s', $options['namespace'], $options['version'] ), 'events/crud', [
      'methods'             => WP_REST_Server::CREATABLE,
      'show_in_index'       => false,
      'callback'            => [$this, 'process_payload'],
      'permission_callback' => function( $request ) {
        if ( current_user_can( 'publish_posts' ) ) return true;

        return new WP_Error( 'rest_invalid_permissions', __( 'Sorry, you do not have the proper permission to access this content.', 'invintus' ), ['status' => rest_authorization_required_code()] );
      }
    ] );

    register_rest_route( sprintf( '%s/v%s', $options['namespace'], $options['version'] ), 'events/is_live', [
      'methods'             => WP_REST_Server::READABLE,
      'show_in_index'       => false,
      'callback'            => [$this, 'is_live'],
      'permission_callback' => function( $request ) {
        return true;
      }
    ] );

    register_rest_route( sprintf( '%s/v%s', $options['namespace'], $options['version'] ), 'settings/player_prefs', [
      [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => [$this, 'get_player_prefs'],
        'permission_callback' => function( $request ) {
          if ( current_user_can( 'publish_posts' ) ) return true;

          return new WP_Error( 'rest_invalid_permissions', __( 'Sorry, you do not have the proper permission to access this content.', 'invintus' ), ['status' => rest_authorization_required_code()] );
        },
        'show_in_index' => false,
      ],
      [
        'methods'             => WP_REST_Server::DELETABLE,
        'callback'            => [$this, 'purge_player_prefs'],
        'permission_callback' => function( $request ) {
          if ( current_user_can( 'publish_posts' ) ) return true;

          return new WP_Error( 'rest_invalid_permissions', __( 'Sorry, you do not have the proper permission to access this content.', 'invintus' ), ['status' => rest_authorization_required_code()] );
        },
        'show_in_index' => false,
      ],
    ] );

    register_rest_route( sprintf( '%s/v%s', $options['namespace'], $options['version'] ), 'settings', [
      [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => [$this, 'set_settings'],
        'permission_callback' => function( $request ) {
          if ( current_user_can( 'publish_posts' ) ) return true;

          return new WP_Error( 'rest_invalid_permissions', __( 'Sorry, you do not have the proper permission to access this content.', 'invintus' ), ['status' => rest_authorization_required_code()] );
        },
        'show_in_index' => false,
      ],
      [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => [$this, 'get_settings'],
        'permission_callback' => function( $request ) {
          if ( current_user_can( 'publish_posts' ) ) return true;

          return new WP_Error( 'rest_invalid_permissions', __( 'Sorry, you do not have the proper permission to access this content.', 'invintus' ), ['status' => rest_authorization_required_code()] );
        },
        'show_in_index' => false,
      ],
    ] );
  }

  /**
   * Sets the settings.
   *
   * This method first gets the parameters from the request.
   * If the 'errors' parameter is set and 'hasError' is true, it returns a WP_Error with the error message and status code.
   * Otherwise, it defines the expected settings keys and their corresponding request parameter keys.
   * It retrieves the existing settings or initializes to an empty array.
   * Then, it updates each setting based on the expected settings.
   * It saves the updated settings array and returns them in a WP_REST_Response.
   *
   * @param  WP_REST_Request           $request The request.
   * @return WP_Error|WP_REST_Response The updated settings in a WP_REST_Response on success, or a WP_Error on failure.
   */
  public function set_settings( WP_REST_Request $request )
  {
    $params = $request->get_params();

    $request_errors = $this->maybe_get( $params, 'errors' );
    $has_errors     = $this->maybe_get( $request_errors, 'hasError' );

    if ( $has_errors ):

      return new WP_Error( 'rest_invintus_error', $request_errors['message'], ['status' => rest_authorization_required_code()] );

    endif;

    // Define the expected settings keys and their corresponding request parameter keys
    $settings_map = [
      'invintus_client_id'                 => 'clientId',
      'invintus_api_key'                   => 'apiKey',
      'invintus_player_preference_default' => 'defaultPlayerPreference',
      'can_public_future_events'           => 'enablePublicEvents',
      'invintus_watch_path'                => 'watchRedirectPath',
      'can_log_payloads'                   => 'enableLogs',
      'invintus_log_retention'             => 'logRetention',
    ];

    // Retrieve the existing settings or initialize to an empty array
    $settings = get_option( 'invintus_video_settings', [] );

    // Update each setting based on the expected settings
    foreach ( $settings_map as $settings_key => $param_key ):
      if ( isset( $params[$param_key] ) ):
        $settings[$settings_key] = sanitize_text_field( $params[$param_key] );
      endif;
    endforeach;

    // Save the updated settings array
    update_option( 'invintus_video_settings', $settings );

    // Return the updated settings
    return rest_ensure_response( $settings );
  }

  /**
   * Adds action hooks.
   *
   * This method is called in the 'setup' method.
   */
  private function actions()
  {
    add_action( 'init', [$this, 'create_post_status'] );

    add_action( 'rest_api_init', [$this, 'register_routes'] );
  }

  /**
   * ANCHOR Create category
   *
   * @param  mixed $data
   * @param  mixed $taxonomy
   * @return void
   */
  private function create_category( $data, $taxonomy )
  {
    $parent_id = '';

    $category_id          = $this->maybe_get( $data, 'categoryID', '' );
    $category_name        = $this->maybe_get( $data, 'categoryName', '' );
    $category_description = $this->maybe_get( $data, 'categoryDescription', '' );

    $child_parent_id = $this->maybe_get( $data, 'childOf', '' );

    if ( $child_parent_id ):
      $parent_id = $this->maybe_get( $this->get_category_by_invintus_id( $child_parent_id ), 'term_id' );
    endif;

    $args = [
      'parent'      => $parent_id,
      'slug'        => sanitize_title( $category_name ),
      'description' => $description
    ];

    $term = wp_insert_term( $category_name, $taxonomy, $args );

    if ( is_wp_error( $term ) ) return;

    $term_id = $this->maybe_get( $term, 'term_id' );

    $term_field_id = sprintf( '%s_%d', $taxonomy, $term_id );

    update_term_meta( $term_id, 'invintus_category_id', $category_id );
    update_term_meta( $term_id, 'invintus_parent_category_id', $child_id );

    return $term_id;
  }

  /**
   * Deletes an event from the database.
   *
   * This function first maps the response data to a format that can be used to find the event.
   * It then checks if the current user can delete posts.
   * If the user cannot delete posts, it returns an error.
   * It then checks if the event exists in the database.
   * If the event does not exist, it returns an error.
   * If the event exists, it deletes the event and adds the result to a response array.
   *
   * @param  array          $response The response data.
   * @return WP_Error|array The error or the array of results from the delete operations.
   */
  private function delete_events( $response )
  {
    $data   = $this->map_data( $response );
    $events = $this->event_exists( $data );

    // Validate user can publish posts
    if ( !current_user_can( 'delete_posts' ) )
      return new WP_Error( 'rest_cannot_delete_posts', __( 'Sorry, you are not allowed to delete posts as this user.' ), ['status' => rest_authorization_required_code()] );

    $events = $this->event_exists( $data );

    if ( !$events )
      return new WP_Error( 'rest_no_event', __( 'Sorry, the event you are looking for does not exist.' ), ['status' => rest_authorization_required_code()] );

    $res = [];

    foreach ( $events as $post_id ):
      $res[] = wp_delete_post( $post_id );
    endforeach;

    return $res;
  }

  /**
   * Check if an event exists
   *
   * @param  mixed $data
   * @return void
   */
  private function event_exists( $data )
  {
    $args = [
      'posts_per_page'         => 1,
      'meta_key'               => 'invintus_event_id',
      'meta_value'             => $data['event_id'],
      'no_found_rows'          => true,
      'update_post_term_cache' => false,
      'fields'                 => 'ids',
      'post_type'              => 'invintus_video',
      'post_status'            => ['publish', 'future', 'draft', 'pending', 'private', 'live']
    ];

    $query = new WP_Query( $args );

    if ( !$query->have_posts() ) return [];

    return $query->posts;
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
   * Retrieves a category by its Invintus ID.
   *
   * This function first checks if the ID is not null.
   * If the ID is null, it returns.
   * It then checks if a term exists with the given ID in the 'invintus_category' taxonomy.
   * If a term does not exist or an error occurs, it returns.
   * If a term exists, it retrieves the term and returns it as an array.
   *
   * @param  int        $id       The Invintus ID of the category.
   * @param  string     $meta_key The meta key to search for the ID (default is 'invintus_category_id').
   * @return array|null The category or null if the category does not exist or an error occurs.
   */
  private function get_category_by_invintus_id( $id, $meta_key = 'invintus_category_id' )
  {
    if ( !$id ) return;

    // Check if parent exists
    $term = get_terms( [
      'taxonomy'   => 'invintus_category',
      'hide_empty' => false,
      'meta_query' => [
        [
          'key'     => $meta_key,
          'value'   => (int) $id,
          'compare' => '='
        ]
      ]
    ] );

    if ( is_wp_error( $term ) || !$term ) return;

    return (array) $term[0];
  }

  /**
   * Retrieves a category by its name.
   *
   * This function first checks if a term exists with the given name in the 'invintus_category' taxonomy.
   * If a term does not exist, it returns.
   * If a term exists, it retrieves the term and returns it as an array.
   *
   * @param  string     $name The name of the category.
   * @return array|null The category or null if the category does not exist.
   */
  private function get_category_by_name( $name )
  {
    $term_exists = term_exists( $name, 'invintus_category' );

    if ( !$term_exists ) return;

    return (array) get_term( $term_exists['term_id'], $taxonomy );
  }

  /**
   * Retrieves the API options.
   *
   * This method is called in the 'register_routes' method.
   * It applies the 'invintus/api/options' and 'invintus/api/statuses' filters to the API options and returns them.
   *
   * @return array The API options.
   */
  private function get_options()
  {
    return apply_filters( 'invintus/api/options', [
      'namespace' => $this->api_namespace,
      'version'   => $this->api_version,
      'url'       => $this->invintus()->get_api_url(),
      'statuses'  => apply_filters( 'invintus/api/statuses', [
        'published' => ['live', 'published']
      ] )
    ] );
  }

  /**
   * Create array of extra post meta to import
   *
   * @return void
   */
  private function get_post_meta_keys()
  {
    return ['event_id', 'custom_id', 'event_description', 'caption', 'thumbnail', 'audio', 'location', 'total_runtime'];
  }

  /**
   * Inserts an event into the database.
   *
   * This function first checks if the current user can publish posts.
   * If the user cannot publish posts, it returns an error.
   * It then assigns the necessary variables from the data and inserts a new post.
   * It fetches the taxonomies from the data and assigns them to the event.
   * It also fetches the categories from the data and assigns them to the event.
   * Finally, it assigns the custom meta data to the event.
   *
   * @param  array          $data The data to insert the event with.
   * @return WP_Error|array The error or the inserted data.
   */
  private function insert_events( $data )
  {
    // Validate user can publish posts
    if ( !current_user_can( 'publish_posts' ) )
      return new WP_Error( 'rest_cannot_publish_posts', __( 'Sorry, you are not allowed to publish posts as this user.' ), ['status' => rest_authorization_required_code()] );

    // Assign variables
    $post_id             = wp_insert_post( $data );
    $invnitus_event_id   = $this->maybe_get( $data, 'event_id', '' );
    $invintus_categories = $this->maybe_get( $data, 'categories', [] );

    // Store data in related table
    // $related_data = $this->update_related_table( $invnitus_event_id, $post_id );

    // Fetch taxonomies/keywords from data
    $taxonomies = $this->maybe_get( $data, 'taxonomies', [] );

    // Assign taxonomies/tags to event
    foreach ( $taxonomies as $key => $values ):
      wp_set_object_terms( $post_id, $values, $key );
    endforeach;

    // Fetch and update/create hierarchal categories from data
    $categories = $this->map_categories( $invintus_categories );

    // Assign hierarchal categories to event
    if ( $categories ):
      wp_set_object_terms( $post_id, $categories, 'invintus_category' );
    endif;

    // Assign event custom meta data
    foreach ( $this->get_post_meta_keys() as $key ):
      update_post_meta( $post_id, 'invintus_' . $key, $data[$key] );
    endforeach;

    return $data;
  }

  /**
   * Returns a new instance of the Invintus class.
   *
   * @return Invintus A new instance of the Invintus class.
   */
  private function invintus()
  {
    return new Invintus();
  }

  /**
   * Maps categories to a format that can be used to insert or update categories in the database.
   *
   * This function first checks if the categories parameter is an array.
   * If it is not an array, it returns.
   * It then initializes an array to hold the post categories.
   * It then loops through each category in the categories array.
   * For each category, it gets the category ID and name.
   * It then checks if a term exists with the same category ID.
   * If a term does not exist, it checks if a term exists with the same category name.
   * If a term exists, it updates the term and adds it to the post categories array.
   * If a term does not exist, it creates a new term and adds it to the post categories array.
   * Finally, it returns the post categories array.
   *
   * @param  array      $categories The categories to map.
   * @return array|null The mapped categories or null if the categories parameter is not an array.
   */
  private function map_categories( $categories )
  {
    if ( !$categories || !is_array( $categories ) ) return;

    $taxonomy        = 'invintus_category';
    $parent_id       = null;
    $post_categories = [];

    foreach ( $categories as $category ):

      // Get parent category first
      $invintus_category_id = $this->maybe_get( $category, 'categoryID', '' );
      $category_name        = $this->maybe_get( $category, 'categoryName', '' );

      $existing_term = $this->get_category_by_invintus_id( $invintus_category_id );

      // Check to see if term exists by category name
      // helps update previously existing terms
      if ( !$existing_term ):
        $existing_term_by_name                       = $this->get_category_by_name( $category_name );
        if ( $existing_term_by_name ) $existing_term = $existing_term_by_name;
      endif;

      if ( $existing_term ):
        $post_categories[] = $this->update_category( $category, $taxonomy, $existing_term );
      else:
        $post_categories[] = $this->create_category( $category, $taxonomy );
      endif;

    endforeach;

    return $post_categories;
  }

  /**
   * ANCHOR Map Invintus Data to WP
   * This will map the keys from Invintus to the proper
   * keys for WordPress
   *
   * @return void
   */
  private function map_data( $data )
  {
    $event = $this->maybe_get( $data, 'event' );

    if ( !$event ) return apply_filters( 'invintus/data/prepare', [], [] );

    $post_date  = esc_attr( $this->maybe_get( $event, 'startDateTime', '' ) );
    $is_private = (bool) $this->maybe_get( $event, 'private', '' );

    $now          = new DateTimeImmutable();
    $publish_date = new DateTimeImmutable( $post_date );

    $_event_id = esc_attr( $this->maybe_get( $event, 'eventID', '' ) );
    $_event_id = explode( '_', $_event_id );

    $event_id = end( $_event_id );

    $data = [
      'edit_date'         => true,
      'post_title'        => $this->prepare_title( $this->maybe_get( $event, 'title', '' ) ),
      'post_name'         => sanitize_title_with_dashes( $this->prepare_title( $this->maybe_get( $event, 'title', '' ) ) ) . '-' . $event_id,
      'post_date'         => $post_date,
      'post_status'       => $this->map_statuses( $this->maybe_get( $event, 'eventStatus', '' ) ),
      'post_content'      => $this->prepare_content( $event_id, $this->maybe_get( $event, 'description', '' ) ),
      'post_type'         => $this->invintus()->get_cpt_slug(),
      'event_id'          => esc_attr( $event_id ),
      'custom_id'         => esc_attr( $this->maybe_get( $event, 'customID', '' ) ),
      'event_description' => esc_attr( $this->maybe_get( $event, 'description', '' ) ),
      'caption'           => esc_url( $this->maybe_get( $event, 'captionPath', '' ) ),
      'thumbnail'         => esc_url( $this->maybe_get( $event, 'videoThumbnail', '' ) ),
      'audio'             => esc_url( $this->maybe_get( $event, 'publishedAudio', '' ) ),
      'location'          => esc_url( $this->maybe_get( $event, 'locationName', '' ) ),
      'total_runtime'     => esc_attr( $this->maybe_get( $event, 'totalRunTime', '' ) ),
      'taxonomies'        => [
        'invintus_tag' => $this->maybe_get( $event, 'keywords', [] ),
        // 'invintus_category' => isset( $event['categories'] ) ? $event['categories'] : []
      ],
      'categories' => $this->maybe_get( $event, 'categoryXtended', [] )
    ];

    // Override status if date is in the future
    if ( $publish_date > $now ) $data['post_status'] = 'future';

    // Override status if it's a private event
    if ( $is_private ) $data['post_status'] = 'private';

    return apply_filters( 'invintus/data/prepare', $data, $event );
  }

  /**
   * Convert Invintus' statuses into WP counterparts
   *
   * @param  mixed $status
   * @return void
   */
  private function map_statuses( $status )
  {
    $live_statuses    = apply_filters( 'invintus/statuses/live', ['live', 'onBreak', 'disconnected', 'break', 'on break'] );
    $publish_statuses = apply_filters( 'invintus/statuses/publish', ['published'] );
    $future_statuses  = apply_filters( 'invintus/statuses/future', ['new', 'available'] );

    if ( in_array( $status, $live_statuses ) ):

      return 'live';

    elseif ( in_array( $status, $future_statuses ) ):

      return 'future';

    elseif ( in_array( $status, $publish_statuses ) ):

      return 'publish';

    else:

      return 'draft';

    endif;
  }

  /**
   * Deletes log entries based on the 'invintus_log_retention' option.
   *
   * This method first checks if the 'can_log_payloads' option is true. If it's not, the method returns immediately.
   * Then, it retrieves the 'invintus_log_retention' option, which specifies the number of days to keep log entries.
   * If the 'invintus_log_retention' option is not set or is not a number, the method returns immediately.
   * Finally, it constructs a SQL query to delete log entries that are older than the 'invintus_log_retention' number of days and executes the query.
   */
  private function maybe_delete_log_entries()
  {
    global $wpdb;

    $can_log_payloads = $this->invintus()->get_option( 'can_log_payloads' );

    if ( !$can_log_payloads ) return;

    $table_name = $wpdb->prefix . 'invintus_logs';

    // Sanitize and validate the log retention value
    $invintus_log_retention = $this->invintus()->get_option( 'invintus_log_retention' );
    $invintus_log_retention = is_numeric( $invintus_log_retention ) ? intval( $invintus_log_retention ) : null;

    if ( !$invintus_log_retention ) return;

    $query = "DELETE FROM $table_name WHERE date < DATE_SUB( NOW(), INTERVAL $invintus_log_retention DAY )";

    $wpdb->query( $query );
  }

  /**
   * Retrieves a value from an array if it exists, otherwise returns a default value.
   *
   * @param  array $array   The array to search.
   * @param  mixed $key     The key to search for.
   * @param  mixed $default The default value to return if the key is not found.
   * @return mixed The value from the array if it exists, otherwise the default value.
   */
  private function maybe_get( $array = [], $key = 0, $default = null )
  {
    return $this->invintus()->maybe_get( $array, $key, $default );
  }

  /**
   * Converts common elements to WordPress, Gutenberg-ready tags
   *
   * @param  mixed $content
   * @return void
   */
  private function prepare_content( $event_id, $content )
  {
    $content = str_replace( ['<p', '</p>'], ["<!-- wp:paragraph {\"className\":\"invintus-content\"} -->\n<p class=\"invintus-content\"", "</p>\n<!-- /wp:paragraph -->"], $content ); // paragraphs
    $content = str_replace( ['<ul', '</ul>'], ["<!-- wp:list {\"className\":\"invintus-content\"} -->\n<ul class=\"invintus-content\"", "</ul>\n<!-- /wp:list -->"], $content ); // unordered lists
    $content = str_replace( ['<ol', '</ol>'], ["<!-- wp:list {\"className\":\"invintus-content\", \"ordered\":true} -->\n<ol class=\"invintus-content\"", "</ol>\n<!-- /wp:list -->"], $content ); // ordered lists

    $content = apply_filters( 'invintus/videos/content', $this->prepend_invintus_player( $event_id ) . $content );

    return $content;
  }

  /**
   * Try to convert and clean titles up a little
   *
   * @param  mixed $content
   * @return void
   */
  private function prepare_title( $content )
  {
    $content = iconv( 'UTF-8', 'ASCII//TRANSLIT', $content );

    return $content;
  }

  /**
   * Prepend the Invintus event block to the content
   *
   * @param  mixed $event_id
   * @return void
   */
  private function prepend_invintus_player( $event_id )
  {
    return sprintf( '<!-- wp:taproot/invintus {"invintus_event_id":"%s"} /-->%s', $event_id, "\n" );
  }

  /**
   * Creates a new instance of the Settings class.
   *
   * @return Settings A new Settings object.
   */
  private function settings()
  {
    return new Settings();
  }

  /**
   * ANCHOR Update Category
   *
   * @param  mixed $data
   * @param  mixed $taxonomy
   * @param  mixed $existing_term
   * @return void
   */
  private function update_category( $data, $taxonomy, $existing_term )
  {
    $parent_id = '';

    $existing_term_id = $this->maybe_get( $existing_term, 'term_id', '' );

    $category_id          = $this->maybe_get( $data, 'categoryID', '' );
    $category_name        = $this->maybe_get( $data, 'categoryName', '' );
    $category_description = $this->maybe_get( $data, 'categoryDescription', '' );

    $child_parent_id = $this->maybe_get( $data, 'childOf', '' );

    if ( $child_parent_id ):
      $parent_id = $this->maybe_get( $this->get_category_by_invintus_id( $child_parent_id ), 'term_id' );
    endif;

    $args = [
      'name'        => esc_html( $category_name ),
      'parent'      => $parent_id,
      'slug'        => sanitize_title( $category_name ),
      'description' => $category_description
    ];

    $term = wp_update_term( $existing_term_id, $taxonomy, $args );

    if ( is_wp_error( $term ) ) return;

    $term_id = $this->maybe_get( $term, 'term_id', '' );

    $term_field_id = sprintf( '%s_%d', $taxonomy, $term_id );

    update_term_meta( $term_id, 'invintus_category_id', $category_id );
    update_term_meta( $term_id, 'invintus_parent_category_id', $child_parent_id );

    return $term_id;
  }

  /**
   * Updates an event in the database.
   *
   * This function first assigns the necessary variables from the data.
   * It then checks if the current user can edit the post.
   * If the user cannot edit the post, it returns an error.
   * If the user can edit the post, it updates the post.
   * It then fetches the taxonomies from the data and assigns them to the event.
   * It also fetches the categories from the data and assigns them to the event.
   * Finally, it assigns the custom meta data to the event.
   *
   * @param  array          $data The data to update the event with.
   * @return WP_Error|array The error or the updated data.
   */
  private function update_events( $data )
  {
    // Assign variables
    $post_id             = $this->maybe_get( $data, 'ID', '' );
    $invnitus_event_id   = $this->maybe_get( $data, 'event_id', '' );
    $invintus_categories = $this->maybe_get( $data, 'categories', [] );

    // Validate user can edit current post
    if ( !current_user_can( 'edit_post', $post_id ) )
      return new WP_Error( 'rest_cannot_edit_post', __( 'Sorry, you are not allowed to edit this posts as this user.' ), ['status' => rest_authorization_required_code()] );

    // Store data in related table
    // $related_data = $this->update_related_table( $invnitus_event_id, $post_id );

    // Update post
    $post_id = wp_update_post( $data );

    // Fetch taxonomies/keywords from data
    $taxonomies = $this->maybe_get( $data, 'taxonomies', [] );

    // Assign taxonomies/tags to event
    if ( $taxonomies ):
      foreach ( $taxonomies as $key => $values ):
        wp_set_object_terms( $post_id, $values, $key );
      endforeach;
    endif;

    // Fetch and update/create hierarchal categories from data
    $categories = $this->map_categories( $invintus_categories );

    // Assign hierarchal categories to event
    if ( $categories ):
      wp_set_object_terms( $post_id, $categories, 'invintus_category' );
    endif;

    // Assign event custom meta data
    foreach ( $this->get_post_meta_keys() as $key ):
      update_post_meta( $post_id, 'invintus_' . $key, $data[$key] );
    endforeach;

    return $data;
  }

  /**
   * Updates or inserts data into a related table in the database.
   *
   * This function first gets the table name for the related events.
   * It then checks if the event already exists in the table.
   * If the event exists, it updates the event with the new post ID.
   * If the event does not exist, it inserts a new row with the event ID and post ID.
   *
   * @param  int       $event_id The ID of the event.
   * @param  int       $post_id  The ID of the post.
   * @return int|false The number of rows affected or false on error.
   */
  private function update_related_table( $event_id, $post_id )
  {
    global $wpdb;

    $table_name = $wpdb->prefix . 'invintus_events_relationships';

    $query        = "SELECT COUNT(*) FROM $table_name WHERE event_id = $event_id";
    $event_exists = $wpdb->get_var( $query );

    if ( $event_exists ):
      $rows = $wpdb->update(
        $table_name,
        ['event_id' => $event_id, 'post_id' => $post_id],
        ['event_id' => $event_id],
      );
    else:
      $rows = $wpdb->insert(
        $table_name,
        ['event_id' => $event_id, 'post_id' => $post_id],
      );
    endif;

    return $rows;
  }

  /**
   * Updates or inserts data into the database.
   *
   * This function first maps the response data to a format that can be inserted into the database.
   * It then checks if the event already exists in the database.
   * If the event is private, it deletes the event from the database and returns a success message.
   * If the event is not private, it checks if the event already exists.
   * If the event exists, it updates the event.
   * If the event does not exist, it inserts the event.
   *
   * @param  array $response The response data.
   * @return array The result of the upsert operation.
   */
  private function upsert_data( $response )
  {
    $data   = $this->map_data( $response );
    $events = $this->event_exists( $data );

    // Allow developers to modify or extend the data before it's saved
    $data = apply_filters( 'invintus/data/before_save', $data, $events );

    // handle "private" separate from other crud events
    // TODO create a dev hook to toggle private video visibility
    if ( 'private' == $data['post_status'] ):

      // If events exist permanently delete them from WP, bypassing trash
      if ( $events ):

        $response = [];

        // remove events by ID
        foreach ( $events as $post_id ):
          $response[] = wp_delete_post( $post_id );
        endforeach;

        return wp_send_json_success( ['message' => 'Private: Events were deleted.', 'events' => $response] );

      endif;

      wp_send_json_success( ['message' => 'Private: No action was taken.', 'events' => []] );
    endif;

    // If event exists update, if it doesn't then insert
    if ( $events ):
      $result = $this->update_events( array_merge( $data, ['ID' => $events[0]] ) );
      // Fire after_save hook with the updated data, post ID, and operation type
      do_action( 'invintus/data/after_save', $result, $events[0], 'update' );

      return $result;
    else:
      $result = $this->insert_events( $data );
      // Get the post ID from the result (it's stored in post meta)
      $post_id = $this->event_exists( $result )[0] ?? null;
      if ( $post_id ) {
        // Fire after_save hook with the inserted data, new post ID, and operation type
        do_action( 'invintus/data/after_save', $result, $post_id, 'insert' );
      }

      return $result;
    endif;
  }
}
