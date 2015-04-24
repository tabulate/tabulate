<?php

class TestBase extends WP_UnitTestCase {

	/** @var WordPress\Tabulate\DB\Database */
	protected $db;

	/** @var wpdb */
	protected $wpdb;

	public function setUp() {
		parent::setUp();

		// Get the database.
		global $wpdb;
		$this->wpdb = $wpdb;

		// Prevent parent from enforcing TEMPORARY tables.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		// Create some testing tables and link them together.
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_table`' );
		$this->wpdb->query( 'CREATE TABLE `test_table` ('
			. ' id INT(10) AUTO_INCREMENT PRIMARY KEY,'
			. ' title VARCHAR(100) NOT NULL,'
			. ' description TEXT NULL,'
			. ' active BOOLEAN NULL DEFAULT TRUE,'
			. ' a_date DATE NULL,'
			. ' type_id INT(10) NULL DEFAULT NULL,'
			. ' widget_size DECIMAL(10,2) NOT NULL DEFAULT 5.6'
			. ');'
		);
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_types`' );
		$this->wpdb->query( 'CREATE TABLE `test_types` ('
			. ' id INT(10) AUTO_INCREMENT PRIMARY KEY,'
			. ' title VARCHAR(100) NOT NULL'
			. ');'
		);
		$this->wpdb->query( 'ALTER TABLE `test_table` '
			. ' ADD FOREIGN KEY ( `type_id` )'
			. ' REFERENCES `test_types` (`id`)'
			. ' ON DELETE CASCADE ON UPDATE CASCADE;'
		);
		$this->db = new WordPress\Tabulate\DB\Database( $this->wpdb );
	}

	public function tearDown() {
		$this->wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		$this->wpdb->query( 'DROP TABLE `test_types`' );
		$this->wpdb->query( 'DROP TABLE `test_table`' );
		$this->wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
		parent::tearDown();
	}

}
