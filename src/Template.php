<?php

namespace WordPress\Tabulate;

class Template {

	protected $templateName;
	protected $data;

	public function __construct( $templateName ) {
		$this->templateName = $templateName;
		$this->data = array(
			'tabulate_version' => TABULATE_VERSION,
			'notices' => array(),
			'wp_api' => is_plugin_active( 'json-rest-api/plugin.php' ),
			'tfo_graphviz' => is_plugin_active( 'tfo-graphviz/tfo-graphviz.php' ),
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
	 * Add a notice.
	 * @param string $type Either 'updated' or 'error'.
	 * @param string $message The message to display.
	 */
	public function add_notice( $type, $message ) {
		$this->data['notices'][] = array(
			'type' => $type,
			'message' => $message,
		);
	}

	/**
	 * Render the template and output it.
	 * @return void
	 */
	public function __toString() {
		echo $this->render();
	}

	public function render() {
		$loader = new \Twig_Loader_Filesystem( __DIR__ . '/../templates' );
		$twig = new \Twig_Environment( $loader );

		// Add the admin_url() function.
		$twig->addFunction('admin_url', new \Twig_SimpleFunction( 'admin_url', 'admin_url' ) );

		// Add titlecase filter.
		$titlecase_filter = new \Twig_SimpleFilter( 'titlecase', '\\WordPress\\Tabulate\\Text::titlecase' );
		$twig->addFilter( $titlecase_filter );

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
