<?php

namespace Activitypub\Collection;

use Activitypub\Collection\Users;
use WP_Query;

class Extra_Fields {

	const USER_POST_TYPE = 'ap_extrafield';

	const BLOG_POST_TYPE = 'ap_extrafield_blog';

	/**
	 * Register the post types for extra fields.
	 */
	public static function register_post_types() {
		// Both User and Blog Extra Fields types have the same args.
		$args = array(
			'labels'           => array(
				'name'          => _x( 'Extra fields', 'post_type plural name', 'activitypub' ),
				'singular_name' => _x( 'Extra field', 'post_type single name', 'activitypub' ),
				'add_new'       => __( 'Add new', 'activitypub' ),
				'add_new_item'  => __( 'Add new extra field', 'activitypub' ),
				'new_item'      => __( 'New extra field', 'activitypub' ),
				'edit_item'     => __( 'Edit extra field', 'activitypub' ),
				'view_item'     => __( 'View extra field', 'activitypub' ),
				'all_items'     => __( 'All extra fields', 'activitypub' ),
			),
			'public'              => false,
			'hierarchical'        => false,
			'query_var'           => false,
			'has_archive'         => false,
			'publicly_queryable'  => false,
			'show_in_menu'        => false,
			'delete_with_user'    => true,
			'can_export'          => true,
			'exclude_from_search' => true,
			'show_in_rest'        => true,
			'map_meta_cap'        => true,
			'show_ui'             => true,
			'supports'            => array( 'title', 'editor' ),
		);

		\register_post_type( self::USER_POST_TYPE, $args );
		\register_post_type( self::BLOG_POST_TYPE, $args );
	}

	/**
	 * Get the extra fields for a user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return WP_Post[] The extra fields.
	 */
	public static function get_actor_fields( $user_id ) {
		$is_blog = Users::BLOG_USER_ID === $user_id;
		$post_type = $is_blog ? self::BLOG_POST_TYPE : self::USER_POST_TYPE;
		$args = array(
			'post_type' => $post_type,
			'nopaging'  => true,
		);
		if ( ! $is_blog ) {
			$args['author'] = $user_id;
		}

		$query = new \WP_Query( $args );
		$fields = $query->posts ?? array();

		return apply_filters( 'activitypub_get_actor_extra_fields', $fields, $user_id );
	}

	public static function fields_to_attachments( $fields ) {
		$attachments = array();

		foreach ( $fields as $post ) {
			$content = \get_the_content( null, false, $post );
			$content = \make_clickable( $content );
			$content = \do_blocks( $content );
			$content = \wptexturize( $content );
			$content = \wp_filter_content_tags( $content );
			// replace script and style elements
			$content = \preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $content );
			$content = \strip_shortcodes( $content );
			$content = \trim( \preg_replace( '/[\n\r\t]/', '', $content ) );

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
			if ( \class_exists( '\WP_HTML_Tag_Processor' ) ) {
				$tags = new \WP_HTML_Tag_Processor( $content );
				$tags->next_tag();

				if ( 'P' === $tags->get_tag() ) {
					$tags->next_tag();
				}

				if ( 'A' === $tags->get_tag() ) {
					$tags->set_bookmark( 'link' );
					if ( ! $tags->next_tag() ) {
						$tags->seek( 'link' );
						$attachment = array(
							'type' => 'Link',
							'name' => \get_the_title( $post ),
							'href' => \esc_url( $tags->get_attribute( 'href' ) ),
							'rel'  => explode( ' ', $tags->get_attribute( 'rel' ) ),
						);

						$link_added = true;
					}
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

	public static function is_post_type( $post_type ) {
		return \in_array( $post_type, array( self::USER_POST_TYPE, self::BLOG_POST_TYPE ), true );
	}
}
