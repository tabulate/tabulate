<?php

namespace WordPress\Tabulate\DB;

class Reports {

	/** @const The ID of the primary report. */
	const DEFAULT_REPORT_ID = 1;

	/** @var \WordPress\Tabulate\DB\Database */
	protected $db;

	public function __construct( Database $db ) {
		$this->db = $db;
	}

	public static function reports_table_name() {
		global $wpdb;
		return $wpdb->prefix . TABULATE_SLUG . '_reports';
	}

	public static function report_sources_table_name() {
		global $wpdb;
		return $wpdb->prefix . TABULATE_SLUG . '_report_sources';
	}

	/**
	 * Get a Template instance based on a given report's template string and
	 * populated with all of the report's source queries.
	 * @param int $report_id
	 * @return \WordPress\Tabulate\Template
	 */
	public function get_template( $report_id ) {
		// Find the report.
		$reports = $this->db->get_table( self::reports_table_name() );
		$report = $reports->get_record( $report_id );
		if ( ! $report) {
			throw new \Exception("Report $report_id not found.");
		}
		$template = new \WordPress\Tabulate\Template( false, $report->template() );
		$template->title = $report->title();
		$template->file_extension = $report->file_extension();
		$template->mime_type = $report->mime_type();

		// Populate with source data.
		$sql = "SELECT * FROM `".self::report_sources_table_name()."` WHERE report = " . $report_id;
		$sources = $this->db->get_wpdb()->get_results( $sql );
		foreach ( $sources as $source ) {
			$data = $this->db->get_wpdb()->get_results( $source->query );
			$template->{$source->name} = $data;
		}

		// Return the template.
		return $template;
	}

	public static function activate() {
		global $wpdb;
		$db = new Database( $wpdb );

		if ( ! $db->get_table( self::reports_table_name() ) ) {
			$sql = "CREATE TABLE IF NOT EXISTS `" . self::reports_table_name() . "` (
				`id` INT(4) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`title` varchar(200) NOT NULL UNIQUE,
				`description` text NOT NULL,
				`mime_type` varchar(50) NOT NULL DEFAULT 'text/html',
				`file_extension` varchar(10) DEFAULT NULL COMMENT 'If defined, this report will be downloaded.',
				`template` text NOT NULL COMMENT 'The Twig template used to display this report.'
				) ENGINE=InnoDB;";
			$wpdb->query( $sql );
		}

		if ( ! $db->get_table( self::report_sources_table_name() ) ) {
			$sql = "CREATE TABLE IF NOT EXISTS `" . self::report_sources_table_name() . "` (
				`id` INT(5) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`report` INT(4) unsigned NOT NULL,
						FOREIGN KEY (`report`) REFERENCES `" . self::reports_table_name() . "` (`id`),
				`name` varchar(50) NOT NULL,
				`query` text NOT NULL
				) ENGINE=InnoDB;";
			$wpdb->query( $sql );
		}

		if ( 0 == $wpdb->get_var( "SELECT COUNT(*) FROM `" . self::reports_table_name() . "`" ) ) {
			// Create the default report, to list all reports.
			$template_string = "<dl>\n"
			. "{% for report in reports %}\n"
			. "  <dt><a href='{{ admin_url('admin.php?page=tabulate&controller=reports&id='~report.id) }}'>{{report.title}}</a></dt>\n"
			. "  <dd>{{report.description}}</dd>\n"
			. "{% endfor %}\n"
			. "</dl>";
			$sql1 = "INSERT INTO `" . self::reports_table_name() . "` SET"
				. " id          = ".self::DEFAULT_REPORT_ID.", "
				. " title       = 'Reports', "
				. " description = 'List of all Reports.',"
				. " template    = %s;";
			$wpdb->query( $wpdb->prepare( $sql1, array( $template_string ) ) );
			// And the query for the above report.
			$query = "SELECT * FROM ".self::reports_table_name();
			$sql2 = "INSERT INTO `" . self::report_sources_table_name() . "` SET "
				. " report = ".self::DEFAULT_REPORT_ID.","
				. " name   = 'reports',"
				. " query  = %s;";
			$wpdb->query( $wpdb->prepare( $sql2, array( $query ) ) );
		}

	}

}
