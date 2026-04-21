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
		\add_filter( 'render_block_activitypub/followers', array( self::class, 'escape_at_signs' ) );
		\add_filter( 'render_block_activitypub/following', array( self::class, 'escape_at_signs' ) );
	}

	/**
	 * Escape `@` signs in block output to prevent BuddyPress mention linking.
	 *
	 * BuddyPress hooks `bp_activity_at_name_filter` into `the_content` to convert
	 * `@username` mentions into profile links. This corrupts the JSON in the
	 * `data-wp-context` attribute of Followers/Following blocks because the handles
	 * contain `@username` patterns that match BuddyPress's regex.
	 *
	 * Encoding `@` as `&#x40;` in the HTML attribute makes it invisible to
	 * BuddyPress's regex. The browser decodes the HTML entity before JavaScript
	 * reads the attribute, so the Interactivity API receives the original `@`.
	 *
	 * @since 8.1.0
	 *
	 * @param string $block_content The block content.
	 *
	 * @return string The block content with `@` signs escaped in data attributes.
	 */
	public static function escape_at_signs( $block_content ) {
		return \preg_replace_callback(
			'/data-wp-context="([^"]*)"/',
			static function ( $matches ) {
				return 'data-wp-context="' . \str_replace( '@', '&#x40;', $matches[1] ) . '"';
			},
			$block_content
		);
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
		if ( \function_exists( 'bp_members_get_user_url' ) ) {
			$author->url = bp_members_get_user_url( $author_id );
		} else {
			$author->url = bp_core_get_user_domain( $author_id );
		}

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
					\esc_attr( $author->url ),
					\esc_url( $author->url ),
					\wp_parse_url( $author->url, \PHP_URL_HOST )
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
