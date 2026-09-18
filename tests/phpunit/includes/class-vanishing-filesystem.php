<?php
/**
 * A filesystem whose first move fails, as it does when the directory is gone.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

/**
 * Reproduces the race where an entity is invalidated while its file is being cached.
 */
class Vanishing_Filesystem extends \WP_Filesystem_Direct {
	/**
	 * How many times move() was called.
	 *
	 * @var int
	 */
	public static $moves = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( null );
	}

	/**
	 * Delete the destination directory and fail on the first move, then behave normally.
	 *
	 * @param string $source      Source path.
	 * @param string $destination Destination path.
	 * @param bool   $overwrite   Optional. Whether to overwrite. Default false.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function move( $source, $destination, $overwrite = false ) {
		++self::$moves;

		if ( 1 === self::$moves ) {
			$this->rmdir( \dirname( $destination ), true );

			return false;
		}

		return parent::move( $source, $destination, $overwrite );
	}
}
