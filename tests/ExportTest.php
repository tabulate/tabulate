<?php

class ExportTest extends TestBase {

	/**
	 * @testdox A table can be exported to CSV.
	 * @test
	 */
	public function basic_export() {
		// Let the current user do anything.
		global $current_user;
		$current_user->add_cap( 'promote_users' );

		// Add some data to the table.
		$test_table = $this->db->get_table( 'test_types' );
		$test_table->save_record( array( 'title' => 'One' ) );
		$test_table->save_record( array( 'title' => 'Two' ) );
		$filename = $test_table->export();
		$this->assertFileExists( $filename );
		$csv = '"ID","Title"' . "\r\n"
			. '"1","One"' . "\r\n"
			. '"2","Two"' . "\r\n";
		$this->assertEquals( $csv, file_get_contents( $filename ) );
	}

}
