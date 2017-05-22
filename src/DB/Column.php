<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

namespace WordPress\Tabulate\DB;

/**
 * The column class represents a single column in a single table in the database.
 */
class Column {

	/**
	 * The table to which this column belongs.
	 *
	 * @var Table
	 */
	private $table;

	/**
	 * The name of this column.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The type of this column.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The size, or length, of this column.
	 *
	 * @var integer
	 */
	private $size;

	/**
	 * This column's collation.
	 *
	 * @var string
	 */
	private $collation;

	/**
	 * The total number of digits in a DECIMAL column.
	 *
	 * @var integer
	 */
	private $precision;

	/**
	 * The number of digits after the decimal point in a DECIMAL column.
	 *
	 * @var integer
	 */
	private $scale;

	/**
	 * Whether or not this column is the Primary Key.
	 *
	 * @var boolean
	 */
	private $is_primary_key = false;

	/**
	 * Whether or not this column is a Unique Key.
	 *
	 * @var boolean
	 */
	private $is_unique = false;

	/**
	 * The default value for this column.
	 *
	 * @var mixed
	 */
	private $default_value;

	/**
	 * Whether or not this column is auto-incrementing.
	 *
	 * @var boolean
	 */
	private $is_auto_increment = false;

	/**
	 * Whether NULL values are allowed for this column.
	 *
	 * @var boolean
	 */
	private $nullable;

	/**
	 * Is this an unsigned number?
	 *
	 * @var boolean
	 */
	private $unsigned = false;

	/**
	 * ENUM options.
	 *
	 * @var string[]
	 */
	private $options;

	/**
	 * The comment attached to this column.
	 *
	 * @var string
	 */
	private $comment;

	/**
	 * The table that this column refers to, or false if it is not a foreign key.
	 *
	 * @var Table|false
	 */
	private $references = false;

	/**
	 * Create a column of a given table and based on given info.
	 *
	 * @param \WordPress\Tabulate\DB\Table $table The table that this column belongs to.
	 * @param string[]                     $info The output array of a SHOW COLUMNS query.
	 */
	public function __construct( Table $table, $info = false ) {
		$this->table = $table;
		$this->parse_info( $info );
	}

	/**
	 * Take the output of SHOW COLUMNS and populate this object's data.
	 *
	 * @param string[] $info The output array of a SHOW COLUMNS query.
	 */
	protected function parse_info( $info ) {

		// Name.
		$this->name = $info['Field'];

		// Type.
		$this->parse_type( $info['Type'] );

		// Default.
		$this->default_value = $info['Default'];

		// Primary key.
		if ( 'PRI' === strtoupper( $info['Key'] ) ) {
			$this->is_primary_key = true;
			if ( 'auto_increment' === $info['Extra'] ) {
				$this->is_auto_increment = true;
			}
		}

		// Unique key.
		$this->is_unique = ( 'UNI' === strtoupper( $info['Key'] ) );

		// Comment.
		$this->comment = $info['Comment'];

		// Collation.
		$this->collation = $info['Collation'];

		// Is this column NULL?
		$this->nullable = ( 'YES' === $info['Null'] );

		// Is this a foreign key?
		if ( in_array( $this->get_name(), $this->get_table()->get_foreign_key_names(), true ) ) {
			$referenced_tables = $this->get_table()->get_referenced_tables( false );
			$this->references = $referenced_tables[ $this->get_name() ];
		}

	}

	/**
	 * Get this column's name.
	 *
	 * @return string The name of this column.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the valid options for this column; only applies to ENUM and SET.
	 *
	 * @return array The available options.
	 */
	public function get_options() {
		return $this->options;
	}

	/**
	 * Get the human-readable title of this column.
	 */
	public function get_title() {
		return \WordPress\Tabulate\Text::titlecase( $this->get_name() );
	}

	/**
	 * Get this column's type.
	 *
	 * @return string The type of this column.
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Get the definitive list of xtypes.
	 *
	 * @return string[] The xtypes.
	 */
	public static function get_xtypes() {
		return array(
			'text_short' => array(
				'name' => 'text_short',
				'title' => 'Text (short)',
				'type' => 'VARCHAR',
				'sizes' => 1,
				'options' => array(),
			),
			'text_long' => array(
				'name' => 'text_long',
				'title' => 'Text (long)',
				'type' => 'TEXT',
				'sizes' => 0,
				'options' => array( 'autop', 'html', 'md', 'rst', 'plain' ),
			),
			'integer' => array(
				'name' => 'integer',
				'title' => 'Integer',
				'type' => 'INT',
				'sizes' => 1,
				'options' => array(),
			),
			'boolean' => array(
				'name' => 'boolean',
				'title' => 'Boolean',
				'type' => 'TINYINT',
				'sizes' => 0,
				'options' => array(),
			),
			'decimal' => array(
				'name' => 'decimal',
				'title' => 'Decimal',
				'type' => 'DECIMAL',
				'sizes' => 2,
				'options' => array(),
			),
			'date' => array(
				'name' => 'date',
				'title' => 'Date',
				'type' => 'DATE',
				'sizes' => 0,
				'options' => array(),
			),
			'time' => array(
				'name' => 'time',
				'title' => 'Time',
				'type' => 'TIME',
				'sizes' => 0,
				'options' => array(),
			),
			'datetime' => array(
				'name' => 'datetime',
				'title' => 'Date & Time',
				'type' => 'DATETIME',
				'sizes' => 0,
				'options' => array(),
			),
			'fk' => array(
				'name' => 'fk',
				'title' => 'Cross Reference',
				'type' => 'INT',
				'sizes' => 1,
				'options' => array(),
			),
			'point' => array(
				'name' => 'point',
				'title' => 'Geographic location',
				'type' => 'POINT',
				'sizes' => 0,
				'options' => array(),
			),
			'enum' => array(
				'name' => 'enum',
				'title' => 'Fixed list',
				'type' => 'ENUM',
				'sizes' => 0,
				'options' => array(),
			),
		);
	}

	/**
	 * Get the X-Type of this column.
	 *
	 * @return string[] An array containing details of the xtype: name, title, type, sizes, and options.
	 */
	public function get_xtype() {
		$xtypes = self::get_xtypes();
		if ( $this->is_foreign_key() ) {
			return $xtypes['fk'];
		}
		if ( $this->is_boolean() ) {
			return $xtypes['boolean'];
		}
		// Otherwise fall back on the first xtype with a matching type.
		foreach ( $xtypes as $xtype ) {
			if ( strtoupper( $this->get_type() ) === $xtype['type'] ) {
				return $xtype;
			}
		}
		return false;
	}

	/**
	 * Set the X-Type of this column.
	 *
	 * @param string $type The name of the type.
	 */
	public function set_xtype( $type ) {
		$option_name = TABULATE_SLUG . '_xtypes';
		$xtypes = update_option( $option_name );
		$table_name = $this->get_table()->get_name();
		if ( ! is_array( $xtypes[ $table_name ] ) ) {
			$xtypes[ $table_name ] = array();
		}
		$xtypes[ $table_name ][ $this->get_name() ] = $type;
		update_option( $option_name, $xtypes );
	}

	/**
	 * Get the column's comment.
	 *
	 * @return string
	 */
	public function get_comment() {
		return $this->comment;
	}

	/**
	 * Get the default value for this column.
	 *
	 * @return mixed
	 */
	public function get_default() {
		if ( 'CURRENT_TIMESTAMP' === $this->default_value ) {
			return date( 'Y-m-d h:i:s' );
		}
		return $this->default_value;
	}

	/**
	 * Get this column's size, or (for ENUM columns) its CSV options string.
	 *
	 * @return string The size of this column.
	 */
	public function get_size() {
		$size = $this->size;
		if ( 'decimal' === $this->get_type() ) {
			$size = "$this->precision,$this->scale";
		}
		if ( 'enum' === $this->get_type() ) {
			return "'" . join( "','", $this->get_options() ) . "'";
		}
		return $size;
	}

	/**
	 * Whether or not a non-NULL value needs to be supplied for this column.
	 *
	 * Not-NULL columns that have default values are *not* considered to be
	 * required.
	 *
	 * @return boolean
	 */
	public function is_required() {
		$has_default = ( $this->get_default() !== null || $this->is_auto_increment() );
		return ( ! $this->nullable() && ! $has_default );
	}

	/**
	 * Whether or not this column is the Primary Key for its table.
	 *
	 * @return boolean True if this is the PK, false otherwise.
	 */
	public function is_primary_key() {
		return $this->is_primary_key;
	}

	/**
	 * Whether or not this column is a unique key.
	 *
	 * @return boolean True if this is a Unique Key, false otherwise.
	 */
	public function is_unique() {
		return $this->is_unique;
	}

	/**
	 * Whether or not this column is an auto-incrementing integer.
	 *
	 * @return boolean True if this column has AUTO_INCREMENT set, false otherwise.
	 */
	public function is_auto_increment() {
		return $this->is_auto_increment;
	}

	/**
	 * Whether or not this column is allowed to have NULL values.
	 *
	 * @return boolean
	 */
	public function nullable() {
		return $this->nullable;
	}

	/**
	 * Only NOT NULL text fields are allowed to have empty strings.
	 *
	 * @return boolean
	 */
	public function allows_empty_string() {
		$text_types = array( 'text', 'varchar', 'char' );
		$is_text_type = in_array( $this->get_type(), $text_types, true );
		return ( ! $this->nullable() ) && $is_text_type;
	}

	/**
	 * Is this a boolean field?
	 *
	 * This method deals with the silliness that is MySQL's boolean datatype. Or, rather, it will do when it's finished.
	 * For now, it just reports true when this is a TINYINT(1) column.
	 *
	 * @return boolean
	 */
	public function is_boolean() {
		return $this->get_type() === 'tinyint' && $this->get_size() === 1;
	}

	/**
	 * Whether this column is an unsigned number.
	 *
	 * @return boolean
	 */
	public function is_unsigned() {
		return $this->unsigned;
	}

	/**
	 * Whether or not this column is an integer, float, or decimal column.
	 */
	public function is_numeric() {
		$is_int = substr( $this->get_type(), 0, 3 ) === 'int';
		$is_decimal = substr( $this->get_type(), 0, 7 ) === 'decimal';
		$is_float = substr( $this->get_type(), 0, 5 ) === 'float';
		return $is_int || $is_decimal || $is_float;
	}

	/**
	 * Whether or not this column is a foreign key.
	 *
	 * @return boolean True if $this->_references is not empty, otherwise false.
	 */
	public function is_foreign_key() {
		return ! empty( $this->references );
	}

	/**
	 * Get the table object of the referenced table, if this column is a foreign
	 * key.
	 *
	 * @return Table The referenced table.
	 */
	public function get_referenced_table() {
		return $this->table->get_database()->get_table( $this->references );
	}

	/**
	 * Get the table that this column belongs to.
	 *
	 * @return Table The table object.
	 */
	public function get_table() {
		return $this->table;
	}

	/**
	 * Take an SQL string and parse out column information.
	 *
	 * @param string $type_string The SQL.
	 */
	private function parse_type( $type_string ) {

		$this->unsigned = ( false !== stripos( $type_string, 'unsigned' ) );

		$varchar_pattern = '/^((?:var)?char)\((\d+)\)/';
		$decimal_pattern = '/^decimal\((\d+),(\d+)\)/';
		$float_pattern = '/^float\((\d+),(\d+)\)/';
		$integer_pattern = '/^((?:big|medium|small|tiny)?int|year)\(?(\d+)\)?/';
		$enum_pattern = '/^(enum|set)\(\'(.*?)\'\)/';

		$this->type = $type_string;
		$this->size = null;
		$this->precision = null;
		$this->scale = null;
		$this->options = null;
		if ( preg_match( $varchar_pattern, $type_string, $matches ) ) {
			$this->type = $matches[1];
			$this->size = (int) $matches[2];
		} elseif ( preg_match( $decimal_pattern, $type_string, $matches ) ) {
			$this->type = 'decimal';
			$this->precision = $matches[1];
			$this->scale = $matches[2];
		} elseif ( preg_match( $float_pattern, $type_string, $matches ) ) {
			$this->type = 'float';
			$this->precision = $matches[1];
			$this->scale = $matches[2];
		} elseif ( preg_match( $integer_pattern, $type_string, $matches ) ) {
			$this->type = $matches[1];
			$this->size = (int) $matches[2];
		} elseif ( preg_match( $enum_pattern, $type_string, $matches ) ) {
			$this->type = $matches[1];
			$values = explode( "','", $matches[2] );
			$this->options = array_combine( $values, $values );
		}
	}

	/**
	 * Get a human-readable string representation of this column.
	 *
	 * @return string
	 */
	public function __toString() {
		$pk = ($this->is_primary_key) ? ' PK' : '';
		$auto = ($this->is_auto_increment) ? ' AI' : '';
		if ( $this->references ) {
			$ref = ' References ' . $this->references . '.';
		} else {
			$ref = '';
		}
		$size = ($this->size > 0) ? "($this->size)" : '';
		return $this->name . ' ' . strtoupper( $this->type ) . $size . $pk . $auto . $ref;
	}

	/**
	 * Get the defining SQL for this column.
	 *
	 * @return string
	 */
	public function get_current_column_definition() {
		return self::get_column_definition(
			$this->get_name(),
			$this->get_xtype()['name'],
			$this->get_size(),
			$this->nullable(),
			$this->get_default(),
			$this->is_auto_increment(),
			$this->is_unique(),
			$this->get_comment(),
			$this->get_referenced_table()
		);
	}

	/**
	 * Alter this column.
	 *
	 * @param string                       $new_name The column name.
	 * @param string                       $xtype_name The x-type name. Must exist.
	 * @param string                       $size Either a single integer, or a x,y string of two integers.
	 * @param boolean                      $nullable Whether to allow NULl values.
	 * @param string                       $default The default value.
	 * @param boolean                      $auto_increment Auto-increment or not.
	 * @param boolean                      $unique Whether a unique constraint should apply.
	 * @param string                       $comment The column's comment. Default empty.
	 * @param \WordPress\Tabulate\DB\Table $target_table The target table for a foreign key.
	 * @param string                       $after The column that this one will be after.
	 * @throws Exception If unable to alter the table.
	 */
	public function alter( $new_name = null, $xtype_name = null, $size = null, $nullable = null, $default = null, $auto_increment = null, $unique = null, $comment = null, $target_table = null, $after = null ) {
		// Any that have not been set explicitly should be unchanged.
		$new_name = ! is_null( $new_name ) ? (string) $new_name : $this->get_name();
		$xtype_name = ! is_null( $xtype_name ) ? (string) $xtype_name : $this->get_xtype()['name'];
		$size = ! is_null( $size ) ? $size : $this->get_size();
		$nullable = ! is_null( $nullable ) ? (boolean) $nullable : $this->nullable();
		$default = ! is_null( $default ) ? (string) $default : $this->get_default();
		$auto_increment = ! is_null( $auto_increment ) ? (boolean) $auto_increment : $this->is_auto_increment();
		$unique = ! is_null( $unique ) ? (boolean) $unique : $this->is_unique();
		$comment = ! is_null( $comment ) ? (string) $comment : $this->get_comment();
		$target_table = ! is_null( $target_table ) ? $target_table : $this->get_referenced_table();

		// Check the current column definition.
		$col_def = self::get_column_definition( $new_name, $xtype_name, $size, $nullable, $default, $auto_increment, $unique, $comment, $target_table, $after );
		if ( $this->get_current_column_definition() === $col_def ) {
			return;
		}

		// Drop the unique key if it exists; it'll be re-created after.
		$table = $this->get_table();
		$wpdb = $table->get_database()->get_wpdb();
		if ( $this->is_unique() ) {
			$sql = 'SHOW INDEXES FROM `' . $table->get_name() . '` WHERE Column_name LIKE "' . $this->get_name() . '"';
			foreach ( $wpdb->get_results( $sql, ARRAY_A ) as $index ) {
				$sql = "DROP INDEX `" . $index['Key_name'] . "` ON `" . $table->get_name() . "`";
				$wpdb->query( $sql );
			}
		}

		// Drop any foreign keys if they exist; they'll be re-created after.
		if ( $this->is_foreign_key() ) {
			$fks_sql = 'SELECT CONSTRAINT_NAME AS fk_name FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS '
				. ' WHERE TABLE_SCHEMA = SCHEMA() '
				. ' AND TABLE_NAME = "' . $table->get_name() . '" '
				. ' AND CONSTRAINT_TYPE = "FOREIGN KEY" ';
			$fks = $wpdb->get_results( $fks_sql );
			foreach ( $fks as $key ) {
				$sql = 'ALTER TABLE `' . $table->get_name() . '` DROP FOREIGN KEY `' . $key->fk_name . '`';
				$wpdb->query( $sql );
			}
			$this->references = false;
		}

		// Alter the column.
		$sql = "ALTER TABLE `" . $table->get_name() . "` CHANGE COLUMN `" . $this->get_name() . "` $col_def";
		$wpdb->hide_errors();
		$altered = $wpdb->query( $sql );
		if ( false === $altered ) {
			$err = "Unable to alter '" . $table->get_name() . "." . $this->get_name() . "'"
				. " &mdash; $wpdb->last_error &mdash; <code>$sql</code>";
			throw new Exception( $err );
		}
		$wpdb->show_errors();

		// Reset the Column and Table objects' data.
		$table->reset();
		$sql = "SHOW FULL COLUMNS FROM `" . $table->get_name() . "` LIKE '$new_name'";
		$column_info = $table->get_database()->get_wpdb()->get_row( $sql, ARRAY_A );
		$this->parse_info( $column_info );
		if ( $this->is_foreign_key() ) {
			$this->get_referenced_table()->reset();
		}
	}

	/**
	 * Get an SQL column definition.
	 *
	 * @param string                       $name The column name.
	 * @param string                       $xtype_name The x-type name. Must exist.
	 * @param string                       $size Either a single integer, or a x,y string of two integers.
	 * @param boolean                      $nullable Whether to allow NULl values.
	 * @param string                       $default The default value.
	 * @param boolean                      $auto_increment Auto-increment or not.
	 * @param boolean                      $unique Whether a unique constraint should apply.
	 * @param string                       $comment The column's comment. Default empty.
	 * @param \WordPress\Tabulate\DB\Table $target_table The target table for a foreign key.
	 * @param string                       $after The column that this one will be after.
	 * @return string
	 */
	public static function get_column_definition( $name, $xtype_name = null, $size = null, $nullable = true, $default = null, $auto_increment = null, $unique = null, $comment = null, $target_table = null, $after = null ) {
		// Type.
		$xtypes = self::get_xtypes();
		$xtype = ( isset( $xtypes[ $xtype_name ] ) ) ? $xtypes[ $xtype_name ] : $xtypes['text_short'];
		$type_str = $xtype['type'];
		// Size or options.
		$size_str = '';
		if ( is_numeric( $xtype['sizes'] ) && $xtype['sizes'] > 0 ) {
			$size_str = '(' . ( $size ? : 50 ) . ')';
		}
		if ( 'enum' === $xtype_name ) {
			// If not already wraped in quotes, explode and quote each option.
			if ( 0 === preg_match( '/^["\'].*["\']$/', $size ) ) {
				$size = "'" . join( "','", explode( ',', $size ) ) . "'";
			}
			$size_str = "($size)";
		}
		if ( 'boolean' === $xtype_name ) {
			$size_str = '(1)';
		}
		// Nullable.
		$null_str = (true === $nullable) ? 'NULL' : 'NOT NULL';
		// Default.
		$default_str = '';
		if ( 'text_long' !== $xtype_name ) {
			$default_str = ! empty( $default ) ? "DEFAULT '$default'" : ( $nullable ? 'DEFAULT NULL' : '' );
		}
		$auto_increment_str = '';
		if ( $auto_increment && 'integer' === $xtype['name'] ) {
			$auto_increment_str = 'AUTO_INCREMENT';
		}
		$unique_str = $unique ? 'UNIQUE' : '';
		$comment_str = ! is_null( $comment ) ? "COMMENT '$comment'" : '';

		$after_str = ( ! empty( $after ) ) ? "AFTER `$after`" : '';
		if ( 'FIRST' === strtoupper( $after ) ) {
			$after_str = " FIRST ";
		}

		$ref_str = '';
		$sign_str = '';
		if ( $target_table instanceof \WordPress\Tabulate\DB\Table ) {
			$pk_col = $target_table->get_pk_column();
			$ref_str = ', ADD CONSTRAINT `' . $name . '_fk_to_' . $target_table->get_name() . '`'
				. ' FOREIGN KEY (`' . $name . '`) '
				. ' REFERENCES `' . $target_table->get_name() . '` '
				. ' (`' . $pk_col->get_name() . '`)';
			$type_str = $pk_col->get_type();
			$size_str = '(' . $pk_col->get_size() . ')';
			$sign_str = ($pk_col->is_unsigned()) ? 'UNSIGNED' : '';
		}

		// Put it all together.
		$col_def = "`$name` $type_str$size_str $sign_str $null_str $default_str $auto_increment_str $unique_str $comment_str $after_str $ref_str";
		return preg_replace( '/ +/', ' ', trim( $col_def ) );

	}
}
