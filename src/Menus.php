<?php

namespace WordPress\Tabulate;

class Menus {

	/**
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * The page output is stored between being called/created in
	 * self::dispatch() and output in self::add_menu_pages()
	 *
	 * @var string
	 */
	protected $output;

	public function __construct($wpdb) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Set up all required hooks.
	 * 
	 * This is called from the top level of tabulate.php
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'dispatch' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
	}

	/**
	 * Add 
	 */
	public function add_menu_pages() {
		$dispatch_callback = array( $this, 'output' );

		// Home page (also change the first submenu item's title).
		add_menu_page( 'Tabulate', 'Tabulate', 'read', TABULATE_SLUG, $dispatch_callback );
		add_submenu_page( TABULATE_SLUG, 'Tabulate Overview', 'Overview', 'read', TABULATE_SLUG, $dispatch_callback );

		// Add submenu pages.
		add_submenu_page( TABULATE_SLUG, 'Tabulate Grants', 'Grants', 'promote_users', TABULATE_SLUG.'_grants', $dispatch_callback );

	}

	/**
	 * Get any currently-stored output. This is the callback for all the menu
	 * items.
	 *
	 * @return string The page HTML.
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

		// Discern the controller name, based on an explicit GET parameter, or
		// the trailing part of the page slug (i.e. after 'tabulate_').
		$controllerName = 'home';
		if ( isset( $_GET['controller'] ) ) {
			$controllerName = $_GET['controller'];
		} elseif ( strlen( $_GET['page'] ) > strlen( TABULATE_SLUG ) ) {
			$controllerName = substr( $_GET['page'], strlen( TABULATE_SLUG ) + 1 );
		}

		// Create the controller and run the action.
		$controllerClassName = 'WordPress\\Tabulate\\Controllers\\' . ucfirst( $controllerName ) . 'Controller';
		$controller = new $controllerClassName( $this->wpdb );
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'index';
		unset( $_GET['page'], $_GET['controller'], $_GET['action'] );
		$this->output = $controller->$action( $_GET );
	}

	/**
	 * This is the callback method used in self::init() to add scripts and
	 * styles to the admin pages.
	 * 
	 * @return void
	 */
	public function admin_enqueue($page) {
		// Make sure we only enqueue on Tabulate pages.
		$allowed_pages = array('toplevel_page_tabulate', 'tabulate_page_tabulate_grants');
		if ( ! in_array( $page, $allowed_pages ) ) {
			return;
		}

		// Add scripts.
		$script_url = plugins_url( TABULATE_SLUG ) . '/assets/scripts.js';
		$deps = array( 'jquery-ui-datepicker', 'jquery-ui-autocomplete' );
		if ( is_plugin_active( 'json-rest-api/plugin.php' ) ) {
			$deps[] = 'wp-api';
		}
		wp_enqueue_script( 'tabulate-scripts', $script_url, $deps, TABULATE_VERSION );
		$js_vars = array(
			'admin_url' => admin_url() . 'admin.php?page=' . TABULATE_SLUG
		);
		wp_localize_script( 'tabulate-scripts', 'tabulate', $js_vars );

		// Add stylesheets.
		$style_url_1 = plugins_url( TABULATE_SLUG ) . '/assets/jquery-ui-1.11.4/jquery-ui.min.css';
		wp_enqueue_style( 'tabulate-jquery-ui', $style_url_1, null, TABULATE_VERSION );
		$style_url_2 = plugins_url( TABULATE_SLUG ) . '/assets/style.css';
		wp_enqueue_style( 'tabulate-styles', $style_url_2, null, TABULATE_VERSION );
	}

}
