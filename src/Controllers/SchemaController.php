<?php

namespace WordPress\Tabulate\Controllers;

use \WordPress\Tabulate\DB\Database;
use \WordPress\Tabulate\DB\Table;
use \WordPress\Tabulate\DB\Column;
use \WordPress\Tabulate\Template;

class SchemaController extends ControllerBase {

	public function index( $args ) {
		$template = new Template( 'schema.html' );
		if ( ! current_user_can( 'promote_users' ) ) {
			$template->add_notice( 'error', 'Only administrators are allowed to edit table structure.' );
		}

		$db = new Database( $this->wpdb );
		$template->action = 'structure';
		$template->tables = $db->get_tables();
		if ( isset( $args['table'] ) ) {
			$template->table = $db->get_table( $args['table'] );
		}
		$template->xtypes = Column::get_xtypes();
		return $template->render();
	}

	public function newtable( $args ) {
		// Create table.
		$db = new Database( $this->wpdb );
		$table = $db->create_table( $args['new_table_name'] );

		// Redirect user with message.
		$template = new Template( 'schema.html' );
		$template->add_notice( 'updated', 'New table created' );
		$url = 'admin.php?page=tabulate&controller=schema&table='.$table->get_name();
		wp_redirect( admin_url( $url ) );
	}

	public function save( $args ) {
		if ( ! isset( $args['table'] ) ) {
			$url = admin_url( 'admin.php?page=tabulate&controller=schema' );
			wp_redirect( $url );
		}
		$db = new Database( $this->wpdb );
		$table = $db->get_table( $args['table'] );

		// Rename.
		$new_name = $args['table'];
		if ( $table instanceof Table && ! empty( $args['new_name'] ) ) {
			$table->rename( $args['new_name'] );
			$new_name = $table->get_name();
		}

		// Update columns.
		foreach ( $args['columns'] as $col_info ) {
			$col = $table->get_column( $col_info['old_name'] );
			if ( $col instanceof Column ) {
				$col->alter( $col_info['new_name'] );
			} else {
				//$table->add_column( $col_info['new_name'] );
			}
		}

		// Finish up.
		$template = new Template( 'schema.html' );
		$template->add_notice( 'updated', 'Schema updated.' );
		$url = admin_url( 'admin.php?page=tabulate&controller=schema&table=' . $new_name );
		wp_redirect( $url );
	}
}
