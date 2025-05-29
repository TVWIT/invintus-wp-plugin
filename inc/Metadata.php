<?php

/**
 * This class is responsible for handling the metadata of the custom post type.
 *
 * @package InvintusPlugin
 */

namespace Taproot\Invintus;

class Metadata
{
  /**
   * The sidebar assets.
   *
   * This property is used to store the sidebar assets for the plugin.
   *
   * @var mixed
   */
  private $sidebar_assets;

  /**
   * The sidebar assets data.
   *
   * This property is used to store the data for the sidebar assets, such as dependencies and version.
   *
   * @var array
   */
  private $sidebar_assets_data;

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

    $this->sidebar_assets = sprintf( '%sbuild/sidebar.asset.php', INVINTUS_PLUGIN_PATH );

    if ( file_exists( $this->sidebar_assets ) ):
      $this->sidebar_assets_data = require_once $this->sidebar_assets;
    else:
      $this->sidebar_assets_data = [
        'dependencies' => [],
        'version'      => filemtime( sprintf( '%sbuild/sidebar.js', INVINTUS_PLUGIN_PATH ) )
      ];
    endif;
  }

  /**
   * Registers the meta boxes for the custom post type.
   *
   * The 'show_in_rest' property is set to true so that the meta boxes are included in the REST API responses.
   * The 'single' property is set to true so that a single value is returned for the meta boxes.
   * The 'type' property is set to 'string' so that the meta boxes are treated as strings.
   */
  public function meta_boxes()
  {
    $meta_fields = [
      'invintus_event_id',
      'invintus_custom_id',
      'invintus_event_description',
      'invintus_caption',
      'invintus_audio',
      'invintus_location',
      'invintus_total_runtime',
      'invintus_thumbnail',
    ];

    foreach ( $meta_fields as $meta_field ):
      register_post_meta( $this->invintus()->get_cpt_slug(), $meta_field, [
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
      ] );
    endforeach;
  }

  /**
   * Enqueues the scripts and styles for the sidebar.
   *
   * This method is called in the 'setup' method.
   * It enqueues the 'taproot-invintus-sidebar' script and style, which are located in the 'build' directory of the plugin.
   * The script's dependencies and version are retrieved from the 'sidebar_assets_data' property.
   */
  public function sidebar_scripts()
  {
    $screen = get_current_screen();

    if ( !$screen || $screen->post_type !== $this->invintus()->get_cpt_slug() )
    return;

    wp_enqueue_script(
      'taproot-invintus-sidebar',
      sprintf( '%sbuild/sidebar.js', INVINTUS_PLUGIN_URL ),
      $this->sidebar_assets_data['dependencies'],
      $this->sidebar_assets_data['version'],
    );

    wp_enqueue_style(
      'taproot-invintus-sidebar',
      sprintf( '%sbuild/sidebar.css', INVINTUS_PLUGIN_URL ),
      [],
      $this->sidebar_assets_data['version']
    );
  }

  /**
   * Adds action hooks.
   *
   * This method is called in the 'setup' method.
   */
  private function actions()
  {
    add_action( 'init', [$this, 'meta_boxes'] );
    add_action( 'enqueue_block_editor_assets', [$this, 'sidebar_scripts'] );
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
   * Returns a new instance of the Invintus class.
   *
   * @return Invintus A new instance of the Invintus class.
   */
  private function invintus()
  {
    return Invintus::init();
  }
}