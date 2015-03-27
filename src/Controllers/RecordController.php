<?php

namespace WordPress\Tabulate\Controllers;

class RecordController extends ControllerBase {

	public function view($args) {
		// Get database and table.
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$table = $db->get_table( $args['table'] );

		// Give it all to the template.
		$template = new \WordPress\Tabulate\Template( 'record_view.html' );
		$template->table = $table;
		$template->record = $table->get_record($args['ident']);
		echo $template->render();

	}

}
