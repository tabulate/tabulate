<?php
/**
 * This file contains only one class.
 *
 * @package Tabulate
 * @file
 */

namespace WordPress\Tabulate\Controllers;

use WordPress\Tabulate\DB\Database;
use WordPress\Tabulate\DB\Table;
use WordPress\Tabulate\DB\Grants;
use WordPress\Tabulate\Template;

/**
 * The shortcode controller.
 */
class ShortcodeController extends ControllerBase {

	/**
	 * The Database object.
	 *
	 * @var \WordPress\Tabulate\DB\Database
	 */
	private $db;

	/**
	 * Create the controller and dequeue scripts.
	 *
	 * @param \wpdb $wpdb The global wpdb object.
	 */
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
	 *
	 * @param string[] $raw_attrs The shortcode attributes.
	 * @return string
	 */
	public function run( $raw_attrs ) {
		$defaults = array(
			'format' => 'table',
			'table' => null,
			'ident' => null,
			'search' => null,
		);
		$attrs = shortcode_atts( $defaults, $raw_attrs );
		if ( ! isset( $attrs['table'] ) ) {
			$msg = "The 'table' attribute must be set. Attributes found: [";
			foreach ( $raw_attrs as $k => $v ) {
				$msg .= ' ' . htmlentities2( $k ) . ' = "' . htmlentities2( $v ) . '" ';
			}
			$msg .= "]";
			return $this->error( $msg );
		}
		$table = $this->db->get_table( $attrs['table'] );
		if ( ! $table ) {
			if ( ! is_user_logged_in() ) {
				return $this->error( "You are not logged in. " . wp_loginout( get_the_permalink(), false ) );
			}
			return $this->error();
		}
		$format_method = $attrs['format'] . '_format';
		if ( is_callable( array( $this, $format_method ) ) ) {
			wp_enqueue_script( 'tabulate-scripts' );
			return $this->$format_method( $table, $attrs, $_REQUEST );
		} else {
			return $this->error( "Format '{$attrs['format']}' not available." );
		}
	}

	/**
	 * Get a formatted error message.
	 *
	 * @param string $message The error message to display.
	 * @return string The error HTML.
	 */
	protected function error( $message = '' ) {
		$url = "https://tabulate.readthedocs.io/en/latest/shortcode.html";
		return "<div class='tabulate shortcode-error'>"
			. "<h3>Tabulate shortcode error:</h3> "
			. "<p class='message'>$message</p>"
			. "<p>For more information, please "
			. "<a href='$url' target='_blank' title='Opens in new tab'>read the docs</a>."
			. "</p></div>";
	}

	/**
	 * The 'record' format.
	 *
	 * @param Table    $table The table to display.
	 * @param string[] $attrs The shortcode attributes.
	 * @param string   $query The query parameters.
	 * @return string
	 */
	protected function record_format( Table $table, $attrs, $query = null ) {
		// Check for the ident shortcode parameter...
		if ( isset( $attrs['ident'] ) ) {
			$ident = $attrs['ident'];
		}
		// ...or the tablename=ident URL parameter.
		if ( isset( $query[ $table->get_name() ] ) && is_scalar( $query[ $table->get_name() ] ) ) {
			$ident = $query[ $table->get_name() ];
		}
		if ( ! isset( $ident ) ) {
			return $this->error( __( 'No record identifier could be determined.', 'tabulate' ) );
		}

		// Get the record.
		$record = $table->get_record( $ident );
		if ( false === $record ) {
			return $this->error( __( 'No record found.', 'tabulate' ) );
		}
		$template = new Template( 'record/view.html' );
		$template->table = $table;
		$template->record = $record;
		return $template->render();
	}

	/**
	 * The 'form' format.
	 *
	 * @param Table    $table The table to display.
	 * @param string[] $attrs The shortcode attributes.
	 * @return string
	 */
	protected function form_format( Table $table, $attrs ) {
		if ( ! Grants::current_user_can( Grants::CREATE, $table ) ) {
			return 'You do not have permission to create ' . $table->get_title() . ' records.';
		}
		$template = new Template( 'record/shortcode.html' );
		$template->table = $table;
		$template->record = $table->get_default_record();
		$template->return_to = ( isset( $attrs['return_to'] ) ) ? $attrs['return_to'] : get_the_permalink();
		return $template->render();
	}

	/**
	 * The 'count' format.
	 *
	 * @param Table $table The table to display.
	 * @return string
	 */
	protected function count_format( Table $table ) {
		$count = number_format( $table->count_records() );
		return '<span class="tabulate count-format">' . $count . '</span>';
	}

	/**
	 * The 'list' format.
	 *
	 * @param Table    $table The table to display.
	 * @param string[] $attrs The shortcode attributes.
	 * @return string
	 */
	protected function list_format( Table $table, $attrs ) {
		$titles = array();
		foreach ( $table->get_records() as $rec ) {
			$titles[] = $rec->get_title();
		}
		$glue = ( ! empty( $attrs['glue'] ) ) ? $attrs['glue'] : ', ';
		return '<span class="tabulate list-format">' . join( $glue, $titles ) . '</span>';
	}

	/**
	 * The 'table' format.
	 *
	 * @param Table    $table The table to display.
	 * @param string[] $attrs The shortcode attributes.
	 * @param string   $query The query parameters.
	 * @return string
	 */
	protected function table_format( Table $table, $attrs, $query = null ) {
		// Filters.
		// Apply filters from the URL query parameters.
		if ( isset( $query['table'] ) && $query['table'] === $table->get_name() ) {
			$query_filters = (isset( $query['filter'] )) ? $query['filter'] : array();
			$table->add_filters( $query_filters );
		}

		// Pagination.
		$page_num = 1;
		if ( isset( $query['tabulate_p'] ) && is_numeric( $query['tabulate_p'] ) ) {
			$page_num = abs( $query['tabulate_p'] );
		}
		$table->set_current_page_num( $page_num );
		if ( isset( $query['tabulate_psize'] ) ) {
			$table->set_records_per_page( $query['tabulate_psize'] );
		}

		// Construct the HTML.
		$template = new Template( 'table/shortcode.html' );
		$template->table = $table;
		$template->record = $table->get_default_record();
		$template->records = $table->get_records();

		// Add the search form if required.
		$template->search = ! empty( $attrs['search'] );
		$template->form_action = get_the_permalink();

		// Return completed HTML output.
		return $template->render();
	}
}
