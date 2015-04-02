<?php

namespace WordPress\Tabulate\Controllers;

class ApiController extends ControllerBase {

	public function register_routes($routes) {
		$routes[ '/' . TABULATE_SLUG . '/tables' ] = array(
			array( array( $this, 'table_names' ), \WP_JSON_Server::READABLE ),
		);
		return $routes;
	}

	/**
	 * Get a list of table names, filtered by `$_GET['term']`, for use in the
	 * quick-jump menu.
	 *
	 * @return array
	 */
	public function table_names() {
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$tables = $db->get_tables( false );
		$out = array();
		foreach ( $tables as $table ) {
			if ( false !== stripos( $table->get_title(), $_GET['term'] ) ) {
				$out[] = array(
					'value' => $table->get_name(),
					'label' => $table->get_title(),
				);
			}
		}
		return $out;
	}

}
