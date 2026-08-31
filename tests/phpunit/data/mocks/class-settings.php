<?php
/**
 * Mock of Jetpack's podcast Settings for tests.
 *
 * @package Activitypub
 */

namespace Automattic\Jetpack\Podcast;

if ( ! class_exists( __NAMESPACE__ . '\Settings' ) ) {
	/**
	 * Minimal stand-in for {@see \Automattic\Jetpack\Podcast\Settings}.
	 *
	 * The real class reads the show image from an attachment ID or a stored URL; the mock returns
	 * whatever a test assigns to {@see self::$show_image_url}.
	 */
	class Settings {
		/**
		 * Show image URL returned by raw_show_image_url(), set by tests.
		 *
		 * @var string
		 */
		public static $show_image_url = '';

		/**
		 * Return the fixture show image URL.
		 *
		 * @return string
		 */
		public static function raw_show_image_url() {
			return self::$show_image_url;
		}
	}
}
