<?php

namespace WordPress\Tabulate\Controllers;

class TableController extends ControllerBase {

	public function index($args) {

		// Get database and table.
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$table = $db->get_table( $args['table'] );

		// Pagination.
		$page_num = (isset( $args['p'] )) ? $args['p'] : 1;
		$table->set_current_page_num( $page_num );
		$table->set_records_per_page( 20 );

		// Filters.
		$filter_param = (isset( $args['filter'] )) ? $args['filter'] : array();
		$table->add_filters( $filter_param );
		$filters = $table->get_filters();
		$filters[] = array(
			'column' => $table->get_title_column()->get_name(),
			'operator' => 'like',
			'value' => ''
		);

		// Give it all to the template.
		$template = new \WordPress\Tabulate\Template( 'table.html' );
		$template->controller = 'table';
		$template->table = $table;
		$template->columns = $table->get_columns();
		$template->operators = $table->get_operators();
		$template->filters = $filters;
		$template->filter_count = count( $filters );
		$template->records = $table->get_records();
		$template->record_count = $table->count_records();
		echo $template->render();
	}

}
