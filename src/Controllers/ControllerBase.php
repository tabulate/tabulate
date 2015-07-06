<?php

namespace WordPress\Tabulate\Controllers;

abstract class ControllerBase {

	/** @var \wpdb */
	protected $wpdb;

	public function __construct($wpdb) {
		$this->wpdb = $wpdb;
	}

}
