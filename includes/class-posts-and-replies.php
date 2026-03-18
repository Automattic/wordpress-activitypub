<?php
/**
 * Posts and Replies query helper.
 *
 * Provides query methods for the Posts and Replies block,
 * separated from the render template for testability.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Posts_And_Replies class.
 *
 * @since unreleased
 */
class Posts_And_Replies {

	/**
	 * Get the base query arguments.
	 *
	 * Builds query args from the current request context (author archive,
	 * pagination, tab state). When not on an author archive, the author
	 * constraint is omitted so the block works on any page.
	 *
	 * @since unreleased
	 *
	 * @param array $args Optional overrides merged on top of defaults.
	 *
	 * @return array Query arguments for WP_Query.
	 */
	public static function get_query_args( $args = array() ) {
		$defaults = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'paged'          => \max( 1, \get_query_var( 'paged', 1 ) ),
		);

		// Add author constraint when on an author archive.
		if ( \is_author() ) {
			$author = \get_queried_object();
			if ( $author instanceof \WP_User ) {
				$defaults['author'] = $author->ID;
			}
		}

		return \wp_parse_args( $args, $defaults );
	}

	/**
	 * Query posts, optionally excluding replies.
	 *
	 * When $exclude_replies is true, posts containing the
	 * `activitypub/reply` block are filtered out via a WHERE clause.
	 *
	 * @since unreleased
	 *
	 * @param array $args            WP_Query arguments.
	 * @param bool  $exclude_replies Whether to exclude reply posts.
	 *
	 * @return \WP_Query The query result.
	 */
	public static function query( $args, $exclude_replies = false ) {
		if ( $exclude_replies ) {
			$args['activitypub_exclude_replies'] = true;
			\add_filter( 'posts_where', array( self::class, 'exclude_replies_where' ), 10, 2 );
		}

		$query = new \WP_Query( $args );

		if ( $exclude_replies ) {
			\remove_filter( 'posts_where', array( self::class, 'exclude_replies_where' ), 10 );
		}

		return $query;
	}

	/**
	 * Modify the WHERE clause to exclude posts containing the reply block.
	 *
	 * @since unreleased
	 *
	 * @param string    $where The WHERE clause.
	 * @param \WP_Query $query The query object.
	 *
	 * @return string Modified WHERE clause.
	 */
	public static function exclude_replies_where( $where, $query ) {
		if ( $query->get( 'activitypub_exclude_replies' ) ) {
			global $wpdb;
			$where .= $wpdb->prepare(
				" AND {$wpdb->posts}.post_content NOT LIKE %s",
				'%<!-- wp:activitypub/reply%'
			);
		}

		return $where;
	}

	/**
	 * Get the active tab from the URL parameter.
	 *
	 * @since unreleased
	 *
	 * @return string The active tab identifier ('posts' or 'posts-and-replies').
	 */
	public static function get_active_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['ap_tab'] ) ? \sanitize_key( $_GET['ap_tab'] ) : 'posts';

		if ( ! \in_array( $tab, array( 'posts', 'posts-and-replies' ), true ) ) {
			$tab = 'posts';
		}

		return $tab;
	}

	/**
	 * Render pagination links that preserve the active tab parameter.
	 *
	 * @since unreleased
	 *
	 * @param \WP_Query $query The query object.
	 * @param string    $tab   The tab identifier to preserve in URLs.
	 *
	 * @return string Pagination HTML or empty string.
	 */
	public static function render_pagination( $query, $tab ) {
		if ( $query->max_num_pages <= 1 ) {
			return '';
		}

		$big  = 999999999;
		$base = \str_replace( $big, '%#%', \esc_url( \get_pagenum_link( $big ) ) );
		$base = \add_query_arg( 'ap_tab', $tab, $base );

		$links = \paginate_links(
			array(
				'base'    => $base,
				'format'  => '',
				'total'   => $query->max_num_pages,
				'current' => $query->get( 'paged' ) ? $query->get( 'paged' ) : 1,
			)
		);

		if ( ! $links ) {
			return '';
		}

		return '<nav class="wp-block-query-pagination is-layout-flex wp-block-query-pagination-is-layout-flex" aria-label="' . \esc_attr__( 'Posts pagination', 'activitypub' ) . '">' . $links . '</nav>';
	}
}
