<?php
/**
 * Autoloader for Activitypub.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * An Autoloader that respects WordPress's filename standards.
 */
class Autoloader {

	/**
	 * Namespace separator.
	 */
	const NS_SEPARATOR = '\\';

	/**
	 * The prefix to compare classes against.
	 *
	 * @var string
	 * @access protected
	 */
	protected $prefix;

	/**
	 * Length of the prefix string.
	 *
	 * @var int
	 * @access protected
	 */
	protected $prefix_length;

	/**
	 * Path to the file to be loaded.
	 *
	 * @var string
	 * @access protected
	 */
	protected $path;

	/**
	 * Constructor.
	 *
	 * @param string $prefix Namespace prefix all classes have in common.
	 * @param string $path   Path to the files to be loaded.
	 */
	public function __construct( $prefix, $path ) {
		$this->prefix        = $prefix;
		$this->prefix_length = \strlen( $prefix );
		$this->path          = \rtrim( $path . '/' );
	}

	/**
	 * Registers Autoloader's autoload function.
	 *
	 * @throws \Exception When autoload_function cannot be registered.
	 *
	 * @param string $prefix Namespace prefix all classes have in common.
	 * @param string $path   Path to the files to be loaded.
	 */
	public static function register_path( $prefix, $path ) {
		$loader = new self( $prefix, $path );
		\spl_autoload_register( array( $loader, 'load' ) );
	}

	/**
	 * Loads a class if its namespace starts with `$this->prefix`.
	 *
	 * @param string $class_name The class to be loaded.
	 */
	public function load( $class_name ) {
		if ( \strpos( $class_name, $this->prefix . self::NS_SEPARATOR ) !== 0 ) {
			return;
		}

		// Strip prefix from the start (ala PSR-4).
		$class_name = \substr( $class_name, $this->prefix_length + 1 );
		$class_name = \strtolower( $class_name );
		$dir        = '';

		$last_ns_pos = \strripos( $class_name, self::NS_SEPARATOR );
		if ( false !== $last_ns_pos ) {
			$namespace  = \substr( $class_name, 0, $last_ns_pos );
			$namespace  = \str_replace( '_', '-', $namespace );
			$class_name = \substr( $class_name, $last_ns_pos + 1 );
			$dir        = \str_replace( self::NS_SEPARATOR, DIRECTORY_SEPARATOR, $namespace ) . DIRECTORY_SEPARATOR;
		}

		$path = $this->path . $dir . 'class-' . \str_replace( '_', '-', $class_name ) . '.php';

		if ( ! \file_exists( $path ) ) {
			$path = $this->path . $dir . 'interface-' . \str_replace( '_', '-', $class_name ) . '.php';
		}

		if ( ! \file_exists( $path ) ) {
			$path = $this->path . $dir . 'trait-' . \str_replace( '_', '-', $class_name ) . '.php';
		}

		if ( \file_exists( $path ) ) {
			require_once $path;
		}
	}
}
