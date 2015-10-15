<?php

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	return false;
}

// Clear Grants' option.
$grants = new \WordPress\Tabulate\DB\Grants();
$grants->delete();

// Drop the ChangeTracker's and Reports' tables.
global $wpdb;
$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
foreach ( \WordPress\Tabulate\DB\ChangeTracker::table_names() as $tbl ) {
	$wpdb->query( "DROP TABLE IF EXISTS `$tbl`;" );
}
$wpdb->query( "DROP TABLE IF EXISTS `" . \WordPress\Tabulate\DB\Reports::reports_table_name() . "`;" );
$wpdb->query( "DROP TABLE IF EXISTS `" . \WordPress\Tabulate\DB\Reports::report_sources_table_name() . "`;" );
$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
