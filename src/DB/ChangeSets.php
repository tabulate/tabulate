<?php

namespace WordPress\Tabulate\DB;

use WordPress\Tabulate\DB\Database;
use WordPress\Tabulate\DB\Table;
use WordPress\Tabulate\DB\Record;

class ChangeSets {

	private static $current_changeset = false;
	private static $current_changeset_comment = null;
	private static $old_record = false;

	/**
	 * Get a list of the names used by the change-tracking subsystem.
	 * @global wpdb $wpdb
	 * @return array|string
	 */
	public static function table_names() {
		global $wpdb;
		return array(
			$wpdb->prefix . 'changesets',
			$wpdb->prefix . 'changes',
		);
	}

	public static function before_validate( Table $table, $data, $pk_value ) {
		self::$current_changeset_comment = isset( $data['changeset_comment'] ) ? $data['changeset_comment'] : null;
	}

	public static function before_save( Table $table, $data, $pk_value ) {
		global $wpdb, $current_user;

		// Don't save changes to the changes tables.
		if ( in_array( $table->get_name(), self::table_names() ) ) {
			return false;
		}

		// Open a changeset if required.
		if ( ! self::$current_changeset ) {
			$changesets_name = $wpdb->prefix . 'changesets';
			$changesets = $table->get_database()->get_table( $changesets_name );
			$data = array(
				'date_and_time' => date( 'Y-m-d H:i:s' ),
				'user_id' => $current_user->ID,
				'comment' => self::$current_changeset_comment,
			);
			self::$current_changeset = $changesets->save_record( $data );
		}

		// Get the current (i.e. soon-to-be-old) data for later use.
		self::$old_record = $table->get_record( $pk_value );
	}

	public static function after_save( Table $table, Record $new_record ) {
		global $wpdb;

		// Don't save changes to the changes tables.
		if ( in_array( $table->get_name(), self::table_names() ) ) {
			return false;
		}

		$changes_name = $wpdb->prefix . 'changes';
		$changes_table = $table->get_database()->get_table( $changes_name );
		foreach ( $table->get_columns() as $column ) {
			$col_name = $column->get_name();
			$old_val = ( is_callable( [ self::$old_record, $col_name ] ) ) ? self::$old_record->$col_name() : null;
			$new_val = $new_record->$col_name();
			if ($new_val == $old_val ) {
				// Ignore unchanged columns.
				continue;
			}
			$data = array(
				'changeset_id' => self::$current_changeset->id(),
				'change_type' => 'field',
				'table_name' => $table->get_name(),
				'column_name' => $col_name,
				'record_ident' => $new_record->get_primary_key(),
				'old_value' => $old_val,
				'new_value' => $new_val,
			);
			$changes_table->save_record( $data );
		}
		// Close this changeset.
		self::$current_changeset = false;
		self::$current_changeset_comment = null;
	}

	public static function activate() {
		global $wpdb;
		$db = new Database( $wpdb );
		$prefix = $wpdb->prefix;
		if ( ! $db->get_table( "{$prefix}changesets" ) ) {
			$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}changesets` (
			`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`date_and_time` DATETIME NOT NULL,
			`user_id` BIGINT(20) UNSIGNED NOT NULL,
			FOREIGN KEY (`user_id`) REFERENCES `{$prefix}users` (`ID`),
			`comment` TEXT NULL DEFAULT NULL
			);";
			$wpdb->query( $sql );
		}
		if ( ! $db->get_table( "{$prefix}changes" ) ) {
			$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}changes` (
			`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`changeset_id` INT(10) UNSIGNED NOT NULL,
			FOREIGN KEY (`changeset_id`) REFERENCES `{$prefix}changesets` (`id`),
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

}
