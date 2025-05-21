<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

// Get the default player preference ID from settings if none is set in block
$settings       = get_option( 'invintus_video_settings', [] );
$player_pref_id = !empty( $attributes['invintus_player_pref_id'] ) ? $attributes['invintus_player_pref_id'] : ( $settings['invintus_player_preference_default'] ?? '' );
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
  <div class="invintus-player" data-eventid="<?php echo esc_attr( $attributes['invintus_event_id'] ); ?>"
    data-simple="<?php echo esc_attr( $attributes['invintus_event_is_simple'] ); ?>"
    data-playerid="<?php echo esc_attr( $player_pref_id ); ?>"></div>
</div>