<?php

namespace WordPress\Tabulate\Controllers;

use WordPress\Tabulate\DB\Grants;

class RecordController extends ControllerBase {

	/**
	 * @return \WordPress\Tabulate\Template
	 */
	private function get_template( $table ) {
		$template = new \WordPress\Tabulate\Template( 'record.html' );
		$template->table = $table;
		$template->controller = 'record';
		return $template;
	}

	public function index( $args ) {
		// Get database and table.
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$table = $db->get_table( $args[ 'table' ] );

		// Give it all to the template.
		$template = $this->get_template( $table );
		if ( isset( $args[ 'ident' ] ) ) {
			$template->record = $table->get_record( $args[ 'ident' ] );
			// Check permission.
			if ( ! Grants::current_user_can( Grants::UPDATE, $table->get_name() ) ) {
				$template->add_notice( 'error', 'You do not have permission to update data in this table.' );
			}
		}
		if ( ! isset( $template->record ) ) {
			$template->record = $table->get_default_record();
			// Check permission.
			if ( ! Grants::current_user_can( Grants::CREATE, $table->get_name() ) ) {
				$template->add_notice( 'error', 'You do not have permission to create records in this table.' );
			}
		}

		echo $template->render();
	}

	public function save( $args ) {
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$table = $db->get_table( $args[ 'table' ] );
		$record_ident = isset( $args[ 'ident' ] ) ? $args[ 'ident' ] : false;
		$template = $this->get_template( $table );

		// Make sure we're not saving over an already-existing record.
		$pk_name = $table->get_pk_column()->get_name();
		$pk = $_POST[ $pk_name ];
		$existing = $table->get_record( $pk );
		if ( ! $record_ident && $existing ) {
			$template->add_notice( 'error', "A record identified by '$pk' already exists." );
		} else {
			// Otherwise, create a new one.
			try {
				$data = wp_unslash( $_POST );
				$template->record = $table->save_record( $data, $record_ident );
				$template->add_notice( 'updated', 'Record saved.' );
			} catch ( \WordPress\Tabulate\DB\Exception $e ) {
				$template->add_notice( 'error', $e->getMessage() );
				$template->record = new \WordPress\Tabulate\DB\Record( $table, $data );
			}
		}
		echo $template->render();
	}

}
