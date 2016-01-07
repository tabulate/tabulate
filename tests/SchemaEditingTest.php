<?php

use \WordPress\Tabulate\DB\Column;

class SchemaEditingTest extends TestBase {

	public function setUp() {
		parent::setUp();
		// Let the current user do anything.
		global $current_user;
		$current_user->add_cap( 'promote_users' );
	}

	public function tearDown() {
		$this->wpdb->query( "DROP TABLE IF EXISTS `testing_table`;" );
		$this->wpdb->query( 'DROP TABLE IF EXISTS `new_table`;' );
		$this->wpdb->query( 'DROP TABLE IF EXISTS `new_table_2`;' );
		parent::tearDown();
	}

	/**
	 * @testdox It is possible to rename a table.
	 * @test
	 */
	public function rename_table() {
		$test_table = $this->db->get_table( 'test_table' );
		$test_table->rename( 'testing_table' );
		$testing_table = $this->db->get_table( 'testing_table' );
		$this->assertEquals( 'testing_table', $testing_table->get_name() );
		$this->assertEquals( 'testing_table', $test_table->get_name() );
		$this->assertFalse( $this->db->get_table( 'test_table' ) );
	}

	/**
	 * @testdox When renaming a table, its history comes along with it.
	 * @test
	 */
	public function rename_table_history() {
		// Create a record in the table and check its history size.
		$test_table = $this->db->get_table( 'test_table' );
		$rec1 = $test_table->save_record( array( 'title' => 'Testing' ) );
		$this->assertEquals( 1, $rec1->id() );
		$this->assertCount( 4, $rec1->get_changes() );

		// Rename the table, and make sure the history is the same size.
		$test_table->rename( 'testing_table' );
		$testing_table = $this->db->get_table( 'testing_table' );
		$rec2 = $testing_table->get_record( 1 );
		$this->assertCount( 4, $rec2->get_changes() );
	}

	/**
	 * @testdox It should be possible to 'rename' to the same name (nothing should happen).
	 * @test
	 */
	public function rename_to_same_name() {
		$test_table = $this->db->get_table( 'test_table' );
		$test_table->rename( 'test_table' );
		$this->assertEquals( 'test_table', $test_table->get_name() );
	}

	/**
	 * @testdox When creating a table, we first create a minumum table.
	 * @test
	 */
	public function create_table() {
		// Basic one without comment.
		$table = $this->db->create_table( 'new_table' );
		$this->assertInstanceOf( '\WordPress\Tabulate\DB\Table', $table );
		$this->assertContains( 'id', array_keys( $table->get_columns() ) );

		// Now with a comment, and other basics.
		$table2 = $this->db->create_table( 'new_table_2', 'The comment text' );
		$this->assertEquals( 'The comment text', $table2->get_comment() );
		$this->assertCount( 1, $table2->get_columns() );
		$this->assertTrue( $table2->get_column( 'id' )->is_primary_key() );
	}

	/**
	 * @testdox Build column definition statements.
	 * @test
	 * data_type [NOT NULL | NULL] [DEFAULT default_value]
	 * [AUTO_INCREMENT] [UNIQUE [KEY] | [PRIMARY] KEY]
	 * [COMMENT 'string']
	 * [COLUMN_FORMAT {FIXED|DYNAMIC|DEFAULT}]
	 * [STORAGE {DISK|MEMORY|DEFAULT}]
	 * [reference_definition]
	 */
	public function column_definitions() {
		$def1 = Column::get_column_definition('test_name', 'text_short');
		$this->assertEquals('`test_name` VARCHAR(200) NULL DEFAULT NULL', $def1);

		$def2 = Column::get_column_definition('test_name', 'text_short', 40, false);
		$this->assertEquals('`test_name` VARCHAR(40) NOT NULL', $def2);

		$def3 = Column::get_column_definition('test_name', 'text_long', null, true, 'Test');
		$this->assertEquals("`test_name` TEXT NULL DEFAULT 'Test'", $def3);

		$def4 = Column::get_column_definition('ident', 'integer', 5, false, '', true, true, true, 'The Ident');
		$this->assertEquals("`ident` INT(5) NOT NULL AUTO_INCREMENT UNIQUE COMMENT 'The Ident'", $def4);
	}

	/**
	 * @testdox Alter a column.
	 * @test
	 */
	public function alter_column() {
		$table = $this->db->create_table( 'new_table' );
		// Check the initial state of the table.
		$this->assertContains( 'id', array_keys( $table->get_columns() ) );
		$id_col = $table->get_column( 'id' );
		$this->assertEquals( 'integer', $id_col->get_xtype()['name'] );
		$this->assertEquals( 10, $id_col->get_size() );
		$this->assertTrue( $id_col->is_primary_key() );
		$this->assertTrue( $id_col->is_auto_increment() );

		// Make a change.
		$table->get_column( 'id' )->alter( 'identifier' );

		// Check the change.
		$this->assertCount( 1, $table->get_columns() );
		$this->assertContains( 'identifier', array_keys( $table->get_columns() ) );
		$this->assertNotContains( 'id', array_keys( $table->get_columns() ) );
		$identifier_col = $table->get_column( 'identifier' );
		$this->assertEquals( 'integer', $table->get_column( 'identifier' )->get_xtype()['name'] );
		$this->assertEquals( 10, $identifier_col->get_size() );
		$this->assertTrue( $identifier_col->is_primary_key() );
		$this->assertTrue( $identifier_col->is_auto_increment() );
	}

}
