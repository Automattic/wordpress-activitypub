<?php
/**
 * Minimal WP-CLI utility stub for command tests.
 *
 * @package Activitypub
 */

namespace WP_CLI\Utils;

if ( ! \function_exists( __NAMESPACE__ . '\\get_flag_value' ) ) {
	/**
	 * Read a command flag or its default.
	 *
	 * @param array  $assoc_args Command arguments.
	 * @param string $flag       Flag name.
	 * @param mixed  $fallback   Default value.
	 * @return mixed Flag value.
	 */
	function get_flag_value( $assoc_args, $flag, $fallback = null ) {
		return \array_key_exists( $flag, $assoc_args ) ? $assoc_args[ $flag ] : $fallback;
	}
}
