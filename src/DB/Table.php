<?php

namespace WordPress\Tabulate\DB;

class Table {

	/** @static A base table. */
	const TYPE_TABLE = 'table';

	/** @static A database view, possibly of multiple base tables. */
	const TYPE_VIEW = 'view';

	/** @var Database The database to which this table belongs. */
	protected $database;

	/** @var string The name of this table. */
	protected $name;

	/** @var string This table's comment. False until initialised. */
	protected $comment = false;

	/** @var string The SQL statement used to create this table. */
	protected $defining_sql;

	/** @var string The SQL statement most recently saved by $this->getRows() */
	protected $saved_sql;

	/** @var string The statement parameters most recently saved by $this->getRows() */
	protected $saved_parameters;

	/** @var array|Table Array of tables referred to by columns in this one. */
	protected $referenced_tables;

	/** @var array|string The names (only) of tables referenced by columns in this one. */
	protected $referenced_table_names;

	/** @var array Each joined table gets a unique alias, based on this. */
	protected $alias_count = 1;

	/**
	 * @var array|Column Array of column names and objects for all of the
	 * columns in this table.
	 */
	protected $columns;

	/** @var array */
	protected $filters = array();

	/** @var array Permitted operators. */
	protected $operators = array(
		'like' => 'contains',
		'not like' => 'does not contain',
		'=' => 'is',
		'!=' => 'is not',
		'empty' => 'is empty',
		'not empty' => 'is not empty',
		'>=' => 'is greater than or equal to',
		'>' => 'is greater than',
		'<=' => 'is less than or equal to',
		'<' => 'is less than'
	);

	/**
	 * @var string|false The name of the column by which to order, or false if
	 * no column has been set.
	 */
	protected $order_by = false;

	/**
	 * @var integer|false The number of currently-filtered rows, or false if no
	 * query has been made yet or the filters have been reset.
	 */
	protected $record_count = false;

	/** @var integer The current page number. */
	protected $current_page_num = 1;

	/** @var integer The number of records to show on each page. */
	protected $records_per_page = 10;

	/**
	 * Create a new database table object.
	 *
	 * @param Database The database to which this table belongs.
	 * @param string $name The name of the table.
	 */
	public function __construct($database, $name) {
		$this->database = $database;
		$this->name = $name;

		$this->columns = array();
		$columns = $this->database->get_wpdb()->get_results( "SHOW FULL COLUMNS FROM `$name`" );
		foreach ( $columns as $column_info ) {
			$column = new Column( $this->database, $this, $column_info );
			$this->columns[$column->get_name()] = $column;
		}
	}

	/**
	 * Add a filter.
	 * @param type $column
	 * @param type $operator
	 * @param type $value
	 * @param boolean $force Whether to transform the value, for FKs.
	 */
	public function add_filter($column, $operator, $value, $force = false) {
		$valid_columm = in_array( $column, array_keys( $this->columns ) );
		$valid_operator = in_array( $operator, array_keys( $this->operators ) );
		$emptyValueAllowed = (strpos( $operator, 'empty' ) === false && !empty( $value ));
		$valid_value = (strpos( $operator, 'empty' ) !== false) || $emptyValueAllowed;
		if ( $valid_columm && $valid_operator && $valid_value ) {
			$this->filters[] = array(
				'column' => $column,
				'operator' => $operator,
				'value' => trim( $value ),
				'force' => $force,
			);
		}
	}

	/**
	 * Add multiple filters.
	 */
	public function add_filters($filters) {
		foreach ( $filters as $filter ) {
			$column = (isset( $filter['column'] )) ? $filter['column'] : false;
			$operator = (isset( $filter['operator'] )) ? $filter['operator'] : false;
			$value = (isset( $filter['value'] )) ? $filter['value'] : false;
			$this->add_filter( $column, $operator, $value );
		}
	}

	public function get_filters() {
		return $this->filters;
	}

	protected function get_fk_join_clause($table, $alias, $column) {
		return 'LEFT OUTER JOIN `' . $table->get_name() . '` AS f' . $alias
				. ' ON (`' . $this->get_name() . '`.`' . $column->get_name() . '` '
				. ' = `f' . $alias . '`.`' . $table->get_pk_column()->get_name() . '`)';
	}

	/**
	 * Apply the stored filters to the supplied SQL.
	 *
	 * @param string $sql The SQL to modify
	 * @return array Parameter values, in the order of their occurence in $sql
	 */
	public function apply_filters(&$sql) {

		$params = array();
		$param_num = 1; // Incrementing parameter suffix, to permit duplicate columns.
		$where_clause = '';
		$join_clause = '';
		foreach ( $this->filters as $filter ) {
			$f_column = $filter['column'];
			$param_name = $filter['column'] . $param_num;

			// Filters on foreign keys need to work on the FKs title column.
			$column = $this->columns[$f_column];
			if ( $column->is_foreign_key() && !$filter['force'] ) {
				$join = $this->join_on( $column );
				$f_column = $join['column_alias'];
				$join_clause .= $join['join_clause'];
			} else {
				// The result of join_on() above is quoted, so this must also be.
				$f_column = "`$f_column`";
			}

			// LIKE or NOT LIKE
			if ( $filter['operator'] == 'like' || $filter['operator'] == 'not like' ) {
				$where_clause .= " AND CONVERT($f_column, CHAR) " . strtoupper( $filter['operator'] ) . " %s ";
				$params[$param_name] = '%' . $filter['value'] . '%';
			} // Equals or does-not-equal
			elseif ( $filter['operator'] == '=' || $filter['operator'] == '!=' ) {
				$where_clause .= " AND $f_column " . strtoupper( $filter['operator'] ) . " %s ";
				$params[$param_name] = $filter['value'];
			} // IS EMPTY
			elseif ( $filter['operator'] == 'empty' ) {
				$where_clause .= " AND ($f_column IS NULL OR $f_column = '')";
			} // IS NOT EMPTY
			elseif ( $filter['operator'] == 'not empty' ) {
				$where_clause .= " AND ($f_column IS NOT NULL AND $f_column != '')";
			} // Other operators. They're already validated in $this->addFilter()
			else {
				$where_clause .= " AND ($f_column " . $filter['operator'] . " %s)";
				$params[$param_name] = $filter['value'];
			}

			$param_num++;
		} // end foreach filter
		// Add clauses into SQL
		if ( !empty( $where_clause ) ) {
			$where_clause_pattern = '/^(.* FROM .*?)((?:GROUP|HAVING|ORDER|LIMIT|$).*)$/m';
			$where_clause = substr( $where_clause, 5 ); // Strip leading ' AND'.
			$where_clause = "$1 $join_clause WHERE $where_clause $2";
			$sql = preg_replace( $where_clause_pattern, $where_clause, $sql );
		}

		return $params;
	}

	/**
	 * Get the name of the column by which this table should be ordered.
	 *
	 * There is no default for this, as some orderings can result in quite slow
	 * queries and it's best to let the user request this. It used to order by
	 * the title column by default.
	 *
	 * @return string
	 */
	public function get_order_by() {
		return $this->order_by;
	}

	public function set_order_by($order_by) {
		if ( in_array( $order_by, array_keys( $this->columns ) ) ) {
			$this->order_by = $order_by;
		}
	}

	public function get_order_dir() {
		if ( empty( $this->orderdir ) ) {
			$this->orderdir = 'ASC';
		}
		return $this->orderdir;
	}

	public function set_order_dir($orderdir) {
		if ( in_array( strtoupper( $orderdir ), array( 'ASC', 'DESC' ) ) ) {
			$this->orderdir = $orderdir;
		}
	}

	/**
	 * For a given foreign key column, get an alias and join clause for selecting
	 * against that column's foreign values. If the column is not a foreign key,
	 * the alias will just be the qualified column name, and the join clause will
	 * be the empty string.
	 *
	 * @param Column $column The FK column
	 * @return array Array with 'join_clause' and 'column_alias' keys
	 */
	protected function join_on($column) {
		$join_clause = '';
		$column_alias = '`' . $this->get_name() . '`.`' . $column->get_name() . '`';
		if ( $column->is_foreign_key() ) {
			$fk1_table = $column->get_referenced_table();
			$fk1_title_column = $fk1_table->get_title_column();
			$join_clause .= ' LEFT OUTER JOIN `' . $fk1_table->get_name() . '` AS f' . $this->alias_count
					. ' ON (`' . $this->get_name() . '`.`' . $column->get_name() . '` '
					. ' = `f' . $this->alias_count . '`.`' . $fk1_table->get_pk_column()->get_name() . '`)';
			$column_alias = "`f$this->alias_count`.`" . $fk1_title_column->get_name() . "`";
			$this->joined_tables[] = $column_alias;
			// FK is also an FK?
			if ( $fk1_title_column->is_foreign_key() ) {
				$fk2_table = $fk1_title_column->get_referenced_table();
				$fk2_title_column = $fk2_table->get_title_column();
				$join_clause .= ' LEFT OUTER JOIN `' . $fk2_table->get_name() . '` AS ff' . $this->alias_count
						. ' ON (f' . $this->alias_count . '.`' . $fk1_title_column->get_name() . '` '
						. ' = ff' . $this->alias_count . '.`' . $fk1_table->get_pk_column()->get_name() . '`)';
				$column_alias = "`ff$this->alias_count`.`" . $fk2_title_column->getName() . "`";
				$this->joined_tables[] = $column_alias;
			}
			$this->alias_count++;
		}
		return array( 'join_clause' => $join_clause, 'column_alias' => $column_alias );
	}

	/**
	 * Get rows, with pagination.
	 *
	 * Note that rows are returned as arrays and not objects, because MySQL
	 * allows column names to begin with a number, but PHP does not variables to
	 * do so.
	 *
	 * @return array|Record The row data
	 */
	public function get_records($with_pagination = true, $save_sql = false) {
		$columns = array();
		foreach ( array_keys( $this->columns ) as $col ) {
			$columns[] = "`$this->name`.`$col`";
		}

		// Build basic SELECT statement.
		$sql = 'SELECT ' . join( ',', $columns ) . ' FROM `' . $this->get_name() . '`';

		// Ordering.
		if ($this->get_order_by()) {
			$order_by_join = $this->join_on( $this->get_column( $this->get_order_by() ) );
			$sql .= $order_by_join['join_clause'] . ' ORDER BY ' . $order_by_join['column_alias'] . ' ' . $this->get_order_dir();
		}

		$params = $this->apply_filters( $sql );

		// Then limit to the ones on the current page.
		if ( $with_pagination ) {
			$records_per_page = $this->get_records_per_page();
			$sql .= ' LIMIT ' . $records_per_page;
			if ( $this->page() > 1 ) {
				$sql .= ' OFFSET ' . ($records_per_page * ($this->get_current_page_num() - 1));
			}
		}

		// Run query and save SQL
		if ( $params ) {
			$sql = $this->database->get_wpdb()->prepare( $sql, $params );
		}
		$rows = $this->database->get_wpdb()->get_results( $sql );

		$records = array();
		foreach ( $rows as $row ) {
			$records[] = new Record( $this, $row );
		}

		if ( $save_sql ) {
			$this->saved_sql = $sql;
			$this->saved_parameters = $params;
		}

		return $records;
	}

	public function get_current_page_num() {
		return $this->current_page_num;
	}

	public function set_current_page_num($current_page_num) {
		$this->current_page_num = $current_page_num;
	}

	public function get_records_per_page() {
		return $this->records_per_page;
	}

	public function set_records_per_page($recordsPerPage) {
		$this->records_per_page = $recordsPerPage;
	}

	public function get_saved_query() {
		return array(
			'sql' => $this->saved_sql,
			'parameters' => $this->saved_parameters
		);
	}

	/**
	 * Get a single record as an associative array.
	 *
	 * @param string $pk_val The value of the PK of the record to get.
	 * @return Record|false The record object, or false if it wasn't found.
	 */
	public function get_record($pk_val) {
		$pk_column = $this->get_pk_column();
		if ( !$pk_column ) {
			return false;
		}
		$sql = "SELECT `" . join( '`, `', array_keys( $this->get_columns() ) ) . "` "
				. "FROM `" . $this->get_name() . "` "
				. "WHERE `" . $pk_column->get_name() . "` = %s "
				. "LIMIT 1";
		$params = array( $pk_val );
		$stmt = $this->database->get_wpdb()->prepare( $sql, $params );
		$row = $this->database->get_wpdb()->get_row( $stmt );
		return ( $row ) ? new Record( $this, $row ) : false;
	}

	/**
	 * Get a bare record with only default values.
	 *
	 * @return Record
	 */
	public function get_default_record() {
		$row = array();
		foreach ( $this->get_columns() as $col ) {
			$row[ $col->get_name() ] = $col->get_default();
		}
		$record = new Record($this, $row);
		return $record;
	}

	/**
	 * Get this table's name.
	 *
	 * @return string The name of this table.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Whether this is a base table or a view.
	 *
	 * @return string Either `Table::TYPE_TABLE` or `Table::TYPE_VIEW`.
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Get this table's title. This is the title-cased name, if not otherwise
	 * defined.
	 *
	 * @return string The title
	 */
	public function get_title() {
		return \WordPress\Tabulate\Text::titlecase( $this->get_name() );
	}

	/**
	 * Get a list of permitted operators.
	 *
	 * @return array[string]=>string List of operators.
	 */
	public function get_operators() {
		return $this->operators;
	}

	public function get_page_count() {
		return ceil( $this->count_records() / $this->get_records_per_page() );
	}

	/**
	 * Get or set the current page.
	 *
	 * @param integer $page
	 * @return integer Current page
	 */
	public function page($page = false) {
		if ( $page !== false ) {
			$this->current_page_num = $page;
		} else {
			return $this->current_page_num;
		}
	}

	/**
	 * Get the number of rows in the current filtered set.  This leaves the
	 * actual counting up to `$this->get_rows()`, rather than doing the query
	 * itself, because filtering is applied in that method, and I didn't want to
	 * duplicate that here (or anywhere else).
	 *
	 * @return integer
	 */
	public function count_records() {
		if ( !$this->record_count ) {
			if ($this->get_pk_column()) {
				$count_col = '`' . $this->get_name() . '`.`'.$this->get_pk_column()->get_name().'`';
			} else {
				$count_col = '*';
			}
			$sql = 'SELECT COUNT(' . $count_col . ') as `count` FROM `' . $this->get_name() . '`';
			$params = $this->apply_filters( $sql );
			if ( $params ) {
				$sql = $this->database->get_wpdb()->prepare( $sql, $params );
			}
			$count = $this->database->get_wpdb()->get_var( $sql, 0, 0 );
			$this->record_count = $count;
		}
		return $this->record_count;
	}

	/**
	 * @return string Full filesystem path to resulting temporary file.
	 */
	public function export() {

		$columns = array();
		$column_headers = array();
		$join_clause = '';
		foreach ( $this->columns as $col_name => $col ) {
			if ( $col->is_foreign_key() ) {
				$col_join = $this->join_on( $col );
				$column_name = $col_join['column_alias'];
				$join_clause .= $col_join['join_clause'];
			} else {
				$column_name = "`$this->name`.`$col_name`";
			}
			$columns[] = "REPLACE(IFNULL($column_name, ''),'\r\n', '\n')";
			$column_headers[] = $col->get_title();
		}

		// Build basic SELECT statement
		$sql = 'SELECT ' . join( ',', $columns )
			. ' FROM `' . $this->get_name() . '` ' . $join_clause;

		$params = $this->apply_filters( $sql );

		$filename = get_temp_dir() . uniqid( 'tabulate_' ) . '.csv';
		if ( DIRECTORY_SEPARATOR == '\\' ) {
			// Clean Windows slashes, for MySQL's benefit.
			$filename = str_replace( '\\', '/', $filename );
		}
		// Clear out any old copy.
		if ( file_exists( $filename ) ) {
			unlink( $filename );
		}
		// Build the final SQL, appending the column headers in a UNION.
		$sql = 'SELECT "' . join( '", "', $column_headers ) . '"'
			. ' UNION ' . $sql
			. ' INTO OUTFILE "' . $filename.'" '
			. ' FIELDS TERMINATED BY ","'
			. ' ENCLOSED BY \'"\''
			. ' ESCAPED BY \'"\''
			. ' LINES TERMINATED BY "\r\n"';
		// Execute the SQL.
		if ( $params ) {
			$sql = $this->database->get_wpdb()->prepare( $sql, $params );
		}
		$this->database->get_wpdb()->query( $sql );
		// Make sure it exported.
		if ( ! file_exists( $filename ) ) {
			echo '<pre>' . $sql . '</pre>';
			throw new \Exception( "Failed to create $filename" );
		}
		// Give the filename back to the controller, to send to the client.
		return $filename;
	}

	/**
	 * Get one of this table's columns.
	 *
	 * @return Column The column.
	 */
	public function get_column($name) {
		return $this->columns[$name];
	}

	/**
	 * Get a list of this table's columns, optionally constrained by their type.
	 *
	 * @param string $type Only return columns of this type.
	 * @return array|Column This table's columns.
	 */
	public function get_columns($type = null) {
		if ( is_null( $type ) ) {
			return $this->columns;
		} else {
			$out = array();
			foreach ( $this->get_columns() as $col ) {
				if ( $col->get_type() == $type ) {
					$out[$col->get_name()] = $col;
				}
			}
			return $out;
		}
	}

	/**
	 * Get the table comment text.
	 *
	 * @return string
	 */
	public function get_comment() {
		if ( !$this->comment ) {
			$sql = $this->get_defining_sql();
			$comment_pattern = '/.*\)(?:.*COMMENT[\w=]*\'(.*)\')?/si';
			preg_match( $comment_pattern, $sql, $matches );
			$this->comment = (isset( $matches[1] )) ? $matches[1] : '';
			$this->comment = str_replace( "''", "'", $this->comment );
		}
		return $this->comment;
	}

	/**
	 * Get the first unique-keyed column, or if there is no unique non-ID column
	 * then use the second column (because this is often a good thing to do).
	 * Unless there's only one column; then, just use that.
	 *
	 * @return Column
	 */
	public function get_title_column() {
		// Try to get the first non-PK unique key
		foreach ( $this->get_columns() as $column ) {
			if ( $column->is_unique() && !$column->is_primary_key() ) {
				return $column;
			}
		}
		// But if that fails, just use the second (or the first) column.
		$columnIndices = array_keys( $this->columns );
		if ( isset( $columnIndices[1] ) ) {
			$titleColName = $columnIndices[1];
		} else {
			$titleColName = $columnIndices[0];
		}
		return $this->columns[$titleColName];
	}

	/**
	 * Get the SQL statement used to create this table, as given by the 'SHOW
	 * CREATE TABLE' command.
	 *
	 * @return string The SQL statement used to create this table.
	 */
	public function get_defining_sql() {
		if ( !isset( $this->defining_sql ) ) {
			$defining_sql = $this->database->get_wpdb()->get_row( "SHOW CREATE TABLE `$this->name`" );
			if ( isset( $defining_sql->{'Create Table'} ) ) {
				$defining_sql = $defining_sql->{'Create Table'};
				$this->type = self::TYPE_TABLE;
			} elseif ( isset( $defining_sql->{'Create View'} ) ) {
				$defining_sql = $defining_sql->{'Create View'};
				$this->type = self::TYPE_VIEW;
			} else {
				throw new \Exception( 'Table or view not found: ' . $this->name );
			}
			$this->defining_sql = $defining_sql;
		}
		return $this->defining_sql;
	}

	/**
	 * Get this table's Primary Key column.
	 *
	 * @return Column The PK column.
	 */
	public function get_pk_column() {
		foreach ( $this->get_columns() as $column ) {
			if ( $column->is_primary_key() ) {
				return $column;
			}
		}
		return false;
	}

	/**
	 * Get a list of this table's foreign keys and the tables to which they refer.
	 * This does <em>not</em> take into account a user's permissions (i.e. the
	 * name of a table which the user is not allowed to read may be returned).
	 *
	 * @return array[string => string] The list of <code>column_name => table_name</code> pairs.
	 */
	public function get_referenced_tables($instantiate = false) {

		// Extract the FK info from the CREATE TABLE statement.
		if ( !is_array( $this->referenced_tables ) ) {
			$this->referenced_table_names = array();
			$definingSql = $this->get_defining_sql();
			$foreignKeyPattern = '|FOREIGN KEY \(`(.*?)`\) REFERENCES `(.*?)`|';
			preg_match_all( $foreignKeyPattern, $definingSql, $matches );
			if ( isset( $matches[1] ) && count( $matches[1] ) > 0 ) {
				foreach ( array_combine( $matches[1], $matches[2] ) as $colName => $tabName ) {
					$this->referenced_table_names[$colName] = $tabName;
				}
			}
		}

		if ( $instantiate ) {
			$this->referenced_tables = array();
			foreach ( $this->referenced_table_names as $refCol => $ref_tab ) {
				$this->referenced_tables[$refCol] = $this->get_database()->get_table( $ref_tab );
			}
		}

		return ($instantiate) ? $this->referenced_tables : $this->referenced_table_names;
	}

	/**
	 * Get tables with foreign keys referring here.
	 *
	 * @return array|Table
	 */
	public function get_referencing_tables() {
		$out = array();
		// For all tables in the Database...
		foreach ( $this->get_database()->get_tables() as $table ) {
			// ...get a list of the tables they reference.
			$foreignTables = $table->get_referenced_tables();
			foreach ( $foreignTables as $foreign_column => $referenced_table_name ) {
				// If this table is a referenced table, collect the table from which it's referenced.
				if ( $referenced_table_name == $this->get_name() ) {
					$out[$foreign_column] = $table; //array('table' => $table, 'column' => $foreign_column);
				}
			}
		}
		return $out;
	}

	/**
	 * Get a list of the names of the foreign keys in this table.
	 *
	 * @return array[string] Names of foreign key columns in this table.
	 */
	public function get_foreign_key_names() {
		return array_keys( $this->get_referenced_tables( false ) );
	}

	/**
	 * Get the database to which this table belongs.
	 *
	 * @return Database The database object.
	 */
	public function get_database() {
		return $this->database;
	}

	/**
	 * Get a string representation of this table; a succinct summary of its
	 * columns and their types, keys, etc.
	 *
	 * @return string A summary of this table.
	 */
	public function __toString() {
		$colCount = count( $this->getColumns() );
		$out = "\n+-----------------------------------------+\n";
		$out .= "| " . $this->name . " ($colCount columns)\n";
		$out .= "+-----------------------------------------+\n";
		foreach ( $this->getColumns() as $column ) {
			$out .= "| $column \n";
		}
		$out .= "+-----------------------------------------+\n\n";
		return $out;
	}

	/**
	 * Get an XML representation of the structure of this table.
	 *
	 * @return DOMElement The XML 'table' node.
	 */
	public function to_xml() {
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$table = $dom->createElement( 'table' );
		$dom->appendChild( $table );
		$name = $dom->createElement( 'name' );
		$name->appendChild( $dom->createTextNode( $this->name ) );
		$table->appendChild( $name );
		foreach ( $this->get_columns() as $column ) {
			$table->appendChild( $dom->importNode( $column->toXml(), true ) );
		}
		return $table;
	}

	/**
	 * Get a JSON representation of the structure of this table.
	 *
	 * @return string
	 */
	public function to_json() {
		$json = new Services_JSON();
		$metadata = array();
		foreach ( $this->getColumns() as $column ) {
			$metadata[] = array(
				'name' => $column->getName()
			);
		}
		return $json->encode( $metadata );
	}

	/**
	 * Remove all filters.
	 *
	 * @return void
	 */
	public function reset_filters() {
		$this->filters = array();
		$this->recordCount = false;
	}

	public function delete_record($primaryKeyValue) {
		$sql = "DELETE FROM `" . $this->get_name() . "` "
				. "WHERE `" . $this->get_pk_column()->get_name() . "` = :primaryKeyValue";
		$data = array( 'primaryKeyValue' => $primaryKeyValue );
		return $this->database->get_wpdb()->query( $sql, $data );
	}

	/**
	 * Save data to this table.  If the 'id' key of the data array is numeric,
	 * the row with that ID will be updated; otherwise, a new row will be
	 * inserted.
	 *
	 * @param array $data The data to insert.
	 * @param string $pk_value The value of the record's PK.
	 * @return Record The updated or inserted record.
	 */
	public function save_record($data, $pk_value = null) {

		$columns = $this->get_columns();

		/*
		 * Go through all data and clean it up before saving.
		 */
		foreach ( $data as $field => $value ) {
			// Make sure this column exists in the DB.
			if ( !isset( $columns[$field] ) ) {
				unset( $data[$field] );
				continue;
			}
			$column = $this->get_column($field);

			// Boolean values.
			if ( $column->is_boolean() ) {
				$zeroValues = array( 0, '0', false, 'false', 'FALSE', 'off', 'OFF', 'no', 'NO' );
				if ( ($value === null || $value === '') && $column->nullable() ) {
					$data[$field] = null;
				} elseif ( in_array( $value, $zeroValues, true ) ) {
					$data[$field] = false;
				} else {
					$data[$field] = true;
				}
			}

			// Empty strings.
			if ( ! $column->allows_empty_string() && '' === $value && $column->nullable() ) {
				$data[ $field ] = null;
			}
		}

		// Find the PK, and hide errors (for now).
		$pk_name = $this->get_pk_column()->get_name();
		$this->database->get_wpdb()->hide_errors();

		// Compile SQL for insert and update statements.
		// This is a workaround for NULL support in $wpdb->update() and $wpdb->insert().
		// Can probably be removed when https://core.trac.wordpress.org/ticket/15158 is resolved.
		$set_items = array();
		foreach ( $data as $field => $datum ) {
			if ( is_null( $datum ) ) {
				$escd_datum = 'NULL';
			} elseif ( is_numeric( $datum ) ) {
				$escd_datum = $datum;
			} else {
				$escd_datum = "'" . esc_sql( $datum ) ."'";
			}
			$set_items[] = "`$field` = $escd_datum";
		}
		$set_clause = 'SET ' . join( ', ', $set_items );

		// Prevent PK from being set to empty.
		if ( isset( $data[ $pk_name ] ) && empty( $data[ $pk_name ] ) ) {
			unset( $data[ $pk_name ] );
		}

		if ( $pk_value ) { // Update?
			// Check permission.
			if ( ! Grants::current_user_can( Grants::UPDATE, $this->get_name() ) ) {
				throw new \Exception( 'You do not have permission to update data in this table.' );
			}
			$where_clause = $this->database->get_wpdb()->prepare( "WHERE `$pk_name` = %s", $pk_value );
			$this->database->get_wpdb()->query( 'UPDATE ' . $this->get_name()." $set_clause $where_clause;" );
			$new_pk_value = (isset( $data[ $pk_name ] ) ) ? $data[ $pk_name ] : $pk_value;

		} else { // Or insert?
			// Check permission.
			if ( ! Grants::current_user_can( Grants::CREATE, $this->get_name() ) ) {
				throw new \Exception( 'You do not have permission to insert records into this table.' );
			}
			$this->database->get_wpdb()->query( 'INSERT INTO ' . $this->get_name() . " $set_clause;" );
			if ( ! empty( $this->database->get_wpdb()->last_error ) ) {
				throw new Exception( $this->database->get_wpdb()->last_error );
			}
			$new_pk_value = $this->database->get_wpdb()->insert_id;

		}

		// Show errors again and return the new or updated record.
		$this->database->get_wpdb()->show_errors();
		return $this->get_record( $new_pk_value );
	}

	public function get_url($action = 'index', $merge_existing = false) {
		$params = array(
			'page' => 'tabulate',
			'controller' => 'table',
			'action' => $action,
			'table' => $this->get_name(),
		);
		if ( $merge_existing ) {
			$params = array_merge( $_GET, $params );
		}
		return admin_url( 'admin.php?' . http_build_query( $params ) );
	}

}
