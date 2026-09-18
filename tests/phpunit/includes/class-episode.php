<?php
/**
 * Minimal Podlove Episode model stub for tests.
 *
 * @package Activitypub
 */

namespace Podlove\Model;

if ( ! class_exists( __NAMESPACE__ . '\Episode' ) ) {
	/**
	 * Stub of the Podlove Episode model exposing only what the integration reads.
	 *
	 * `find_one_by_post_id()` returns whatever the test assigns to `$mock` (null by
	 * default, so the absent-Podlove fallbacks keep working). The audio/duration/
	 * cover/title accessors return empty values so `to_object()` can build a full
	 * object without fatals while a test exercises the summary path. Parameters the
	 * real API takes are omitted; PHP ignores the extra call arguments.
	 */
	class Episode {
		/**
		 * The episode returned by find_one_by_post_id(), or null.
		 *
		 * @var self|null
		 */
		public static $mock = null;

		/**
		 * The episode summary.
		 *
		 * @var string
		 */
		public $summary = '';

		/**
		 * Resolve the episode for a post id.
		 *
		 * @return self|null The mocked episode or null.
		 */
		public static function find_one_by_post_id() {
			return self::$mock;
		}

		/**
		 * Media files for the episode.
		 *
		 * @return array Always empty in the stub.
		 */
		public function media_files() {
			return array();
		}

		/**
		 * Episode duration.
		 *
		 * @return null Always null in the stub.
		 */
		public function get_duration() {
			return null;
		}

		/**
		 * Episode cover art.
		 *
		 * @return null Always null in the stub.
		 */
		public function cover_art_with_fallback() {
			return null;
		}

		/**
		 * Episode title.
		 *
		 * @return string Always empty in the stub.
		 */
		public function title() {
			return '';
		}
	}
}
