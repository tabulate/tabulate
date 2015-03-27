<?php

namespace WordPress\Tabulate\Controllers;

abstract class ControllerBase {

	protected $wpdb;

	public function __construct($wpdb) {
		$this->wpdb = $wpdb;
	}

}
