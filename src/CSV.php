<?php

namespace WordPress\Tabulate;

/**
 * A class for parsing a CSV file has either just been uploaded (i.e. $_FILES is
 * set), or is stored as a temporary file (as defined herein).
 */
class CSV {

	/** @var array|string The headers in the CSV data. */
	public $headers;

	/** @var array two-dimenstional integer-indexed array of the CSV's data */
	public $data;

	/** @var string Temporary identifier for CSV file. */
	public $hash = false;

	/**
	 * Create a new CSV object based on a file.
	 *
	 * 1. If a file is being uploaded (i.e. `$_FILES['file']` is set), attempt
	 *    to use it as the CSV file.
	 * 2. On the otherhand, if we're given a hash, attempt to use this to locate
	 *    a local temporary file.
	 *
	 * In either case, if a valid CSV file cannot be found and parsed, throw an
	 * exception.
	 *
	 * @param string $hash The hash of an in-progress import.
	 * @param array $uploaded The result of wp_handle_upload().
	 */
	public function __construct( $hash = false, $uploaded = false ) {
		if ( $uploaded ) {
			$this->save_file( $uploaded );
		}

		if ( $hash ) {
			$this->hash = $hash;
		}

		$this->load_data();
	}

	/**
	 * Check the (already-handled) upload and rename the uploaded file.
	 * @see wp_handle_upload()
	 * @param array $uploaded
	 * @throws \Exception
	 */
	private function save_file( $uploaded ) {
		if ( isset( $uploaded['error'] ) ) {
			throw new \Exception( $uploaded['error'] );
		}
		if ( $uploaded['type'] != 'text/csv' ) {
			unlink( $uploaded['file'] );
			throw new \Exception( 'Only CSV files can be imported.' );
		}
		$this->hash = md5( time() );
		rename( $uploaded['file'], get_temp_dir().'/'.$this->hash );
	}

	/**
	 * Load CSV data from the file identified by the current hash. If no hash is
	 * set, this method does nothing.
	 * @return void
	 * @throws \Exception If the hash-identified file doesn't exist.
	 */
	public function load_data() {
		if ( ! $this->hash ) {
			return;
		}
		$file_path = get_temp_dir() . '/' . $this->hash;
		if ( !file_exists( $file_path ) ) {
			throw new \Exception( "No import was found with the identifier &lsquo;$this->hash&rsquo;" );
		}

		// Get all rows.
		$this->data = array();
		$file = fopen( $file_path, 'r' );
		while ( $line = fgetcsv( $file ) ) {
			$this->data[] = $line;
		}
		fclose( $file );

		// Extract headers.
		$this->headers = $this->data[0];
		unset( $this->data[0] );
	}

	/**
	 * Get the number of data rows in the file (i.e. excluding the header row).
	 * 
	 * @return integer The number of rows.
	 */
	public function row_count() {
		return count( $this->data );
	}

	/**
	 * Whether or not a file has been successfully loaded.
	 *
	 * @return boolean
	 */
	public function loaded() {
		return $this->hash !== FALSE;
	}

	/**
	 * Take a mapping of DB column name to CSV column name, and convert it to
	 * a mapping of CSV column number to DB column name. This ignores empty
	 * column headers in the CSV (so we don't have to distinguish between
	 * not-matching and matching-on-empty-string).
	 *
	 * @param array $column_map
	 * @return array Keys are CSV indexes, values are DB column names
	 */
	private function remap($column_map) {
		$heads = array();
		foreach ( $column_map as $db_col_name => $csv_col_name ) {
			foreach ( $this->headers as $head_num => $head_name ) {
				// If the header has a name, and it matches that of the column.
				if ( ! empty( $head_name ) && strcasecmp( $head_name, $csv_col_name ) === 0 ) {
					$heads[$head_num] = $db_col_name;
				}
			}
		}
		return $heads;
	}

	/**
	 * Rename all keys in all data rows to match DB column names, and normalize
	 * all values to be valid for the `$table`.
	 *
	 * If a _value_ in the array matches a lowercased DB column header, the _key_
	 * of that value is the DB column name to which that header has been matched.
	 *
	 * @param DB\Table $table
	 * @param array $column_map
	 * @return array Array of error messages.
	 */
	public function match_fields($table, $column_map) {
		// First get the indexes of the headers
		$heads = $this->remap( $column_map );

		$errors = array();
		for ( $row_num = 1; $row_num <= $this->row_count(); $row_num++ ) {
			foreach ( $this->data[$row_num] as $col_num => $value ) {
				if ( !isset( $heads[$col_num] ) ) {
					continue;
				}
				$col_errors = array();
				$db_column_name = $heads[$col_num];
				$column = $table->get_column( $db_column_name );
				// Required, has no default, and is empty
				if ( $column->is_required() AND ! $column->get_default() AND empty( $value ) ) {
					$col_errors[] = 'Required but empty';
				}
				// Already exists
				if ( $column->is_unique() && $this->value_exists( $table, $column, $value ) ) {
					$col_errors[] = "Unique value already present: '$value'";
				}
				// Too long (if the column has a size and the value is greater than this)
				if ( ! $column->is_foreign_key() AND ! $column->is_boolean()
						AND $column->get_size() > 0
						AND strlen( $value ) > $column->get_size() ) {
					$col_errors[] = 'Value (' . $value . ') too long (maximum length of ' . $column->get_size() . ')';
				}
				// Invalid foreign key value
				if ( !empty( $value ) AND $column->is_foreign_key() ) {
					$err = $this->validate_foreign_key( $column, $col_num, $row_num, $value );
					if ( $err ) {
						$col_errors[] = $err;
					}
				}
				// Dates
				if ( $column->get_type() == 'date' AND ! empty( $value ) AND preg_match( '/\d{4}-\d{2}-\d{2}/', $value ) !== 1 ) {
					$col_errors[] = 'Value (' . $value . ') not in date format';
				}

				if ( count( $col_errors ) > 0 ) {
					// Construct error details array
					$errors[] = array(
						'column_name' => $this->headers[$col_num],
						'column_number' => $col_num,
						'field_name' => $column->get_name(),
						'row_number' => $row_num,
						'messages' => $col_errors,
					);
				}
			}
		}
		return $errors;
	}

	/**
	 * Assume all data is now valid, and only FK values remain to be translated.
	 * 
	 * @param DB\Table $table The table into which to import data.
	 * @param array $column_map array of DB names to import names.
	 * @return integer The number of rows imported.
	 */
	public function import_data($table, $column_map) {
		global $wpdb;
		$change_tracker = new \WordPress\Tabulate\DB\ChangeTracker( $wpdb );
		$change_tracker->open_changeset( 'CSV import.', true );
		$count = 0;
		$headers = $this->remap( $column_map );
		for ( $row_num = 1; $row_num <= $this->row_count(); $row_num++ ) {
			$row = array();
			foreach ( $this->data[$row_num] as $col_num => $value ) {
				if ( !isset( $headers[$col_num] ) ) {
					continue;
				}
				$db_column_name = $headers[$col_num];
				$column = $table->get_column( $db_column_name );

				// Get actual foreign key value
				if ( $column->is_foreign_key() ) {
					if ( empty( $value ) ) {
						// Ignore empty-string FKs.
						continue;
					} else {
						$fk_rows = $this->get_fk_rows( $column->get_referenced_table(), $value );
						$foreign_row = array_shift( $fk_rows );
						$value = $foreign_row->get_primary_key();
					}
				}

				// All other values are used as they are
				$row[$db_column_name] = $value;
			}
			
			$pk_name = $table->get_pk_column()->get_name();
			$pk_value = ( isset( $row[ $pk_name ] ) ) ? $row[ $pk_name ] : null;
			$table->save_record( $row, $pk_value );
			$count++;
		}
		$change_tracker->close_changeset();
		return $count;
	}

	/**
	 * Determine whether a given value is valid for a foreign key (i.e. is the
	 * title of a foreign row).
	 * 
	 * @param Webdb_DBMS_Column $column
	 * @param integer $col_num
	 * @param integer $row_num
	 * @param string $value
	 * @return FALSE if the value is valid
	 * @return array error array if the value is not valid
	 */
	protected function validate_foreign_key($column, $col_num, $row_num, $value) {
		$foreign_table = $column->get_referenced_table();
		if ( ! $this->get_fk_rows( $foreign_table, $value ) ) {
			$link = '<a href="' . $foreign_table->get_url() . '" title="Opens in a new tab or window" target="_blank" >'
				. $foreign_table->get_title()
				. '</a>';
			return "Value <code>$value</code> not found in $link";
		}
		return false;
	}

	/**
	 * Get the rows of a foreign table where the title column equals a given
	 * value.
	 * 
	 * @param DB\Table $foreign_table
	 * @param string $value The value to match against the title column.
	 * @return Database_Result
	 */
	protected function get_fk_rows($foreign_table, $value) {
		$foreign_table->reset_filters();
		$foreign_table->add_filter( $foreign_table->get_title_column()->get_name(), '=', $value );
		return $foreign_table->get_records();
	}

	/**
	 * Determine whether the given value exists.
	 * @param DB\Table $table
	 * @param DB\Column $column
	 * @param mixed $value
	 */
	protected function value_exists( $table, $column, $value ) {
		$wpdb = $table->get_database()->get_wpdb();
		$sql = 'SELECT 1 FROM `' . $table->get_name() . '` '
			. 'WHERE `' . $column->get_name() . '` = %s '
			. 'LIMIT 1';
		$exists = $wpdb->get_row( $wpdb->prepare( $sql, array( $value ) ) );
		return ! is_null( $exists );
	}

}
