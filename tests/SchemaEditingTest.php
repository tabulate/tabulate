<?php

class SchemaEditingTest extends TestBase {

	public function setUp() {
		parent::setUp();
		// Let the current user do anything.
		global $current_user;
		$current_user->add_cap( 'promote_users' );
	}

	/**
	 * @testdox It is possible to rename a table.
	 * @test
	 */
	public function renameTable() {
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
	public function renameTableHistory() {
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

	public function tearDown() {
		$this->wpdb->query( "DROP TABLE IF EXISTS `testing_table`;" );
		parent::tearDown();
	}

}
