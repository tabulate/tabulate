<?php

namespace WordPress\Tabulate\DB;

use WordPress\Tabulate\DB\Database;
use WordPress\Tabulate\DB\Table;
use WordPress\Tabulate\DB\Record;

class ChangeTracker {

	private $current_changeset_id = false;
	private $current_changeset_comment = null;
	private $old_record = false;

	public function __construct( $wpdb, $comment = null ) {
		$this->wpdb = $wpdb;
		$this->current_changeset_comment = $comment;
	}

	public function before_save( Table $table, $data, $pk_value ) {
		global $current_user;

		// Don't save changes to the changes tables.
		if ( in_array( $table->get_name(), $this->table_names() ) ) {
			return false;
		}

		// Open a changeset if required.
		if ( ! $this->current_changeset_id ) {
			$data = array(
				'date_and_time' => date( 'Y-m-d H:i:s' ),
				'user_id' => $current_user->ID,
				'comment' => $this->current_changeset_comment,
			);
			$this->wpdb->insert( $this->changesets_name(), $data );
			$this->current_changeset_id = $this->wpdb->insert_id;
		}

		// Get the current (i.e. soon-to-be-old) data for later use.
		$this->old_record = $table->get_record( $pk_value );
	}

	public function after_save( Table $table, Record $new_record ) {
		// Don't save changes to the changes tables.
		if ( in_array( $table->get_name(), self::table_names() ) ) {
			return false;
		}

		// Save a change for each changed column.
		foreach ( $table->get_columns() as $column ) {
			$col_name = $column->get_name();
			$old_val = ( is_callable( array( $this->old_record, $col_name ) ) ) ? $this->old_record->$col_name() : null;
			$new_val = $new_record->$col_name();
			if ($new_val == $old_val ) {
				// Ignore unchanged columns.
				continue;
			}
			$data = array(
				'changeset_id' => $this->current_changeset_id,
				'change_type' => 'field',
				'table_name' => $table->get_name(),
				'column_name' => $col_name,
				'record_ident' => $new_record->get_primary_key(),
			);
			// Daft workaround for https://core.trac.wordpress.org/ticket/15158
			if ( ! is_null( $old_val ) ) {
				$data[ 'old_value' ] = $old_val;
			}
			if ( ! is_null( $new_val ) ) {
				$data[ 'new_value' ] = $new_val;
			}
			// Save the change record.
			$this->wpdb->insert( $this->changes_name(), $data );
		}
		// Close this changeset.
		$this->current_changeset_id = false;
		$this->current_changeset_comment = null;
	}

	public static function activate() {
		global $wpdb;
		$db = new Database( $wpdb );
		if ( ! $db->get_table( self::changesets_name() ) ) {
			$sql = "CREATE TABLE IF NOT EXISTS `" . self::changesets_name() . "` (
			`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`date_and_time` DATETIME NOT NULL,
			`user_id` BIGINT(20) UNSIGNED NOT NULL,
			FOREIGN KEY (`user_id`) REFERENCES `{$wpdb->prefix}users` (`ID`),
			`comment` TEXT NULL DEFAULT NULL
			);";
			$wpdb->query( $sql );
		}
		if ( ! $db->get_table( self::changes_name() ) ) {
			$sql = "CREATE TABLE IF NOT EXISTS `" . self::changes_name() . "` (
			`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`changeset_id` INT(10) UNSIGNED NOT NULL,
			FOREIGN KEY (`changeset_id`) REFERENCES `" . self::changesets_name() . "` (`id`),
			`change_type` ENUM('field', 'file', 'foreign_key') NOT NULL DEFAULT 'field',
			`table_name` TEXT(65) NOT NULL,
			`record_ident` TEXT(65) NOT NULL,
			`column_name` TEXT(65) NOT NULL,
			`old_value` LONGTEXT NULL DEFAULT NULL,
			`new_value` LONGTEXT NULL DEFAULT NULL
			);";
			$wpdb->query( $sql );
		}
	}

	public static function changesets_name() {
		global $wpdb;
		return $wpdb->prefix . TABULATE_SLUG . '_changesets';
	}

	public static function changes_name() {
		global $wpdb;
		return $wpdb->prefix . TABULATE_SLUG . '_changes';
	}

	/**
	 * Get a list of the names used by the change-tracking subsystem.
	 * @global wpdb $wpdb
	 * @return array|string
	 */
	public static function table_names() {
		return array( self::changesets_name(), self::changes_name() );
	}

}
