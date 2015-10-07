<?php
/**
 * This file contains only the RecordCounter class.
 *
 * @package Tabulate
 * @file
 */

namespace WordPress\Tabulate\DB;

/**
 * A record counter takes care of counting and caching the records in a single
 * table.
 */
class RecordCounter {

	/**
	 * The table.
	 * @var \WordPress\Tabulate\DB\Table
	 */
	protected $table;

	/**
	 * The time-to-live of the cached record count, in seconds.
	 * @var integer
	 */
	protected $transient_expiration;

	/**
	 * Create a new RecordCounter.
	 * @param \WordPress\Tabulate\DB\Table $table The table to count.
	 */
	public function __construct( \WordPress\Tabulate\DB\Table $table ) {
		$this->table = $table;
		$this->transient_expiration = 5 * 60;
	}

	/**
	 * Get the record count of this table. Will use a cached value only for base
	 * tables and where there are no filters.
	 * @return integer The record count.
	 */
	public function get_count() {
		// Only cache if this is a base table and there are no filters.
		$can_cache = $this->table->is_table() && count( $this->table->get_filters() ) === 0;

		if ( $can_cache ) {
			$count = get_transient( $this->transient_name() );
			if ( $count !== false ) {
				return $count;
			}
		}

		// Otherwise, run the COUNT() query.
		$pk_col = $this->table->get_pk_column();
		if ( $pk_col instanceof Column ) {
			$count_col = '`' . $this->table->get_name() . '`.`' . $pk_col->get_name() . '`';
		} else {
			$count_col = '*';
		}
		$sql = 'SELECT COUNT(' . $count_col . ') as `count` FROM `' . $this->table->get_name() . '`';
		$params = $this->table->apply_filters( $sql );
		if ( ! empty( $params ) ) {
			$sql = $this->table->get_database()->get_wpdb()->prepare( $sql, $params );
		}
		$count = $this->table->get_database()->get_wpdb()->get_var( $sql, 0, 0 );
		if ( $can_cache ) {
			set_transient( $this->transient_name(), $count, $this->transient_expiration );
		}
		return $count;
	}

	/**
	 * Empty the cached record count for this table.
	 * @return void
	 */
	public function clear() {
		delete_transient( $this->transient_name() );
	}

	/**
	 * Get the name of the transient under which this table's record count is
	 * stored. All Tabulate transients start with TABULATE_SLUG.
	 * @return string
	 */
	public function transient_name() {
		return TABULATE_SLUG . '_' . $this->table->get_name() . '_count';
	}

}
