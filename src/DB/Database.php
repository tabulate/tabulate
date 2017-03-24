<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

namespace WordPress\Tabulate\DB;

/**
 * The database class represents the entire MySQL database that WordPress uses.
 */
class Database {

	/**
	 * The global wpdb object.
	 *
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * A list of all table names.
	 *
	 * @var string[]
	 */
	protected $table_names;

	/**
	 * The filesystem.
	 *
	 * @var \WP_Filesystem_Base
	 */
	protected $filesystem;

	/**
	 * The list of all tables that the user can read.
	 *
	 * @var Table[]
	 */
	protected $tables;

	/**
	 * Create a new Database object based on the given wpdb object.
	 *
	 * @param \wpdb $wpdb The global wpdb object.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Set the filesystem.
	 *
	 * @param \WP_Filesystem_Base $filesystem The filesystem object.
	 */
	public function set_filesystem( \WP_Filesystem_Base $filesystem ) {
		$this->filesystem = $filesystem;
	}

	/**
	 * Get the filesystem.
	 *
	 * @return \WP_Filesystem_Base
	 */
	public function get_filesystem() {
		return $this->filesystem;
	}

	/**
	 * Get the global wpdb object.
	 *
	 * @return \wpdb
	 */
	public function get_wpdb() {
		return $this->wpdb;
	}

	/**
	 * Get a list of tables and views that the current user can read.
	 *
	 * @return string[] The table names.
	 */
	public function get_table_names() {
		if ( ! $this->table_names ) {
			$this->table_names = array();
			foreach ( $this->wpdb->get_col( 'SHOW TABLES' ) as $table_name ) {
				if ( Grants::current_user_can( Grants::READ, $table_name ) ) {
					$this->table_names[ $table_name ] = $table_name;
				}
			}
		}
		return $this->table_names;
	}

	/**
	 * Get a table from the database.
	 *
	 * @param string $name The name of the desired table.
	 * @return \WordPress\Tabulate\DB\Table|false The table, or false if it's not available.
	 */
	public function get_table( $name ) {
		if ( ! in_array( $name, $this->get_table_names(), true ) ) {
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
	 * @param boolean $exclude_views Whether to exclude database views from the returned list.
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
			if ( $exclude_views && $table->is_view() ) {
				continue;
			}
			$out[ $table->get_name() ] = $table;
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
			if ( $table->is_view() ) {
				$out[ $table->get_name() ] = $table;
			}
		}
		return $out;
	}

	/**
	 * Create a new table.
	 *
	 * @param string $name The name of the new table.
	 * @param string $comment The table comment.
	 * @throws Exception If the current user cannot 'promote_users'.
	 */
	public function create_table( $name, $comment = '' ) {
		if ( ! current_user_can( 'promote_users' ) ) {
			throw new Exception( 'Only administrators are allowed to create tables' );
		}
		$sql = "CREATE TABLE IF NOT EXISTS `$name` ( "
			. " `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY "
			. ") ENGINE=InnoDB, COMMENT='$comment';";
		$this->query( $sql );
		$this->reset();
		return $this->get_table( $name );
	}

	/**
	 * A wrapper around wpdb::prepare() and wpdb::query()
	 * that also checks wpdb::$last_error and throws up on occasion of badness.
	 *
	 * @param string   $sql The SQL statement to execute.
	 * @param string[] $params Parameters to pass to wpdb::prepare().
	 * @param string   $error_message What to tell the user if this query fails.
	 * @throws Exception The exception message is taken from wpdb::$last_error and if WP_DEBUG is set will also include the erroneous SQL.
	 */
	public function query( $sql, $params = null, $error_message = null ) {
		if ( $params ) {
			$sql = $this->get_wpdb()->prepare( $sql, $params );
		}
		$this->get_wpdb()->query( $sql );
		if ( ! empty( $this->get_wpdb()->last_error ) ) {
			$msg = $error_message . ': ' . $this->get_wpdb()->last_error;
			if ( WP_DEBUG ) {
				$msg .= " <code>$sql</code>";
			}
			throw new Exception( $msg );
		}
	}

	/**
	 * Get the name of the directory to which MySQL will write temporary export files.
	 * This is either the value of the 'secure_file_priv' server variable,
	 * or WordPress's normal temporary directory as returned by get_temp_dir().
	 * Always has a trailing slash.
	 *
	 * @return string Full path of the directory.
	 * @throws Exception If the directory is not writable.
	 */
	public function get_tmp_dir() {
		$query = "SHOW VARIABLES LIKE 'secure_file_priv';";
		$db_dir = $this->get_wpdb()->get_var( $query, 1 );
		$dir = empty( $db_dir ) ? get_temp_dir() : $db_dir;
		$out = rtrim( $dir, '/' ) . '/';
		if ( ! $this->get_filesystem()->is_writable( $out ) ) {
			throw new Exception( "Unable to write to temporary directory $out" );
		}
		return $out;
	}
}
