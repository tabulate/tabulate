<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

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
		$this->assertEquals( 'test_table', $referencing_table[ 'table' ]->get_name() );
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
		$this->assertCount( 2, $referencingTables );
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

	/**
	 * @testdox Numeric and Decimal columns.
	 * @test
	 */
	public function decimal() {
		$test_table = $this->db->get_table( 'test_table' );
		$rec = $test_table->save_record( array(
			'title' => 'Decimal Test',
			'a_numeric' => '123.4',
			) );
		$this->assertEquals( '123.40', $rec->a_numeric() );
		$comment = $test_table->get_column( 'a_numeric' )->get_comment();
		$this->assertEquals( 'NUMERIC is the same as DECIMAL.', $comment );
	}

	/**
	 * @testdox Values for boolean columns can be entered in a variety of ways.
	 * @test
	 */
	public function boolean() {
		// Set up a test record to work with.
		$test_table = $this->db->get_table( 'test_table' );
		$rec = $test_table->save_record( array( 'title' => 'Boolean Test' ) );

		// Default is 'true' and the column IS nullable.
		$this->assertTrue( $rec->active() );
		$this->assertTrue( $test_table->get_column('active')->is_boolean() );
		$this->assertTrue( $test_table->get_column('active')->nullable() );

		// One and zero.
		$rec2 = $test_table->save_record(array('active' => 1), $rec->id());
		$this->assertTrue( $rec2->active() );
		$rec3 = $test_table->save_record(array('active' => '1'), $rec->id());
		$this->assertTrue( $rec3->active() );
		$rec4 = $test_table->save_record(array('active' => 0), $rec->id());
		$this->assertFalse( $rec4->active() );
		$rec5 = $test_table->save_record(array('active' => '0'), $rec->id());
		$this->assertFalse( $rec5->active() );

		// Yes and No.
		$rec6 = $test_table->save_record(array('active' => 'Yes'), $rec->id());
		$this->assertTrue( $rec6->active() );
		$rec7 = $test_table->save_record(array('active' => 'No'), $rec->id());
		$this->assertFalse( $rec7->active() );

		// True and false.
		$rec8 = $test_table->save_record(array('active' => 'TRUE'), $rec->id());
		$this->assertTrue( $rec8->active() );
		$rec9 = $test_table->save_record(array('active' => 'false'), $rec->id());
		$this->assertFalse( $rec9->active() );

		// Empty equals null.
		$rec10 = $test_table->save_record(array('active' => ''), $rec->id());
		$this->assertNull( $rec10->active() );
	}

	/**
	 * @testdox A table can have a multi-column primary key.
	 * @test
	 */
	/* public function multicol_primary_key() {
	  $this->wpdb->query( 'DROP TABLE IF EXISTS `test_multicol_primary_key`' );
	  $this->wpdb->query( 'CREATE TABLE `test_multicol_primary_key` ('
	  . ' ident_a VARCHAR(10),'
	  . ' ident_b VARCHAR(10),'
	  . ' PRIMARY KEY (ident_a, ident_b)'
	  . ');'
	  );
	  $db = new WordPress\Tabulate\DB\Database( $this->wpdb );
	  $tbl = $db->get_table( 'test_multicol_primary_key' );
	  var_dump($tbl->get_pk_column()->get_name());
	  } */

	/**
	 * @link https://github.com/tabulate/tabulate/issues/21
	 * @test
	 */
	public function github_21() {
		$this->wpdb->query( 'DROP TABLE IF EXISTS `test_pb_servicio`' );
		$sql = "CREATE TABLE IF NOT EXISTS test_pb_servicio (
			s_id VARCHAR(4) NOT NULL COMMENT 'código identificador del servicio',
			s_nom VARCHAR(80) NOT NULL COMMENT 'nombre del servicio',
			s_des TEXT COMMENT 'texto con información/condiciones/descripción del servicio',
			s_pre NUMERIC (10,2) NOT NULL DEFAULT '0' COMMENT 'precio por persona del servicio',
			s_iva NUMERIC (3,2) NOT NULL DEFAULT '0.21' COMMENT 'IVA del artículo -por defecto el 21%-',
			s_dto NUMERIC (3,2) NOT NULL DEFAULT '0' COMMENT 'si es 0.5, tiene un descuento del 50%',
			s_tsini TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'timestamp con la fecha de creación del servicio',
			s_tsfin TIMESTAMP COMMENT 'timestamp con la fecha de la desactivación del servicio',
			s_img VARCHAR(160) COMMENT 'url o dirección del fichero de imagen asociado al servicio',
			s_bitblo ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'activación(0)/bloqueo(1) del servicio',
			CONSTRAINT servicio_pk PRIMARY KEY(s_id)
			)
			ENGINE InnoDB
			COMMENT 'producto concertado con un proveedor o proveedores';";
		$this->wpdb->query( $sql );
		$db = new WordPress\Tabulate\DB\Database( $this->wpdb );
		$tbl = $db->get_table( 'test_pb_servicio' );
		$this->assertTrue( $tbl->get_column( 's_pre' )->is_numeric() );
		$rec = $tbl->save_record( array(
			's_id' => 'TEST',
			's_nom' => 'A name',
			's_pre' => 123.45,
			's_bitblo' => '1',
		) );
		$this->assertEquals( 123.45, $rec->s_pre() );
		$this->assertEquals( 0.21, $rec->s_iva() );
		$s_pre = $tbl->get_column( 's_pre' );
		$this->assertTrue( $s_pre->is_numeric() );
		$this->assertEquals( 1, $rec->s_bitblo() );
	}

	/**
	 * @testdox It should be possible to provide a value for a (non-autoincrementing) PK.
	 * @test
	 */
	public function provided_pk() {
		$this->wpdb->query( 'DROP TABLE IF EXISTS `provided_pk`' );
		$sql = "CREATE TABLE `provided_pk` ( "
			. "  `code` VARCHAR(10) NOT NULL PRIMARY KEY, "
			. "  `title` VARCHAR(100) "
			. ");";
		$this->wpdb->query( $sql );
		$db = new WordPress\Tabulate\DB\Database( $this->wpdb );
		$tbl = $db->get_table( 'provided_pk' );
		$rec = $tbl->save_record( array( 'code' => 'TEST') );
		$this->assertEquals( 'TEST', $rec->get_primary_key() );
	}

	/**
	 * @testdox It is possible to list base-tables and views separately.
	 * @test
	 */
	public function views() {
		$sql = "CREATE OR REPLACE VIEW test_types_view AS "
			. " SELECT * FROM `test_types`;";
		$this->db->query( $sql );
		$this->db->reset();
		$view = $this->db->get_table( 'test_types_view' );
		$this->assertInstanceOf( 'WordPress\\Tabulate\\DB\\Table', $view );
		$this->assertTrue( $view->is_view() );
		$this->assertArrayHasKey( 'test_types_view', $this->db->get_table_names() );
		$this->assertArrayHasKey( 'test_types_view', $this->db->get_views() );
		$this->assertArrayHasKey( 'test_types_view', $this->db->get_tables( false ) );
		$this->assertArrayNotHasKey( 'test_types_view', $this->db->get_tables( true ) );
	}
}
