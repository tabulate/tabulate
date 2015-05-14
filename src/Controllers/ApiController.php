<?php

namespace WordPress\Tabulate\Controllers;

/**
 * This controller is different from the others in that it is not called via the
 * usual Menu dispatch system, but rather from a hook in `tabulate.php`.
 */
class ApiController extends ControllerBase {

	public function register_routes($routes) {
		$routes[ '/' . TABULATE_SLUG . '/tables' ] = array(
			array( array( $this, 'table_names' ), \WP_JSON_Server::READABLE ),
		);
		$routes[ '/' . TABULATE_SLUG . '/fk/(?P<table_name>.*)' ] = array(
			array( array( $this, 'foreign_key_values' ), \WP_JSON_Server::READABLE ),
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

	/**
	 * Get a list of a table's records' IDs and titles, filtered by
	 * `$_GET['term']`, for foreign-key fields. Only used when there are more
	 * than N records in a foreign table (otherwise the options are presented in
	 * a select list).
	 *
	 * @return array
	 */
	public function foreign_key_values( $table_name ) {
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$table = $db->get_table( $table_name );
		$table->add_filter( $table->get_title_column(), 'like', '%'.$_GET[ 'term' ].'%' );
		$out = array();
		foreach ( $table->get_records() as $record ) {
			$out[] = array(
				'value' => $record->get_primary_key(),
				'label' => $record->get_title(),
			);
		}
		return $out;
	}

}
