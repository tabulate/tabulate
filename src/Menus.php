<?php

namespace WordPress\Tabulate;

class Menus {

	protected $wpdb;

	public function __construct($wpdb) {
		$this->wpdb = $wpdb;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
	}

	public function add_menu_pages() {
		$dispatch_callback = array( $this, 'dispatch' );

		// Home page.
		$hook_suffix = add_menu_page( 'Tabulate', 'Tabulate', 'read', TABULATE_SLUG, $dispatch_callback );
		add_submenu_page( TABULATE_SLUG, 'Tabulate Overview', 'Overview', 'read', TABULATE_SLUG, $dispatch_callback );
		add_action( "admin_print_scripts-$hook_suffix", array( $this, 'admin_print_scripts' ) );

		// Add submenu pages.
		$hook_suffix = add_submenu_page( TABULATE_SLUG, 'Tabulate Grants', 'Grants', 'promote_users', TABULATE_SLUG.'_grants', $dispatch_callback );
		add_action( "admin_print_scripts-$hook_suffix", array( $this, 'admin_print_scripts' ) );

	}

	/**
	 * This is the callback method used in self::add_menu_pages to add scripts
	 * and styles to the admin pages.
	 */
	public function admin_print_scripts() {
		// Scripts.
		$script_url = plugins_url( TABULATE_SLUG ) . '/assets/scripts.js';
		$deps = array( 'jquery-ui-autocomplete', 'wp-api' );
		wp_enqueue_script( 'tabulate-scripts', $script_url, $deps );
		$js_vars = array(
			'admin_url' => admin_url() . 'admin.php?page=' . TABULATE_SLUG
		);
		wp_localize_script( 'tabulate-scripts', 'tabulate', $js_vars );

		// Styles.
		$style_url = plugins_url( TABULATE_SLUG ) . '/assets/style.css';
		wp_enqueue_style( 'tabulate-styles', $style_url );
	}

	/**
	 * Create and dispatch the controller.
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
		$controller->$action( $_GET );
	}

}
