<?php

namespace WordPress\Tabulate\Controllers;

class ErdController extends ControllerBase {

	/** @var array|string */
	private $tables;

	/** @var array|string */
	private $selected_tables;

	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$this->selected_tables = array();
		foreach ( $db->get_tables() as $table ) {
			$this->tables[] = $table;
			// If any tables are requested, only show them
			if ( isset( $_GET['tables'] ) && count( $_GET['tables'] ) > 0 ) {
				if ( isset( $_GET['tables'][ $table->get_name() ] ) ) {
					$this->selected_tables[ $table->get_name() ] = $table;
				}
			} else { // Otherwise, default to all linked tables
				$referenced = count( $table->get_referencing_tables() ) > 0;
				$referencing = count( $table->get_referenced_tables() ) > 0;
				if ( $referenced || $referencing ) {
					$this->selected_tables[ $table->get_name() ] = $table;
				}
			}
		}
	}

	public function index() {
		$template = new \WordPress\Tabulate\Template( 'erd/display.html' );
		$template->title = 'ERD';
		$template->tables = $this->tables;
		$template->selected_tables = $this->selected_tables;

		$dot = new \WordPress\Tabulate\Template( 'erd/erd.dot' );
		$dot->tables = $this->tables;
		$dot->selected_tables = $this->selected_tables;
		$gv = new \TFO_Graphviz();
		$gv->init();
		$template->graphviz = $gv->shortcode( array(), $dot->render() );

		return $template->render();
	}

}
