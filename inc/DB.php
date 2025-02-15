<?php

/**
 * This class is responsible for handling the metadata of the custom post type.
 *
 * @package InvintusPlugin
 */

namespace Taproot\Invintus;

class DB
{
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
   * Creates the necessary tables.
   *
   * This method is responsible for creating the necessary tables in the database.
   * It uses the WordPress dbDelta function to create the tables if they don't exist.
   */
  public function create_tables()
  {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta( $this->create_log_table() );
  }

  /**
   * Adds action hooks.
   *
   * This method is called in the 'setup' method.
   */
  private function actions()
  {
  }

  /**
   * Creates the log table.
   *
   * This method is responsible for creating the SQL query to create the log table.
   * The log table includes columns for id, event_id, action, payload, and date.
   *
   * @return string The SQL query to create the log table.
   */
  private function create_log_table()
  {
    global $wpdb;

    $collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'invintus_logs';

    $table = "
    CREATE TABLE {$table_name} (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      event_id bigint(20) unsigned NOT NULL,
      action varchar(20) NOT NULL DEFAULT '',
      payload longtext NOT NULL,
      date datetime NOT NULL,
      PRIMARY KEY (id)
    ) $collate;
    ";

    return $table;
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
