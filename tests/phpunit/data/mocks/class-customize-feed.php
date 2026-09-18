<?php
/**
 * Mock of Jetpack's podcast Customize_Feed for tests.
 *
 * @package Activitypub
 */

namespace Automattic\Jetpack\Podcast\Feed;

if ( ! class_exists( __NAMESPACE__ . '\Customize_Feed' ) ) {
	/**
	 * Minimal stand-in for {@see \Automattic\Jetpack\Podcast\Feed\Customize_Feed}.
	 *
	 * The real class resolves the podcast category from either an ID or an archive slug; the mock
	 * returns whatever a test assigns to {@see self::$category_id}.
	 */
	class Customize_Feed {
		/**
		 * Podcast category ID returned by resolve_category_id(), set by tests.
		 *
		 * @var int
		 */
		public static $category_id = 0;

		/**
		 * Return the fixture podcast category ID.
		 *
		 * @return int
		 */
		public static function resolve_category_id() {
			return self::$category_id;
		}
	}
}
