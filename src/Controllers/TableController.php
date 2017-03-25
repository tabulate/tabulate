<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

namespace WordPress\Tabulate\Controllers;

use \WordPress\Tabulate\Util;
use \WordPress\Tabulate\DB\Grants;
use \WordPress\Tabulate\DB\Database;
use \WordPress\Tabulate\DB\Table;
use \WordPress\Tabulate\CSV;

/**
 * The table controller handles viewing, exporting, and importing table data.
 */
class TableController extends ControllerBase {

	/**
	 * Get a Table object for a given table, or an error message and the
	 * Tabulate overview page.
	 *
	 * @param string $table_name The name of the table to get.
	 * @return Table|string The table, or an HTML error message.
	 */
	protected function get_table( $table_name ) {
		$db = new Database( $this->wpdb );
		$db->set_filesystem( $this->filesystem );
		$table = $db->get_table( $table_name );
		if ( ! $table ) {
			add_action( 'admin_notices', function() use ( $table_name ) {
				// Translators: Error message shown when the table can not be found.
				$err = __( 'Table "%s" not found.', 'tabulate' );
				echo "<div class='error'><p>" . sprintf( $err, $table_name ) . "</p></div>";
			} );
			$home = new HomeController( $this->wpdb );
			return $home->index();
		}
		return $table;
	}

	/**
	 * View and search a table's data.
	 *
	 * @param string[] $args The request arguments.
	 * @return string
	 */
	public function index( $args ) {
		$table = $this->get_table( $args['table'] );
		if ( ! $table instanceof Table ) {
			return $table;
		}

		// Pagination.
		$page_num = (isset( $args['p'] ) && is_numeric( $args['p'] ) ) ? abs( $args['p'] ) : 1;
		$table->set_current_page_num( $page_num );
		if ( isset( $args['psize'] ) ) {
			$table->set_records_per_page( $args['psize'] );
		}

		// Ordering.
		if ( isset( $args['order_by'] ) ) {
			$table->set_order_by( $args['order_by'] );
		}
		if ( isset( $args['order_dir'] ) ) {
			$table->set_order_dir( $args['order_dir'] );
		}

		// Filters.
		$filter_param = (isset( $args['filter'] )) ? $args['filter'] : array();
		$table->add_filters( $filter_param );

		// Give it all to the template.
		$template = new \WordPress\Tabulate\Template( 'table.html' );
		$template->controller = 'table';
		$template->table = $table;
		$template->columns = $table->get_columns();
		$template->sortable = true;
		$template->record = $table->get_default_record();
		$template->records = $table->get_records();
		return $template->render();
	}

	/**
	 * This action is for importing a single CSV file into a single database table.
	 * It guides the user through the four stages of importing:
	 * uploading, field matching, previewing, and doing the actual import.
	 * All of the actual work is done in [WebDB_File_CSV].
	 *
	 * 1. In the first stage, a CSV file is **uploaded**, validated, and moved to a temporary directory.
	 *    The file is then accessed from this location in the subsequent stages of importing,
	 *    and only deleted upon either successful import or the user cancelling the process.
	 *    (The 'id' parameter of this action is the identifier for the uploaded file.)
	 * 2. Once a valid CSV file has been uploaded,
	 *    its colums are presented to the user to be **matched** to those in the database table.
	 *    The columns from the database are presented first and the CSV columns are matched to these,
	 *    rather than vice versa,
	 *    because this way the user sees immediately what columns are available to be imported into.
	 * 3. The column matches are then used to produce a **preview** of what will be added to and/or changed in the database.
	 *    All columns from the database are shown (regardless of whether they were in the import) and all rows of the import.
	 *    If a column is not present in the import the database will (obviously) use the default value if there is one;
	 *    this will be shown in the preview.
	 * 4. When the user accepts the preview, the actual **import** of data is carried out.
	 *    Rows are saved to the database using the usual Table::save() method
	 *    and a message presented to the user to indicate successful completion.
	 *
	 * @param string[] $args The request parameters.
	 * @return string
	 */
	public function import( $args ) {
		$template = new \WordPress\Tabulate\Template( 'import.html' );
		// Set up the progress bar.
		$template->stages = array(
			'choose_file',
			'match_fields',
			'preview',
			'complete_import',
		);
		$template->stage = 'choose_file';

		// First make sure the user is allowed to import data into this table.
		$table = $this->get_table( $args['table'] );
		$template->record = $table->get_default_record();
		$template->action = 'import';
		$template->table = $table;
		$template->maxsize = size_format( wp_max_upload_size() );
		if ( ! Grants::current_user_can( Grants::IMPORT, $table->get_name() ) ) {
			$template->add_notice( 'error', 'You do not have permission to import data into this table.' );
			return $template->render();
		}

		/*
		 * Stage 1 of 4: Uploading.
		 */
		require_once ABSPATH . '/wp-admin/includes/file.php';
		$template->form_action = $table->get_url( 'import' );
		try {
			$hash = isset( $_GET['hash'] ) ? $_GET['hash'] : false;
			$uploaded = false;
			if ( isset( $_FILES['file'] ) ) {
				check_admin_referer( 'import-upload' );
				$uploaded = wp_handle_upload( $_FILES['file'], array(
					'action' => $template->action,
				) );
			}
			$csv_file = new CSV( $this->filesystem, $hash, $uploaded );
		} catch ( \Exception $e ) {
			$template->add_notice( 'error', $e->getMessage() );
			return $template->render();
		}

		/*
		 * Stage 2 of 4: Matching fields.
		 */
		if ( $csv_file->loaded() ) {
			$template->file = $csv_file;
			$template->stage = $template->stages[1];
			$template->form_action .= "&hash=" . $csv_file->hash;
		}

		/*
		 * Stage 3 of 4: Previewing.
		 */
		if ( $csv_file->loaded() && isset( $_POST['preview'] ) ) {
			check_admin_referer( 'import-preview' );
			$template->stage = $template->stages[2];
			$template->columns = wp_json_encode( $_POST['columns'] );
			$errors = array();
			// Make sure all required columns are selected.
			foreach ( $table->get_columns() as $col ) {
				// Handle missing columns separately; other column errors are
				// done in the CSV class. Missing columns don't matter if importing
				// existing records.
				$is_missing = empty( $_POST['columns'][ $col->get_name() ] );
				$pk = $table->get_pk_column();
				$pk_present = $pk && isset( $_POST['columns'][ $pk->get_name() ] );
				if ( ! $pk_present && $col->is_required() && $is_missing ) {
					$errors[] = array(
						'column_name' => '',
						'column_number' => '',
						'field_name' => $col->get_name(),
						'row_number' => 'N/A',
						'messages' => array( 'Column required, but not found in CSV' ),
					);
				}
			}
			$template->errors = empty( $errors ) ? $csv_file->match_fields( $table, wp_unslash( $_POST['columns'] ) ) : $errors;
		}

		/*
		 * Stage 4 of 4: Import.
		 */
		if ( $csv_file->loaded() && isset( $_POST['import'] ) ) {
			check_admin_referer( 'import-finish' );
			$template->stage = $template->stages[3];
			$this->wpdb->query( 'BEGIN' );
			$column_map = json_decode( wp_unslash( $_POST['columns'] ), true );
			$result = $csv_file->import_data( $table, $column_map );
			$this->wpdb->query( 'COMMIT' );
			$template->add_notice( 'updated', 'Import complete; ' . $result . ' rows imported.' );
		}

		return $template->render();
	}

	/**
	 * A calendar for tables with a date column.
	 *
	 * @param string[] $args The request parameters.
	 * @return string The calendar HTML.
	 */
	public function calendar( $args ) {
		// @todo Validate args.
		$year_num = (isset( $args['year'] )) ? $args['year'] : date( 'Y' );
		$month_num = (isset( $args['month'] )) ? $args['month'] : date( 'm' );

		$template = new \WordPress\Tabulate\Template( 'calendar.html' );
		$table = $this->get_table( $args['table'] );

		$template->table = $table;
		$template->action = 'calendar';
		$template->record = $table->get_default_record();

		$factory = new \CalendR\Calendar();
		$template->weekdays = $factory->getWeek( new \DateTime( 'Monday this week' ) );
		$month = $factory->getMonth( new \DateTime( $year_num . '-' . $month_num . '-01' ) );
		$template->month = $month;
		$records = array();
		foreach ( $table->get_columns( 'date' ) as $date_col ) {
			$date_col_name = $date_col->get_name();
			// Filter to the just the requested month.
			$table->add_filter( $date_col_name, '>=', $month->getBegin()->format( 'Y-m-d' ) );
			$table->add_filter( $date_col_name, '<=', $month->getEnd()->format( 'Y-m-d' ) );
			foreach ( $table->get_records() as $rec ) {
				$date_val = $rec->$date_col_name();
				// Initialise the day's list of records.
				if ( ! isset( $records[ $date_val ] ) ) {
					$records[ $date_val ] = array();
				}
				// Add this record to the day's list.
				$records[ $date_val ][] = $rec;
			}
		}
		// $records is grouped by date, with each item in a single date being
		// an array with 'record' and 'column' keys.
		$template->records = $records;

		return $template->render();
	}

	/**
	 * Export the current table with the current filters applied.
	 * Filters are passed as request parameters, just as for the index action.
	 *
	 * @param string[] $args The request parameters.
	 * @return void
	 */
	public function export( $args ) {
		// Get table.
		$table = $this->get_table( $args['table'] );

		// Filter and export.
		$filter_param = ( isset( $args['filter'] )) ? $args['filter'] : array();
		$table->add_filters( $filter_param );
		$filename = $table->export();

		// Send CSV to client.
		$download_name = date( 'Y-m-d' ) . '_' . $table->get_name() . '.csv';
		header( 'Content-Encoding: UTF-8' );
		header( 'Content-type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
		echo "\xEF\xBB\xBF";
		echo $table->get_database()->get_filesystem()->get_contents( $filename );
		exit;
	}

	/**
	 * Download a CSV of given titles that could not be found in this table.
	 *
	 * @param string[] $args The request parameters.
	 */
	public function notfound( $args ) {
		// Get table.
		$table = $this->get_table( $args['table'] );

		// Get the values from the request, or give up.
		$filter_id = isset( $args['notfound'] ) ? $args['notfound'] : false;
		$values_string = isset( $args['filter'][ $filter_id ]['value'] ) ? $args['filter'][ $filter_id ]['value'] : false;
		if ( ! $table instanceof Table || ! $values_string ) {
			return;
		}
		$values = Util::split_newline( $values_string );

		// Find all values that exist.
		$title_col = $table->get_title_column();
		$table->add_filter( $title_col, 'in', $values_string );

		// And remove them from the list of supplied values.
		$recs = $table->get_records( false );
		foreach ( $recs as $rec ) {
			$key = array_search( $rec->get_title(), $values, true );
			if ( false !== $key ) {
				unset( $values[ $key ] );
			}
		}

		$download_name = date( 'Y-m-d' ) . '_' . $table->get_name() . '_not_found.csv';
		header( 'Content-Encoding: UTF-8' );
		header( 'Content-type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
		echo "\xEF\xBB\xBF";
		echo $title_col->get_title() . "\n" . join( "\n", $values );
		exit;
	}

	/**
	 * Display a horizontal timeline of any table with a date field.
	 *
	 * @param string[] $args Request arguments.
	 * @return string
	 */
	public function timeline( $args ) {
		$table = $this->get_table( $args['table'] );
		$template = new \WordPress\Tabulate\Template( 'timeline.html' );
		$template->action = 'timeline';
		$template->table = $table;
		$start_date_arg = (isset( $args['start_date'] )) ? $args['start_date'] : date( 'Y-m-d' );
		$end_date_arg = (isset( $args['end_date'] )) ? $args['end_date'] : date( 'Y-m-d' );
		$start_date = new \DateTime( $start_date_arg );
		$end_date = new \DateTime( $end_date_arg );
		if ( $start_date->diff( $end_date, true )->d < 7 ) {
			// Add two weeks to the end date.
			$end_date->add( new \DateInterval( 'P14D' ) );
		}
		$date_period = new \DatePeriod( $start_date, new \DateInterval( 'P1D' ), $end_date );
		$template->start_date = $start_date->format( 'Y-m-d' );
		$template->end_date = $end_date->format( 'Y-m-d' );
		$template->date_period = $date_period;
		$data = array();
		foreach ( $table->get_records( false ) as $record ) {
			if ( ! isset( $data[ $record->get_title() ] ) ) {
				$data[ $record->get_title() ] = array();
			}
			$data[ $record->get_title() ][] = $record;
		}
		$template->data = $data;
		return $template->render();
	}
}
