<?php

namespace WordPress\Tabulate\Controllers;

class HomeController extends ControllerBase {

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
		$template->reports = $reports_table->get_records( false );

		return $template->render();
	}
}
