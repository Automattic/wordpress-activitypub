<?php

namespace Activitypub\Integration;

function plugin_init() {
	require_once __DIR__ . '/class-webfinger.php';
	Webfinger::init();

	require_once __DIR__ . '/class-nodeinfo.php';
	Nodeinfo::init();

	require_once __DIR__ . '/class-enable-mastodon-apps.php';
	Enable_Mastodon_Apps::init();

	if ( '1' === \get_option( 'activitypub_use_opengraph', '1' ) ) {
		require_once __DIR__ . '/class-opengraph.php';
		Opengraph::init();
	}

	if ( \defined( 'JETPACK__VERSION' ) && ! \defined( 'IS_WPCOM' ) ) {
		require_once __DIR__ . '/class-jetpack.php';
		Jetpack::init();
	}

	if ( \defined( 'SSP_VERSION' ) ) {
		require_once __DIR__ . '/class-seriously-simple-podcasting.php';
		Seriously_Simple_Podcasting::init();
	}
}
\add_action( 'plugins_loaded', __NAMESPACE__ . '\plugin_init' );

/**
 * Only load code that needs BuddyPress to run once BP is loaded and initialized.
 */
add_action(
	'bp_include',
	function () {
		require_once __DIR__ . '/class-buddypress.php';
		Buddypress::init();
	},
	0
);
