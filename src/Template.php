<?php

namespace WordPress\Tabulate;

class Template {

	/** @var string */
	protected $template_name;

	/** @var string */
	protected $template_string;

	/** @var string[] */
	protected $data;

	/** @var string[] Paths at which to find templates. */
	protected static $paths = array();

	/** @var string The name of the transient used to store notices. */
	protected $transient_notices;

	/**
	 * Create a new template either with a file-based Twig template, or a Twig string.
	 * @global type $wpdb
	 * @param string|false $template_name
	 * @param string|false $template_string
	 */
	public function __construct( $template_name = false, $template_string = false ) {
		global $wpdb;
		$this->template_name = $template_name;
		$this->template_string = $template_string;
		$this->transient_notices = TABULATE_SLUG . '_notices';
		$notices = get_transient( $this->transient_notices );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}
		$this->data = array(
			'tabulate_version' => TABULATE_VERSION,
			'notices' => $notices,
			'wp_api' => is_plugin_active( 'rest-api/plugin.php' ),
			'tfo_graphviz' => is_plugin_active( 'tfo-graphviz/tfo-graphviz.php' ),
			'wpdb_prefix' => $wpdb->prefix,
		);
		self::add_path( __DIR__ . '/../templates' );
	}

	/**
	 * Add a filesystem path under which to look for template files.
	 * @param string $new_path
	 */
	public static function add_path( $new_path ) {
		$path = realpath( $new_path );
		if ( ! in_array( $path, self::$paths ) ) {
			self::$paths[] = $path;
		}
	}

	public static function get_paths() {
		return self::$paths;
	}

	/**
	 * Get a list of templates in a given directory, across all registered template paths.
	 * @param string $directory
	 */
	public function get_templates( $directory ) {
		$templates = array();
		foreach ( self::$paths as $path ) {
			$dir = $path . '/' . ltrim( $directory, '/' );
			foreach ( preg_grep( '/^[^\.].*\.(twig|html)$/', scandir( $dir ) ) as $file ) {
				$templates[] = $directory . '/' . $file;
			}
		}
		return $templates;
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

	/**
	 * Render the template and return the output.
	 * @return string
	 */
	public function render() {
		delete_transient( $this->transient_notices );
		$loader = new \Twig_Loader_Filesystem( self::$paths );
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
		$twig->addFilter( new \Twig_SimpleFilter( 'get_date_from_gmt', 'get_date_from_gmt' ) );

		// Add strtolower filter.
		$strtolower_filter = new \Twig_SimpleFilter( 'strtolower', function( $str ) {
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
		if ( ! empty( $this->template_string ) ) {
			$template = $twig->createTemplate( $this->template_string );
		} else {
			$template = $twig->loadTemplate( $this->template_name );
		}
		return $template->render( $this->data );
	}

}
