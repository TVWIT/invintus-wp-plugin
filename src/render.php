<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
  <div class="invintus-player" data-eventid="<?php echo esc_attr( $attributes['invintus_event_id'] ); ?>"
    data-simple="<?php echo esc_attr( $attributes['invintus_event_is_simple'] ); ?>"
    data-playerid="<?php echo esc_attr( $attributes['invintus_player_pref_id'] ); ?>"></div>
</div>
