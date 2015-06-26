<?php

namespace WordPress\Tabulate;

class Template {

	protected $templateName;

	protected $data;

	/** @var string The name of the transient used to store notices. */
	protected $transient_notices;

	public function __construct( $templateName ) {
		global $wpdb;
		$this->templateName = $templateName;
		$this->transient_notices = TABULATE_SLUG . '_notices';
		$notices = get_transient( $this->transient_notices );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}
		$this->data = array(
			'tabulate_version' => TABULATE_VERSION,
			'notices' => $notices,
			'wp_api' => is_plugin_active( 'json-rest-api/plugin.php' ),
			'tfo_graphviz' => is_plugin_active( 'tfo-graphviz/tfo-graphviz.php' ),
			'wpdb_prefix' => $wpdb->prefix,
		);
	}

	public function __set( $name, $value ) {
		$this->data[ $name ] = $value;
	}

	/**
	 * Find out whether a given item of template data is set.
	 *
	 * @param string $name The property name.
	 * @return boolean
	 */
	public function __isset( $name ) {
		return isset( $this->data[ $name ] );
	}

	/**
	 * Get an item from this template's data.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get( $name ) {
		return $this->data[ $name ];
	}

	/**
	 * Add a notice. All notices are saved to a Transient, which is deleted when
	 * the template is rendered but otherwise available to all subsequent
	 * instances of the Template class.
	 * @param string $type Either 'updated' or 'error'.
	 * @param string $message The message to display.
	 */
	public function add_notice( $type, $message ) {
		$this->data['notices'][] = array(
			'type' => $type,
			'message' => $message,
		);
		set_transient( $this->transient_notices, $this->data['notices'] );
	}

	/**
	 * Render the template and output it.
	 * @return void
	 */
	public function __toString() {
		echo $this->render();
	}

	public function render() {
		delete_transient( $this->transient_notices );
		$loader = new \Twig_Loader_Filesystem( __DIR__ . '/../templates' );
		$twig = new \Twig_Environment( $loader );

		// Add the admin_url() function.
		$twig->addFunction( 'admin_url', new \Twig_SimpleFunction( 'admin_url', 'admin_url' ) );

		// Add titlecase filter.
		$titlecase_filter = new \Twig_SimpleFilter( 'titlecase', '\\WordPress\\Tabulate\\Text::titlecase' );
		$twig->addFilter( $titlecase_filter );

		// Add date and time filters.
		$date_filter = new \Twig_SimpleFilter( 'wp_date_format', '\\WordPress\\Tabulate\\Text::wp_date_format' );
		$twig->addFilter( $date_filter );
		$time_filter = new \Twig_SimpleFilter( 'wp_time_format', '\\WordPress\\Tabulate\\Text::wp_time_format' );
		$twig->addFilter( $time_filter );

		// Add strtolower filter.
		$strtolower_filter = new \Twig_SimpleFilter( 'strtolower', function( $str ){
			if ( is_array( $str ) ) {
				return array_map( 'strtolower', $str );
			} else {
				return strtolower( $str );
			}
		} );
		$twig->addFilter( $strtolower_filter );

		// Enable debugging.
		if ( WP_DEBUG ) {
			$twig->enableDebug();
			$twig->addExtension( new \Twig_Extension_Debug() );
		}

		// Render the template.
		$template = $twig->loadTemplate( $this->templateName );
		return $template->render( $this->data );
	}

}
