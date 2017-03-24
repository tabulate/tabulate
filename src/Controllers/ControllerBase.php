<?php
/**
 * This file contains only a single class.
 *
 * @package Tabulate
 * @file
 */

namespace WordPress\Tabulate\Controllers;

/**
 * All controllers must inherit from this class.
 */
abstract class ControllerBase {

	/**
	 * The global database object.
	 *
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * The injected $_GET query string.
	 *
	 * @var string[]
	 */
	protected $get;

	/**
	 * The global filesystem object.
	 *
	 * @var \WP_Filesystem_Base
	 */
	protected $filesystem;

	/**
	 * Create the controller, with the global database and query string.
	 *
	 * @param \wpdb    $wpdb The global wpdb object.
	 * @param string[] $get The $_GET array.
	 */
	public function __construct( $wpdb, $get = array() ) {
		$this->wpdb = $wpdb;
		$this->get = $get;
	}

	/**
	 * Set the filesystem.
	 *
	 * @param \WP_Filesystem_Base $filesystem The filesystem object.
	 */
	public function set_filesystem( \WP_Filesystem_Base $filesystem ) {
		$this->filesystem = $filesystem;
	}

	/**
	 * Send specified content to the client as a downloadable file.
	 *
	 * @param string         $ext The file extension.
	 * @param string         $mime The mime-type of the file.
	 * @param string         $content The file's contents.
	 * @param string|boolean $download_name The name of the file, for the user to download.
	 */
	protected function send_file( $ext, $mime, $content, $download_name = false ) {
		$download_name = ($download_name ?: date( 'Y-m-d' ) ) . '.' . $ext;
		header( 'Content-Encoding: UTF-8' );
		header( 'Content-type: ' . $mime . '; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
		echo $content;
		exit;
	}

	/**
	 * Verify the _wpnonce request value.
	 *
	 * @param string $action The action name.
	 */
	public function verify_nonce( $action ) {
		$nonce = wp_verify_nonce( $_REQUEST['_wpnonce'], $action );
		if ( ! $nonce ) {
			wp_die();
		}
	}
}
