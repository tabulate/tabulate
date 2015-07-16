<?php

namespace WordPress\Tabulate\DB;

class Exception extends \Exception {

	public static function wp_die( $message, $title, $error, $sql ) {
		$msg = "<p>$message</p>";
		if ( WP_DEBUG ) {
			$msg .= "<h2>Debug info:</h2>"
				. "<p>Error was: " . $error . "</p>"
				. "<p>Query was:</p><pre>$sql</pre>";
		}
		wp_die( $msg, $title, array( 'back_link' => true ) );
	}

}
