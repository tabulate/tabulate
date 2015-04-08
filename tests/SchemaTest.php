<?php

class SchemaTest extends WP_UnitTestCase {

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
		$this->wpdb->query( 'CREATE TABLE `test_table` ('
			. ' id INT(10) PRIMARY KEY,'
			. ' title VARCHAR(100) NOT NULL,'
			. ' description TEXT NULL,'
			. ' active BOOLEAN NULL DEFAULT TRUE,'
			. ' a_date DATE NULL,'
			. ' type_id INT(10) NULL DEFAULT NULL'
			. ');'
		);
		$this->wpdb->query( 'CREATE TABLE `test_types` ('
			. ' id INT(10) PRIMARY KEY,'
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

	/**
	 * @testdox Tabulate lists all tables in the database (by default, only for users with the 'promote_users' capability).
	 * @test
	 */
	public function no_access() {
		global $current_user;

		// Make sure they can't see anything yet.
		$this->assertFalse( current_user_can( 'promote_users' ) );
		$this->assertEmpty( $this->db->get_table_names() );

		// Now promote them, and try again.
		$current_user->add_cap( 'promote_users' );
		$this->assertTrue( current_user_can( 'promote_users' ) );
		$table_names = $this->db->get_table_names();
		// A WP core table.
		$this->assertContains( $this->wpdb->prefix . 'posts', $table_names );
		// And one of ours.
		$this->assertContains( 'test_table', $table_names );
	}

	/**
	 * @testdox Tables can be linked to each other; one is the referenced table, the other the referencing.
	 * @test
	 */
	public function references() {
		// That test_table references test_types
		$test_table = $this->db->get_table( 'test_table' );
		$referenced_tables = $test_table->get_referenced_tables( true );
		$referenced_table = array_pop( $referenced_tables );
		$this->assertEquals( 'test_types', $referenced_table->get_name() );

		// And the other way around.
		$type_table = $this->db->get_table( 'test_types' );
		$referencing_tables = $type_table->get_referencing_tables();
		$referencing_table = array_pop( $referencing_tables );
		$this->assertEquals( 'test_table', $referencing_table->get_name() );
	}

}
