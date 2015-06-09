<?php

class ChangesetsTest extends TestBase {

	public function setUp() {
		parent::setUp();
		// Let the current user do anything.
		global $current_user;
		$current_user->add_cap( 'promote_users' );
	}

	/**
	 * @testdox Two changeset tables are created on activation.
	 * @test
	 */
	public function activate() {
		$changesets = $this->db->get_table( $this->wpdb->prefix . 'changesets' );
		$this->assertEquals( $this->wpdb->prefix . 'changesets', $changesets->get_name() );
		$changes = $this->db->get_table( $this->wpdb->prefix . 'changes' );
		$this->assertEquals( $this->wpdb->prefix . 'changes', $changes->get_name() );
	}

	/**
	 * @testdox Saving a new record creates a changeset and some changes.
	 * @test
	 */
	public function basic() {
		// test_table: { id, title }
		$test_table = $this->db->get_table( 'test_types' );
		$rec = $test_table->save_record( array( 'title' => 'One' ) );

		// Inition changeset and changes.
		$changesets = $this->db->get_table( $this->wpdb->prefix . 'changesets' );
		$this->assertEquals( 1, $changesets->count_records() );
		$changes = $this->db->get_table( $this->wpdb->prefix . 'changes' );
		$this->assertEquals( 2, $changes->count_records() );
		// Check the second change record.
		$change_rec_2 = $changes->get_record( 2 );
		$this->assertequals( 'title', $change_rec_2->column_name() );
		$this->assertNull( $change_rec_2->old_value() );
		$this->assertEquals( 'One', $change_rec_2->new_value() );

		// Modify one value, and a new changeset and one change is created.
		$test_table->save_record( array( 'title' => 'Two' ), $rec->id() );
		$this->assertEquals( 2, $changesets->count_records() );
		$this->assertEquals( 3, $changes->count_records() );

		// Check the new (3rd) change record.
		$change_rec_3 = $changes->get_record( 3 );
		$this->assertequals( 'title', $change_rec_3->column_name() );
		$this->assertequals( 'One', $change_rec_3->old_value() );
		$this->assertEquals( 'Two', $change_rec_3->new_value() );
	}

	/**
	 * @testdox A changeset can have an associated comment.
	 * @test
	 */
	public function changeset_comment() {
		$test_table = $this->db->get_table( 'test_types' );
		$rec = $test_table->save_record( [ 'title' => 'One', 'changeset_comment' => 'Testing.' ] );
		$changes = $rec->get_changes();
		$change = array_pop( $changes );
		$this->assertEquals( "Testing.", $change->comment );
	}

}
