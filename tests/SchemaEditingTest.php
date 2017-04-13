<?php
/**
 * This file contains only a single class.
 *
 * @file
 * @package Tabulate
 */

use \WordPress\Tabulate\DB\Column;
use \WordPress\Tabulate\DB\ChangeTracker;

/**
 * Test the schema-editing features.
 */
class SchemaEditingTest extends TestBase {

	/**
	 * Make sure the current user can do anything.
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
	 * Delete any created tables.
	 */
	public function tearDown() {
		$this->wpdb->query( "DROP TABLE IF EXISTS `testing_table`;" );
		$this->wpdb->query( 'DROP TABLE IF EXISTS `new_table`;' );
		$this->wpdb->query( 'DROP TABLE IF EXISTS `new_table_2`;' );
		$this->wpdb->query( 'DROP TABLE IF EXISTS `widgets`;' );
		$this->wpdb->query( 'DROP TABLE IF EXISTS `types`;' );
		parent::tearDown();
	}

	/**
	 * It is possible to rename a table.
	 *
	 * @test
	 */
	public function rename_table() {
		$test_table = $this->db->get_table( 'test_table' );
		$test_table->rename( 'testing_table' );
		$testing_table = $this->db->get_table( 'testing_table' );
		$this->assertEquals( 'testing_table', $testing_table->get_name() );
		$this->assertEquals( 'testing_table', $test_table->get_name() );
		$this->assertFalse( $this->db->get_table( 'test_table' ) );
	}

	/**
	 * When renaming a table, its history comes along with it.
	 *
	 * @test
	 */
	public function rename_table_history() {
		// Create a record in the table and check its history size.
		$test_table = $this->db->get_table( 'test_table' );
		$rec1 = $test_table->save_record( array(
			'title' => 'Testing',
		) );
		$this->assertEquals( 1, $rec1->id() );
		$this->assertCount( 4, $rec1->get_changes() );

		// Rename the table, and make sure the history is the same size.
		$test_table->rename( 'testing_table' );
		$testing_table = $this->db->get_table( 'testing_table' );
		$rec2 = $testing_table->get_record( 1 );
		$this->assertCount( 4, $rec2->get_changes() );
	}

	/**
	 * It should be possible to 'rename' to the same name (nothing should happen).
	 *
	 * @test
	 */
	public function rename_to_same_name() {
		$test_table = $this->db->get_table( 'test_table' );
		$test_table->rename( 'test_table' );
		$this->assertEquals( 'test_table', $test_table->get_name() );
	}

	/**
	 * When creating a table, we first create a minumum table.
	 *
	 * @test
	 */
	public function create_table() {
		// Basic one without comment.
		$table = $this->db->create_table( 'new_table' );
		$this->assertInstanceOf( '\WordPress\Tabulate\DB\Table', $table );
		$this->assertContains( 'id', array_keys( $table->get_columns() ) );

		// Now with a comment, and other basics.
		$table2 = $this->db->create_table( 'new_table_2', 'The comment text' );
		$this->assertEquals( 'The comment text', $table2->get_comment() );
		$this->assertCount( 1, $table2->get_columns() );
		$this->assertTrue( $table2->get_column( 'id' )->is_primary_key() );
	}

	/**
	 * Build column definition statements.
	 *
	 * The required SQL structure is as follows:
	 *     data_type [NOT NULL | NULL] [DEFAULT default_value]
	 *     [AUTO_INCREMENT] [UNIQUE [KEY] | [PRIMARY] KEY]
	 *     [COMMENT 'string']
	 *     [COLUMN_FORMAT {FIXED|DYNAMIC|DEFAULT}]
	 *     [STORAGE {DISK|MEMORY|DEFAULT}]
	 *     [reference_definition]
	 *
	 * @test
	 */
	public function column_definitions() {
		$def1 = Column::get_column_definition( 'test_name', 'text_short' );
		$this->assertEquals( '`test_name` VARCHAR(50) NULL DEFAULT NULL', $def1 );

		$def2 = Column::get_column_definition( 'test_name', 'text_short', 40, false );
		$this->assertEquals( '`test_name` VARCHAR(40) NOT NULL', $def2 );

		// No default allowed for TEXT columns.
		$def3 = Column::get_column_definition( 'test_name', 'text_long', null, true, 'Test' );
		$this->assertEquals( "`test_name` TEXT NULL", $def3 );

		$def4 = Column::get_column_definition( 'ident', 'integer', 5, false, '', true, true, 'The Ident' );
		$this->assertEquals( "`ident` INT(5) NOT NULL AUTO_INCREMENT UNIQUE COMMENT 'The Ident'", $def4 );

		$def5 = Column::get_column_definition( 'can_do', 'boolean', 5, false, '', true, false, 'Can it do this thing?' );
		$this->assertEquals( "`can_do` TINYINT(1) NOT NULL COMMENT 'Can it do this thing?'", $def5 );

		// Enum options can be provided either quoted or not.
		$def6 = Column::get_column_definition( 'which_one', 'enum', 'one,two,three' );
		$this->assertEquals( "`which_one` ENUM('one','two','three') NULL DEFAULT NULL", $def6 );
		$def6 = Column::get_column_definition( 'which_one', 'enum', '"four", "five", "six"' );
		$this->assertEquals( '`which_one` ENUM("four", "five", "six") NULL DEFAULT NULL', $def6 );
	}

	/**
	 * Alter a column.
	 *
	 * @test
	 */
	public function alter_column_name() {
		$table = $this->db->create_table( 'new_table' );
		// Check the initial state of the table.
		$this->assertContains( 'id', array_keys( $table->get_columns() ) );
		$id_col = $table->get_column( 'id' );
		$old_def = $id_col->get_current_column_definition();
		$this->assertEquals( 'integer', $id_col->get_xtype()['name'] );
		$this->assertEquals( 10, $id_col->get_size() );
		$this->assertTrue( $id_col->is_primary_key() );
		$this->assertTrue( $id_col->is_auto_increment() );
		$this->assertEquals( $old_def, $id_col->get_current_column_definition() );

		// Make a change.
		$table->get_column( 'id' )->alter( 'identifier' );

		// Check the change.
		$this->assertCount( 1, $table->get_columns() );
		$this->assertContains( 'identifier', array_keys( $table->get_columns() ) );
		$this->assertNotContains( 'id', array_keys( $table->get_columns() ) );
		$identifier_col = $table->get_column( 'identifier' );
		$this->assertEquals( 'integer', $table->get_column( 'identifier' )->get_xtype()['name'] );
		$this->assertEquals( 10, $identifier_col->get_size() );
		$this->assertTrue( $identifier_col->is_primary_key() );
		$this->assertTrue( $identifier_col->is_auto_increment() );
	}

	/**
	 * A column's type can be changed.
	 *
	 * @test
	 */
	public function alter_column_type() {
		$table = $this->db->create_table( 'new_table' );
		$table->add_column( 'info', 'integer', 20 );
		$col = $table->get_column( 'info' );
		$table->save_record( array(
			'info' => 99,
		) );

		// Make sure the column starts as we expect.
		$this->assertEquals( 'INT', $col->get_xtype()['type'] );
		$this->assertEquals( 20, $col->get_size() );
		$rec = $table->get_record( 1 );
		$this->assertEquals( '99', $rec->info() );

		// Change it to a decimal.
		$col->alter( null, 'decimal', '5,2' );
		$this->assertEquals( 'DECIMAL', $col->get_xtype()['type'] );
		$this->assertEquals( '5,2', $col->get_size() );
		$rec2 = $table->get_record( 1 );
		$this->assertEquals( '99.00', $rec2->info() );

		// Change the decimal's scale.
		$col->alter( null, 'decimal', '6,3' );
		$this->assertEquals( 'DECIMAL', $col->get_xtype()['type'] );
		$this->assertEquals( '6,3', $col->get_size() );
		$rec3 = $table->get_record( 1 );
		$this->assertEquals( '99.000', $rec3->info() );
	}

	/**
	 * Columns can be re-ordered, including into first place.
	 *
	 * @test
	 */
	public function reorder_columns() {
		$table = $this->db->create_table( 'new_table' );
		// Add a column.
		$table->add_column( 'title', 'text_long' );
		$this->assertEquals( array( 'id', 'title' ), array_keys( $table->get_columns() ) );
		// Change the column's position.
		$table->get_column( 'title' )->alter( null, null, null, null, null, null, null, null, null, 'FIRST' );
		$this->assertEquals( array( 'title', 'id' ), array_keys( $table->get_columns() ) );
		// Insert a third column.
		$table->add_column( 'size', 'decimal', null, null, null, null, null, null, null, 'title' );
		$this->assertEquals( array( 'title', 'size', 'id' ), array_keys( $table->get_columns() ) );
	}

	/**
	 * A column can be changed and then back again, to leave the structure the same.
	 *
	 * @test
	 */
	public function is_column_change_idempotent() {
		$table = $this->db->create_table( 'new_table' );
		$table->add_column( 'title', 'text_long' );
		$defining_sql_1 = $table->get_defining_sql();

		$col = $table->get_column( 'title' );
		$col->alter( 'name', 'text_short', 80, false, 'The def', null, true );
		$this->assertEquals( 'name', $col->get_name() );
		$this->assertEquals( 'text_short', $col->get_xtype()['name'] );
		$this->assertEquals( '80', $col->get_size() );
		$this->assertEquals( false, $col->nullable() );
		$this->assertEquals( false, $col->is_required() );
		$this->assertEquals( 'The def', $col->get_default() );
		$this->assertEquals( false, $col->is_auto_increment() );
		$this->assertEquals( true, $col->is_unique() );

		// Change back again.
		$col->alter( 'title', 'text_long', null, false, null, null, false );
		$this->assertEquals( 'title', $col->get_name() );
		$this->assertEquals( 'text_long', $col->get_xtype()['name'] );
		$this->assertEquals( null, $col->get_size() );
		$this->assertEquals( false, $col->nullable() );
		$this->assertEquals( true, $col->is_required() );
		$this->assertEquals( null, $col->get_default() );
		$this->assertEquals( false, $col->is_auto_increment() );
		$this->assertEquals( false, $col->is_unique() );
		$this->assertEquals( $defining_sql_1, $table->get_defining_sql() );
	}

	/**
	 * Alter a column's comment.
	 *
	 * @test
	 */
	public function add_column() {
		$table = $this->db->create_table( 'new_table' );
		// Check the initial state of the table.
		$this->assertEquals( array( 'id' ), array_keys( $table->get_columns() ) );

		// Add a column.
		$table->add_column( 'title', 'text_short', 80, false, null, false, true, 'A comment', false, 'FIRST' );

		// Check the change.
		$this->assertCount( 2, $table->get_columns() );
		$this->assertEquals( array( 'title', 'id' ), array_keys( $table->get_columns() ) );
		$this->assertEquals( 'A comment', $table->get_column( 'title' )->get_comment() );
		$this->assertTrue( $table->get_column( 'title' )->is_unique() );

		// Add a unique column.
		$table->add_column( 'birthday', 'date', null, null, null, false, true );
		$this->assertCount( 3, $table->get_columns() );
		$this->assertEquals( array( 'title', 'id', 'birthday' ), array_keys( $table->get_columns() ) );
		$this->assertTrue( $table->get_column( 'birthday' )->is_unique() );
	}

	/**
	 * Change a column's type.
	 *
	 * @test
	 */
	public function change_column_type() {
		// Make a table and a column to test with.
		$table = $this->db->create_table( 'new_table' );
		$table->add_column( 'count', 'text_short', 80 );
		// Make sure the column is what we think it is.
		$count = $table->get_column( 'count' );
		$this->assertEquals( 'varchar', $count->get_type() );
		$this->assertEquals( 80, $count->get_size() );

		// Change it to an integer.
		$count->alter( 'count', 'integer', 8 );
		$this->assertEquals( 'int', $count->get_type() );
		$this->assertEquals( 8, $count->get_size() );
	}

	/**
	 * Making a column unique should only add a new index if it's not already unique.
	 *
	 * @test
	 */
	public function make_column_unique() {
		$table = $this->db->create_table( 'new_table' );
		$wpdb = $table->get_database()->get_wpdb();

		// Make sure there's only 1 index (the PK).
		$sql = "SHOW INDEXES FROM `new_table`";
		$this->assertCount( 1, $wpdb->get_results( $sql ) );

		// Add a new unique column, make sure there's 2 indexes.
		$table->add_column( 'title', 'text_short', 80, null, null, null, true );
		$this->assertTrue( $table->get_column( 'title' )->is_unique() );
		$this->assertCount( 2, $wpdb->get_results( $sql ) );

		// Change it to not unique, and check that the index has been dropped.
		$title_col = $table->get_column( 'title' );
		$title_col->alter( null, null, null, null, null, null, false );
		$this->assertFalse( $title_col->is_unique() );
		$this->assertCount( 1, $wpdb->get_results( $sql ) );

		// And back to unique, in a different way.
		$table->get_column( 'title' )->alter( null, null, null, null, null, null, true );
		$this->assertTrue( $table->get_column( 'title' )->is_unique() );
		$this->assertCount( 2, $wpdb->get_results( $sql ) );
	}

	/**
	 * Tables can be deleted.
	 *
	 * @test
	 */
	public function drop_table() {
		// Create a table and add some data to it.
		$table = $this->db->create_table( 'new_table' );
		$table->add_column( 'title', 'text_short' );
		$table->save_record( array(
			'title' => 'Test Record',
		) );
		// Make sure it and the data were added as we expect.
		$this->assertContains( 'new_table', $this->db->get_table_names() );
		$this->assertCount( 1, $table->get_records() );
		// Drop the table.
		$table->drop();
		// Make sure the table and its history have gone.
		$this->assertNotContains( 'new_table', $this->db->get_table_names() );
		$sql = "SELECT * FROM `" . ChangeTracker::changes_name() . "` WHERE table_name = 'new_table'";
		$this->assertEmpty( $this->db->get_wpdb()->get_results( $sql ) );
	}

	/**
	 * Table comments can be changed.
	 *
	 * @test
	 */
	public function table_comment() {
		$table = $this->db->create_table( 'new_table' );
		$this->assertEmpty( $table->get_comment() );
		$table->set_comment( 'New comment.' );
		$this->assertEquals( 'New comment.', $table->get_comment() );
	}

	/**
	 * A column can be made UNIQUE twice without it creating duplicate indexes.
	 *
	 * @test
	 */
	public function double_unique_column() {
		$table = $this->db->create_table( 'new_table' );
		// Create a unique column.
		$table->add_column( 'title', 'text_short', null, null, null, null, true );
		$col = $table->get_column( 'title' );
		$this->assertTrue( $col->is_unique() );
		// Un-unique it.
		$col->alter( null, null, null, null, null, null, false );
		$this->assertFalse( $col->is_unique() );
		// Re-unique it, twice.
		$col->alter( null, null, null, null, null, null, true );
		$col->alter( null, null, null, null, null, null, true );
		$this->assertTrue( $col->is_unique() );
		// Un-unique it again.
		$col->alter( null, null, null, null, null, null, false );
		$this->assertFalse( $col->is_unique() );
	}

	/**
	 * A column can be a foreign key.
	 *
	 * @test
	 */
	public function foreign_keys() {
		$widgets = $this->db->create_table( 'widgets' );
		$types = $this->db->create_table( 'types' );
		$widgets->add_column( 'type', 'fk', null, null, null, null, null, null, $types );
		$type_col = $widgets->get_column( 'type' );
		$this->assertTrue( $type_col->is_foreign_key() );
		$this->assertEquals( $widgets->get_pk_column()->get_type(), $type_col->get_type() );
		$this->assertEquals( $widgets->get_pk_column()->get_size(), $type_col->get_size() );
		$this->assertEquals( $widgets->get_pk_column()->is_unsigned(), $type_col->is_unsigned() );

		// It's also possible to alter a column after it's a FK.
		$type_col->alter( 'the_type' );
		$this->assertEquals( 'the_type', $type_col->get_name() );
		$this->assertTrue( $type_col->is_foreign_key() );
		$this->assertEquals( 'types', $type_col->get_referenced_table()->get_name() );
		$this->assertArrayHasKey( 'widgets.the_type', $types->get_referencing_tables() );
	}

	/**
	 * Make sure we can create and modify enum columns.
	 *
	 * @test
	 */
	public function enum_fields() {
		$widgets = $this->db->create_table( 'widgets' );
		$options_string = '"first","second"';
		$widgets->add_column( 'class', 'enum', $options_string, null, null, null, null, null );
		$options = [ 'first', 'second' ];
		$this->assertEquals( "'first','second'", $widgets->get_column( 'class' )->get_size() );
		$this->assertEquals( array_combine( $options, $options ), $widgets->get_column( 'class' )->get_options() );
	}
}
