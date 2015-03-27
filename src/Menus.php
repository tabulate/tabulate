<?php

namespace WordPress\Tabulate;

class Menus {

	protected $wpdb;

	const HOME_SLUG = 'tabulate';

	public function __construct($wpdb) {
		$this->wpdb = $wpdb;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
	}

	public function add_menu_pages() {
		$page_hook_suffix = add_menu_page( 'Tabulate', 'Tabulate', 'read', self::HOME_SLUG, array( $this, 'dispatch' ), null, 20 );

		// Add scripts.
		add_action( "admin_print_scripts-$page_hook_suffix", function() {

			// Scripts.
			$script_url = plugins_url( 'tabulate' ) . '/assets/scripts.js';
			wp_enqueue_script( 'tabulate-scripts', $script_url );

			// Styles.
			$style_url = plugins_url( 'tabulate' ) . '/assets/style.css';
			wp_enqueue_style( 'tabulate-styles', $style_url );
		} );

		// Add submenu pages.
//		$db = new DB\Database( $this->wpdb );
//		foreach ( $db->get_table_names() as $table ) {
//			// The submenu adding is a bit odd, and should be explained.
//			$title = Text::titlecase( $table );
//			$slug = self::HOME_SLUG . '&controller=table&action=index&table=' . $table;
//			add_submenu_page( self::HOME_SLUG, $title, $title, 'read', $slug, 'nop' );
//		}
	}

	/**
	 * Create and dispatch the controller.
	 */
	public function dispatch() {
		$controllerName = (isset( $_GET['controller'] )) ? $_GET['controller'] : 'home';
		$controllerClassName = 'WordPress\\Tabulate\\Controllers\\' . ucfirst( $controllerName ) . 'Controller';
		$controller = new $controllerClassName( $this->wpdb );
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'index';
		unset( $_GET['page'], $_GET['controller'], $_GET['action'] );
		$controller->$action( $_GET );
	}

}
