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

	/**
	 * Get a list of tables that the current user can read.
	 * @return string[] The table names.
	 */
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
	 * @return \WordPress\Tabulate\DB\Table|false The table, or false if it's not available.
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
	 * Forget all table information, forcing it to be re-read from the database
	 * when next required. Used after schema changes.
	 */
	public function reset() {
		$this->table_names = false;
		$this->tables = false;
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

	/**
	 * Create a new table.
	 * @param string $name
	 * @param string $comment
	 */
	public function create_table( $name, $comment = '' ) {
		if ( ! current_user_can( 'promote_users' ) ) {
			throw new Exception( 'Only administrators are allowed to create tables' );
		}
		$sql = "CREATE TABLE IF NOT EXISTS `$name` ( "
			. " `id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY "
			. ") ENGINE=InnoDB, COMMENT='$comment';";
		$this->get_wpdb()->query( $sql );
		$this->reset();
		return $this->get_table( $name );
	}

}
