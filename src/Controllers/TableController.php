<?php

namespace WordPress\Tabulate\Controllers;

use \WordPress\Tabulate\DB\Grants;
use \WordPress\Tabulate\DB\Database;

class TableController extends ControllerBase {

	private function get_table( $table_name ) {
		$db = new Database( $this->wpdb );
		$table = $db->get_table( $table_name );
		if ( ! $table ) {
			throw new \Exception( "Table '$table' not found." );
		}
		return $table;
	}

	public function index( $args ) {
		$table = $this->get_table( $args[ 'table' ] );

		// Pagination.
		$page_num = (isset( $args[ 'p' ] )) ? $args[ 'p' ] : 1;
		$table->set_current_page_num( $page_num );
		$table->set_records_per_page( 20 );

		// Filters.
		$filter_param = (isset( $args[ 'filter' ] )) ? $args[ 'filter' ] : array();
		$table->add_filters( $filter_param );
		$filters = $table->get_filters();
		$filters[] = array(
			'column' => $table->get_title_column()->get_name(),
			'operator' => 'like',
			'value' => ''
		);

		// Give it all to the template.
		$template = new \WordPress\Tabulate\Template( 'table.html' );
		$template->controller = 'table';
		$template->table = $table;
		$template->columns = $table->get_columns();
		$template->operators = $table->get_operators();
		$template->filters = $filters;
		$template->filter_count = count( $filters );
		$template->record = $table->get_default_record();
		$template->records = $table->get_records();
		$template->record_count = $table->count_records();
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
	 *    Rows are saved to the database using the usual [WebDB_DBMS_Table::save()](api/Webdb_DBMS_Table#save_row),
	 *    and a message presented to the user to indicate successful completion.
	 *
	 * @return void
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
		$template->maxsize = size_format(wp_max_upload_size());
		if ( ! Grants::current_user_can( Grants::IMPORT, $table->get_name() ) ) {
			$template->add_notice( 'error', 'You do not have permission to import data into this table.' );
			return $template->render();
		}

		/*
		 * Stage 1 of 4: Uploading.
		 */
		$template->form_action = $table->get_url( 'import' );
		try {
			$hash = isset( $_GET[ 'hash' ] ) ? $_GET[ 'hash' ] : false;
			$uploaded = isset( $_FILES['file'] ) ? wp_handle_upload( $_FILES['file'] ) : false;
			$csv_file = new \WordPress\Tabulate\CSV( $hash, $uploaded );
		} catch ( \Exception $e ) {
			$template->add_notice( 'error', $e->getMessage() );
			return $template->render();
		}

		/*
		 * Stage 2 of 4: Matching fields
		 */
		if ( $csv_file->loaded() ) {
			$template->file = $csv_file;
			$template->stage = $template->stages[ 1 ];
			$template->form_action .= "&hash=" . $csv_file->hash;
		}

		/*
		 * Stage 3 of 4: Previewing
		 */
		if ( $csv_file->loaded() AND isset( $_POST[ 'preview' ] ) ) {
			$template->stage = $template->stages[ 2 ];
			$template->columns = serialize( $_POST[ 'columns' ] );
			$errors = array();
			// Make sure all required columns are selected
			foreach ( $table->get_columns() as $col ) {
				// Handle missing columns separately; other column errors are
				// done in the CSV class. Missing columns don't matter if importing
				// existing records.
				$missing = empty( $_POST['columns'][ $col->get_name() ] );
				$pk_present = isset( $_POST['columns'][ $table->get_pk_column()->get_name() ] );
				if ( ! $pk_present && $col->is_required() && $missing ) {
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
		 * Stage 4 of 4: Import
		 */
		if ( $csv_file->loaded() AND isset( $_POST['import'] ) ) {
			$template->stage = $template->stages[ 3 ];
			$this->wpdb->query( 'BEGIN' );
			$result = $csv_file->import_data( $table, unserialize( wp_unslash( $_POST['columns'] ) ) );
			$this->wpdb->query( 'COMMIT' );
			$template->add_notice( 'updated', 'Import complete; ' . $result . ' rows imported.' );
		}

		return $template->render();
	}

	public function calendar( $args ) {
		// @todo Validate args.
		$yearNum = (isset( $args['year'] )) ? $args['year'] : date( 'Y' );
		$monthNum = (isset( $args['month'] )) ? $args['month'] : date( 'm' );

		$template = new \WordPress\Tabulate\Template( 'calendar.html' );
		$table = $this->get_table( $args['table'] );

		$template->table = $table;
		$template->action = 'calendar';
		$template->record = $table->get_default_record();

		$factory = new \CalendR\Calendar();
		$template->weekdays = $factory->getWeek( new \DateTime( 'Monday this week' ) );
		$month = $factory->getMonth( new \DateTime( $yearNum . '-' . $monthNum . '-01' ) );
		$template->month = $month;
		$records = array();
		foreach ( $table->get_columns( 'date' ) as $dateCol ) {
			$dateColName = $dateCol->get_name();
			// Filter to the just the requested month.
			$table->add_filter( $dateColName, '>=', $month->getBegin()->format( 'Y-m-d' ) );
			$table->add_filter( $dateColName, '<=', $month->getEnd()->format( 'Y-m-d' ) );
			foreach ( $table->get_records() as $rec ) {
				$dateVal = $rec->$dateColName();
				// Initialise the day's list of records.
				if ( ! isset( $records[ $dateVal ] ) ) {
					$records[ $dateVal ] = array();
				}
				// Add this record to the day's list.
				$records[ $dateVal ][] = $rec;
			}
		}
		// $records is grouped by date, with each item in a single date being
		// an array like: ['record'=>Record, 'column'=>$name_of_date_column]
		$template->records = $records;

		return $template->render();
	}

	/**
	 * Export the current table with the current filters applied.
	 * Filters are passed as $_GET parameters, just as for the index action.
	 *
	 * @return void
	 */
	public function export( $args )
	{
		// Get database and table.
		$table = $this->get_table( $args['table'] );

		// Filter and export.
		$filter_param = (isset( $args[ 'filter' ] )) ? $args[ 'filter' ] : array();
		$table->add_filters( $filter_param );
		$filename = $table->export();

		// Send CSV to client.
		$download_name = date('Y-m-d').'_'.$table->get_name().'.csv';
		header('Content-Encoding: UTF-8');
		header('Content-type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="'.$download_name.'"');
		echo "\xEF\xBB\xBF";
		readfile($filename);
		exit( 0 );
	}

}
