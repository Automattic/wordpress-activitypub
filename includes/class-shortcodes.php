<?php
/**
 * Shortcodes class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Shortcodes class.
 */
class Shortcodes {
	/**
	 * Register the shortcodes.
	 */
	public static function register() {
		foreach ( get_class_methods( self::class ) as $shortcode ) {
			if ( 'init' !== $shortcode ) {
				add_shortcode( 'ap_' . $shortcode, array( self::class, $shortcode ) );
			}
		}
	}

	/**
	 * Unregister the shortcodes.
	 */
	public static function unregister() {
		foreach ( get_class_methods( self::class ) as $shortcode ) {
			if ( 'init' !== $shortcode ) {
				remove_shortcode( 'ap_' . $shortcode );
			}
		}
	}

	/**
	 * Generates output for the 'ap_hashtags' shortcode.
	 *
	 * @return string The post tags as hashtags.
	 */
	public static function hashtags() {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$tags = \get_the_tags( $item->ID );

		if ( ! $tags ) {
			return '';
		}

		$hash_tags = array();

		foreach ( $tags as $tag ) {
			// Tag can be empty.
			if ( ! $tag ) {
				continue;
			}

			$hash_tags[] = \sprintf(
				'<a rel="tag" class="hashtag u-tag u-category" href="%s">%s</a>',
				\esc_url( \get_tag_link( $tag ) ),
				esc_hashtag( $tag->name )
			);
		}

		return \implode( ' ', $hash_tags );
	}

	/**
	 * Generates output for the 'ap_title' Shortcode
	 *
	 * @param array  $attributes The Shortcode attributes.
	 * @param string $content    The ActivityPub post-content.
	 * @param string $tag        The tag/name of the Shortcode.
	 *
	 * @return string The post title.
	 */
	public static function title( $attributes, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$title = \wp_strip_all_tags( \get_the_title( $item->ID ), true );

		if ( ! $title ) {
			return '';
		}

		$attributes = shortcode_atts(
			array( 'type' => 'plain' ),
			$attributes,
			$tag
		);

		if ( 'html' !== $attributes['type'] ) {
			return $title;
		}

		return sprintf( '<h2>%s</h2>', $title );
	}

	/**
	 * Generates output for the 'ap_excerpt' Shortcode
	 *
	 * @param array  $attributes The Shortcode attributes.
	 * @param string $content    The ActivityPub post-content.
	 * @param string $tag        The tag/name of the Shortcode.
	 *
	 * @return string The post excerpt.
	 */
	public static function excerpt( $attributes, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$attributes = shortcode_atts(
			array( 'length' => ACTIVITYPUB_EXCERPT_LENGTH ),
			$attributes,
			$tag
		);

		$excerpt_length = intval( $attributes['length'] );

		if ( 0 === $excerpt_length ) {
			$excerpt_length = ACTIVITYPUB_EXCERPT_LENGTH;
		}

		$excerpt = generate_post_summary( $item, $excerpt_length );

		/** This filter is documented in wp-includes/post-template.php */
		return \apply_filters( 'the_excerpt', $excerpt );
	}

	/**
	 * Generates output for the 'ap_content' Shortcode.
	 *
	 * @param array  $attributes The Shortcode attributes.
	 * @param string $content    The ActivityPub post-content.
	 * @param string $tag        The tag/name of the Shortcode.
	 *
	 * @return string The post content.
	 */
	public static function content( $attributes, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		// Prevent inception.
		remove_shortcode( 'ap_content' );

		$attributes = shortcode_atts(
			array( 'apply_filters' => 'yes' ),
			$attributes,
			$tag
		);

		$content = '';

		if ( 'attachment' === $item->post_type ) {
			// Get title of attachment with fallback to alt text.
			$content = wp_get_attachment_caption( $item->ID );
			if ( empty( $content ) ) {
				$content = get_post_meta( $item->ID, '_wp_attachment_image_alt', true );
			}
		}

		if ( empty( $content ) ) {
			$content = \get_post_field( 'post_content', $item );
		}

		if ( 'yes' === $attributes['apply_filters'] ) {
			/** This filter is documented in wp-includes/post-template.php */
			$content = \apply_filters( 'the_content', $content );
		} else {
			if ( site_supports_blocks() ) {
				$content = \do_blocks( $content );
			}
			$content = \wptexturize( $content );
			$content = \wp_filter_content_tags( $content );
		}

		// Replace script and style elements.
		$content = \preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $content );
		$content = \strip_shortcodes( $content );
		$content = \trim( \preg_replace( '/[\n\r\t]/', '', $content ) );

		add_shortcode( 'ap_content', array( 'Activitypub\Shortcodes', 'content' ) );

		return $content;
	}

	/**
	 * Generates output for the 'ap_permalink' Shortcode.
	 *
	 * @param array  $attributes The Shortcode attributes.
	 * @param string $content    The ActivityPub post-content.
	 * @param string $tag        The tag/name of the Shortcode.
	 *
	 * @return string The post permalink.
	 */
	public static function permalink( $attributes, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$attributes = shortcode_atts(
			array(
				'type' => 'url',
			),
			$attributes,
			$tag
		);

		if ( 'html' !== $attributes['type'] ) {
			return \esc_url( \get_permalink( $item->ID ) );
		}

		return \sprintf(
			'<a href="%1$s" class="status-link unhandled-link">%1$s</a>',
			\esc_url( \get_permalink( $item->ID ) )
		);
	}

	/**
	 * Generates output for the 'ap_shortlink' Shortcode.
	 *
	 * @param array  $attributes The Shortcode attributes.
	 * @param string $content    The ActivityPub post-content.
	 * @param string $tag        The tag/name of the Shortcode.
	 *
	 * @return string The post shortlink.
	 */
	public static function shortlink( $attributes, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$attributes = shortcode_atts(
			array(
				'type' => 'url',
			),
			$attributes,
			$tag
		);

		if ( 'html' !== $attributes['type'] ) {
			return \esc_url( \wp_get_shortlink( $item->ID ) );
		}

		return \sprintf(
			'<a href="%1$s" class="status-link unhandled-link">%1$s</a>',
			\esc_url( \wp_get_shortlink( $item->ID ) )
		);
	}

	/**
	 * Generates output for the 'ap_image' Shortcode.
	 *
	 * @param array  $attributes The Shortcode attributes.
	 * @param string $content    The ActivityPub post-content.
	 * @param string $tag        The tag/name of the Shortcode.
	 *
	 * @return string
	 */
	public static function image( $attributes, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$attributes = shortcode_atts(
			array(
				'type' => 'full',
			),
			$attributes,
			$tag
		);

		$size = 'full';

		if ( in_array(
			$attributes['type'],
			array( 'thumbnail', 'medium', 'large', 'full' ),
			true
		) ) {
			$size = $attributes['type'];
		}

		$image = \get_the_post_thumbnail_url( $item->ID, $size );

		if ( ! $image ) {
			return '';
		}

		return \esc_url( $image );
	}

	/**
	 * Generates output for the 'ap_hashcats' Shortcode.
	 *
	 * @deprecated 7.0.0
	 *
	 * @return string The post categories as hashtags.
	 */
	public static function hashcats() {
		return '';
	}

	/**
	 * Generates output for the 'ap_author' Shortcode.
	 *
	 * @return string The author name.
	 */
	public static function author() {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$author_id = \get_post_field( 'post_author', $item->ID );
		$name      = \get_the_author_meta( 'display_name', $author_id );

		if ( ! $name ) {
			return '';
		}

		return wp_strip_all_tags( $name );
	}

	/**
	 * Generates output for the 'ap_authorurl' Shortcode.
	 *
	 * @return string The author URL.
	 */
	public static function authorurl() {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$author_id = \get_post_field( 'post_author', $item->ID );
		$url       = \get_the_author_meta( 'user_url', $author_id );

		if ( ! $url ) {
			return '';
		}

		return \esc_url( $url );
	}

	/**
	 * Generates output for the 'ap_blogurl' Shortcode.
	 *
	 * @return string The site URL.
	 */
	public static function blogurl() {
		return \esc_url( \get_bloginfo( 'url' ) );
	}

	/**
	 * Generates output for the 'ap_blogname' Shortcode.
	 *
	 * @return string
	 */
	public static function blogname() {
		return \wp_strip_all_tags( \get_bloginfo( 'name' ) );
	}

	/**
	 * Generates output for the 'ap_blogdesc' Shortcode.
	 *
	 * @return string The site description.
	 */
	public static function blogdesc() {
		return \wp_strip_all_tags( \get_bloginfo( 'description' ) );
	}

	/**
	 * Generates output for the 'ap_date' Shortcode.
	 *
	 * @return string The post date.
	 */
	public static function date() {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$datetime   = \get_post_datetime( $item );
		$dateformat = \get_option( 'date_format' );

		$date = $datetime->format( $dateformat );

		if ( ! $date ) {
			return '';
		}

		return $date;
	}

	/**
	 * Generates output for the 'ap_time' Shortcode.
	 *
	 * @return string The post time.
	 */
	public static function time() {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$datetime = \get_post_datetime( $item );
		$date     = $datetime->format( \get_option( 'time_format' ) );

		if ( ! $date ) {
			return '';
		}

		return $date;
	}

	/**
	 * Generates output for the 'ap_datetime' Shortcode.
	 *
	 * @return string The post date/time.
	 */
	public static function datetime() {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$datetime    = \get_post_datetime( $item );
		$date_format = \get_option( 'date_format' );
		$time_format = \get_option( 'time_format' );

		$date = $datetime->format( $date_format . ' @ ' . $time_format );

		if ( ! $date ) {
			return '';
		}

		return $date;
	}

	/**
	 * Get a WordPress item to federate.
	 *
	 * Checks if item (WP_Post) is "public", a supported post type
	 * and not password protected.
	 *
	 * @return null|\WP_Post The WordPress item.
	 */
	protected static function get_item() {
		$post = \get_post();

		if ( ! $post ) {
			return null;
		}

		if ( 'publish' !== \get_post_status( $post ) ) {
			return null;
		}

		if ( \post_password_required( $post ) ) {
			return null;
		}

		if ( ! \in_array( \get_post_type( $post ), \get_post_types_by_support( 'activitypub' ), true ) ) {
			return null;
		}

		return $post;
	}
}
