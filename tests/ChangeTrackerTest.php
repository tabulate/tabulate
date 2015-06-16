<?php

use WordPress\Tabulate\DB\ChangeTracker;
use WordPress\Tabulate\DB\Grants;

class ChangeTrackerTest extends TestBase {

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
		$changesets = $this->db->get_table( $this->wpdb->prefix . TABULATE_SLUG . '_changesets' );
		$this->assertEquals( $this->wpdb->prefix . TABULATE_SLUG . '_changesets', $changesets->get_name() );
		$changes = $this->db->get_table( $this->wpdb->prefix . TABULATE_SLUG . '_changes' );
		$this->assertEquals( $this->wpdb->prefix . TABULATE_SLUG . '_changes', $changes->get_name() );
	}

	/**
	 * @testdox Saving a new record creates a changeset and some changes.
	 * @test
	 */
	public function basic() {
		// test_table: { id, title }
		$test_table = $this->db->get_table( 'test_types' );
		$rec = $test_table->save_record( array( 'title' => 'One' ) );

		// Initial changeset and changes.
		$changes1 = $rec->get_changes();
		$this->assertCount( 2, $changes1 );
		// Check the second change record.
		$changes1_rec = array_pop( $changes1 );
		$this->assertequals( 'title', $changes1_rec->column_name );
		$this->assertNull( $changes1_rec->old_value );
		$this->assertEquals( 'One', $changes1_rec->new_value );

		// Modify one value, and inspect the new change record.
		$rec2 = $test_table->save_record( array( 'title' => 'Two' ), $rec->id() );
		$changes2 = $rec2->get_changes();
		$this->assertCount( 3, $changes2 );
		$changes2_rec = array_shift( $changes2 );
		$this->assertequals( 'title', $changes2_rec->column_name );
		$this->assertequals( 'One', $changes2_rec->old_value );
		$this->assertEquals( 'Two', $changes2_rec->new_value );
	}

	/**
	 * @testdox A changeset can have an associated comment.
	 * @test
	 */
	public function changeset_comment() {
		$test_types = $this->db->get_table( 'test_types' );
		$rec = $test_types->save_record( array( 'title' => 'One', 'changeset_comment' => 'Testing.' ) );
		$changes = $rec->get_changes();
		$change = array_pop( $changes );
		$this->assertEquals( "Testing.", $change->comment );
	}

	/**
	 * @testdox A user who can only create records in one table can still use the change-tracker (i.e. creating changesets is not influenced by standard grants).
	 * @test
	 */
	public function minimal_grants() {
		global $current_user;
		$current_user->remove_cap( 'promote_users' );
		$current_user->add_role( 'subscriber' );
		$grants = new Grants();
		$grants->set(
			array(
				'test_table' => array(
					Grants::READ => array( 'subscriber' ),
					Grants::CREATE => array( 'subscriber' ),
				),
			)
		);
		// Assert that the permissions are set as we want them.
		$this->assertTrue( Grants::current_user_can( Grants::CREATE, 'test_table' ) );
		$this->assertFalse( Grants::current_user_can( Grants::CREATE, ChangeTracker::changesets_name() ) );
		$this->assertFalse( Grants::current_user_can( Grants::CREATE, ChangeTracker::changes_name() ) );
		// Succcessfully save a record.
		$test_table = $this->db->get_table( 'test_table' );
		$rec = $test_table->save_record( array( 'title' => 'One', 'changeset_comment' => 'Testing.' ) );
		$this->assertEquals( 1, $rec->id() );
	}

	/**
	 * @testdox Foreign Keys are tracked by their titles (not their PKs).
	 * @test
	 */
	public function fk_titles() {
		// Set up data.
		$test_types = $this->db->get_table( 'test_types' );
		$type = $test_types->save_record( array( 'title' => 'The Type' ) );
		$test_table = $this->db->get_table( 'test_table' );
		$rec = $test_table->save_record( array( 'title' => 'A Record', 'type_id' => $type->id() ) );
		// Test.
		$changes = $rec->get_changes();
		//print_r($changes);
		$change = $changes[ 3 ];
		$this->assertEquals( "type_id", $change->column_name );
		$this->assertEquals( "The Type", $change->new_value );
	}

}
