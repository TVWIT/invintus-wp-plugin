<?php

namespace Taproot\Invintus;

use WP_Block_Type_Registry;

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
    register_block_type( sprintf( '%sbuild', INVINTUS_PLUGIN_PATH ), [
      'render_callback' => [$this, 'render'],
    ] );

    // Register the legacy block type if it's not already registered.
    if ( !WP_Block_Type_Registry::get_instance()->is_registered( 'acf/invintus-event' ) ):
      register_block_type( 'acf/invintus-event', [
        'render_callback' => [$this, 'render_legacy'],
      ] );
    endif;
  }

  /**
   * Renders the block.
   *
   * @param  array  $attributes The attributes of the block.
   * @param  string $content    The content of the block.
   * @return string The rendered block.
   */
  public function render( $attributes, $content )
  {
    return $this->render_block_html( $attributes );
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
      'wpClassName'              => $attributes['wpClassName']                      ?? '',
    ];

    return $this->render_block_html( $legacyAttributes );
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
   * Helper method to render the common HTML structure of the block.
   *
   * This method first checks if the script has been localized using the 'script_localized' flag.
   * If the script has not been localized, it enqueues the necessary scripts and localizes the script with the necessary configurations.
   * The 'script_localized' flag is then set to true to prevent the scripts from being enqueued and localized multiple times.
   *
   * After that, it returns the HTML structure for the block with the appropriate classes and data attributes.
   *
   * @param  array  $attributes The attributes of the block.
   * @return string The HTML output for the block.
   */
  private function render_block_html( $attributes )
  {
    if ( !self::$script_localized ):
      wp_enqueue_script( 'invintus-player-script', $this->invintus()->get_invintus_script_url(), [], null, true );

      wp_localize_script( 'invintus-player-script', 'invintusConfig', [
        'clientId' => $this->client_id,
      ] );

      self::$script_localized = true;
    endif;

    return sprintf(
      '<div %s><div class="invintus-player" data-eventid="%s" data-simple="%s"></div></div>',
      get_block_wrapper_attributes( apply_filters( 'invintus/block/attributes', [] ) ),
      esc_attr( $attributes['invintus_event_id'] ?? '' ),
      esc_attr( $attributes['invintus_event_is_simple'] ?? false )
    );
  }
}
