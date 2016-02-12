<?php
/**
 * This file contains only the Util class
 *
 * @package Tabulate
 */

namespace WordPress\Tabulate;

/**
 * This is a utility class to hold miscellaneous helper methods.
 * Not great design, certainly, but then neither is lots of WP core.
 */
class Util {

	/**
	 * Check whether the plugin is active by checking the active_plugins list.
	 *
	 * This is an duplicate of the function defined in wp-adin/includes/plugin.php
	 * It's redefined here so we can use it in the frontend without including
	 * all of the plugin.php file.
	 *
	 * @param string $plugin Base plugin path from plugins directory.
	 * @return bool True, if in the active plugins list. False, not in the list.
	 */
	public static function is_plugin_active( $plugin ) {
		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( $plugin );
		}
		return in_array( $plugin, get_option( 'active_plugins', array() ), true );
	}

	/**
	 * Split a string on line boundaries.
	 *
	 * @param string $val The string to split.
	 * @return string[] The resulting array.
	 */
	public static function split_newline( $val ) {
		$vals = preg_split( '/\n|\r|\r\n/', $val, -1, PREG_SPLIT_NO_EMPTY );
		return array_filter( array_map( 'trim', $vals ) );
	}
}
