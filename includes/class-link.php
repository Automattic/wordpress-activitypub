<?php
/**
 * Link class.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * ActivityPub Summery Links Class.
 */
class Link {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_extra_field_content', array( self::class, 'the_content' ) );
		\add_filter( 'activitypub_activity_object_array', array( self::class, 'filter_activity_object' ), 99 );
	}

	/**
	 * Filter only the activity object and replace the summary with URLs.
	 *
	 * @param array $activity The activity object array.
	 *
	 * @return array Rhe activity object array.
	 */
	public static function filter_activity_object( $activity ) {
		/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		Only changed it for Person and Group as long is not merged: https://github.com/mastodon/mastodon/pull/28629
		*/
		if ( ! empty( $activity['summary'] ) && in_array( $activity['type'], array( 'Person', 'Group' ), true ) ) {
			$activity['summary'] = self::the_content( $activity['summary'] );
		}

		if ( ! empty( $activity['content'] ) ) {
			$activity['content'] = self::the_content( $activity['content'] );
		}

		return $activity;
	}

	/**
	 * Filter to replace the URLS in the content with links
	 *
	 * @param string $the_content The post content.
	 *
	 * @return string the filtered post content.
	 */
	public static function the_content( $the_content ) {
		return enrich_content_data( $the_content, '/' . ACTIVITYPUB_URL_REGEXP . '/i', array( self::class, 'replace_with_links' ) );
	}

	/**
	 * A callback for preg_replace to build the links.
	 *
	 * Link shortening https://docs.joinmastodon.org/api/guidelines/#links
	 *
	 * @param array $result The preg_match results.
	 *
	 * @return string The final string.
	 */
	public static function replace_with_links( $result ) {
		if ( 'www.' === substr( $result[0], 0, 4 ) ) {
			$result[0] = 'https://' . $result[0];
		}
		$parsed_url = \wp_parse_url( html_entity_decode( $result[0] ) );
		if ( ! $parsed_url || empty( $parsed_url['host'] ) ) {
			return $result[0];
		}

		if ( empty( $parsed_url['scheme'] ) ) {
			$invisible_prefix = 'https://';
		} else {
			$invisible_prefix = $parsed_url['scheme'] . '://';
		}
		if ( ! empty( $parsed_url['user'] ) ) {
			$invisible_prefix .= $parsed_url['user'];
		}
		if ( ! empty( $parsed_url['pass'] ) ) {
			$invisible_prefix .= ':' . $parsed_url['pass'];
		}
		if ( ! empty( $parsed_url['user'] ) ) {
			$invisible_prefix .= '@';
		}

		$text_url = $parsed_url['host'];
		if ( 'www.' === substr( $text_url, 0, 4 ) ) {
			$text_url          = substr( $text_url, 4 );
			$invisible_prefix .= 'www.';
		}
		if ( ! empty( $parsed_url['port'] ) ) {
			$text_url .= ':' . $parsed_url['port'];
		}
		if ( ! empty( $parsed_url['path'] ) ) {
			$text_url .= $parsed_url['path'];
		}
		if ( ! empty( $parsed_url['query'] ) ) {
			$text_url .= '?' . $parsed_url['query'];
		}
		if ( ! empty( $parsed_url['fragment'] ) ) {
			$text_url .= '#' . $parsed_url['fragment'];
		}

		$display          = \substr( $text_url, 0, 30 );
		$invisible_suffix = \substr( $text_url, 30 );

		$display_class = '';
		if ( $invisible_suffix ) {
			$display_class .= 'ellipsis';
		}

		/**
		 * Filters the rel attribute for ActivityPub links.
		 *
		 * @param string $rel The rel attribute string. Default 'nofollow noopener noreferrer'.
		 */
		$rel = apply_filters( 'activitypub_link_rel', 'nofollow noopener noreferrer' );

		return \sprintf(
			'<a href="%s" target="_blank" rel="%s" translate="no"><span class="invisible">%s</span><span class="%s">%s</span><span class="invisible">%s</span></a>',
			esc_url( $result[0] ),
			$rel,
			esc_html( $invisible_prefix ),
			$display_class,
			esc_html( $display ),
			esc_html( $invisible_suffix )
		);
	}
}
