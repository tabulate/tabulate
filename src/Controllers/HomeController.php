<?php
/**
 * This file contains only a single file.
 *
 * @file
 * @package Tabulate
 */

namespace WordPress\Tabulate\Controllers;

/**
 * The home controller displays a dashboard
 * that lists all tables, views, and reports.
 */
class HomeController extends ControllerBase {

	/**
	 * The Tabulate dashboard.
	 *
	 * @return string
	 */
	public function index() {
		$template = new \WordPress\Tabulate\Template( 'home.html' );
		$template->title = 'Tabulate';
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );

		// Tables.
		$transient_name = TABULATE_SLUG . 'home_table_list';
		$table_info = get_transient( $transient_name );
		if ( ! $table_info ) {
			$table_info = array();
			foreach ( $db->get_tables() as $table ) {
				$table_info[] = array(
					'title' => $table->get_title(),
					'count' => $table->count_records(),
					'url' => $table->get_url(),
				);
			}
			set_transient( $transient_name, $table_info, MINUTE_IN_SECONDS * 5 );
		}
		$template->tables = $table_info;

		// Views.
		$template->views = $db->get_views();

		// Reports.
		$reports_table = $db->get_table( \WordPress\Tabulate\DB\Reports::reports_table_name() );
		$template->reports = ($reports_table) ? $reports_table->get_records( false ) : array();

		return $template->render();
	}
}
