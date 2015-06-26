<?php

namespace WordPress\Tabulate\Controllers;

class HomeController extends ControllerBase {

	public function index() {
		$template = new \WordPress\Tabulate\Template( 'home.html' );
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$template->tables = $db->get_tables();
		$template->views = $db->get_views();
		return $template->render();
	}

}
