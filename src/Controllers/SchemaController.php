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
			$template->record = $template->table->get_default_record();
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
			// Validate inputs.
			$old_col_name = isset( $col_info['old_name'] ) ? $col_info['old_name'] : null;
			$new_col_name = isset( $col_info['new_name'] ) ? $col_info['new_name']: null;
			$xtype = isset( $col_info['xtype'] ) ? $col_info['xtype']: null;
			$size = isset( $col_info['size'] ) ? $col_info['size']: null;
			$nullable = isset( $col_info['nullable'] ) ? $col_info['nullable']: null;
			$default = isset( $col_info['default'] ) ? $col_info['default']: null;
			$auto_increment = isset( $col_info['auto_increment'] ) ? $col_info['auto_increment']: null;
			$unique = isset( $col_info['unique'] ) ? $col_info['unique']: null;
			$primary = isset( $col_info['primary'] ) ? $col_info['primary']: null;
			$comment = isset( $col_info['comment'] ) ? $col_info['comment']: null;
			$target_table = isset( $col_info['target_table'] ) ? $col_info['target_table']: null;
			$after = isset( $col_info['after'] ) ? $col_info['after']: null;

			// Change existing or insert new column.
			$altered = false;
			if ( $old_col_name ) {
				$col = $table->get_column( $col_info['old_name'] );
				if ( $col instanceof Column ) {
					$col->alter( $new_col_name, $xtype, $size, $nullable, $default, $auto_increment, $unique, $primary, $comment, $target_table, $after );
					$altered = true;
				}
			}
			if ( ! $altered && ! empty( $new_col_name ) ) {
				$table->add_column( $new_col_name, $xtype, $size, $nullable, $default, $auto_increment, $unique, $primary, $comment, $target_table, $after );
			}
		}

		// Finish up.
		$template = new Template( 'schema.html' );
		$template->add_notice( 'updated', 'Schema updated.' );
		$url = admin_url( 'admin.php?page=tabulate&controller=schema&table=' . $new_name );
		wp_redirect( $url );
	}
}
