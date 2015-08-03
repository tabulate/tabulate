<?php

namespace WordPress\Tabulate\Controllers;

use WordPress\Tabulate\DB\Database;
use WordPress\Tabulate\DB\Table;
use WordPress\Tabulate\DB\Grants;
use WordPress\Tabulate\Template;

class ShortcodeController extends ControllerBase {

	/** @var \WordPress\Tabulate\DB\Database */
	private $db;

	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
		$this->db = new Database( $wpdb );
		// Dequeue scripts, but they'll be requeued in self::run() if required.
		add_action( 'wp_enqueue_scripts', function() {
			wp_dequeue_script( 'tabulate-scripts' );
		} );
	}

	/**
	 * Substitute the Shortcode with the relevant formatted output.
	 * @param string[] $atts
	 * @return string
	 */
	public function run( $atts ) {
		$defaults = array(
			'format' => 'table',
			'table' => null,
		);
		$attrs = shortcode_atts( $defaults, $atts );
		if ( ! isset( $attrs['table'] ) ) {
			return $this->error( "The 'table' attribute must be set." );
		}
		$table = $this->db->get_table( $attrs['table'] );
		if ( ! $table ) {
			if ( ! is_user_logged_in() ) {
				return $this->error( "You are not logged in. " . wp_loginout( get_the_permalink(), false ) );
			}
			return $this->error();
		}
		$format_method = $attrs['format'].'_format';
		if ( is_callable( array( $this, $format_method ) ) ) {
			wp_enqueue_script( 'tabulate-scripts' );
			return $this->$format_method( $table, $attrs );
		} else {
			return $this->error( "Format '{$attrs['format']}' not available." );
		}
	}

	/**
	 * Get an error message, and dequeue scripts.
	 * It's too late now to dequeue styles.
	 * @param string $message
	 */
	protected function error( $message = '' ) {
		return "<div class='tabulate shortcode-error'>$message</div>";
	}

	protected function form_format( Table $table, $attrs ) {
		if ( ! Grants::current_user_can( Grants::CREATE, $table ) ) {
			return 'You do not have permission to create ' . $table->get_title() . ' records.';
		}
		$template = new Template( 'record/shortcode.html' );
		$template->table = $table;
		$template->record = $table->get_default_record();
		$template->return_to = get_the_permalink();
		return $template->render();
	}

	protected function count_format( Table $table, $attrs ) {
		$count = number_format( $table->count_records() );
		return '<span class="tabulate count-format">'.$count.'</span>';
	}

	protected function list_format( Table $table, $attrs ) {
		$titles = array();
		foreach ( $table->get_records() as $rec ) {
			$titles[] = $rec->get_title();
		}
		$glue = ( ! empty( $attrs['glue'] ) ) ? $attrs['glue'] : ', ';
		return '<span class="tabulate list-format">' . join( $glue, $titles ) . '</span>';
	}

	protected function table_format( Table $table, $attrs ) {
		$template = new Template( 'data_table.html' );
		$template->table = $table;
		$template->links = false;
		$template->record = $table->get_default_record();
		$template->records = $table->get_records( false );
		return $template->render();
	}

}
