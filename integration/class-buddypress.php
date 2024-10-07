<?php
/**
 * BuddyPress integration class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

/**
 * Compatibility with the BuddyPress plugin.
 *
 * @see https://buddypress.org/
 */
class Buddypress {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_json_author_array', array( self::class, 'add_user_metadata' ), 11, 2 );
	}

	/**
	 * Add BuddyPress user metadata to the author array.
	 *
	 * @param object $author    The author object.
	 * @param int    $author_id The author ID.
	 *
	 * @return object The author object.
	 */
	public static function add_user_metadata( $author, $author_id ) {
		$author->url = bp_core_get_user_domain( $author_id ); // Add BP member profile URL as user URL.

		// Add BuddyPress' cover_image instead of WordPress' header_image.
		$cover_image_url = bp_attachments_get_attachment( 'url', array( 'item_id' => $author_id ) );

		if ( $cover_image_url ) {
			$author->image = array(
				'type' => 'Image',
				'url'  => $cover_image_url,
			);
		}

		// Change profile URL to BuddyPress' profile URL.
		$author->attachment['profile_url'] = array(
			'type'  => 'PropertyValue',
			'name'  => \__( 'Profile', 'activitypub' ),
			'value' => \html_entity_decode(
				sprintf(
					'<a rel="me" title="%s" target="_blank" href="%s">%s</a>',
					\esc_attr( bp_core_get_user_domain( $author_id ) ),
					\bp_core_get_user_domain( $author_id ),
					\wp_parse_url( \bp_core_get_user_domain( $author_id ), \PHP_URL_HOST )
				),
				\ENT_QUOTES,
				'UTF-8'
			),
		);

		// Replace blog URL on multisite.
		if ( is_multisite() ) {
			$user_blogs = get_blogs_of_user( $author_id ); // Get sites of user to send as AP metadata.

			if ( ! empty( $user_blogs ) ) {
				unset( $author->attachment['blog_url'] );

				foreach ( $user_blogs as $blog ) {
					if ( 1 !== $blog->userblog_id ) {
						$author->attachment[] = array(
							'type'  => 'PropertyValue',
							'name'  => $blog->blogname,
							'value' => \html_entity_decode(
								sprintf(
									'<a rel="me" title="%s" target="_blank" href="%s">%s</a>',
									\esc_attr( $blog->siteurl ),
									$blog->siteurl,
									\wp_parse_url( $blog->siteurl, \PHP_URL_HOST )
								),
								\ENT_QUOTES,
								'UTF-8'
							),
						);
					}
				}
			}
		}

		return $author;
	}
}
