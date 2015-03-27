<?php

namespace WordPress\Tabulate\DB;

class Database {

	/** @var wpdb */
	protected $wpdb;

	public function __construct($wpdb) {
		$this->wpdb = $wpdb;
	}

	public function get_wpdb() {
		return $this->wpdb;
	}

	public function get_table_names() {
		return $this->wpdb->get_col('SHOW TABLES');
	}

	public function get_table($name) {
		$table = new Table($this, $name);
		return $table;
	}

}
