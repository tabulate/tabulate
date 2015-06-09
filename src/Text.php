<?php

namespace WordPress\Tabulate;

class Text {

	/**
	 * Turn a spaced or underscored string to camelcase (with no spaces or underscores).
	 *
	 * @param string $str
	 * @return string
	 */
	public static function camelcase($str) {
		return str_replace( ' ', '', ucwords( str_replace( '_', ' ', $str ) ) );
	}

	/**
	 * Apply the titlecase filter to a string: removing underscores, uppercasing
	 * initial letters, and performing a few common (and not-so-common) word
	 * replacements such as initialisms and punctuation.
	 *
	 * @param string|array $value    The underscored and lowercase string to be
	 *                               titlecased, or an array of such strings.
	 * @param 'html'|'latex' $format The desired output format.
	 * @return string                A properly-typeset title.
	 * @todo Get replacement strings from configuration file.
	 */
	public static function titlecase($value, $format = 'html') {

		/**
		 * The mapping of words (and initialisms, etc.) to their titlecased
		 * counterparts for HTML output.
		 * @var array
		 */
		$html_replacements = array(
			'id' => 'ID',
			'cant' => "can't",
			'in' => 'in',
			'at' => 'at',
			'of' => 'of',
			'for' => 'for',
			'sql' => 'SQL',
			'todays' => "Today's",
		);

		/**
		 * The mapping of words (and initialisms, etc.) to their titlecased
		 * counterparts for LaTeX output.
		 * @var array
		 */
		$latex_replacements = array(
			'cant' => "can't",
		);

		/**
		 * Marshall the correct replacement strings.
		 */
		if ( 'latex' == $format ) {
			$replacements = array_merge( $html_replacements, $latex_replacements );
		} else {
			$replacements = $html_replacements;
		}

		/**
		 * Recurse if neccessary
		 */
		if ( is_array( $value ) ) {
			return array_map( array( self, 'titlecase' ), $value );
		} else {
			$out = ucwords( preg_replace( '|_|', ' ', $value ) );
			foreach ( $replacements as $search => $replacement ) {
				$out = preg_replace( "|\b$search\b|i", $replacement, $out );
			}
			return trim( $out );
		}
	}

	/**
	 * Format a date according to WP's preference.
	 * @param string $date
	 * @return string|int|bool
	 */
	public static function wp_date_format( $date ) {
		return mysql2date( get_option( 'date_format' ), $date );
	}

	/**
	 * Format a time according to WP's preference.
	 * @param string $time
	 * @return string|int|bool
	 */
	public function wp_time_format( $time ) {
		return mysql2date( get_option( 'time_format' ), $time );
	}

}
