<?php
  // Exit if accessed directly
  if ( ! defined( 'ABSPATH' ) ) exit;

  $uri = parse_url( add_query_arg( [] ) );

  wp_parse_str( $uri['query'], $params );

  $event_id_param = apply_filters( 'invintus/events/watch/event_id', 'eventID' );

  if ( empty( $params[$event_id_param] ) ):
    wp_safe_redirect( get_home_url() );
    exit;
  endif;

  $event_id = ( int ) $params[$event_id_param];

  unset( $params['clientID'] );

  $can_public_future_events = get_option( 'can_public_future_events' );

  $post_statuses = ['publish', 'live'];

  if ( $can_public_future_events ) $post_statuses[] = 'future';

  $_args = array(
    'post_type'      => 'invintus_video',
    'posts_per_page' => 1,
    'post_status'    => 'any',
    'no_found_rows'  => true,
    'fields'         => 'ids',
    'meta_query'     => array(
      [
        'key'   => 'invintus_event_id',
        'value' => $event_id
      ]
    )
  );

  $args = apply_filters( 'invintus/events/watch/redirect', $_args );

  $query = new WP_Query( $args );

  if ( !$query->have_posts() ):
    wp_safe_redirect( get_home_url() );
    exit;
  endif;

  while ( $query->have_posts() ):
    $query->the_post();

    $post_id     = get_the_ID();
    $post_status = get_post_status( $post_id );

    if ( !in_array( $post_status, $post_statuses ) ):
      wp_safe_redirect( get_home_url() );
      exit;
    endif;

    $redirect_uri = add_query_arg(
      $params,
      get_permalink( $post_id )
    );

    wp_safe_redirect( $redirect_uri );
    exit;
  endwhile;
