<?php
/**
 * Multisite Language Switcher integration class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Collection\Outbox;

/**
 * Compatibility with the Multisite Language Switcher plugin.
 *
 * @see https://github.com/lloc/Multisite-Language-Switcher/
 */
class Multisite_Language_Switcher {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'save_post', array( self::class, 'ignore_outbox_post' ), 9, 2 );
		\add_action( 'save_post', array( self::class, 'unignore_outbox_post' ), 11, 2 );
	}

	/**
	 * Short-circuit saving Multisite Language Switcher data for the Outbox post type.
	 *
	 * @param int      $post_id The post id.
	 * @param \WP_Post $post The post object.
	 */
	public static function ignore_outbox_post( $post_id, $post ) {
		if ( Outbox::POST_TYPE === $post->post_type ) {
			\add_action( 'msls_main_save', '__return_null' );
		}
	}

	/**
	 * Remove short-circuit for Multisite Language Switcher data.
	 *
	 * @param int      $post_id The post id.
	 * @param \WP_Post $post The post object.
	 */
	public static function unignore_outbox_post( $post_id, $post ) {
		if ( Outbox::POST_TYPE === $post->post_type ) {
			\remove_action( 'msls_main_save', '__return_null' );
		}
	}
}
