<?php

namespace WordPress\Tabulate;

class Shortcode {

	/** @var DB\Database */
	private $db;

	public function __construct( $wpdb ) {
		$this->db = new DB\Database( $wpdb );
	}

	/**
	 * 
	 */
	public function run( $atts ) {
		$defaults = array(
			'format' => 'table',
			'table' => null,
		);
		$attrs = shortcode_atts( $defaults, $atts );
		if ( ! isset( $attrs['table'] ) ) {
			return "<div class='tabulate error'>The 'table' attribute must be set.</div>";
		}
		$table = $this->db->get_table( $attrs['table'] );
		if ( ! $table ) {
			// Show no error for not-found tables?
			return '';
		}
		$format_method = $attrs['format'].'_format';
		if (  is_callable( array($this, $format_method ) ) ) {
			return $this->$format_method( $table, $attrs );
		} else {
			return "Format '{$attrs['format']}' not available.";
		}
	}

	protected function count_format( DB\Table $table, $attrs ) {
		$count = number_format( $table->count_records() );
		return '<span class="tabulate count-format">'.$count.'</span>';
	}

	protected function list_format( DB\Table $table, $attrs ) {
		$titles = array();
		foreach ( $table->get_records() as $rec ) {
			$titles[] = $rec->get_title();
		}
		$glue = ( ! empty( $attrs['glue'] ) ) ? $attrs['glue'] : ', ';
		return '<span class="tabulate list-format">' . join( $glue, $titles ) . '</span>';
	}

	protected function table_format( DB\Table $table, $attrs ) {
		$template = new \WordPress\Tabulate\Template( 'data_table.html' );
		$template->table = $table;
		$template->links = false;
		$template->record = $table->get_default_record();
		$template->records = $table->get_records();
		return $template->render();
	}

}
