<?php

namespace WordPress\Tabulate;

class Template {

	protected $templateName;
	protected $data;

	public function __construct($templateName) {
		$this->templateName = $templateName;
		$this->data = array(
			'tabulate_version' => TABULATE_VERSION,
			'notices' => array(),
		);
	}

	public function __set($name, $value) {
		$this->data[ $name ] = $value;
	}

	/**
	 * Add a notice.
	 * @param string $type Either 'updated' or 'error'.
	 * @param string $message The message to display.
	 */
	public function add_notice($type, $message) {
		$this->data[ 'notices' ][] = array(
			'type' => $type,
			'message' => $message
		);
	}

	public function render() {
		$loader = new \Twig_Loader_Filesystem( __DIR__ . '/../templates' );
		$twig = new \Twig_Environment( $loader );

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
