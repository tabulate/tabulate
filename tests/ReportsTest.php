<?php

use \WordPress\Tabulate\DB\Reports;

class ReportsTest extends TestBase {

	public function setUp() {
		parent::setUp();
		// Let the current user do anything.
		global $current_user;
		$current_user->add_cap( 'promote_users' );
	}

	/**
	 * @testdox The Reports system uses two database tables, which are created on activation.
	 * @test
	 */
	public function activate() {
		$reports = $this->db->get_table( Reports::reports_table_name() );
		$this->assertEquals( $this->wpdb->prefix . TABULATE_SLUG . '_reports', $reports->get_name() );
		$reportSources = $this->db->get_table( Reports::report_sources_table_name() );
		$this->assertEquals( $this->wpdb->prefix . TABULATE_SLUG . '_report_sources', $reportSources->get_name() );
	}

	/**
	 * @testdox On activation, a default report is created that lists all reports. Its ID is 1.
	 * @test
	 */
	public function default_report() {
		$reports = new Reports( $this->db );
		$default = $reports->get_template( 1 );
		$this->assertEquals( 'Reports', $default->title );
		$default_html = "<dl>\n"
			. "  <dt><a href='%Swp-admin/admin.php?page=tabulate&amp;controller=reports&amp;id=1'>Reports</a></dt>\n"
			. "  <dd>List of all Reports.</dd>\n"
			. "</dl>";
		$this->assertStringMatchesFormat( $default_html, $default->render() );
	}

	/**
	 * @testdox A report has, at a minimum, a title and a template. It handled as a normal record in the reports table.
	 * @test
	 */
	public function template() {
		$reportsTable = $this->db->get_table( Reports::reports_table_name() );
		$report = $reportsTable->save_record( array(
			'title' => 'Test Report',
			'template' => 'Lorem ipsum.',
			) );
		$this->assertEquals( 'Test Report', $report->title() );
		$reports = new Reports( $this->db );
		$template = $reports->get_template( $report->id() );
		$this->assertEquals( 'Test Report', $report->title() );
		$this->assertInstanceOf( '\WordPress\Tabulate\Template', $template );
		$this->assertEquals( 'Lorem ipsum.', $template->render() );
	}

	/**
	 * @testdox Reports can have source queries injected into them.
	 * @test
	 */
	public function sources() {
		$reportsTable = $this->db->get_table( Reports::reports_table_name() );
		$report = $reportsTable->save_record( array(
			'title' => 'Test Report',
			'template' => 'Today is {{dates.0.date}}'
			) );
		$reportSourcesTable = $this->db->get_table( Reports::report_sources_table_name() );
		$reportSourcesTable->save_record( array(
			'report' => $report->id(),
			'name' => 'dates',
			'query' => "SELECT CURRENT_DATE AS `date`;",
			) );
		$reports = new Reports( $this->db );
		$template = $reports->get_template( $report->id() );
		$this->assertEquals( 'Today is ' . date( 'Y-m-d' ), $template->render() );
	}

	/**
	 * @testdox A report's Template inherits  the report's `file_extension`, `mime_type`, and `title` attributes.
	 * @test
	 */
	public function file_extension() {
		$reportsTable = $this->db->get_table( Reports::reports_table_name() );
		$reports = new Reports( $this->db );

		// 1. No file_extension attribute is set, but the others are.
		$report1 = $reportsTable->save_record( array(
			'title' => 'Test Report 1',
			'mime_type' => 'text/plain',
		) );
		$template1 = $reports->get_template( $report1->id() );
		$this->assertNull( $template1->file_extension );
		$this->assertEquals( 'text/plain', $template1->mime_type );

		// 2. A 'GPX' file extension is set, and the default mime_type.
		$report2 = $reportsTable->save_record( array(
			'title' => 'Test Report 2',
			'file_extension' => 'gpx',
		) );
		$template2 = $reports->get_template( $report2->id() );
		$this->assertEquals( 'gpx', $template2->file_extension );
		$this->assertEquals( 'text/html', $template2->mime_type );
	}

}
