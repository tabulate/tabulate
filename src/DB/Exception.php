<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

namespace WordPress\Tabulate\DB;

/**
 * The database exception class.
 */
class Exception extends \Exception {

	/**
	 * A wrapper for wp_die() that makes it easier to add debug and SQL information.
	 *
	 * @param string $message The error message.
	 * @param string $title The title of the Error.
	 * @param string $error The more detailed error message, only shown on debug.
	 * @param string $sql The SQL that created the error, only shown on debug.
	 */
	public static function wp_die( $message, $title, $error, $sql = null ) {
		$msg = "<p>$message</p>";
		if ( WP_DEBUG ) {
			$msg .= '<h2>Debug info:</h2><p>Error was: ' . $error . '</p>';
			if ( ! is_null( $sql ) ) {
				$msg .= '<p>Query was:</p><pre>' . esc_html( $sql ) . '</pre>';
			}
		}
		wp_die( $msg, $title, array(
			'back_link' => true,
		) );
	}
}
