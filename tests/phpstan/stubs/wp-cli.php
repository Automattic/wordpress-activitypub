<?php
/**
 * Minimal WP-CLI stubs for static analysis: only what the plugin calls.
 *
 * @package Activitypub
 */

// phpcs:ignoreFile

class WP_CLI {
	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public static function add_command( ...$args ) {}
	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public static function colorize( ...$args ) {}
	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public static function confirm( ...$args ) {}
	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public static function error( ...$args ) {}
	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public static function line( ...$args ) {}
	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public static function log( ...$args ) {}
	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public static function success( ...$args ) {}
	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	public static function warning( ...$args ) {}
}

class WP_CLI_Command {}

