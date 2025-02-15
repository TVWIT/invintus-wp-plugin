<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
  <div class="invintus-player" data-eventid="<?php esc_attr_e( $attributes['invintus_event_id'] ); ?>"
    data-simple="<?php esc_attr_e( $attributes['invintus_event_is_simple'] ); ?>"></div>
</div>
