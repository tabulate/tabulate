<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

/**
 * Test single-record functionality.
 */
class RecordsTest extends TestBase {

	/**
	 * Make sure the current user can do everything.
	 *
	 * @global WP_User $current_user
	 */
	public function setUp() {
		parent::setUp();
		// Let the current user do anything.
		global $current_user;
		$current_user->add_cap( 'promote_users' );
	}

	/**
	 * Getting the *FKTITLE() variant of a foreign key returns the title of the foreign record.
	 *
	 * @test
	 */
	public function related() {
		$test_table = $this->db->get_table( 'test_table' );
		$type_rec = $this->db->get_table( 'test_types' )->save_record( array( 'title' => 'Type 1' ) );
		$data_rec = $test_table->save_record( array( 'title' => 'Rec 1', 'type_id' => $type_rec->id() ) );
		$this->assertEquals( 'Type 1', $data_rec->type_idFKTITLE() );
		$referecing_recs = $type_rec->get_referencing_records( $test_table, 'type_id' );
		$this->assertCount( 1, $referecing_recs );
		$referecing_rec = array_pop( $referecing_recs );
		$this->assertEquals( 'Rec 1', $referecing_rec->title() );
	}

	/**
	 * Where there is no unique column, the 'title' is just the foreign key.
	 *
	 * @test
	 */
	public function titles() {
		$test_table = $this->db->get_table( 'test_table' );
		$this->assertEmpty( $test_table->get_unique_columns() );
		$this->assertEquals( 'id', $test_table->get_title_column()->get_name() );
		$rec = $test_table->save_record( array( 'title' => 'Rec 1', 'description' => 'Lorem ipsum.' ) );
		$this->assertEquals( '[ 1 | Rec 1 | Lorem ipsum. | 1 |  |  |  | 5.60 |  |  ]', $rec->get_title() );
	}

	/**
	 * Count records in various circumstances.
	 *
	 * @test
	 */
	public function record_counts() {
		$test_table = $this->db->get_table( 'test_table' );

		// Initially empty.
		$this->assertEquals( 0, $test_table->count_records() );

		// Add one.
		$rec1 = $test_table->save_record( array( 'title' => 'Rec 1', 'description' => 'Testing.' ) );
		$this->assertEquals( 1, $test_table->count_records() );

		// Add 2.
		$test_table->save_record( array( 'title' => 'Rec 2' ) );
		$test_table->save_record( array( 'title' => 'Rec 3' ) );
		$this->assertEquals( 3, $test_table->count_records() );

		// Add 50.
		for ( $i = 0; $i < 50; $i ++ ) {
			$test_table->save_record( array( 'title' => "Record $i" ) );
		}
		$this->assertEquals( 53, $test_table->count_records() );

		// Make sure it still works with filters applied.
		$test_table->add_filter( 'title', 'like', 'Record' );
		$this->assertEquals( 50, $test_table->count_records() );
		$test_table->reset_filters();
		$test_table->add_filter( 'description', 'like', 'Testing' );
		$this->assertEquals( 1, $test_table->count_records() );
		$test_table->reset_filters();
		$test_table->add_filter( 'description', 'not empty', '' );
		$this->assertEquals( 1, $test_table->count_records() );
		$test_table->reset_filters();
		$test_table->add_filter( 'description', 'empty', '' );
		$this->assertEquals( 52, $test_table->count_records() );
		$test_table->reset_filters();

		// Delete a record.
		$test_table->delete_record( $rec1->id() );
		$this->assertEquals( 52, $test_table->count_records() );
	}

	/**
	 * Cache the record counts for base tables which have no filters applied.
	 *
	 * @test
	 */
	public function count_store_some() {
		$test_table = $this->db->get_table( 'test_table' );
		// Add some records.
		for ( $i = 0; $i < 50; $i ++ ) {
			$test_table->save_record( array( 'title' => "Record $i" ) );
		}
		$this->assertEquals( 50, $test_table->count_records() );
		$transient_name = TABULATE_SLUG . '_test_table_count';
		$this->assertEquals( 50, get_transient( $transient_name ) );
		// With filters applied, the transient value shouldn't change.
		$test_table->add_filter( 'title', 'like', 'Record 1' );
		$this->assertEquals( 11, $test_table->count_records() );
		$this->assertEquals( 50, get_transient( $transient_name ) );
	}

	/**
	 * Bug description: when editing a record, and a filter has been applied to
	 * a referenced table, the count is the *filtered* count (and thus
	 * incorrect).
	 *
	 * @test
	 */
	public function count_related() {
		// Create 50 types.
		$types_normal = $this->db->get_table( 'test_types' );
		for ( $i = 0; $i < 50; $i ++ ) {
			$types_normal->save_record( array( 'title' => "Type $i" ) );
		}
		$this->assertEquals( 50, $types_normal->count_records() );

		// The test_table should know there are 50.
		$test_table = $this->db->get_table( 'test_table' );
		$type_col = $test_table->get_column( 'type_id' );
		$referenced_tables = $test_table->get_referenced_tables( true );
		$types_referenced = $referenced_tables['type_id'];
		$this->assertNotSame( $types_normal, $types_referenced );
		$this->assertCount( 0, $types_normal->get_filters() );
		$this->assertCount( 0, $types_referenced->get_filters() );
		$this->assertEquals( 50, $types_normal->count_records() );
		$this->assertEquals( 50, $types_referenced->count_records() );
		$this->assertEquals( 50, $type_col->get_referenced_table()->count_records() );

		// Now apply a filter to the test_types table.
		$types_normal->add_filter( 'title', 'like', '20' );
		$this->assertCount( 1, $types_normal->get_filters() );
		$this->assertCount( 0, $types_referenced->get_filters() );
		$this->assertEquals( 1, $types_normal->count_records() );
		$this->assertEquals( 50, $types_referenced->count_records() );
		$this->assertEquals( 1, $type_col->get_referenced_table()->count_records() );
	}

	/**
	 * It is possible to provide multiple filter values, one per line.
	 *
	 * @test
	 */
	public function multiple_filter_values() {
		// Set up some test data.
		$types = $this->db->get_table( 'test_table' );
		$types->save_record( array( 'title' => "Type One", 'description' => '' ) );
		$types->save_record( array( 'title' => "One Type", 'description' => 'One' ) );
		$types->save_record( array( 'title' => "Type Two", 'description' => 'Two' ) );
		$types->save_record( array( 'title' => "Four", 'description' => ' ' ) );

		// Search for 'One' and get 2 records.
		$types->add_filter( 'description', 'in', "One\nTwo" );
		$this->assertEquals( 2, $types->count_records() );

		// Make sure empty lines are ignored in filter value.
		$types->reset_filters();
		$types->add_filter( 'description', 'in', "One\n\nTwo\n" );
		$this->assertEquals( 2, $types->count_records() );
	}

	/**
	 * Empty values can be saved (where a field permits it).
	 *
	 * @test
	 */
	public function save_null_values() {
		// Get a table and make sure it is what we want it to be.
		$test_table = $this->db->get_table( 'test_table' );
		$this->assertTrue( $test_table->get_column( 'description' )->nullable() );

		// Save a record and check it.
		$rec1a = $test_table->save_record( array( 'title' => "Test One", 'description' => 'Desc' ) );
		$this->assertEquals( 1, $rec1a->id() );
		$this->assertEquals( 'Desc', $rec1a->description() );

		// Save the same record to set 'description' to null.
		$rec1b = $test_table->save_record( array( 'description' => '' ), 1 );
		$this->assertEquals( 1, $rec1b->id() );
		$this->assertEquals( null, $rec1b->description() );

		// Now repeat that, but with a nullable foreign key.
		$this->db->get_table( 'test_types' )->save_record( [ 'title' => 'A type' ] );
		$rec2a = $test_table->save_record( array( 'title' => 'Test Two', 'type_id' => 1 ) );
		$this->assertEquals( 2, $rec2a->id() );
		$this->assertEquals( 'Test Two', $rec2a->title() );
		$this->assertEquals( 'A type', $rec2a->type_idFKTITLE() );
		$rec2b = $test_table->save_record( array( 'type_id' => '' ), 2 );
		$this->assertEquals( 2, $rec2b->id() );
		$this->assertEquals( 'Test Two', $rec2b->title() );
		$this->assertEquals( null, $rec2b->type_idFKTITLE() );
	}

	/**
	 * Not sure what's happening yet.
	 *
	 * @test
	 */
	public function boolean_notnull() {
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_booleans`' );
		$create_table = "CREATE TABLE `test_booleans` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`a_bool_no_default` tinyint(1) NOT NULL,
			`a_bool_with_default` tinyint(1) NOT NULL DEFAULT '0'
			)";
		$this->wpdb->query( $create_table );
		$db = new WordPress\Tabulate\DB\Database( $this->wpdb );
		$tbl = $db->get_table( 'test_booleans' );
		$this->assertSame( null, $tbl->get_column( 'a_bool_no_default' )->get_default() );
		$this->assertSame( "0", $tbl->get_column( 'a_bool_with_default' )->get_default() );

		// Save a record, relying on the default.
		$rec1 = $tbl->save_record( array( 'a_bool_no_default' => 'Yes' ) );
		$this->assertSame( true, $rec1->a_bool_no_default() );
		$this->assertSame( false, $rec1->a_bool_with_default() );

		// Save a record, providing the same as the default.
		$rec2 = $tbl->save_record( array( 'a_bool_no_default' => '0', 'a_bool_with_default' => '' ) );
		$this->assertSame( false, $rec2->a_bool_no_default() );
		$this->assertSame( false, $rec2->a_bool_with_default() );
	}
}
