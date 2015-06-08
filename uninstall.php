<?php

if( ! defined( 'ABSPATH') || ! defined('WP_UNINSTALL_PLUGIN') ) {
	echo "Not uninstalling.\n";
	return false;
}

// Clear Grants' option.
$grants = new \WordPress\Tabulate\DB\Grants();
$grants->delete();

// Drop ChangeSets' tables.
global $wpdb;
$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
foreach ( \WordPress\Tabulate\DB\ChangeSets::table_names() as $tbl ) {
	$wpdb->query( "DROP TABLE IF EXISTS `$tbl`;" );
}
$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
