<?php

namespace WordPress\Tabulate\Controllers;

use WordPress\Tabulate\DB\Database;
use WordPress\Tabulate\DB\Table;
use WordPress\Tabulate\Template;

class SchemaController extends ControllerBase {

	public function index( $args ) {
		$db = new Database( $this->wpdb );
		$tables = $db->get_tables();
		$template = new Template( 'schema.html' );
		$template->tables = $tables;
		$template->managed_tables = get_option(TABULATE_SLUG . '_managed_tables', array());
		if ( isset( $args['schema'] ) ) {
			$template->schema = $db->get_table( $args['schema'] );
		}
		$template->types = array(
			'varchar' => 'Text (short)',
			'text' => 'Text (long)',
			'int' => 'Number',
			'date' => 'Date',
			'fk' => 'Cross reference',
		);
		return $template->render();
	}

	public function tables( $args ) {
		// Only save table names that we've got access to (which should be all).
		$db = new Database( $this->wpdb );
		$tables = array_intersect($db->get_table_names(), $_POST['managed_tables']);
		update_option(TABULATE_SLUG . '_managed_tables', $tables);

		// Inform the user and redirect.
		$template = new Template( 'schema.html' );
		$template->add_notice( 'updated', 'Table list saved.' );
		wp_redirect( admin_url( 'admin.php?page=tabulate_schema' ) );
	}

	public function save( $args ) {
		if ( ! isset( $args['schema'] ) ) {
			$url = admin_url( 'admin.php?page=tabulate_schema' );
			wp_redirect( $url );
		}
		$db = new Database( $this->wpdb );
		$schema = $db->get_table( $args['schema'] );
		$new_name = $args['schema'];
		if ( $schema instanceof Table && ! empty( $args['new_name'] ) ) {
			$schema->rename( $args['new_name'] );
			$new_name = $schema->get_name();
		}
		$template = new Template( 'schema.html' );
		$template->add_notice( 'updated', 'Schema updated.' );
		$url = admin_url( 'admin.php?page=tabulate_schema&schema=' . $new_name );
		wp_redirect( $url );
	}
}
