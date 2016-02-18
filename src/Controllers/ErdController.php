<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

namespace WordPress\Tabulate\Controllers;

/**
 * The Entity-Relationship-Diagram controller.
 */
class ErdController extends ControllerBase {

	/**
	 * The list of all names of tables.
	 *
	 * @var string[]
	 */
	private $tables;

	/**
	 * The list of names of tables to display.
	 *
	 * @var string[]
	 */
	private $selected_tables;

	/**
	 * Set up the list of all tables and the list of tables-to-display.
	 *
	 * @param \spdb $wpdb The global wpdb object.
	 */
	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$this->selected_tables = array();
		foreach ( $db->get_tables() as $table ) {
			$this->tables[] = $table;
			// If any tables are requested, only show them.
			if ( isset( $_GET['tables'] ) && count( $_GET['tables'] ) > 0 ) {
				if ( isset( $_GET['tables'][ $table->get_name() ] ) ) {
					$this->selected_tables[ $table->get_name() ] = $table;
				}
			} else { // Otherwise, default to all linked tables.
				$referenced = count( $table->get_referencing_tables() ) > 0;
				$referencing = count( $table->get_referenced_tables() ) > 0;
				if ( $referenced || $referencing ) {
					$this->selected_tables[ $table->get_name() ] = $table;
				}
			}
		}
	}

	/**
	 * Display the ERD page with option for limiting which tables are displayed.
	 * This uses the TFO GraphViz plugin's shortcode to do the actual graph.
	 *
	 * @return string
	 */
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
