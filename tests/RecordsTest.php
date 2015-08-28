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
		$this->assertEquals( '[ 1 | Rec 1 | Lorem ipsum. | 1 |  |  |  | 5.60 |  ]', $rec->get_title() );
	}

}
