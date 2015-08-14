<?php

use WordPress\Tabulate\DB\Grants;

class SchemaTest extends TestBase {

	/**
	 * @testdox Tabulate lists all tables in the database (by default, only for users with the 'promote_users' capability).
	 * @test
	 */
	public function no_access() {
		global $current_user;

		// Make sure they can't see anything yet.
		$current_user->remove_cap( 'promote_users' );
		$this->assertFalse( current_user_can( 'promote_users' ) );
		$this->assertEmpty( $this->db->get_table_names() );

		// Now promote them, and try again.
		$current_user->add_cap( 'promote_users' );
		$this->assertTrue( current_user_can( 'promote_users' ) );
		$table_names = $this->db->get_table_names();
		// A WP core table.
		$this->assertContains( $this->wpdb->prefix . 'posts', $table_names );
		// And one of ours.
		$this->assertContains( 'test_table', $table_names );
	}

	/**
	 * @testdox Tables can be linked to each other; one is the referenced table, the other the referencing.
	 * @test
	 */
	public function references() {
		// Make sure the user can edit things.
		global $current_user;
		$current_user->add_role( 'administrator' );
		$grants = new Grants();
		$grants->set(
			array(
				'test_table' => array( Grants::READ => array( 'administrator' ), ),
			)
		);

		// That test_table references test_types
		$test_table = $this->db->get_table( 'test_table' );
		$referenced_tables = $test_table->get_referenced_tables( true );
		$referenced_table = array_pop( $referenced_tables );
		$this->assertEquals( 'test_types', $referenced_table->get_name() );

		// And the other way around.
		$type_table = $this->db->get_table( 'test_types' );
		$referencing_tables = $type_table->get_referencing_tables();
		$referencing_table = array_pop( $referencing_tables );
		$this->assertEquals( 'test_table', $referencing_table['table']->get_name() );
	}

	/**
	 * @testdox More than one table can reference a table, and even a single table can reference a table more than once.
	 * @test
	 */
	public function multiple_references() {
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_widgets`' );
		$this->wpdb->query( 'CREATE TABLE `test_widgets` ('
			. ' id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
			. ' title VARCHAR(100) NOT NULL UNIQUE,'
			. ' type_1_a INT(10) UNSIGNED,'
			. ' type_1_b INT(10) UNSIGNED,'
			. ' type_2 INT(10) UNSIGNED'
			. ');'
		);
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_widget_types_1`' );
		$this->wpdb->query( 'CREATE TABLE `test_widget_types_1` ('
			. ' id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
			. ' title VARCHAR(100) NOT NULL'
			. ');'
		);
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_widget_types_2`' );
		$this->wpdb->query( 'CREATE TABLE `test_widget_types_2` ('
			. ' id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
			. ' title VARCHAR(100) NOT NULL'
			. ');'
		);
		$this->wpdb->query( 'ALTER TABLE `test_widgets` '
			. ' ADD FOREIGN KEY ( `type_1_a` ) REFERENCES `test_widget_types_1` (`id`),'
			. ' ADD FOREIGN KEY ( `type_1_b` ) REFERENCES `test_widget_types_1` (`id`),'
			. ' ADD FOREIGN KEY ( `type_2` ) REFERENCES `test_widget_types_2` (`id`);'
		);
		$db = new WordPress\Tabulate\DB\Database( $this->wpdb );
		$table = $db->get_table( 'test_widgets' );

		// Check references from Widgets to Types.
		$referencedTables = $table->get_referenced_tables();
		$this->assertCount( 3, $referencedTables );
		$this->assertArrayHasKey( 'type_1_a', $referencedTables );
		$this->assertArrayHasKey( 'type_1_b', $referencedTables );
		$this->assertArrayHasKey( 'type_2', $referencedTables );

		// Check references from Types to Widgets.
		$type1 = $db->get_table( 'test_widget_types_1' );
		$referencingTables = $type1->get_referencing_tables();
		$this->assertCount( 2, $referencingTables);
	}

	/**
	 * @testdox A not-null column "is required" but if it has a default value then no value need be set when saving.
	 * @test
	 */
	public function required_columns() {
		// 'widget_size' is a not-null column with a default value.
		$test_table = $this->db->get_table( 'test_table' );
		$widget_size_col = $test_table->get_column( 'widget_size' );
		$this->assertFalse( $widget_size_col->is_required() );
		// 'title' is a not-null column with no default.
		$title_col = $test_table->get_column( 'title' );
		$this->assertTrue( $title_col->is_required() );

		// Create a basic record.
		$widget = array(
			'title' => 'Test Item'
		);
		$test_table->save_record( $widget );
		$this->assertEquals( 1, $test_table->count_records() );
		$widget_records = $test_table->get_records();
		$widget_record = array_shift( $widget_records );
		$this->assertEquals( 5.6, $widget_record->widget_size() );
	}

	/**
	 * @testdox Null values can be inserted, and existing values can be updated to be null.
	 * @test
	 */
	public function null_values() {
		$test_table = $this->db->get_table( 'test_table' );

		// Start with null.
		$widget = array(
			'title' => 'Test Item',
			'ranking' => null,
		);
		$record = $test_table->save_record( $widget );
		$this->assertEquals( 'Test Item', $record->title() );
		$this->assertNull( $record->ranking() );

		// Update to a number.
		$widget = array(
			'title' => 'Test Item',
			'ranking' => 12,
		);
		$record = $test_table->save_record( $widget, 1 );
		$this->assertEquals( 12, $record->ranking() );

		// Then update to null again.
		$widget = array(
			'title' => 'Test Item',
			'ranking' => null,
		);
		$record = $test_table->save_record( $widget, 1 );
		$this->assertNull( $record->ranking() );
	}

	/**
	 * @testdox Only NOT NULL text fields are allowed to have empty strings.
	 * @test
	 */
	public function empty_string() {
		$test_table = $this->db->get_table( 'test_table' );
		// Title is NOT NULL.
		$this->assertTrue( $test_table->get_column( 'title' )->allows_empty_string() );
		// Description is NULLable.
		$this->assertFalse( $test_table->get_column( 'description' )->allows_empty_string() );

		// Check with some data.
		$data = array(
			'title' => '', 
			'description' => '', 
		);
		$record = $test_table->save_record( $data );
		$this->assertSame( '', $record->title() );
		$this->assertNull( $record->description() );
	}

	/**
	 * @testdox Date and time values are saved correctly.
	 * @test
	 */
	public function date_and_time() {
		$test_table = $this->db->get_table( 'test_table' );
		$rec = $test_table->save_record( array(
			'title' => 'Test',
			'a_date' => '1980-01-01',
			'a_year' => '1980',
		) );
		$this->assertEquals( '1980-01-01', $rec->a_date() );
		$this->assertEquals( '1980', $rec->a_year() );
	}

	/**
	 * @testdox VARCHAR columns can be used as Primary Keys.
	 * @test
	 */
	public function varchar_pk() {
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_varchar_pk`' );
		$this->wpdb->query( 'CREATE TABLE `test_varchar_pk` ('
			. ' ident VARCHAR(10) PRIMARY KEY,'
			. ' description TEXT'
			. ');'
		);
		$db = new WordPress\Tabulate\DB\Database( $this->wpdb );
		$tbl = $db->get_table( 'test_varchar_pk' );
		$this->assertEquals( 'ident', $tbl->get_pk_column()->get_name() );
		$rec = $tbl->save_record( array( 'ident' => 'TEST123' ) );
		$this->assertEquals( 'TEST123', $rec->get_primary_key() );
	}

}
