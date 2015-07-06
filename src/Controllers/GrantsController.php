<?php

namespace WordPress\Tabulate\Controllers;

class GrantsController extends ControllerBase {

	/** @var array|string */
	private $table_names;

	/** @var \WordPress\Tabulate\Template */
	private $template;

	public function __construct($wpdb) {
		parent::__construct($wpdb);
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$this->table_names = $db->get_table_names();
		$this->template = new \WordPress\Tabulate\Template( 'grants.html' );
	}

	public function index() {
		$this->template->tables = $this->table_names;
		$grants = new \WordPress\Tabulate\DB\Grants();
		$this->template->roles = $grants->get_roles();
		$this->template->grants = $grants->get();
		$this->template->capabilities = $grants->get_capabilities();
		$this->template->form_action = $this->get_url( 'save' );
		return $this->template->render();
	}

	public function save() {
		$grants = new \WordPress\Tabulate\DB\Grants();

		// Validate the POSTed grants.
		$new_grants = array();
		foreach ($_POST as $table => $table_grants) {
			if ( in_array( $table, $this->table_names ) ) {
				$new_grants[$table] = array();
				foreach ($table_grants as $capability => $roles) {
					if ( in_array( $capability, $grants->get_capabilities() ) ) {
						$new_grants[$table][$capability] = array_keys($roles);
					}
				}
			}
		}

		// Save the grants and return to the granting table.
		$grants->set( $new_grants );
		$this->template->add_notice( 'updated', 'Grants saved.' );
		wp_redirect($this->get_url( 'index' ) );
		exit;
	}

	/**
	 * Get the URL of the grants' admin page.
	 * @param string $action Either 'save' or 'index'.
	 * @return string
	 */
	public function get_url( $action ) {
		return admin_url( 'admin.php?page=tabulate&controller=grants&action=' . $action );
	}
}
