<?php

namespace Activitypub\Collection;

use Activitypub\Link;
use WP_Query;
use Activitypub\Collection\Users;

use function Activitypub\site_supports_blocks;

class Extra_Fields {

	const USER_POST_TYPE = 'ap_extrafield';
	const BLOG_POST_TYPE = 'ap_extrafield_blog';

	/**
	 * Get the extra fields for a user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return \WP_Post[] The extra fields.
	 */
	public static function get_actor_fields( $user_id ) {
		$is_blog = self::is_blog( $user_id );
		$post_type = $is_blog ? self::BLOG_POST_TYPE : self::USER_POST_TYPE;
		$args = array(
			'post_type' => $post_type,
			'nopaging'  => true,
			'orderby'   => 'menu_order',
			'order'     => 'ASC',
		);
		if ( ! $is_blog ) {
			$args['author'] = $user_id;
		}

		$query = new \WP_Query( $args );
		$fields = $query->posts ?? array();

		return apply_filters( 'activitypub_get_actor_extra_fields', $fields, $user_id );
	}

	private static function get_formatted_content( $post ) {
		$content = \get_the_content( null, false, $post );
		$content = Link::the_content( $content, true );
		$content = \do_blocks( $content );
		$content = \wptexturize( $content );
		$content = \wp_filter_content_tags( $content );
		// replace script and style elements
		$content = \preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $content );
		$content = \strip_shortcodes( $content );
		$content = \trim( \preg_replace( '/[\n\r\t]/', '', $content ) );

		return $content;
	}

	public static function fields_to_attachments( $fields ) {
		$attachments = array();
		\add_filter(
			'activitypub_link_rel',
			function( $rel ) {
				$rel .= ' me';

				return $rel;
			}
		);

		foreach ( $fields as $post ) {
			$content = self::get_formatted_content( $post );
			$attachments[] = array(
				'type' => 'PropertyValue',
				'name' => \get_the_title( $post ),
				'value' => \html_entity_decode(
					$content,
					\ENT_QUOTES,
					'UTF-8'
				),
			);

			$link_added = false;

			// Add support for FEP-fb2a, for more information see FEDERATION.md
			$link_content = \trim( \strip_tags( $content, '<a>' ) );
			if (
				\stripos( $link_content, '<a' ) === 0 &&
				\stripos( $link_content, '<a', 3 ) === false &&
				\stripos( $link_content, '</a>', \strlen( $link_content ) - 4 ) !== false &&
				\class_exists( '\WP_HTML_Tag_Processor' )
			) {
				$tags = new \WP_HTML_Tag_Processor( $link_content );
				$tags->next_tag( 'A' );

				if ( 'A' === $tags->get_tag() ) {
					$attachment = array(
						'type' => 'Link',
						'name' => \get_the_title( $post ),
						'href' => \esc_url( $tags->get_attribute( 'href' ) ),
						'rel' => explode( ' ', $tags->get_attribute( 'rel' ) ),
					);

					$link_added = true;
				}
			}

			if ( ! $link_added ) {
				$attachment = array(
					'type'    => 'Note',
					'name'    => \get_the_title( $post ),
					'content' => \html_entity_decode(
						$content,
						\ENT_QUOTES,
						'UTF-8'
					),
				);
			}

			$attachments[] = $attachment;
		}

		return $attachments;
	}

	/**
	 * Check if a post type is an extra fields post type.
	 *
	 * @param string $post_type The post type.
	 *
	 * @return bool True if the post type is an extra fields post type, otherwise false.
	 */
	public static function is_extra_fields_post_type( $post_type ) {
		return \in_array( $post_type, array( self::USER_POST_TYPE, self::BLOG_POST_TYPE ), true );
	}

	/**
	 * Add default extra fields to an actor.
	 *
	 * @param array $extra_fields The extra fields.
	 * @param int   $user_id      The User-ID.
	 *
	 * @return array The extra fields.
	 */
	public static function default_actor_extra_fields( $extra_fields, $user_id ) {
		// We'll only take action when there are none yet.
		if ( ! empty( $extra_fields ) ) {
			return $extra_fields;
		}

		$is_blog = self::is_blog( $user_id );
		$already_migrated = $is_blog
			? \get_option( 'activitypub_default_extra_fields' )
			: \get_user_meta( $user_id, 'activitypub_default_extra_fields', true );

		if ( $already_migrated ) {
			return $extra_fields;
		}

		\add_filter(
			'activitypub_link_rel',
			function( $rel ) {
				$rel .= ' me';

				return $rel;
			}
		);

		$defaults = array(
			\__( 'Blog', 'activitypub' ) => \home_url( '/' ),
		);

		if ( ! $is_blog ) {
			$author_url = \get_the_author_meta( 'user_url', $user_id );
			$author_posts_url = \get_author_posts_url( $user_id );

			$defaults[ \__( 'Profile', 'activitypub' ) ] = $author_posts_url;
			if ( $author_url !== $author_posts_url ) {
				$defaults[ \__( 'Homepage', 'activitypub' ) ] = $author_url;
			}
		}

		$post_type  = $is_blog ? self::BLOG_POST_TYPE : self::USER_POST_TYPE;
		$menu_order = 10;

		foreach ( $defaults as $title => $url ) {
			if ( ! $url ) {
				continue;
			}

			$extra_field = array(
				'post_type'      => $post_type,
				'post_title'     => $title,
				'post_status'    => 'publish',
				'post_author'    => $user_id,
				'post_content'   => self::make_paragraph_block( Link::the_content( $url ) ),
				'comment_status' => 'closed',
				'menu_order'     => $menu_order,
			);

			$menu_order += 10;
			$extra_field_id = wp_insert_post( $extra_field );
			$extra_fields[] = get_post( $extra_field_id );
		}

		$is_blog
			? \update_option( 'activitypub_default_extra_fields', true )
			: \update_user_meta( $user_id, 'activitypub_default_extra_fields', true );

		return $extra_fields;
	}

	public static function get_extra_fields_for_mastodon_api( $user_id ) {
		$ret    = array();
		$fields = self::get_actor_fields( $user_id );

		foreach ( $fields as $field ) {
			$ret[] = array(
				'name'   => $field->post_title,
				'value'  => self::get_formatted_content( $field ),
			);
		}

		return $ret;
	}

	private static function make_paragraph_block( $content ) {
		if ( ! site_supports_blocks() ) {
			return $content;
		}
		return '<!-- wp:paragraph --><p>' . $content . '</p><!-- /wp:paragraph -->';
	}

	public static function set_extra_fields_from_mastodon_api( $user_id, $fields ) {
		// The Mastodon API submits a simple hash, every field.
		// We can reasonably assume a similar order for our operations below.
		$ids       = wp_list_pluck( self::get_actor_fields( $user_id ), 'ID' );
		$is_blog   = self::is_blog( $user_id );
		$post_type = $is_blog ? self::BLOG_POST_TYPE : self::USER_POST_TYPE;

		foreach ( $fields as $i => $field ) {
			$post_id  = $ids[ $i ] ?? null;
			$has_post = $post_id && \get_post( $post_id );
			$args     = array(
				'post_title'   => $field['name'],
				'post_content' => self::make_paragraph_block( $field['value'] ),
			);

			if ( $has_post ) {
				$args['ID'] = $ids[ $i ];
				\wp_update_post( $args );
			} else {
				$args['post_type']   = $post_type;
				$args['post_status'] = 'publish';
				if ( ! $is_blog ) {
					$args['post_author'] = $user_id;
				}
				\wp_insert_post( $args );
			}
		}

		// Delete any remaining fields.
		if ( \count( $fields ) < \count( $ids ) ) {
			$to_delete = \array_slice( $ids, \count( $fields ) );
			foreach ( $to_delete as $id ) {
				\wp_delete_post( $id, true );
			}
		}
	}

	/**
	 * Checks if the user is the blog user.
	 * @param int $user_id The user ID.
	 * @return bool True if the user is the blog user, otherwise false.
	 */
	private static function is_blog( $user_id ) {
		return Users::BLOG_USER_ID === $user_id;
	}
}
