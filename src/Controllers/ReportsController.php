<?php

namespace WordPress\Tabulate\Controllers;

use \WordPress\Tabulate\DB\Database;
use \WordPress\Tabulate\DB\Reports;

class ReportsController extends ControllerBase {

	public function index( $args ) {
		$db = new Database( $this->wpdb );
		$id = isset( $args[ 'id' ] ) ? $args[ 'id' ] : Reports::DEFAULT_REPORT_ID;
		$reports = new Reports($db);
		$template = $reports->get_template($id);
		$out = $template->render();
		if ( $template->file_extension ) {
			$this->send_file( $template->file_extension, $template->mime_type, $out, $template->title );
		} else {
			return $out;
		}
	}

}
