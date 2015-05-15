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
			if ( isset( $_GET[ 'tables' ] ) && count( $_GET[ 'tables' ] ) > 0 ) {
				if ( isset( $_GET[ 'tables' ][ $table->get_name() ] ) ) {
					$this->selected_tables[$table->get_name()] = $table;
				}
			} else { // Otherwise, default to all linked tables
				$referenced = count( $table->get_referencing_tables() ) > 0;
				$referencing = count( $table->get_referenced_tables() ) > 0;
				
				if ( $referenced OR $referencing ) {
					$this->selected_tables[$table->get_name()] = $table;
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
		$template->graphviz = $gv->shortcode(array(), $dot->render());

		return $template->render();
	}

	public function dot() {
		$template = new \WordPress\Tabulate\Template( 'erd/erd.dot' );
		$template->tables = $this->tables;
		$template->selected_tables = $this->selected_tables;
		header( 'Content-Type: text/plain' );
		echo $template->render();
		exit(0);
	}

	public function png() {
		$dot = new \WordPress\Tabulate\Template( 'erd/erd.dot' );
		$dot->tables = $this->tables;
		$dot->selected_tables = $this->selected_tables;

		//$graphviz_path = WP_PLUGIN_DIR.'/tfo-graphviz/tfo-graphviz.php';
		//var_dump($graphviz_path);
		$gv = new \TFO_Graphviz();
		$gv->init();
		$out = $gv->shortcode(array(), $dot->render());
		echo $out;
		shortcode_parse_atts($text);
		//$gv = new \TFO_Graphviz_Graphviz($dot->render(), array(), TFO_GRAPHVIZ_CONTENT_DIR, TFO_GRAPHVIZ_CONTENT_URL);
		//$gv->init();

//		$graph = Request::factory( '/erd.dot' )
//			->execute()
//			->body();
//		$this->cache_dir = Kohana::$cache_dir . DIRECTORY_SEPARATOR . 'webdb' . DIRECTORY_SEPARATOR . 'erd';
//		if ( ! is_dir( $this->cache_dir ) ) {
//			mkdir( $this->cache_dir, 0777, TRUE );
//		}
//		$dot_filename = $this->cache_dir . DIRECTORY_SEPARATOR . 'erd.dot';
//		$png_filename = $this->cache_dir . DIRECTORY_SEPARATOR . 'erd.png';
//		file_put_contents( $dot_filename, $graph );
//		$dot = WebDB::config( 'dot' );
//		$cmd = '"' . $dot . '"' . ' -Tpng';
//		$cmd .= ' -o' . escapeshellarg( $png_filename ); //output
//		$cmd .= ' ' . escapeshellarg( $dot_filename ); //input
//		$cmd .= ' 2>&1';
//		exec( $cmd, $out, $error );
//		if ( $error != 0 ) {
//			throw new HTTP_Exception_500( 'Unable to produce PNG. Command was: ' . $cmd . ' Output was: ' . implode( PHP_EOL, $out ) );
//		} else {
//			$this->response->send_file( $png_filename, 'erd.png', array( 'inline' => TRUE ) );
//		}
	}

}
