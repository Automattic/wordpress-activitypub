<?php
/**
 * Mock of Jetpack's podcast Episode_Block_Tags for tests.
 *
 * @package Activitypub
 */

namespace Automattic\Jetpack\Podcast\Feed;

/**
 * Minimal stand-in for {@see \Automattic\Jetpack\Podcast\Feed\Episode_Block_Tags}.
 *
 * The real class parses the `jetpack/podcast-episode` block out of a post; the mock returns
 * whatever attributes a test assigns to {@see self::$attrs}, so the integration can be exercised
 * without the Jetpack podcast package installed.
 */
class Episode_Block_Tags {
	/**
	 * Block attributes returned by get_block_attrs(), set by tests.
	 *
	 * @var array
	 */
	public static $attrs = array();

	/**
	 * Return the fixture block attributes.
	 *
	 * @param \WP_Post $post The post being transformed.
	 *
	 * @return array
	 */
	public static function get_block_attrs( $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return self::$attrs;
	}
}
