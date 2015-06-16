<?php

class ImportTest extends TestBase {

	/**
	 * @testdox Rows can be imported from CSV.
	 * @test
	 */
	public function basic_import() {
		// Let the current user do anything.
		global $current_user;
		$current_user->add_cap( 'promote_users' );

		$testtypes_table = $this->db->get_table( 'test_types' );
		$csv = '"ID","Title"' . "\r\n"
			. '"1","One"' . "\r\n"
			. '"2","Two"' . "\r\n";
		$test_filename = get_temp_dir() . '/test.csv';
		file_put_contents( $test_filename, $csv );
		$uploaded = array(
			'type' => 'text/csv',
			'file' => $test_filename,
		);
		$csv = new WordPress\Tabulate\CSV( null, $uploaded );
		$csv->load_data();
		$column_map = array( 'title' => 'Title' );
		$csv->import_data( $testtypes_table, $column_map );
		// Make sure 2 records were imported.
		$this->assertEquals( 2, $testtypes_table->count_records() );
		$rec1 = $testtypes_table->get_record( 1 );
		$this->assertEquals( 'One', $rec1->title() );
		// And that 1 changeset was created, with 4 changes.
		$change_tracker = new \WordPress\Tabulate\DB\ChangeTracker( $this->wpdb );
		$sql = "SELECT COUNT(id) FROM ".$change_tracker->changesets_name();
		$this->assertEquals( 1, $this->wpdb->get_var( $sql ) );
		$sql = "SELECT COUNT(id) FROM ".$change_tracker->changes_name();
		$this->assertEquals( 4, $this->wpdb->get_var( $sql ) );
	}

}
