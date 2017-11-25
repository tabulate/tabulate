<?php
/**
 * This file contains only the Menus class
 *
 * @package Tabulate
 */

namespace WordPress\Tabulate;

use Exception;
use WordPress\Tabulate\DB\ChangeTracker;
use WordPress\Tabulate\DB\Exception as TabulateException;
use WordPress\Tabulate\DB\Reports;
use WP_Admin_Bar;
use WP_Filesystem_Direct;
use wpdb;

/**
 * This class is an attempt to group all functionality around managing the menus
 * in the Admin Area in one place. It includes adding scripts and stylesheets.
 */
class Menus {

	/**
	 * The global wpdb object.
	 *
	 * @var wpdb
	 */
	protected $wpdb;

	/**
	 * The global filesystem object
	 *
	 * @var WP_Filesystem_Direct
	 */
	protected $filesystem;

	/**
	 * The page output is stored between being called/created in
	 * self::dispatch() and output in self::add_menu_pages()
	 *
	 * @var string
	 */
	protected $output;

	/**
	 * Create a new Menus object, supplying it with the database so that it
	 * doesn't have to use a global.
	 *
	 * @param wpdb $wpdb The global wpdb object.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
		// We don't use the global $wp_filesystem because it may be FTP or similar,
		// but we do initialize it here in order to use WP_Filesystem_Direct.
		require_once ABSPATH . "wp-admin/includes/file.php";
		WP_Filesystem();
		require_once ABSPATH . "wp-admin/includes/class-wp-filesystem-direct.php";
		$this->filesystem = new WP_Filesystem_Direct( [] );
	}

	/**
	 * Get the filesystem used for exports etc.
	 *
	 * @return WP_Filesystem_Direct
	 */
	public function get_filesystem() {
		return $this->filesystem;
	}

	/**
	 * Set up all required hooks. This is called from the top level of tabulate.php
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'dispatch' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ) );
	}

	/**
	 * The main plugin activation method. This is called from tabulate.php and from the TestBase class.
	 */
	public function activation() {
		// Add change-tracker and reports' tables.
		ChangeTracker::activate( $this->wpdb );
		Reports::activate( $this->wpdb );
		// Clean up out-of-date option.
		delete_option( TABULATE_SLUG . '_managed_tables' );
	}

	/**
	 * Add Tabulate's menu items to the main admin menu.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		$dispatch_callback = array( $this, 'output' );

		// Home page (also change the first submenu item's title).
		add_menu_page( 'Tabulate', 'Tabulate', 'read', TABULATE_SLUG, $dispatch_callback );
		$page_title = ( isset( $_GET['table'] ) ) ? Text::titlecase( $_GET['table'] ) : 'Tabulate';
		add_submenu_page( TABULATE_SLUG, $page_title, 'Overview', 'read', TABULATE_SLUG, $dispatch_callback );

		// Add submenu pages.
		if ( Util::is_plugin_active( 'tfo-graphviz/tfo-graphviz.php' ) ) {
			add_submenu_page( TABULATE_SLUG, 'Tabulate ERD', 'ERD', 'read', TABULATE_SLUG . '_erd', $dispatch_callback );
		}
		add_submenu_page( TABULATE_SLUG, 'Tabulate Reports', 'Reports', 'promote_users', TABULATE_SLUG . '_reports', $dispatch_callback );
		add_submenu_page( TABULATE_SLUG, 'Tabulate Grants', 'Grants', 'promote_users', TABULATE_SLUG . '_grants', $dispatch_callback );
	}

	/**
	 * Add all tables in which the user is allowed to create records to the
	 * Admin Bar new-content menu. If there are more than ten, none are added
	 * because the menu would get too long. Not sure how this should be fixed.
	 *
	 * @global WP_Admin_Bar $wp_admin_bar
	 * @global wpdb $wpdb
	 */
	public function admin_bar_menu() {
		global $wp_admin_bar, $wpdb;
		$db = new DB\Database( $wpdb );
		$tables = $db->get_tables();
		if ( count( $tables ) > 10 ) {
			return;
		}
		foreach ( $tables as $table ) {
			if ( ! DB\Grants::current_user_can( DB\Grants::CREATE, $table->get_name() ) ) {
				continue;
			}
			$wp_admin_bar->add_menu( array(
				'parent' => 'new-content',
				'id'     => TABULATE_SLUG . '-' . $table->get_name(),
				'title'  => $table->get_title(),
				'href'   => $table->get_url( 'index', false, 'record' ),
			) );
		}
	}

	/**
	 * Print the currently-stored output; this is the callback for all the menu items.
	 */
	public function output() {
		echo $this->output;
	}

	/**
	 * Create and dispatch the controller, capturing its output for use later
	 * in the callback for the menu items.
	 *
	 * @return string The HTML to display.
	 */
	public function dispatch() {
		$request = $_REQUEST;

		// Only dispatch when it's our page.
		$slug_lenth = strlen( TABULATE_SLUG );
		if ( ! isset( $request['page'] ) || substr( $request['page'], 0, $slug_lenth ) !== TABULATE_SLUG ) {
			return;
		}

		// Discern the controller name, based on an explicit request parameter, or
		// the trailing part of the page slug (i.e. after 'tabulate_').
		$controller_name = 'home';
		if ( isset( $request['controller'] ) && strlen( $request['controller'] ) > 0 ) {
			$controller_name = $request['controller'];
		} elseif ( isset( $request['page'] ) && strlen( $request['page'] ) > $slug_lenth ) {
			$controller_name = substr( $request['page'], $slug_lenth + 1 );
		}

		// Create the controller and run the action.
		$controller_classname = '\\WordPress\\Tabulate\\Controllers\\' . ucfirst( $controller_name ) . 'Controller';
		if ( ! class_exists( $controller_classname ) ) {
			TabulateException::wp_die( "Controller '$controller_name' not found", 'Error', "Class doesn't exist: $controller_classname" );
		}
		$controller = new $controller_classname( $this->wpdb );
		$controller->set_filesystem( $this->filesystem );
		$action = ! empty( $request['action'] ) ? $request['action'] : 'index';
		unset( $request['page'], $request['controller'], $request['action'] );
		try {
			$this->output = $controller->$action( $request );
		} catch ( Exception $e ) {
			$this->output = '<h1>An error occured</h1><div class="error"><p>' . $e->getMessage() . '</p></div>';
			if ( WP_DEBUG ) {
				$this->output .= '<h2>Stack trace</h2><pre>' . $e->getTraceAsString() . '</pre>';
			}
		}
	}

	/**
	 * This is the callback method used in self::init() to add scripts and
	 * styles to the Tabulate admin pages and everywhere the shortcode is used.
	 *
	 * @param string $page The current page name.
	 * @return void
	 */
	public function enqueue( $page ) {
		// Make sure we only enqueue on Tabulate pages.
		$allowed_pages = array(
			'index.php', // For the Dashboard widget.
			'tabulate_shortcode', // Not really a page.
			'toplevel_page_tabulate',
			'tabulate_page_tabulate_erd',
			'tabulate_page_tabulate_reports',
			'tabulate_page_tabulate_grants',
			'tabulate_page_tabulate_schema',
		);
		if ( ! ( empty( $page ) || in_array( $page, $allowed_pages, true ) ) ) {
			return;
		}

		// Register dependency scripts.
		$maskedinput_url = plugins_url( TABULATE_SLUG ) . '/assets/jquery.maskedinput.min.js';
		wp_register_script( 'tabulate-maskedinput', $maskedinput_url, array( 'jquery' ), '1.4.1', true );
		$timepicker_url = plugins_url( TABULATE_SLUG ) . '/assets/jquery-ui-timepicker-addon.min.js';
		wp_register_script( 'tabulate-timepicker', $timepicker_url, array( 'jquery-ui-datepicker' ), TABULATE_VERSION, true );
		$omnivore_url = plugins_url( TABULATE_SLUG ) . '/assets/leaflet-omnivore.min.js';
		wp_register_script( 'tabulate-omnivore', $omnivore_url, array( 'tabulate-leaflet' ), '0.3.1', true );
		$leaflet_url = plugins_url( TABULATE_SLUG ) . '/assets/leaflet/js/leaflet.min.js';
		wp_register_script( 'tabulate-leaflet', $leaflet_url, null, TABULATE_VERSION, true );

		// Enqueue Tabulate's scripts.
		$script_url = plugins_url( TABULATE_SLUG ) . '/assets/scripts.js';
		$deps = array( 'jquery-ui-autocomplete', 'tabulate-omnivore', 'tabulate-maskedinput', 'tabulate-timepicker', 'wp-api' );
		wp_enqueue_script( 'tabulate-scripts', $script_url, $deps, TABULATE_VERSION, true );

		// Javascript page variables.
		$js_vars = array(
			'admin_url' => admin_url() . 'admin.php?page=' . TABULATE_SLUG,
		);
		wp_localize_script( 'tabulate-scripts', 'tabulate', $js_vars );

		// Add stylesheets.
		$timepicker_url = plugins_url( TABULATE_SLUG ) . '/assets/jquery-ui-timepicker-addon.css';
		wp_enqueue_style( 'tabulate-timepicker', $timepicker_url, null, TABULATE_VERSION );
		$leaflet_css_url = plugins_url( TABULATE_SLUG ) . '/assets/leaflet/css/leaflet.css';
		wp_enqueue_style( 'tabulate-leaflet', $leaflet_css_url, null, TABULATE_VERSION );
		$jqueryui_url = plugins_url( TABULATE_SLUG ) . '/assets/jquery-ui/themes/base/jquery-ui.min.css';
		wp_enqueue_style( 'tabulate-jquery-ui', $jqueryui_url, null, TABULATE_VERSION );
		$style_url = plugins_url( TABULATE_SLUG ) . '/assets/style.css';
		wp_enqueue_style( 'tabulate-styles', $style_url, null, TABULATE_VERSION );
	}
}
