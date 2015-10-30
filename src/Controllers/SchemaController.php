<?php

namespace WordPress\Tabulate\Controllers;

use WordPress\Tabulate\DB\Database;

class SchemaController extends ControllerBase {

	public function index( $args ) {
		$db = new Database( $this->wpdb );
		$tables = $db->get_tables();
		$template = new \WordPress\Tabulate\Template('schema.html');
		$template->tables = $tables;
		if (isset($args['schema'])) {
			$template->schema = $db->get_table($args['schema']);
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

	public function save( $args ) {
		if ( ! isset( $args['schema'] ) ) {
			$url = admin_url( 'admin.php?page=tabulate_schema' );
			wp_redirect( $url );
		}
		$db = new Database( $this->wpdb );
		$schema = $db->get_table( $args['schema'] );
		if ( ! empty( $args['new_name'] ) ) {
			$schema->rename( $args['new_name'] );
		}
		$template = new \WordPress\Tabulate\Template('schema.html');
		$template->add_notice( 'updated', 'Schema updated.' );
		$url = admin_url( 'admin.php?page=tabulate_schema&schema=' . $schema->get_name() );
		wp_redirect( $url );
	}
}
