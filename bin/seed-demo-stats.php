<?php
/**
 * Seed demo statistics for testing the stats block.
 *
 * Usage:
 *   wp eval-file bin/seed-demo-stats.php
 *   wp eval-file bin/seed-demo-stats.php --user_id=1 --year=2025
 *
 * Or via wp-env:
 *   npm run env-cli -- eval-file wp-content/plugins/activitypub/bin/seed-demo-stats.php
 *
 * @package Activitypub
 */

$user_id = 0;  // Blog user.
$year    = (int) gmdate( 'Y' ) - 1;

// Parse CLI args.
foreach ( $args as $arg ) {
	if ( strpos( $arg, '--user_id=' ) === 0 ) {
		$user_id = (int) str_replace( '--user_id=', '', $arg );
	}
	if ( strpos( $arg, '--year=' ) === 0 ) {
		$year = (int) str_replace( '--year=', '', $arg );
	}
}

$option_name = sprintf( 'activitypub_stats_%d_%d_annual', $user_id, $year );

$demo_summary = array(
	'posts_count'          => 142,
	'most_active_month'    => 3,
	'followers_start'      => 487,
	'followers_end'        => 1203,
	'followers_net_change' => 716,
	'top_multiplicator'    => array(
		'name'  => '@evan@cosocial.ca',
		'url'   => 'https://cosocial.ca/@evan',
		'count' => 38,
	),
	'top_posts'            => array(
		array(
			'title'            => 'Why ActivityPub is the Future of Social Networking',
			'url'              => home_url( '/2025/03/activitypub-future/' ),
			'engagement_count' => 234,
		),
		array(
			'title'            => 'Introducing Fediverse Stats for WordPress',
			'url'              => home_url( '/2025/06/fediverse-stats/' ),
			'engagement_count' => 189,
		),
		array(
			'title'            => 'How to Set Up Your Blog for Federation',
			'url'              => home_url( '/2025/01/federation-setup/' ),
			'engagement_count' => 156,
		),
		array(
			'title'            => 'The IndieWeb and the Fediverse: Better Together',
			'url'              => home_url( '/2025/09/indieweb-fediverse/' ),
			'engagement_count' => 98,
		),
		array(
			'title'            => 'Year in Review: Open Standards Won',
			'url'              => home_url( '/2025/12/year-in-review/' ),
			'engagement_count' => 87,
		),
	),
	'like_count'           => 1847,
	'announce_count'       => 623,
	'comment_count'        => 312,
	'compiled_at'          => gmdate( 'Y-m-d H:i:s' ),
);

update_option( $option_name, $demo_summary, false );

WP_CLI::success(
	sprintf(
		'Seeded demo stats for user %d, year %d (option: %s).',
		$user_id,
		$year,
		$option_name
	)
);
