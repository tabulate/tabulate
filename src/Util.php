<?php

namespace WordPress\Tabulate;

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
		return in_array( $plugin, get_option( 'active_plugins', array() ) );
	}

}
