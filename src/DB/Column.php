<?php

namespace WordPress\Tabulate\DB;

class Column {

	/**
	 * @var Table The table to which this column belongs.
	 */
	private $table;

	/** @var string The name of this column. */
	private $name;

	/** @var string The type of this column. */
	private $type;

	/** @var integer The size, or length, of this column. */
	private $size;

	/** @var string This column's collation. */
	private $collation;

	/** @var integer The total number of digits in a DECIMAL column. */
	private $precision;

	/** @var integer The number of digits after the decimal point in a DECIMAL column. */
	private $scale;

	/** @var boolean Whether or not this column is the Primary Key. */
	private $is_primary_key = false;

	/** @var boolean Whether or not this column is a Unique Key. */
	private $is_unique = false;

	/** @var mixed The default value for this column. */
	private $default_value;

	/** @var boolean Whether or not this column is auto-incrementing. */
	private $is_auto_increment = false;

	/** @var boolean Whether NULL values are allowed for this column. */
	private $nullable;

	/** @var boolean Is this an unsigned number? */
	private $unsigned = false;

	/** @var string[] ENUM options. */
	private $options;

	/** @var string The comment attached to this column. */
	private $comment;

	/**
	 * @var Table|false The table that this column refers to, or
	 * false if it is not a foreign key.
	 */
	private $references = false;

	public function __construct( Table $table, $info = false ) {
		$this->table = $table;
		$this->parse_info( $info );
	}

	/**
	 * Take the output of SHOW COLUMNS and populate this object's data.
	 * @param string[] $info
	 */
	protected function parse_info( $info ) {

		// Name
		$this->name = $info->Field;

		// Type
		$this->parse_type( $info->Type );

		// Default
		$this->default_value = $info->Default;

		// Primary key
		if ( strtoupper( $info->Key ) == 'PRI' ) {
			$this->is_primary_key = true;
			if ( $info->Extra == 'auto_increment' ) {
				$this->is_auto_increment = true;
			}
		}

		// Unique key
		$this->is_unique = ( strtoupper( $info->Key ) === 'UNI' );

		// Comment
		$this->comment = $info->Comment;

		// Collation
		$this->collation = $info->Collation;

		// NULL?
		$this->nullable = ($info->Null == 'YES');

		// Is this a foreign key?
		if ( in_array( $this->get_name(), $this->get_table()->get_foreign_key_names() ) ) {
			$referencedTables = $this->get_table()->get_referenced_tables( false );
			$this->references = $referencedTables[ $this->get_name() ];
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
				'options' => array('autop', 'html', 'md', 'rst', 'plain'),
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
		);
	}

	/**
	 * Get the X-Type of this column.
	 * @return string
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
			if ( strtoupper( $this->get_type() ) == $xtype['type'] ) {
				return $xtype;
			}
		}
		return false;
	}

	/**
	 * Set the X-Type of this column.
	 * @param string $type
	 */
	public function set_xtype( $type ) {
		$option_name = TABULATE_SLUG . '_xtypes';
		$xtypes = update_option( $option_name );
		$table_name = $this->get_table()->get_name();
		if ( ! is_array( $xtypes[ $table_name ] ) ) {
			$xtypes[ $table_name ] = array();
		}
		// @TODO Validate type.
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
		if ( $this->default_value == 'CURRENT_TIMESTAMP' ) {
			return date( 'Y-m-d h:i:s' );
		}
		return $this->default_value;
	}

	/**
	 * Get this column's size.
	 *
	 * @return string The size of this column.
	 */
	public function get_size() {
		$size = $this->size;
		if ( $this->get_type() === 'decimal' ) {
			$size = "$this->precision,$this->scale";
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
		$has_default = ( $this->get_default() != null || $this->is_auto_increment() );
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
	 * @return boolean
	 */
	public function nullable() {
		return $this->nullable;
	}

	/**
	 * Only NOT NULL text fields are allowed to have empty strings.
	 * @return boolean
	 */
	public function allows_empty_string() {
		$textTypes = array( 'text', 'varchar', 'char' );
		return ( ! $this->nullable() ) && in_array( $this->get_type(), $textTypes );
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
		return $this->get_type() == 'tinyint' && $this->get_size() === 1;
	}

	/**
	 * Whether or not this column is an integer, float, or decimal column.
	 */
	public function is_numeric() {
		$isInt = substr( $this->get_type(), 0, 3 ) == 'int';
		$isDecimal = substr( $this->get_type(), 0, 7 ) == 'decimal';
		$isFloat = substr( $this->get_type(), 0, 5 ) == 'float';
		return $isInt || $isDecimal || $isFloat;
	}

	/**
	 * Whether or not this column is a foreign key.
	 *
	 * @return boolean True if $this->_references is not empty, otherwise false.
	 */
	public function is_foreign_key() {
		return !empty( $this->references );
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
	 *
	 * @param <type> $type_string
	 */
	private function parse_type($type_string) {

		if ( preg_match( '/unsigned/', $type_string ) ) {
			$this->unsigned = true;
		}

		$varchar_pattern = '/^((?:var)?char)\((\d+)\)/';
		$decimal_pattern = '/^decimal\((\d+),(\d+)\)/';
		$float_pattern = '/^float\((\d+),(\d+)\)/';
		$integer_pattern = '/^((?:big|medium|small|tiny)?int|year)\(?(\d+)\)?/';
		//$integer_pattern = '/.*?(int|year)\(+(\d+)\)/';
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
	 * Alter this column.
	 * @param string $new_name
	 * @param string $xtype
	 * @param string $size Either a single integer, or a x,y string of two integers.
	 * @param boolean $nullable
	 * @param boolean $default
	 * @param boolean $auto_increment
	 * @param boolean $unique
	 * @param boolean $primary
	 * @param string $comment
	 * @param string $target_table
	 */
	public function alter( $new_name = null, $xtype_name = null, $size = null, $nullable = null, $default = null, $auto_increment = null, $unique = null, $primary = null, $comment = null, $target_table = null, $after = null ) {
		// Any that have not been set explicitely should be unchanged.
		$new_name = ! is_null($new_name) ? (string) $new_name : $this->get_name();
		$xtype_name = ! is_null($xtype_name) ? (string) $xtype_name : $this->get_xtype()['name'];
		$size = ! is_null($size) ? $size : $this->get_size();
		$nullable = ! is_null($nullable) ? (boolean) $nullable : $this->nullable();
		$default = ! is_null($default) ? (string) $default : $this->get_default();
		$auto_increment = ! is_null($auto_increment) ? (boolean) $auto_increment : $this->is_auto_increment();
		$unique = ! is_null($unique) ? (boolean) $unique : $this->is_unique();
		$primary = ! is_null($primary) ? (boolean) $primary : $this->is_primary_key();
		$comment = ! is_null($comment) ? (string) $comment : $this->get_comment();
		if ( $this->get_referenced_table() instanceof Table ) {
			$target_table = ! is_null($target_table) ? (string) $target_table : $this->get_referenced_table()->get_name();
		}

		// Drop the unique key if it exists; it'll be re-created after.
		$table = $this->get_table();
		$wpdb = $table->get_database()->get_wpdb();
		if ( $this->is_unique() ) {
			$sql = 'SHOW INDEXES FROM `' . $table->get_name() .'` WHERE Column_name LIKE "' . $this->get_name() . '"';
			foreach ( $wpdb->get_results( $sql ) as $index ) {
				$sql = "DROP INDEX `" . $index->Key_name . "` ON `" . $table->get_name() . "`";
				$wpdb->query( $sql );
			}
		}

		// Alter the column.
		$col_def = self::get_column_definition($new_name, $xtype_name, $size, $nullable, $default, $auto_increment, $unique, $primary, $comment, $target_table, $after);
		$sql = "ALTER TABLE `".$table->get_name()."` CHANGE COLUMN `".$this->get_name()."` $col_def";
		$wpdb->hide_errors();
		$altered = $wpdb->query( $sql );
		if ( $altered === false ) {
			throw new Exception( 'Unable to alter column "' . $this->get_name().'" -- ' . $wpdb->last_error );
		}
		$wpdb->show_errors();

		// Reset this object's data.
		$sql = "SHOW FULL COLUMNS FROM `" . $table->get_name() . "` LIKE '$new_name'";
		$column_info = $table->get_database()->get_wpdb()->get_row( $sql );
		$this->parse_info( $column_info );
		$table->reset();

	}

	public static function get_column_definition( $name , $xtype_name = null, $size = null, $nullable = true, $default = null, $auto_increment = null, $unique = null, $primary = null, $comment = null, $tartget_table = null, $after = null ) {
		$xtypes = self::get_xtypes();
		$xtype = ( isset( $xtypes[ $xtype_name ] ) ) ? $xtypes[ $xtype_name ] : $xtypes['text_short'];
		$size_str = '';
		if ( $xtype['sizes'] > 0 ) {
			$size_str = '(' . ( $size ? : 50 ) . ')';
		}
		if ( $xtype_name === 'boolean' ) {
			$size_str = '(1)';
		}
		$null_str = $nullable ? 'NULL' : 'NOT NULL';
		$default_str = '';
		if ( $xtype_name != 'text_long' ) {
			$default_str = ! empty( $default ) ? "DEFAULT '$default'" : ( $nullable ? 'DEFAULT NULL' : '' );
		}
		$auto_increment_str = '';
		if ( $auto_increment && $xtype['name'] == 'integer' ) {
			$auto_increment_str = 'AUTO_INCREMENT';
		}
		$unique_str = $unique ? 'UNIQUE' : '';
		//$primary_str = $primary ? 'PRIMARY KEY' : '';
		$comment_str = ! is_null($comment) ? "COMMENT '$comment'" : '';

		$after_str = ( ! empty( $after ) ) ? "AFTER `$after`" : '';
		if ( strtoupper( $after ) === 'FIRST' ) {
			$after_str = " FIRST ";
		}

		// Put it all together.
		$col_def = "`$name` {$xtype['type']}$size_str $null_str $default_str $auto_increment_str $unique_str $comment_str $after_str ";
		return preg_replace( '/ +/', ' ', trim( $col_def ) );

	}
}
