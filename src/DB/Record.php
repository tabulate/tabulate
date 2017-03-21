<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

namespace WordPress\Tabulate\DB;

/**
 * A record is a single row from a database table.
 */
class Record {

	/**
	 * The table that this record belongs to.
	 *
	 * @var Table
	 */
	protected $table;

	/**
	 * The raw data of this database row.
	 *
	 * @var \stdClass
	 */
	protected $data;

	/**
	 * The suffix that is added to foreign keys when we want to get their
	 * 'title' value instead of their raw integer (or whatever) form.
	 */
	const FKTITLE = 'FKTITLE';

	/**
	 * Create a new Record object.
	 *
	 * @param Table $table The table object.
	 * @param array $data The data of this record.
	 */
	public function __construct( $table, $data = array() ) {
		$this->table = $table;
		$this->data = (object) $data;
	}

	/**
	 * Magic method to set one item in the data object.
	 *
	 * @param string $name The name of the column.
	 * @param mixed  $value The value to set.
	 */
	public function __set( $name, $value ) {
		$this->data->{$name} = $value;
	}

	/**
	 * Set multiple columns' values.
	 *
	 * @param mixed[] $data An array of column names to data values.
	 */
	public function set_multiple( $data ) {
		if ( ! is_array( $data ) ) {
			return;
		}
		foreach ( $data as $col => $datum ) {
			$this->$col = $datum;
		}
	}

	/**
	 * Get a column's value. If suffixed with 'FKTITLE', then get the title of
	 * the foreign record (where applicable).
	 *
	 * @param string   $name The column name.
	 * @param mixed [] $args Parameter not used.
	 * @return string|boolean
	 * @throws Exception If any arguments are passed (as there should never be any).
	 */
	public function __call( $name, $args ) {
		if ( ! empty( $args ) ) {
			throw new Exception( 'Record::colname() functions take no arguments.' );
		}

		// Foreign key 'title' values.
		$use_title = substr( $name, -strlen( self::FKTITLE ) ) === self::FKTITLE;
		if ( $use_title ) {
			$name = substr( $name, 0, -strlen( self::FKTITLE ) );
			$col = $this->get_col( $name );
			if ( $col->is_foreign_key() && ! empty( $this->data->$name ) ) {
				$referenced_table = $col->get_referenced_table();
				$fk_record = $referenced_table->get_record( $this->data->$name );
				$fk_title_col = $referenced_table->get_title_column();
				$fk_title_col_name = $fk_title_col->get_name();
				if ( $fk_title_col->is_foreign_key() ) {
					// Use title if the FK's title column is also an FK.
					$fk_title_col_name .= self::FKTITLE;
				}
				return $fk_record->$fk_title_col_name();
			}
		}
		$col = $this->get_col( $name );

		// Booleans.
		if ( $col->is_boolean() ) {
			// Numbers are fetched from the DB as strings.
			if ( '1' === $this->data->$name ) {
				return true;
			} elseif ( '0' === $this->data->$name ) {
				return false;
			} else {
				return null;
			}
		}

		// Standard column values.
		if ( isset( $this->data->$name ) ) {
			return $this->data->$name;
		}
	}

	/**
	 * Get a column of this record's table, optionally throwing an Exception if
	 * it doesn't exist.
	 *
	 * @param string  $name The name of the column.
	 * @param boolean $required True if this should throw an Exception.
	 * @return \WordPress\Tabulate\DB\Column The column.
	 * @throws \Exception If the column named doesn't exist.
	 */
	protected function get_col( $name, $required = true ) {
		$col = $this->table->get_column( $name );
		if ( $required && false === $col ) {
			throw new \Exception( "Unable to get column $name on table " . $this->table->get_name() );
		}
		return $col;
	}

	/**
	 * Get a string representation of this record.
	 *
	 * @return string
	 */
	public function __toString() {
		return join( ', ', $this->data );
	}

	/**
	 * Get the value of this record's primary key, or false if it doesn't have
	 * one.
	 *
	 * @return string|false
	 */
	public function get_primary_key() {
		if ( $this->table->get_pk_column() ) {
			$pk_col_name = $this->table->get_pk_column()->get_name();
			if ( isset( $this->data->$pk_col_name ) ) {
				return $this->data->$pk_col_name;
			}
		}
		return false;
	}

	/**
	 * Get the value of this Record's title column.
	 *
	 * @return string
	 */
	public function get_title() {
		$title_col = $this->table->get_title_column();
		if ( $title_col !== $this->table->get_pk_column() ) {
			$title_col_name = $title_col->get_name();
			return $this->data->$title_col_name;
		} else {
			$title_parts = array();
			foreach ( $this->table->get_columns() as $col ) {
				$col_name = $col->get_name() . self::FKTITLE;
				$title_parts[] = $this->$col_name();
			}
			return '[ ' . join( ' | ', $title_parts ) . ' ]';
		}
	}

	/**
	 * Get the record that is referenced by this one from the column given.
	 *
	 * @param string $column_name The name of the column.
	 * @return boolean|\WordPress\Tabulate\DB\Record
	 */
	public function get_referenced_record( $column_name ) {
		if ( ! isset( $this->data->$column_name ) ) {
			return false;
		}
		return $this->table
			->get_column( $column_name )
			->get_referenced_table()
			->get_record( $this->data->$column_name );
	}

	/**
	 * Get a list of records that reference this record in one of their columns.
	 *
	 * @param string|\WordPress\Tabulate\DB\Table  $foreign_table The foreign table.
	 * @param string|\WordPress\Tabulate\DB\Column $foreign_column The column in the foreign table that references this record's table.
	 * @param boolean                              $with_pagination Whether to only return the top N records.
	 * @return \WordPress\Tabulate\DB\Record[]
	 */
	public function get_referencing_records( $foreign_table, $foreign_column, $with_pagination = true ) {
		$foreign_table->reset_filters();
		$foreign_table->add_filter( $foreign_column, '=', $this->get_primary_key(), true );
		return $foreign_table->get_records( $with_pagination );
	}

	/**
	 * Get an Admin Area URL.
	 *
	 * @param string   $action The action.
	 * @param boolean  $include_ident Whether to include the record Primary Key.
	 * @param string[] $extra_params Other parameters to append to the URL.
	 * @return string The URL.
	 */
	public function get_url( $action = 'index', $include_ident = true, $extra_params = false ) {
		$params = array(
			'page' => 'tabulate',
			'controller' => 'record',
			'action' => $action,
			'table' => $this->table->get_name(),
		);
		if ( $include_ident && false !== $this->get_primary_key() ) {
			$params['ident'] = $this->get_primary_key();
		}
		if ( is_array( $extra_params ) ) {
			$params = array_merge( $params, $extra_params );
		}
		return admin_url( 'admin.php?' . http_build_query( $params ) );
	}

	/**
	 * Get most recent changes.
	 *
	 * @return string[]
	 */
	public function get_changes() {
		$wpdb = $this->table->get_database()->get_wpdb();
		$sql = "SELECT cs.id AS changeset_id, c.id AS change_id, date_and_time, "
			. "user_nicename, table_name, record_ident, column_name, old_value, "
			. "new_value, comment "
			. "FROM " . ChangeTracker::changes_name() . " c "
			. "  JOIN " . ChangeTracker::changesets_name() . " cs ON (c.changeset_id=cs.id) "
			. "  JOIN {$wpdb->prefix}users u ON (u.ID=cs.user_id) "
			. "WHERE table_name = %s AND record_ident = %s"
			. "ORDER BY date_and_time DESC, cs.id DESC "
			. "LIMIT 15 ";
		$params = array( $this->table->get_name(), $this->get_primary_key() );
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}
}
