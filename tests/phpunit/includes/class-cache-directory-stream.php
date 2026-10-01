<?php
/**
 * Directory stream for testing cache read failures without OS permissions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Simulate unreadable, unopenable, vanished, missing and empty directories.
 *
 * Permission bits on real directories do not deny access to tests running as root.
 */
class Cache_Directory_Stream {

	/**
	 * Stream context supplied by PHP.
	 *
	 * @var resource|null
	 */
	public $context;

	/**
	 * Whether the directory disappeared while it was being opened.
	 *
	 * @var bool
	 */
	public static $vanished = false;

	/**
	 * Return directory permissions or indicate that the directory is missing.
	 *
	 * @param string $path  Directory URL.
	 * @param int    $flags Stat flags.
	 * @return array|false Directory stat or false when missing.
	 */
	public function url_stat( $path, $flags ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required stream-wrapper signature.
		$kind = \wp_parse_url( $path, PHP_URL_HOST );
		if ( 'missing' === $kind || self::$vanished ) {
			return false;
		}

		return array( 'mode' => 0040000 | ( 'unreadable' === $kind ? 0 : 0555 ) );
	}

	/**
	 * Fail to open existing directories or make one disappear during opening.
	 *
	 * @param string $path    Directory URL.
	 * @param int    $options Directory options.
	 * @return bool Whether opening succeeded.
	 */
	public function dir_opendir( $path, $options ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required stream-wrapper signature.
		$kind = \wp_parse_url( $path, PHP_URL_HOST );
		if ( 'vanished' === $kind ) {
			self::$vanished = true;
		}

		return 'empty' === $kind;
	}

	/**
	 * An opened directory contains no entries.
	 *
	 * @return false End of directory.
	 */
	public function dir_readdir() {
		return false;
	}

	/**
	 * Rewind the empty directory.
	 *
	 * @return bool Success.
	 */
	public function dir_rewinddir() {
		return true;
	}

	/**
	 * Close the directory.
	 *
	 * @return bool Success.
	 */
	public function dir_closedir() {
		return true;
	}
}
