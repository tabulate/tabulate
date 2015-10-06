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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		// @TODO Only enable this once it can be scrolled when it's long.
		// add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ) );
	}

	/**
	 * Add Tabulate's menu items to the main admin menu.
	 * @return void
	 */
	public function add_menu_pages() {
		$dispatch_callback = array( $this, 'output' );

		// Home page (also change the first submenu item's title).
		add_menu_page( 'Tabulate', 'Tabulate', 'read', TABULATE_SLUG, $dispatch_callback );
		$page_title = ( isset( $_GET['table'] ) ) ? Text::titlecase( $_GET['table'] ) : 'Tabulate';
		add_submenu_page( TABULATE_SLUG, $page_title, 'Overview', 'read', TABULATE_SLUG, $dispatch_callback );

		// Add submenu pages.
		if ( is_plugin_active( 'tfo-graphviz/tfo-graphviz.php' ) ) {
			add_submenu_page( TABULATE_SLUG, 'Tabulate ERD', 'ERD', 'read', TABULATE_SLUG.'_erd', $dispatch_callback );
		}
		//add_submenu_page( TABULATE_SLUG, 'Tabulate Reports', 'Reports', 'promote_users', TABULATE_SLUG.'_reports', $dispatch_callback );
		add_submenu_page( TABULATE_SLUG, 'Tabulate Grants', 'Grants', 'promote_users', TABULATE_SLUG.'_grants', $dispatch_callback );
	}

	/**
	 * Add all tables in which the user is allowed to create records to the
	 * Admin Bar new-content menu.
	 * @global \WP_Admin_Bar $wp_admin_bar
	 * @global \wpdb $wpdb
	 */
	public function admin_bar_menu() {
		global $wp_admin_bar, $wpdb;
		$db = new DB\Database( $wpdb );
		foreach ( $db->get_tables() as $table ) {
			if ( ! DB\Grants::current_user_can( DB\Grants::CREATE, $table->get_name() ) ) {
				continue;
			}
			$wp_admin_bar->add_menu( array(
				'parent' => 'new-content',
				'id'     => TABULATE_SLUG . '-' . $table->get_name(),
				'title'  => $table->get_title(),
				'href'   => $table->get_url( 'index', null, 'record' ),
			) );
		}
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
		$request = $_REQUEST;

		// Only dispatch when it's our page.
		$slugLenth = strlen( TABULATE_SLUG );
		if ( ! isset( $request['page'] ) || substr( $request['page'], 0, $slugLenth ) != TABULATE_SLUG ) {
			return;
		}

		// Discern the controller name, based on an explicit request parameter, or
		// the trailing part of the page slug (i.e. after 'tabulate_').
		$controllerName = 'home';
		if ( isset( $request['controller'] ) ) {
			$controllerName = $request['controller'];
		} elseif ( isset( $request['page'] ) && strlen( $request['page'] ) > $slugLenth ) {
			$controllerName = substr( $request['page'], $slugLenth + 1 );
		}

		// Create the controller and run the action.
		$controllerClassName = 'WordPress\\Tabulate\\Controllers\\' . ucfirst( $controllerName ) . 'Controller';
		$controller = new $controllerClassName( $this->wpdb );
		$action = ! empty( $request[ 'action' ] ) ? $request[ 'action' ] : 'index';
		unset( $request[ 'page' ], $request[ 'controller' ], $request[ 'action' ] );
		$this->output = $controller->$action( $request );
	}

	/**
	 * This is the callback method used in self::init() to add scripts and
	 * styles to the Tabulate admin pages and everywhere the shortcode is used.
	 *
	 * @return void
	 */
	public function enqueue( $page ) {
		// Make sure we only enqueue on Tabulate pages.
		$allowed_pages = array(
			'index.php', // For the Dashboard widget.
			'tabulate_shortcode', // Not really a page! :-(
			'toplevel_page_tabulate',
			'tabulate_page_tabulate_erd',
			//'tabulate_page_tabulate_reports',
			'tabulate_page_tabulate_grants',
		);
		if ( ! ( empty( $page ) || in_array( $page, $allowed_pages ) ) ) {
			return;
		}

		// Register dependency scripts.
		$maskedinput_url = plugins_url( TABULATE_SLUG ) . '/assets/jquery.maskedinput.min.js';
		wp_register_script( 'tabulate-maskedinput', $maskedinput_url, array( 'jquery' ), '1.4.1', true );
		$timepicker_url = plugins_url( TABULATE_SLUG ) . '/assets/jquery-ui-timepicker-addon.min.js';
		wp_register_script( 'tabulate-timepicker', $timepicker_url, array( 'jquery-ui-datepicker' ), TABULATE_VERSION, true );
		$onmivore_url = plugins_url( TABULATE_SLUG ) . '/assets/leaflet/leaflet-omnivore.min.js';
		wp_register_script( 'tabulate-onmivore', $onmivore_url, array( 'tabulate-leaflet' ), TABULATE_VERSION, true );
		$leaflet_url = plugins_url( TABULATE_SLUG ) . '/assets/leaflet/leaflet.js';
		wp_register_script( 'tabulate-leaflet', $leaflet_url, null, TABULATE_VERSION, true );

		// Enqueue Tabulate's scripts.
		$script_url = plugins_url( TABULATE_SLUG ) . '/assets/scripts.js';
		$deps = array( 'jquery-ui-autocomplete', 'tabulate-leaflet', 'tabulate-maskedinput', 'tabulate-timepicker' );
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'rest-api/plugin.php' ) ) {
			$deps[] = 'wp-api';
		}
		wp_enqueue_script( 'tabulate-scripts', $script_url, $deps, TABULATE_VERSION, true );

		// Javascript page variables.
		$js_vars = array(
			'admin_url' => admin_url() . 'admin.php?page=' . TABULATE_SLUG
		);
		wp_localize_script( 'tabulate-scripts', 'tabulate', $js_vars );

		// Add stylesheets.
		$timepicker_url = plugins_url( TABULATE_SLUG ) . '/assets/jquery-ui-timepicker-addon.css';
		wp_enqueue_style( 'tabulate-timepicker', $timepicker_url, null, TABULATE_VERSION );
		$leaflet_css_url = plugins_url( TABULATE_SLUG ) . '/assets/leaflet/leaflet.css';
		wp_enqueue_style( 'tabulate-leaflet', $leaflet_css_url, null, TABULATE_VERSION );
		$jqueryui_url = plugins_url( TABULATE_SLUG ) . '/assets/jquery-ui/jquery-ui.min.css';
		wp_enqueue_style( 'tabulate-jquery-ui', $jqueryui_url, null, TABULATE_VERSION );
		$jqueryui_theme_url = plugins_url( TABULATE_SLUG ) . '/assets/jquery-ui/jquery-ui.theme.min.css';
		wp_enqueue_style( 'tabulate-jquery-ui-theme', $jqueryui_theme_url, null, TABULATE_VERSION );
		$style_url = plugins_url( TABULATE_SLUG ) . '/assets/style.css';
		wp_enqueue_style( 'tabulate-styles', $style_url, null, TABULATE_VERSION );
	}

}
