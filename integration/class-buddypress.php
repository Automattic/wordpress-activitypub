<?php
namespace Activitypub\Integration;

/**
 * Compatibility with the BuddyPress plugin
 *
 * @see https://buddypress.org/
 */
class Buddypress {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'activitypub_json_author_array', array( self::class, 'add_user_metadata' ), 11, 2 );
	}

	public static function add_user_metadata( $object, $author_id ) {
		$object->url = bp_core_get_user_domain( $author_id ); //add BP member profile URL as user URL

		// add BuddyPress' cover_image instead of WordPress' header_image
		$cover_image_url = bp_attachments_get_attachment( 'url', array( 'item_id' => $author_id ) );

		if ( $cover_image_url ) {
			$object->image = array(
				'type' => 'Image',
				'url'  => $cover_image_url,
			);
		}

		// change profile URL to BuddyPress' profile URL
		$object->attachment['profile_url'] = array(
			'type' => 'PropertyValue',
			'name' => \__( 'Profile', 'activitypub' ),
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

		// replace blog URL on multisite
		if ( is_multisite() ) {
			$user_blogs = get_blogs_of_user( $author_id ); //get sites of user to send as AP metadata

			if ( ! empty( $user_blogs ) ) {
				unset( $object->attachment['blog_url'] );

				foreach ( $user_blogs as $blog ) {
					if ( 1 !== $blog->userblog_id ) {
						$object->attachment[] = array(
							'type' => 'PropertyValue',
							'name' => $blog->blogname,
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

		return $object;
	}
}
