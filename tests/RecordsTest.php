<?php

class RecordsTest extends TestBase {

	public function setUp() {
		parent::setUp();
		// Let the current user do anything.
		global $current_user;
		$current_user->add_cap( 'promote_users' );
	}

	/**
	 * @testdox Getting the *FKTITLE() variant of a foreign key returns the title of the foreign record.
	 * @test
	 */
	public function related() {
		$test_table = $this->db->get_table( 'test_table' );
		$typeRec = $this->db->get_table( 'test_types' )->save_record( array( 'title' => 'Type 1' ) );
		$dataRec = $test_table->save_record( array( 'title' => 'Rec 1', 'type_id' => $typeRec->id() ) );
		$this->assertEquals( 'Type 1', $dataRec->type_idFKTITLE() );
		$referecingRecs = $typeRec->get_referencing_records( $test_table, 'type_id' );
		$this->assertCount( 1, $referecingRecs );
		$referecingRec = array_pop( $referecingRecs );
		$this->assertEquals( 'Rec 1', $referecingRec->title() );
	}

	/**
	 * @testdox Where there is no unique column, the 'title' is just the foreign key.
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
	 * @testdox
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

}
