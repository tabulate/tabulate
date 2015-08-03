<?php

namespace WordPress\Tabulate\Controllers;

abstract class ControllerBase {

	/** @var \wpdb */
	protected $wpdb;

	/** @var string[] The injected $_GET query string. */
	protected $get;

	public function __construct( $wpdb, $get = array() ) {
		$this->wpdb = $wpdb;
		$this->get = $get;
	}

}
