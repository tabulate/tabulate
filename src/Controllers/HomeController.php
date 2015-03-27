<?php

namespace WordPress\Tabulate\Controllers;

class HomeController extends ControllerBase {

	public function index() {
		$template = new \WordPress\Tabulate\Template( 'home.html' );
		echo $template->render();
	}

}
