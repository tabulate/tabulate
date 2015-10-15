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

	protected function send_file( $ext, $mime, $content, $download_name = false ) {
		$download_name = ($download_name ?: date( 'Y-m-d' ) ) . '.' . $ext;
		header( 'Content-Encoding: UTF-8' );
		header( 'Content-type: ' . $mime . '; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
		echo $content;
		exit;
	}

}
