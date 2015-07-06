<?php

namespace WordPress\Tabulate\DB;

class Database {

	/** @var wpdb */
	protected $wpdb;

	/** @var array|string */
	protected $table_names;

	/** @var Table|array */
	protected $tables;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * @return \wpdb
	 */
	public function get_wpdb() {
		return $this->wpdb;
	}

	public function get_table_names() {
		if ( ! $this->table_names ) {
			$this->table_names = array();
			foreach ( $this->wpdb->get_col( 'SHOW TABLES' ) as $table_name ) {
				if ( Grants::current_user_can( Grants::READ, $table_name ) ) {
					$this->table_names[] = $table_name;
				}
			}
		}
		return $this->table_names;
	}

	/**
	 * Get a table from the database.
	 *
	 * @param string $name
	 * @return Table|false The table, or false if it's not available.
	 */
	public function get_table( $name ) {
		if ( ! in_array( $name, $this->get_table_names() ) ) {
			return false;
		}
		if ( ! isset( $this->tables[ $name ] ) ) {
			$this->tables[ $name ] = new Table( $this, $name );
		}
		return $this->tables[ $name ];
	}

	/**
	 * Get all tables in this database.
	 *
	 * @return Table[] An array of all Tables.
	 */
	public function get_tables( $exclude_views = true ) {
		$out = array();
		foreach ( $this->get_table_names() as $name ) {
			$table = $this->get_table( $name );
			// If this table is not available, skip it.
			if ( ! $table ) {
				continue;
			}
			if ( $exclude_views && $table->get_type() == Table::TYPE_VIEW ) {
				continue;
			}
			$out[] = $table;
		}
		return $out;
	}

	/**
	 * Get all views in this database.
	 *
	 * @return Table|array An array of all Tables that are views.
	 */
	public function get_views() {
		$out = array();
		foreach ( $this->get_tables( false ) as $table ) {
			if ( $table->get_type() == Table::TYPE_VIEW ) {
				$out[] = $table;
			}
		}
		return $out;
	}

}
