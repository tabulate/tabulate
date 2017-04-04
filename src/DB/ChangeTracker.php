<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

namespace WordPress\Tabulate\DB;

use WordPress\Tabulate\DB\Database;
use WordPress\Tabulate\DB\Table;
use WordPress\Tabulate\DB\Record;

/**
 * The Change Tracker keeps a log of every data modification made through Tabulate.
 */
class ChangeTracker {

	/**
	 * The global wpdb object.
	 *
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * The ID of the currently-open changeset.
	 *
	 * @var integer
	 */
	private static $current_changeset_id = false;

	/**
	 * The user comment on the currently-open changeset.
	 *
	 * @var string
	 */
	private $current_changeset_comment = null;

	/**
	 * The record prior to modification.
	 *
	 * @var \WordPress\Tabulate\DB\Record|false
	 */
	private $old_record = false;

	/**
	 * Whether the changeset should be closed after the first after_save() call.
	 *
	 * @var boolean
	 */
	private static $keep_changeset_open = false;

	/**
	 * Create a new change tracker.
	 *
	 * @param \wpdb  $wpdb The global wpdb object.
	 * @param string $comment The user's comment about the change.
	 */
	public function __construct( $wpdb, $comment = null ) {
		$this->wpdb = $wpdb;
		$this->current_changeset_comment = $comment;
	}

	/**
	 * When destroying a ChangeTracker object, close the current changeset
	 * unless it has specifically been requested to be kept open.
	 */
	public function __destruct() {
		if ( ! self::$keep_changeset_open ) {
			$this->close_changeset();
		}
	}

	/**
	 * Open a new changeset. If one is already open, this does nothing.
	 *
	 * @global \WP_User $current_user
	 * @param string  $comment The user's comment on the changeset.
	 * @param boolean $keep_open Whether the changeset should be kept open (and manually closed) after after_save() is called.
	 * @throws Exception If the changeset row could not be saved.
	 */
	public function open_changeset( $comment, $keep_open = null ) {
		global $current_user;
		if ( ! is_null( $keep_open ) ) {
			self::$keep_changeset_open = $keep_open;
		}
		if ( ! self::$current_changeset_id ) {
			$data = array(
				'date_and_time' => date( 'Y-m-d H:i:s' ),
				'user_id' => $current_user->ID,
				'comment' => $comment,
			);
			$ret = $this->wpdb->insert( self::changesets_name(), $data );
			if ( false === $ret ) {
				throw new Exception( $this->wpdb->last_error . ' -- Unable to open changeset' );
			}
			self::$current_changeset_id = $this->wpdb->insert_id;
		}
	}

	/**
	 * Close the current changeset.
	 *
	 * @return void
	 */
	public function close_changeset() {
		self::$current_changeset_id = false;
		$this->current_changeset_comment = null;
	}

	/**
	 * This method is called prior to a record being saved, and will open a new
	 * changeset if required, and save the old record for later use.
	 *
	 * @param Table  $table The table into which the record is being saved.
	 * @param string $pk_value The primary key of the record being saved. May be null.
	 * @return boolean
	 */
	public function before_save( Table $table, $pk_value ) {
		// Don't save changes to the changes tables.
		if ( in_array( $table->get_name(), $this->table_names(), true ) ) {
			return false;
		}

		// Open a changeset if required.
		$this->open_changeset( $this->current_changeset_comment );

		// Get the current (i.e. soon-to-be-old) data for later use.
		$this->old_record = $table->get_record( $pk_value );
	}

	/**
	 * This method is called after a record has been saved, and is responsible
	 * for creating the actual change-tracking rows in the database.
	 *
	 * @param Table  $table The table the record is being saved in.
	 * @param Record $new_record The record, after being saved.
	 * @return boolean
	 */
	public function after_save( Table $table, Record $new_record ) {
		// Don't save changes to the changes tables.
		if ( in_array( $table->get_name(), self::table_names(), true ) ) {
			return false;
		}

		// Save a change for each changed column.
		foreach ( $table->get_columns() as $column ) {
			$col_name = ( $column->is_foreign_key() ) ? $column->get_name() . Record::FKTITLE : $column->get_name();
			$old_val = ( is_callable( array( $this->old_record, $col_name ) ) ) ? $this->old_record->$col_name() : null;
			$new_val = $new_record->$col_name();
			if ( $new_val === $old_val ) {
				// Ignore unchanged columns.
				continue;
			}
			$data = array(
				'changeset_id' => self::$current_changeset_id,
				'change_type' => 'field',
				'table_name' => $table->get_name(),
				'column_name' => $column->get_name(),
				'record_ident' => $new_record->get_primary_key(),
			);
			// Daft workaround for https://core.trac.wordpress.org/ticket/15158 .
			if ( ! is_null( $old_val ) ) {
				$data['old_value'] = $old_val;
			}
			if ( ! is_null( $new_val ) ) {
				$data['new_value'] = $new_val;
			}
			// Save the change record.
			$this->wpdb->insert( $this->changes_name(), $data );
		}

		// Close the changeset if required.
		if ( ! self::$keep_changeset_open ) {
			$this->close_changeset();
		}
	}

	/**
	 * On plugin activation, create two new database tables.
	 *
	 * @param \wpdb $wpdb The global database object.
	 */
	public static function activate( \wpdb $wpdb ) {
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

	/**
	 * Get the name of the changesets table.
	 *
	 * @global \WordPress\Tabulate\DB\wpdb $wpdb
	 * @return string
	 */
	public static function changesets_name() {
		global $wpdb;
		return $wpdb->prefix . TABULATE_SLUG . '_changesets';
	}

	/**
	 * Get the name of the changes table.
	 *
	 * @global \WordPress\Tabulate\DB\wpdb $wpdb
	 * @return string
	 */
	public static function changes_name() {
		global $wpdb;
		return $wpdb->prefix . TABULATE_SLUG . '_changes';
	}

	/**
	 * Get a list of the names used by the change-tracking subsystem.
	 *
	 * @global wpdb $wpdb
	 * @return array|string
	 */
	public static function table_names() {
		return array( self::changesets_name(), self::changes_name() );
	}
}
