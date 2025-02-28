<?php

namespace Taproot\Invintus;

use WP_Block_Type_Registry;
use Taproot\Invintus\Settings;

class Block
{
  /**
   * The client ID.
   *
   * @var string
   */
  private $client_id;

  /**
   * A flag to check if the script has been localized.
   *
   * @var bool
   */
  private static $script_localized = false;

  /**
   * Constructor.
   *
   * @param string $client_id The client ID.
   */
  public function __construct( $client_id )
  {
    $this->client_id = $client_id;
  }

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
  }

  /**
   * Initializes the block.
   *
   * This method is responsible for registering the block types.
   * It registers the main block type and the legacy block type if it's not already registered.
   */
  public function block_init()
  {
    // Register the main block type.
    register_block_type( sprintf( '%sbuild', INVINTUS_PLUGIN_PATH ) );

    // Register the legacy block type if it's not already registered.
    if ( !WP_Block_Type_Registry::get_instance()->is_registered( 'acf/invintus-event' ) ):
      register_block_type( 'acf/invintus-event', [
        'render_callback' => [$this, 'render_legacy'],
      ] );
    endif;
  }

  /**
   * Enqueues the necessary scripts for the block.
   */
  public function enqueue_block_scripts()
  {
    $settings = new Settings();

    if ( !self::$script_localized && has_block( 'taproot/invintus' ) ):
      wp_enqueue_script( 'invintus-player-script', $this->invintus()->get_invintus_script_url(), [], null, true );

      wp_localize_script( 'invintus-player-script', 'invintusConfig', [
        'clientId'     => $this->client_id,
        'playerPrefID' => $settings->get_option( 'invintus_player_preference_default' ) ?? '',
      ] );

      self::$script_localized = true;
    endif;
  }

  /**
   * Renders the legacy version of the block.
   *
   * @param  array  $attributes The attributes of the block.
   * @param  string $content    The content of the block.
   * @return string The rendered block.
   */
  public function render_legacy( $attributes, $content )
  {
    wp_enqueue_script( 'taproot-invintus-view-script' );

    // Ensure attributes are formatted correctly for legacy blocks
    $legacyAttributes = [
      'invintus_event_id'        => $attributes['data']['invintus_event_id']        ?? '',
      'invintus_event_is_simple' => $attributes['data']['invintus_event_is_simple'] ?? false,
      'invintus_player_pref_id'  => '',  // Legacy blocks don't support player preferences
    ];

    // Get the default player preference
    $settings     = new Settings();
    $playerPrefId = $settings->get_option( 'invintus_player_preference_default' ) ?? '';

    return sprintf(
      '<div %s><div class="invintus-player" data-eventid="%s" data-simple="%s" data-playerid="%s"></div></div>',
      get_block_wrapper_attributes( apply_filters( 'invintus/block/attributes', [] ) ),
      esc_attr( $legacyAttributes['invintus_event_id'] ),
      esc_attr( $legacyAttributes['invintus_event_is_simple'] ),
      esc_attr( $playerPrefId )
    );
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
    add_action( 'init', [$this, 'block_init'] );
    add_action( 'wp_enqueue_scripts', [$this, 'enqueue_block_scripts'] );
  }

  /**
   * Adds filter hooks.
   *
   * This method is called in the 'setup' method.
   */
  private function filters()
  {
  }
}
