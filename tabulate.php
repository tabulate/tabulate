<?php
/**
 * Plugin Name: Tabulate
 * Description: Manage relational tabular data within the WP admin area, using the full power of your MySQL database.
 * Author: Sam Wilson
 * Author URI: https://samwilson.id.au/
 * License: GPL-2.0+
 * Text Domain: tabulate
 * Domain Path: /languages
 * Version: 2.8.1
 */

define( 'TABULATE_VERSION', '2.8.1' );
define( 'TABULATE_SLUG', 'tabulate' );

// Load textdomain.
add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( TABULATE_SLUG, false, basename( __DIR__ ) . '/languages/' );
} );

// Make sure Composer has been set up (for installation from Git, mostly).
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action( 'admin_notices', function() {
		$msg = __( 'Please run <kbd>composer install</kbd> prior to using Tabulate.', 'tabulate' );
		echo "<div class='error'><p>$msg</p></div>";
	} );
	return;
}
require __DIR__ . '/vendor/autoload.php';

// Get global variables and set up the filesystem.
// This file contains the only global usages of these (other than in the TestBase class);
// they're injected from here to everywhere else.
if ( ! function_exists( 'WP_filesystem' ) ) {
	include ABSPATH . "wp-admin/includes/file.php";
}
WP_Filesystem();
global $wpdb, $wp_filesystem;

// Set up the menus; their callbacks do the actual dispatching to controllers.
$menus = new \WordPress\Tabulate\Menus( $wpdb, $wp_filesystem );
$menus->init();

// Add grants-checking callback.
add_filter( 'user_has_cap', '\\WordPress\\Tabulate\\DB\\Grants::check', 0, 3 );

// Activation hooks. Uninstall is handled by uninstall.php.
add_action( 'activate_' . TABULATE_SLUG, '\\WordPress\\Tabulate\\DB\\ChangeTracker::activate' );
add_action( 'activate_' . TABULATE_SLUG, '\\WordPress\\Tabulate\\DB\\Reports::activate' );
add_action( 'activate_' . TABULATE_SLUG, function() {
	// Clean up out-of-date option.
	delete_option( TABULATE_SLUG . '_managed_tables' );
});

// Register JSON API.
add_action( 'rest_api_init', function() {
	global $wpdb;
	$api_controller = new \WordPress\Tabulate\Controllers\ApiController( $wpdb, $_GET );
	$api_controller->register_routes();
} );

// Shortcode.
$shortcode = new \WordPress\Tabulate\Controllers\ShortcodeController( $wpdb );
add_shortcode( TABULATE_SLUG, array( $shortcode, 'run' ) );

// Dashboard widget.
add_action( 'wp_dashboard_setup', function() {
	wp_add_dashboard_widget( TABULATE_SLUG . 'dashboard_widget', 'Tabulate', function() {
		$template = new \WordPress\Tabulate\Template( 'quick_jump.html' );
		echo $template->render();
	} );
} );
