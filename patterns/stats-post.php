<?php
/**
 * Fediverse Stats Post starter pattern.
 *
 * This pattern is only registered in December and January, giving users
 * a seasonal nudge to share their annual Fediverse stats.
 *
 * @package Activitypub
 */

$selected_user = ! \Activitypub\is_user_type_disabled( 'blog' ) ? 'blog' : 'inherit';

/*
 * Skip if a post with the stats block was already published
 * during the current December–January window.
 *
 * The month is re-read here (already checked in class-blocks.php)
 * because we need to distinguish December from January to build
 * the correct date range.
 */
$month        = (int) \gmdate( 'n' );
$current_year = (int) \gmdate( 'Y' );
$stats_year   = 12 === $month ? $current_year : ( $current_year - 1 );
$transient    = 'activitypub_stats_pattern_' . $stats_year;
$cached       = \get_transient( $transient );

if ( 'hide' === $cached ) {
	return;
}

if ( false === $cached ) {
	$after = $stats_year . '-12-01';

	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$has_stats_post = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT 1 FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_date_gmt >= %s AND post_content LIKE %s LIMIT 1",
			$after,
			'%<!-- wp:activitypub/stats%'
		)
	);

	if ( $has_stats_post ) {
		\set_transient( $transient, 'hide', MONTH_IN_SECONDS );
		return;
	}

	\set_transient( $transient, 'show', DAY_IN_SECONDS );
}

\register_block_pattern(
	'activitypub/stats-post',
	array(
		'title'         => _x( 'Fediverse Year in Review', 'Block pattern title', 'activitypub' ),
		'categories'    => array( 'activitypub' ),
		'keywords'      => array(
			_x( 'stats', 'Block pattern keyword', 'activitypub' ),
			_x( 'fediverse', 'Block pattern keyword', 'activitypub' ),
			_x( 'year in review', 'Block pattern keyword', 'activitypub' ),
		),
		'description'   => _x( 'Share your annual Fediverse stats as a post.', 'Block pattern description', 'activitypub' ),
		'viewportWidth' => 1200,
		'postTypes'     => array( 'post' ),
		'blockTypes'    => array( 'core/post-content' ),
		'content'       => '<!-- wp:activitypub/stats {"selectedUser":"' . $selected_user . '","year":' . $stats_year . '} /-->',
	)
);
