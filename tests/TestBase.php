<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

/**
 * The base test class, from which all tests inherit.
 */
abstract class TestBase extends WP_UnitTestCase {

	/**
	 * The Database.
	 * @var WordPress\Tabulate\DB\Database
	 */
	protected $db;

	/**
	 * The global wpdb object.
	 * @var wpdb
	 */
	protected $wpdb;

	/**
	 * Set up everything common to all Tabulate tests.
	 * @global \wpdb $wpdb
	 */
	public function setUp() {
		parent::setUp();
		global $wpdb;

		// Current a test user and make them current.
		$tester = get_user_by( 'email', 'test@example.com' );
		if ( ! $tester ) {
			$tester_id = wp_create_user( 'tester', 'test123', 'test@example.com' );
		} else {
			$tester_id = $tester->ID;
		}
		wp_set_current_user( $tester_id );

		// Get the database.
		$this->wpdb = $wpdb;

		// Prevent parent from enforcing TEMPORARY tables.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		// Activate.
		do_action( 'activate_tabulate/tabulate.php' );

		// Create some testing tables and link them together.
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_table`' );
		$this->wpdb->query( 'CREATE TABLE `test_table` ('
			. ' id INT(10) AUTO_INCREMENT PRIMARY KEY,'
			. ' title VARCHAR(100) NOT NULL,'
			. ' description TEXT NULL,'
			. ' active BOOLEAN NULL DEFAULT TRUE,'
			. ' a_date DATE NULL,'
			. ' a_year YEAR NULL,'
			. ' type_id INT(10) NULL DEFAULT NULL,'
			. ' widget_size DECIMAL(10,2) NOT NULL DEFAULT 5.6,'
			. ' ranking INT(3) NULL DEFAULT NULL,'
			. ' a_numeric NUMERIC(7,2) NULL DEFAULT NULL COMMENT "NUMERIC is the same as DECIMAL."'
			. ');'
		);
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_types`' );
		$this->wpdb->query( 'CREATE TABLE `test_types` ('
			. ' id INT(10) AUTO_INCREMENT PRIMARY KEY,'
			. ' title VARCHAR(100) NOT NULL UNIQUE'
			. ');'
		);
		$this->wpdb->query( 'ALTER TABLE `test_table` '
			. ' ADD FOREIGN KEY ( `type_id` )'
			. ' REFERENCES `test_types` (`id`)'
			. ' ON DELETE CASCADE ON UPDATE CASCADE;'
		);
		$this->db = new WordPress\Tabulate\DB\Database( $this->wpdb );
	}

	/**
	 * Drop all created tables and uninstall Tabulate.
	 */
	public function tearDown() {
		// Remove test tables.
		$this->wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_types`' );
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_table`' );
		$this->wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );

		$ct = new \WordPress\Tabulate\DB\ChangeTracker( $this->wpdb );
		$ct->close_changeset();

		// Uninstall.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'tabulate/tabulate.php' );
		}
		require __DIR__ . '/../uninstall.php';

		parent::tearDown();
	}
}
