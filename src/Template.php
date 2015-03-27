<?php

namespace WordPress\Tabulate;

class Template {

	protected $templateName;
	protected $data;

	public function __construct($templateName) {
		$this->templateName = $templateName;
		$this->data = array();
	}

	public function __set($name, $value) {
		$this->data[ $name ] = $value;
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
