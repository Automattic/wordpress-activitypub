<?php
/**
 * KSES-safe Insert Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

/**
 * KSES-safe Insert Trait.
 *
 * The CPT-backed collections store ActivityPub JSON in `post_content`. KSES would
 * corrupt that JSON, so it has to be switched off around the write and switched back
 * on afterwards. This wrapper does the toggle once and restores it on every path.
 */
trait With_Kses_Safe_Insert {

	/**
	 * Run a post write with KSES content filtering temporarily disabled.
	 *
	 * @param callable $callback The write to run (e.g. wp_insert_post/wp_update_post).
	 *
	 * @return mixed Whatever the callback returns.
	 */
	protected static function without_kses( $callback ) {
		$has_kses = false !== \has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $has_kses ) {
			// Prevent KSES from corrupting JSON in post_content.
			\kses_remove_filters();
		}

		try {
			return $callback();
		} finally {
			if ( $has_kses ) {
				// Restore KSES filters even if the write threw.
				\kses_init_filters();
			}
		}
	}
}
