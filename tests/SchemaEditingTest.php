<?php

class SchemaEditingTest extends TestBase {

	public function setUp() {
		parent::setUp();
		// Let the current user do anything.
		global $current_user;
		$current_user->add_cap( 'promote_users' );
	}

	public function tearDown() {
		$this->wpdb->query( "DROP TABLE IF EXISTS `testing_table`;" );
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

		// Clean up.
		$this->wpdb->query( 'DROP TABLE `new_table`' );
		$this->wpdb->query( 'DROP TABLE `new_table_2`' );
	}

}
