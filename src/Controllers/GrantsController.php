<?php
/**
 * This file contains only a single class.
 *
 * @package Tabulate
 */

namespace WordPress\Tabulate\Controllers;

/**
 * The GrantsController enables viewing and saving of grants.
 */
class GrantsController extends ControllerBase {

	/**
	 * The list of tables.
	 *
	 * @var string[]
	 */
	private $table_names;

	/**
	 * The Template.
	 *
	 * @var \WordPress\Tabulate\Template
	 */
	private $template;

	/**
	 * Prevent non-admin users from doing anything here (i.e. redirect and exit
	 * instead). Otherwise, setup the list of tables and the template.
	 *
	 * @param \wpdb $wpdb The global wpdb object.
	 */
	public function __construct( $wpdb ) {
		parent::__construct( $wpdb );
		if ( ! current_user_can( 'promote_users' ) ) {
			$url = admin_url( 'admin.php?page=tabulate' );
			wp_safe_redirect( $url );
			exit;
		}
		$db = new \WordPress\Tabulate\DB\Database( $this->wpdb );
		$this->table_names = $db->get_table_names();
		$this->template = new \WordPress\Tabulate\Template( 'grants.html' );
	}

	/**
	 * Get the HTML table of grants.
	 *
	 * @return string
	 */
	public function index() {
		$this->template->tables = $this->table_names;
		$grants = new \WordPress\Tabulate\DB\Grants();
		$this->template->roles = $grants->get_roles();
		$this->template->grants = $grants->get();
		$this->template->capabilities = $grants->get_capabilities();
		$this->template->form_action = $this->get_url( 'save' );
		return $this->template->render();
	}

	/**
	 * Save the POSTed grants array.
	 */
	public function save() {
		check_admin_referer( 'tabulate-grants' );
		$grants = new \WordPress\Tabulate\DB\Grants();

		// Validate the POSTed grants.
		$new_grants = array();
		foreach ( $_POST as $table => $table_grants ) {
			if ( in_array( $table, $this->table_names, true ) ) {
				$new_grants[ $table ] = array();
				foreach ( $table_grants as $capability => $roles ) {
					if ( in_array( $capability, $grants->get_capabilities(), true ) ) {
						$new_grants[ $table ][ $capability ] = array_keys( $roles );
					}
				}
			}
		}

		// Save the grants and return to the granting table.
		$grants->set( $new_grants );
		$this->template->add_notice( 'updated', 'Grants saved.' );
		wp_safe_redirect( $this->get_url( 'index' ) );
		exit;
	}

	/**
	 * Get the URL of the grants' admin page.
	 *
	 * @param string $action Either 'save' or 'index'.
	 * @return string
	 */
	public function get_url( $action ) {
		return admin_url( 'admin.php?page=tabulate&controller=grants&action=' . $action );
	}
}
